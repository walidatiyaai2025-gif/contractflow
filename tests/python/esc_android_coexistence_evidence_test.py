#!/usr/bin/env python3
from __future__ import annotations

import copy
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

from validate_esc_android_coexistence_evidence import (  # noqa: E402
    ESC_APPLICATION_ID,
    SAFE_APPLICATION_ID,
    EvidenceError,
    validate_record,
)

SOURCE_SHA = "1" * 40


def valid_record() -> dict[str, object]:
    return {
        "schema_version": 1,
        "decision": "PASS",
        "source_sha": SOURCE_SHA,
        "tester": "Android UAT Team",
        "tested_at_utc": "2026-08-17T12:00:00Z",
        "device": {
            "reference": "device-lab-01",
            "android_version": "15",
        },
        "safe_contract": {
            "application_id": SAFE_APPLICATION_ID,
            "version": "1.0.0+100",
            "apk_sha256": "2" * 64,
            "signer_sha256": "3" * 64,
        },
        "esc": {
            "application_id": ESC_APPLICATION_ID,
            "version": "1.0.0+1",
            "apk_sha256": "4" * 64,
            "signer_sha256": "5" * 64,
            "firebase_reference": "firebase-app:esc-production",
        },
        "checks": {
            "dual_install": {"status": "PASS", "evidence": "evidence:dual-install"},
            "independent_launch": {"status": "PASS", "evidence": "evidence:launch"},
            "session_isolation": {"status": "PASS", "evidence": "evidence:session"},
            "safe_only_push": {"status": "PASS", "evidence": "evidence:safe-push"},
            "esc_only_push": {"status": "PASS", "evidence": "evidence:esc-push"},
            "deep_link_isolation": {"status": "PASS", "evidence": "evidence:deep-link"},
            "independent_update": {"status": "PASS", "evidence": "evidence:update"},
            "clear_data_uninstall_isolation": {
                "status": "PASS",
                "evidence": "evidence:clear-uninstall",
            },
        },
        "evidence": {
            "device": "issue:421#device",
            "business_uat": "issue:421#business-uat",
            "coexistence": "issue:421#coexistence",
            "firebase": "issue:421#firebase",
        },
    }


class AndroidCoexistenceEvidenceTests(unittest.TestCase):
    def test_valid_exact_source_pass_record(self) -> None:
        record = valid_record()
        expected = {
            "device": "issue:421#device",
            "business_uat": "issue:421#business-uat",
            "coexistence": "issue:421#coexistence",
            "firebase": "issue:421#firebase",
        }
        self.assertEqual(validate_record(record, SOURCE_SHA, expected), expected)

    def test_fail_decision_is_rejected(self) -> None:
        record = valid_record()
        record["decision"] = "FAIL"
        with self.assertRaisesRegex(EvidenceError, "decision must be PASS"):
            validate_record(record, SOURCE_SHA)

    def test_source_mismatch_is_rejected(self) -> None:
        with self.assertRaisesRegex(EvidenceError, "source SHA mismatch"):
            validate_record(valid_record(), "a" * 40)

    def test_shared_signing_lineage_is_rejected(self) -> None:
        record = valid_record()
        safe = record["safe_contract"]
        esc = record["esc"]
        assert isinstance(safe, dict) and isinstance(esc, dict)
        esc["signer_sha256"] = safe["signer_sha256"]
        with self.assertRaisesRegex(EvidenceError, "signing certificate"):
            validate_record(record, SOURCE_SHA)

    def test_missing_required_check_is_rejected(self) -> None:
        record = valid_record()
        checks = record["checks"]
        assert isinstance(checks, dict)
        checks.pop("deep_link_isolation")
        with self.assertRaisesRegex(EvidenceError, "missing required coexistence checks"):
            validate_record(record, SOURCE_SHA)

    def test_failed_check_is_rejected(self) -> None:
        record = valid_record()
        checks = record["checks"]
        assert isinstance(checks, dict)
        check = checks["esc_only_push"]
        assert isinstance(check, dict)
        check["status"] = "FAIL"
        with self.assertRaisesRegex(EvidenceError, "esc_only_push.status must be PASS"):
            validate_record(record, SOURCE_SHA)

    def test_publish_reference_mismatch_is_rejected(self) -> None:
        record = valid_record()
        expected = {
            "device": "different-device-reference",
            "business_uat": "issue:421#business-uat",
            "coexistence": "issue:421#coexistence",
            "firebase": "issue:421#firebase",
        }
        with self.assertRaisesRegex(EvidenceError, "does not match the publish input"):
            validate_record(record, SOURCE_SHA, expected)


if __name__ == "__main__":
    unittest.main()
