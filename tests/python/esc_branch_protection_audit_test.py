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
                    {"context": "esc-foundation"},
                    {"context": "esc-mobile"},
                ],
            },
        },
        {"type": "non_fast_forward"},
        {"type": "deletion"},
    ]


class EscBranchProtectionAuditTests(unittest.TestCase):
    def test_legacy_protection_passes(self) -> None:
        result = audit.evaluate(
            valid_branch(),
            valid_legacy(),
            [],
            "No routine bypass; emergency admin access is documented in #522.",
        )
        self.assertEqual("PASS", result["decision"])
        self.assertTrue(all(result["checks"].values()))
        self.assertEqual(2, result["schema_version"])

    def test_ruleset_only_fails_when_admin_enforcement_is_not_verifiable(self) -> None:
        result = audit.evaluate(
            valid_branch(),
            None,
            valid_rules(),
            "Break-glass bypass is restricted and documented in #522.",
        )
        self.assertEqual("FAIL", result["decision"])
        self.assertFalse(result["checks"]["administrator_enforcement_verified"])
        self.assertEqual("unverified", result["sources"]["administrator_enforcement_source"])

    def test_unprotected_branch_fails(self) -> None:
        branch = valid_branch()
        branch["protected"] = False
        result = audit.evaluate(
            branch,
            valid_legacy(),
            [],
            "No routine bypass; emergency path is documented.",
        )
        self.assertEqual("FAIL", result["decision"])
        self.assertFalse(result["checks"]["protected_branch"])

    def test_missing_required_check_fails(self) -> None:
        protection = valid_legacy()
        protection["required_status_checks"]["contexts"] = ["esc-foundation"]
        result = audit.evaluate(
            valid_branch(),
            protection,
            [],
            "No routine bypass; emergency path is documented.",
        )
        self.assertFalse(result["checks"]["required_status_checks_present"])
        self.assertEqual("FAIL", result["decision"])

    def test_non_strict_status_policy_fails(self) -> None:
        protection = valid_legacy()
        protection["required_status_checks"]["strict"] = False
        result = audit.evaluate(
            valid_branch(),
            protection,
            [],
            "No routine bypass; emergency path is documented.",
        )
        self.assertFalse(result["checks"]["strict_up_to_date_status_checks"])

    def test_admin_enforcement_is_mandatory(self) -> None:
        for value in ({"enabled": False}, None):
            with self.subTest(value=value):
                protection = valid_legacy()
                if value is None:
                    protection.pop("enforce_admins")
                else:
                    protection["enforce_admins"] = value
                result = audit.evaluate(
                    valid_branch(),
                    protection,
                    [],
                    "No routine bypass; emergency path is documented.",
                )
                self.assertFalse(result["checks"]["administrator_enforcement_verified"])
                self.assertEqual("FAIL", result["decision"])

    def test_conversation_resolution_is_mandatory(self) -> None:
        for value in ({"enabled": False}, None):
            with self.subTest(value=value):
                protection = valid_legacy()
                if value is None:
                    protection.pop("required_conversation_resolution")
                else:
                    protection["required_conversation_resolution"] = value
                result = audit.evaluate(
                    valid_branch(),
                    protection,
                    [],
                    "No routine bypass; emergency path is documented.",
                )
                self.assertFalse(result["checks"]["conversation_resolution_required"])
                self.assertEqual("FAIL", result["decision"])

    def test_ruleset_can_supply_conversation_resolution(self) -> None:
        protection = valid_legacy()
        protection.pop("required_conversation_resolution")
        result = audit.evaluate(
            valid_branch(),
            protection,
            valid_rules(),
            "No routine bypass; emergency path is documented.",
        )
        self.assertTrue(result["checks"]["conversation_resolution_required"])
        self.assertEqual("PASS", result["decision"])

    def test_force_push_and_deletion_must_be_blocked(self) -> None:
        protection = valid_legacy()
        protection["allow_force_pushes"]["enabled"] = True
        protection["allow_deletions"]["enabled"] = True
        result = audit.evaluate(
            valid_branch(),
            protection,
            [],
            "No routine bypass; emergency path is documented.",
        )
        self.assertFalse(result["checks"]["force_push_blocked"])
        self.assertFalse(result["checks"]["branch_deletion_blocked"])
        self.assertEqual("FAIL", result["decision"])

    def test_break_glass_statement_is_mandatory(self) -> None:
        result = audit.evaluate(valid_branch(), valid_legacy(), [], "none")
        self.assertFalse(result["checks"]["break_glass_documented"])
        self.assertEqual("FAIL", result["decision"])

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
                    "--output",
                    str(output_path),
                ],
                check=False,
                capture_output=True,
                text=True,
            )

            self.assertEqual(1, completed.returncode)
            evidence = json.loads(output_path.read_text(encoding="utf-8"))
            self.assertEqual("FAIL", evidence["decision"])
            self.assertRegex(evidence["captured_input_sha256"]["branch_json"], r"^[0-9a-f]{64}$")
            self.assertRegex(evidence["captured_input_sha256"]["rules_json"], r"^[0-9a-f]{64}$")


if __name__ == "__main__":
    unittest.main()
