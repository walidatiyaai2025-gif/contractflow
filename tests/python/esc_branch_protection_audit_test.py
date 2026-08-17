#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import json
from pathlib import Path
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SCRIPT_PATH = ROOT / "scripts/audit_esc_branch_protection.py"
EXPECTED_APP_ID = 12345

SPEC = importlib.util.spec_from_file_location("esc_branch_audit", SCRIPT_PATH)
assert SPEC and SPEC.loader
audit = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(audit)


def valid_branch() -> dict:
    return {"name": "enterprise-safecontracts", "protected": True}


def valid_legacy() -> dict:
    return {
        "required_status_checks": {
            "strict": True,
            "contexts": ["esc-foundation", "esc-mobile"],
            "checks": [
                {"context": "esc-foundation", "app_id": EXPECTED_APP_ID},
                {"context": "esc-mobile", "app_id": EXPECTED_APP_ID},
            ],
        },
        "required_pull_request_reviews": {"required_approving_review_count": 0},
        "allow_force_pushes": {"enabled": False},
        "allow_deletions": {"enabled": False},
        "enforce_admins": {"enabled": True},
        "required_conversation_resolution": {"enabled": True},
    }


def valid_rules() -> list[dict]:
    return [
        {
            "type": "pull_request",
            "parameters": {"required_review_thread_resolution": True},
        },
        {
            "type": "required_status_checks",
            "parameters": {
                "strict_required_status_checks_policy": True,
                "required_status_checks": [
                    {"context": "esc-foundation", "integration_id": EXPECTED_APP_ID},
                    {"context": "esc-mobile", "integration_id": EXPECTED_APP_ID},
                ],
            },
        },
        {"type": "non_fast_forward"},
        {"type": "deletion"},
    ]


def evaluate(
    branch: dict | None = None,
    protection: dict | None = None,
    rules: list[dict] | None = None,
    note: str = "No routine bypass; emergency admin access is documented in #522.",
    expected_app_id: int = EXPECTED_APP_ID,
) -> dict:
    return audit.evaluate(
        branch or valid_branch(),
        valid_legacy() if protection is None else protection,
        [] if rules is None else rules,
        note,
        expected_app_id,
    )


