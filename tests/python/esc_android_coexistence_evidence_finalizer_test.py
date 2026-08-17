#!/usr/bin/env python3
from __future__ import annotations

from copy import deepcopy
from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

from finalize_esc_android_coexistence_evidence import (  # noqa: E402
    FinalizerError,
    MANUAL_CHECKS,
    finalize_record,
)
from validate_esc_android_coexistence_evidence import (  # noqa: E402
    ESC_APPLICATION_ID,
    SAFE_APPLICATION_ID,
    validate_record,
)

SOURCE_SHA = "a" * 40
SAFE_APK_SHA = "1" * 64
ESC_APK_SHA = "2" * 64
SAFE_SIGNER = "3" * 64
ESC_SIGNER = "4" * 64


def draft_record() -> dict[str, object]:
    pending = {
        "status": "PENDING",
        "evidence": "Not executed by the non-destructive session harness; explicit real-device UAT evidence is still required.",
    }
    return {
        "schema_version": 1,
        "decision": "PENDING",
        "source_sha": SOURCE_SHA,
        "tester": "ESC QA",
        "tested_at_utc": "2026-08-17T18:00:00Z",
        "device": {
            "reference": "device-01",
            "manufacturer": "Google",
            "model": "Pixel 9",
            "android_version": "15",
            "api_level": "35",
            "safe_contract_installed": True,
            "esc_installed": True,
            "dual_install_observed": True,
        },
        "safe_contract": {
            "application_id": SAFE_APPLICATION_ID,
            "version_name": "1.2.3",
            "version_code": "123",
            "version": "1.2.3+123",
            "apk_sha256": SAFE_APK_SHA,
            "signer_sha256": SAFE_SIGNER,
        },
        "esc": {
            "application_id": ESC_APPLICATION_ID,
            "version_name": "2.0.0",
            "version_code": "200",
            "version": "2.0.0+200",
            "apk_sha256": ESC_APK_SHA,
            "signer_sha256": ESC_SIGNER,
            "firebase_reference": "PENDING_REAL_DEVICE_FIREBASE_UAT",
        },
        "checks": {
            "dual_install": {
                "status": "PASS",
                "evidence": "ADB package paths confirmed both application IDs on device-01.",
            },
            "independent_launch": {
                "status": "PASS",
                "evidence": "Safe Contract PID 4321 and ESC PID 5432 observed independently.",
            },
            "session_isolation": deepcopy(pending),
            "safe_only_push": deepcopy(pending),
            "esc_only_push": deepcopy(pending),
            "deep_link_isolation": {
                "status": "PASS",
                "evidence": "esc-safecontracts URI resolved only to com.safecontracts.enterprise.",
            },
            "independent_update": deepcopy(pending),
            "clear_data_uninstall_isolation": deepcopy(pending),
        },
        "evidence": {
            "device": "ADB device device-01 objective draft",
            "business_uat": "PENDING business-owner runtime UAT sign-off",
            "coexistence": "PENDING remaining runtime coexistence scenarios",
            "firebase": "PENDING Safe-only and ESC-only FCM delivery evidence",
        },
        "objective_session": {
            "note": "objective-only draft retained as provenance",
        },
    }


def manual_evidence() -> dict[str, str]:
    return {
        "session_isolation": "UAT/session-isolation/run-2026-08-17",
        "safe_only_push": "UAT/fcm-safe-only/run-2026-08-17",
        "esc_only_push": "UAT/fcm-esc-only/run-2026-08-17",
        "independent_update": "UAT/independent-update/run-2026-08-17",
        "clear_data_uninstall_isolation": "UAT/data-lifecycle/run-2026-08-17",
    }


def finalize(draft: dict[str, object], evidence: dict[str, str] | None = None):
    return finalize_record(
        draft,
        SOURCE_SHA,
        "2026-08-17T19:00:00Z",
        evidence or manual_evidence(),
        esc_firebase_reference="Firebase/ESC/android-app/production-2026-08-17",
        device_evidence="UAT/device/device-01-2026-08-17",
        business_uat_evidence="UAT/business-owner/signoff-2026-08-17",
        coexistence_evidence="UAT/coexistence/full-run-2026-08-17",
        firebase_evidence="UAT/firebase/dual-delivery-2026-08-17",
    )


class EscAndroidCoexistenceEvidenceFinalizerTests(unittest.TestCase):
    def test_complete_explicit_uat_evidence_produces_validator_accepted_pass(self) -> None:
        draft = draft_record()
        record = finalize(draft)

        self.assertEqual(record["decision"], "PASS")
        self.assertEqual(record["source_sha"], SOURCE_SHA)
        self.assertEqual(record["tested_at_utc"], "2026-08-17T19:00:00Z")
        self.assertEqual(record["safe_contract"], draft["safe_contract"])
        self.assertEqual(record["device"], draft["device"])
        self.assertEqual(
            record["esc"]["apk_sha256"], draft["esc"]["apk_sha256"]
        )
        self.assertEqual(
            record["esc"]["firebase_reference"],
            "Firebase/ESC/android-app/production-2026-08-17",
        )
        for name in MANUAL_CHECKS:
            self.assertEqual(record["checks"][name]["status"], "PASS")
            self.assertEqual(record["checks"][name]["evidence"], manual_evidence()[name])

        validate_record(
            record,
            SOURCE_SHA,
            {
                "device": "UAT/device/device-01-2026-08-17",
                "business_uat": "UAT/business-owner/signoff-2026-08-17",
                "coexistence": "UAT/coexistence/full-run-2026-08-17",
                "firebase": "UAT/firebase/dual-delivery-2026-08-17",
            },
        )

    def test_missing_manual_evidence_key_fails_closed(self) -> None:
        evidence = manual_evidence()
        evidence.pop("safe_only_push")
        with self.assertRaisesRegex(FinalizerError, "exactly the remaining runtime checks"):
            finalize(draft_record(), evidence)

    def test_placeholder_manual_evidence_fails_closed(self) -> None:
        evidence = manual_evidence()
        evidence["esc_only_push"] = "PENDING FCM evidence"
        with self.assertRaisesRegex(FinalizerError, "placeholder"):
            finalize(draft_record(), evidence)

    def test_source_sha_mismatch_fails_closed(self) -> None:
        draft = draft_record()
        draft["source_sha"] = "b" * 40
        with self.assertRaisesRegex(FinalizerError, "source SHA mismatch"):
            finalize(draft)

    def test_missing_objective_pass_fails_closed(self) -> None:
        draft = draft_record()
        draft["checks"]["deep_link_isolation"]["status"] = "PENDING"
        with self.assertRaisesRegex(FinalizerError, "deep_link_isolation must already be PASS"):
            finalize(draft)

    def test_final_pass_record_cannot_be_re_finalized(self) -> None:
        draft = draft_record()
        draft["decision"] = "PASS"
        with self.assertRaisesRegex(FinalizerError, "PENDING coexistence draft"):
            finalize(draft)

    def test_tool_has_no_device_network_or_runtime_mutation_primitive(self) -> None:
        source = (ROOT / "scripts/finalize_esc_android_coexistence_evidence.py").read_text(
            encoding="utf-8"
        )
        for forbidden in (
            "import subprocess",
            "subprocess.run",
            " run_text(",
            '"adb"',
            "pm clear",
            "am force-stop",
            "firebase_admin",
            "requests.post",
            "urllib.request",
        ):
            self.assertNotIn(forbidden, source, forbidden)


if __name__ == "__main__":
    unittest.main()
