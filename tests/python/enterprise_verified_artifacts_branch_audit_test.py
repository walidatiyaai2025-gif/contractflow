#!/usr/bin/env python3
from __future__ import annotations

import importlib.util
import json
from pathlib import Path
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
SCRIPTS = ROOT / "scripts"
SCRIPT_PATH = SCRIPTS / "enterprise_verified_artifacts.py"
sys.path.insert(0, str(SCRIPTS))

SPEC = importlib.util.spec_from_file_location("enterprise_verified_artifacts", SCRIPT_PATH)
assert SPEC and SPEC.loader
artifacts = importlib.util.module_from_spec(SPEC)
SPEC.loader.exec_module(artifacts)

SOURCE_SHA = "a" * 40
SHA256 = "b" * 64


def valid_audit() -> dict[str, object]:
    return {
        "schema_version": 2,
        "branch": "enterprise-safecontracts",
        "decision": "PASS",
        "checks": {
            "protected_branch": True,
            "pull_request_required": True,
            "required_status_checks_present": True,
            "strict_up_to_date_status_checks": True,
            "administrator_enforcement_verified": True,
            "conversation_resolution_required": True,
            "force_push_blocked": True,
            "branch_deletion_blocked": True,
            "break_glass_documented": True,
        },
        "observed_required_checks": ["esc-foundation", "esc-mobile"],
        "required_checks": ["esc-foundation", "esc-mobile"],
        "break_glass_statement": "No routine bypass; emergency owner approval is required.",
        "sources": {
            "legacy_branch_protection_present": True,
            "administrator_enforcement_source": "legacy_branch_protection",
            "effective_rule_types": [],
        },
        "captured_input_sha256": {
            "branch_json": SHA256,
            "rules_json": SHA256,
            "protection_json": SHA256,
        },
        "audited_utc": "2026-08-18T00:00:00Z",
    }


class EnterpriseVerifiedArtifactsBranchAuditTests(unittest.TestCase):
    def test_valid_schema_v2_audit_passes(self) -> None:
        payload = valid_audit()
        result = artifacts.validate_branch_protection_audit_payload(payload)
        self.assertEqual("PASS", result["decision"])
        self.assertRegex(artifacts.audit_document_digest(result), r"^[0-9a-f]{64}$")

    def test_failed_decision_is_rejected(self) -> None:
        payload = valid_audit()
        payload["decision"] = "FAIL"
        with self.assertRaisesRegex(artifacts.PolicyError, "decision=PASS"):
            artifacts.validate_branch_protection_audit_payload(payload)

    def test_admin_enforcement_and_conversation_resolution_are_mandatory(self) -> None:
        for control in (
            "administrator_enforcement_verified",
            "conversation_resolution_required",
        ):
            with self.subTest(control=control):
                payload = valid_audit()
                payload["checks"][control] = False
                with self.assertRaisesRegex(artifacts.PolicyError, "failed controls"):
                    artifacts.validate_branch_protection_audit_payload(payload)

    def test_authoritative_legacy_protection_evidence_is_mandatory(self) -> None:
        payload = valid_audit()
        payload["sources"]["administrator_enforcement_source"] = "unverified"
        with self.assertRaisesRegex(artifacts.PolicyError, "administrator enforcement"):
            artifacts.validate_branch_protection_audit_payload(payload)

        payload = valid_audit()
        payload["captured_input_sha256"]["protection_json"] = None
        with self.assertRaisesRegex(artifacts.PolicyError, "protection_json digest"):
            artifacts.validate_branch_protection_audit_payload(payload)

    def test_load_requires_canonical_audit_document(self) -> None:
        payload = valid_audit()
        with tempfile.TemporaryDirectory() as temp_dir:
            path = Path(temp_dir) / "audit.json"
            path.write_text(
                json.dumps(payload, indent=2, sort_keys=True) + "\n",
                encoding="utf-8",
            )
            loaded, digest = artifacts.load_branch_protection_audit(path)
            self.assertEqual(payload, loaded)
            self.assertEqual(artifacts.audit_document_digest(payload), digest)

            path.write_text(json.dumps(payload), encoding="utf-8")
            with self.assertRaisesRegex(artifacts.PolicyError, "canonical schema-v2"):
                artifacts.load_branch_protection_audit(path)

    def test_embedded_provenance_binds_audit_digest_and_source_sha(self) -> None:
        audit = valid_audit()
        provenance = {
            "branch_protection_audit": audit,
            "branch_protection_audit_sha256": artifacts.audit_document_digest(audit),
            "branch_protection_audit_source_sha": SOURCE_SHA,
        }
        artifacts.validate_embedded_branch_protection_audit(provenance, SOURCE_SHA)

        bad_digest = dict(provenance)
        bad_digest["branch_protection_audit_sha256"] = "0" * 64
        with self.assertRaisesRegex(artifacts.PolicyError, "digest mismatch"):
            artifacts.validate_embedded_branch_protection_audit(bad_digest, SOURCE_SHA)

        bad_source = dict(provenance)
        bad_source["branch_protection_audit_source_sha"] = "c" * 40
        with self.assertRaisesRegex(artifacts.PolicyError, "source SHA mismatch"):
            artifacts.validate_embedded_branch_protection_audit(bad_source, SOURCE_SHA)


if __name__ == "__main__":
    unittest.main()