class EscBranchProtectionAuditTests(unittest.TestCase):
    def test_legacy_protection_passes_with_pinned_github_app_sources(self) -> None:
        result = evaluate()
        self.assertEqual("PASS", result["decision"])
        self.assertTrue(all(result["checks"].values()))
        self.assertEqual(3, result["schema_version"])
        self.assertEqual(EXPECTED_APP_ID, result["expected_status_check_app_id"])
        self.assertEqual(
            {
                "esc-foundation": [EXPECTED_APP_ID],
                "esc-mobile": [EXPECTED_APP_ID],
            },
            result["observed_required_check_source_ids"],
        )

    def test_ruleset_sources_are_recognized_but_admin_enforcement_still_fails_closed(self) -> None:
        result = audit.evaluate(
            valid_branch(),
            None,
            valid_rules(),
            "Break-glass bypass is restricted and documented in #522.",
            EXPECTED_APP_ID,
        )
        self.assertTrue(result["checks"]["required_status_check_sources_verified"])
        self.assertFalse(result["checks"]["administrator_enforcement_verified"])
        self.assertEqual("FAIL", result["decision"])

    def test_unprotected_branch_fails(self) -> None:
        branch = valid_branch()
        branch["protected"] = False
        result = evaluate(branch=branch)
        self.assertFalse(result["checks"]["protected_branch"])
        self.assertEqual("FAIL", result["decision"])

    def test_missing_required_check_fails(self) -> None:
        protection = valid_legacy()
        protection["required_status_checks"]["contexts"] = ["esc-foundation"]
        protection["required_status_checks"]["checks"] = [
            {"context": "esc-foundation", "app_id": EXPECTED_APP_ID}
        ]
        result = evaluate(protection=protection)
        self.assertFalse(result["checks"]["required_status_checks_present"])
        self.assertFalse(result["checks"]["required_status_check_sources_verified"])

    def test_status_check_source_binding_is_mandatory(self) -> None:
        cases = {
            "missing app_id": None,
            "any-source app_id": -1,
            "wrong app_id": EXPECTED_APP_ID + 1,
        }
        for label, source_id in cases.items():
            with self.subTest(label=label):
                protection = valid_legacy()
                item = {"context": "esc-mobile"}
                if source_id is not None:
                    item["app_id"] = source_id
                protection["required_status_checks"]["checks"][1] = item
                result = evaluate(protection=protection)
                self.assertFalse(result["checks"]["required_status_check_sources_verified"])
                self.assertEqual("FAIL", result["decision"])

    def test_ruleset_wrong_or_missing_integration_id_cannot_supply_source_binding(self) -> None:
        protection = valid_legacy()
        protection["required_status_checks"]["checks"] = [
            {"context": "esc-foundation", "app_id": EXPECTED_APP_ID}
        ]
        rules = valid_rules()
        rules[1]["parameters"]["required_status_checks"] = [
            {"context": "esc-mobile", "integration_id": EXPECTED_APP_ID + 1}
        ]
        result = evaluate(protection=protection, rules=rules)
        self.assertFalse(result["checks"]["required_status_check_sources_verified"])

    def test_ruleset_can_supply_expected_source_binding_for_one_required_context(self) -> None:
        protection = valid_legacy()
        protection["required_status_checks"]["checks"] = [
            {"context": "esc-foundation", "app_id": EXPECTED_APP_ID}
        ]
        rules = valid_rules()
        rules[1]["parameters"]["required_status_checks"] = [
            {"context": "esc-mobile", "integration_id": EXPECTED_APP_ID}
        ]
        result = evaluate(protection=protection, rules=rules)
        self.assertTrue(result["checks"]["required_status_check_sources_verified"])
        self.assertEqual("PASS", result["decision"])

    def test_non_strict_status_policy_fails(self) -> None:
        protection = valid_legacy()
        protection["required_status_checks"]["strict"] = False
        result = evaluate(protection=protection)
        self.assertFalse(result["checks"]["strict_up_to_date_status_checks"])

    def test_admin_enforcement_is_mandatory(self) -> None:
        for value in ({"enabled": False}, None):
            with self.subTest(value=value):
                protection = valid_legacy()
                if value is None:
                    protection.pop("enforce_admins")
                else:
                    protection["enforce_admins"] = value
                result = evaluate(protection=protection)
                self.assertFalse(result["checks"]["administrator_enforcement_verified"])

    def test_conversation_resolution_is_mandatory(self) -> None:
        for value in ({"enabled": False}, None):
            with self.subTest(value=value):
                protection = valid_legacy()
                if value is None:
                    protection.pop("required_conversation_resolution")
                else:
                    protection["required_conversation_resolution"] = value
                result = evaluate(protection=protection)
                self.assertFalse(result["checks"]["conversation_resolution_required"])

    def test_ruleset_can_supply_conversation_resolution(self) -> None:
        protection = valid_legacy()
        protection.pop("required_conversation_resolution")
        result = evaluate(protection=protection, rules=valid_rules())
        self.assertTrue(result["checks"]["conversation_resolution_required"])
        self.assertEqual("PASS", result["decision"])

    def test_force_push_and_deletion_must_be_blocked(self) -> None:
        protection = valid_legacy()
        protection["allow_force_pushes"]["enabled"] = True
        protection["allow_deletions"]["enabled"] = True
        result = evaluate(protection=protection)
        self.assertFalse(result["checks"]["force_push_blocked"])
        self.assertFalse(result["checks"]["branch_deletion_blocked"])

    def test_break_glass_statement_is_mandatory(self) -> None:
        result = evaluate(note="none")
        self.assertFalse(result["checks"]["break_glass_documented"])

    def test_expected_app_id_must_be_positive(self) -> None:
        with self.assertRaisesRegex(ValueError, "positive GitHub App ID"):
            evaluate(expected_app_id=-1)

    def test_cli_writes_content_addressed_failure_evidence(self) -> None:
        with tempfile.TemporaryDirectory() as temp_dir:
            temp = Path(temp_dir)
            branch_path = temp / "branch.json"
            rules_path = temp / "rules.json"
            output_path = temp / "audit.json"
            branch_path.write_text(
                json.dumps({"name": "enterprise-safecontracts", "protected": False}),
                encoding="utf-8",
            )
            rules_path.write_text(json.dumps(valid_rules()), encoding="utf-8")

            completed = subprocess.run(
                [
                    sys.executable,
                    str(SCRIPT_PATH),
                    "--branch-json",
                    str(branch_path),
                    "--rules-json",
                    str(rules_path),
                    "--break-glass-note",
                    "No routine bypass; emergency path is documented.",
                    "--expected-status-check-app-id",
                    str(EXPECTED_APP_ID),
                    "--output",
                    str(output_path),
                ],
                check=False,
                capture_output=True,
                text=True,
            )

            self.assertEqual(1, completed.returncode)
            evidence = json.loads(output_path.read_text(encoding="utf-8"))
            self.assertEqual(3, evidence["schema_version"])
            self.assertEqual("FAIL", evidence["decision"])
            self.assertRegex(evidence["captured_input_sha256"]["branch_json"], r"^[0-9a-f]{64}$")
            self.assertRegex(evidence["captured_input_sha256"]["rules_json"], r"^[0-9a-f]{64}$")


if __name__ == "__main__":
    unittest.main()
