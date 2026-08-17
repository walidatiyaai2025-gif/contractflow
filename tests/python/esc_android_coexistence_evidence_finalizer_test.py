#!/usr/bin/env python3
from __future__ import annotations

from copy import deepcopy
import json
from pathlib import Path
import tempfile
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

from build_esc_android_coexistence_evidence_bundle import (  # noqa: E402
    ARTIFACT_KEYS,
    build_manifest,
)
from finalize_esc_android_coexistence_evidence import (  # noqa: E402
    FinalizerError,
    MANUAL_CHECKS,
    assert_draft_matches_manifest,
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


def create_manifest(evidence_root: Path, draft: dict[str, object]):
    paths: dict[str, str] = {}
    draft_relative = "evidence/objective-draft.json"
    draft_path = evidence_root / draft_relative
    draft_path.parent.mkdir(parents=True, exist_ok=True)
    draft_path.write_text(
        json.dumps(draft, indent=2, sort_keys=True) + "\n", encoding="utf-8"
    )
    paths["objective_draft"] = draft_relative

    for key in ARTIFACT_KEYS:
        if key == "objective_draft":
            continue
        relative = f"evidence/{key}.txt"
        path = evidence_root / relative
        path.write_text(f"completed retained evidence for {key}\n", encoding="utf-8")
        paths[key] = relative

    manifest = build_manifest(
        evidence_root,
        SOURCE_SHA,
        "2026-08-17T20:00:00Z",
        paths,
    )
    return draft_path, manifest


class EscAndroidCoexistenceEvidenceFinalizerTests(unittest.TestCase):
    def test_verified_manifest_produces_validator_accepted_pass(self) -> None:
        draft = draft_record()
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            draft_path, manifest = create_manifest(evidence_root, draft)
            assert_draft_matches_manifest(draft_path, manifest)
            record = finalize_record(
                draft,
                SOURCE_SHA,
                "2026-08-17T21:00:00Z",
                manifest,
                evidence_root,
            )

            self.assertEqual(record["decision"], "PASS")
            self.assertEqual(record["source_sha"], SOURCE_SHA)
            self.assertEqual(
                record["evidence_bundle"]["sha256"], manifest["bundle_sha256"]
            )
            self.assertEqual(
                record["evidence_bundle"]["objective_draft_sha256"],
                manifest["artifacts"]["objective_draft"]["sha256"],
            )
            self.assertEqual(record["safe_contract"], draft["safe_contract"])
            self.assertEqual(record["device"], draft["device"])

            for name in ("dual_install", "independent_launch", "deep_link_isolation"):
                self.assertEqual(record["checks"][name], draft["checks"][name])
            for name in MANUAL_CHECKS:
                self.assertEqual(record["checks"][name]["status"], "PASS")
                self.assertIn(
                    manifest["bundle_sha256"], record["checks"][name]["evidence"]
                )

            validate_record(record, SOURCE_SHA, record["evidence"])

    def test_modified_runtime_evidence_fails_closed(self) -> None:
        draft = draft_record()
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            _, manifest = create_manifest(evidence_root, draft)
            path = evidence_root / manifest["artifacts"]["esc_only_push"]["path"]
            path.write_text("tampered FCM evidence\n", encoding="utf-8")
            with self.assertRaisesRegex(FinalizerError, "bundle verification failed"):
                finalize_record(
                    draft,
                    SOURCE_SHA,
                    "2026-08-17T21:00:00Z",
                    manifest,
                    evidence_root,
                )

    def test_source_sha_mismatch_fails_closed(self) -> None:
        draft = draft_record()
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            _, manifest = create_manifest(evidence_root, draft)
            with self.assertRaisesRegex(FinalizerError, "draft source SHA mismatch"):
                finalize_record(
                    draft,
                    "b" * 40,
                    "2026-08-17T21:00:00Z",
                    manifest,
                    evidence_root,
                )

    def test_missing_objective_pass_fails_closed(self) -> None:
        draft = draft_record()
        draft["checks"]["deep_link_isolation"]["status"] = "PENDING"
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            _, manifest = create_manifest(evidence_root, draft)
            with self.assertRaisesRegex(
                FinalizerError, "deep_link_isolation must already be PASS"
            ):
                finalize_record(
                    draft,
                    SOURCE_SHA,
                    "2026-08-17T21:00:00Z",
                    manifest,
                    evidence_root,
                )

    def test_draft_must_match_hashed_objective_artifact(self) -> None:
        draft = draft_record()
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            draft_path, manifest = create_manifest(evidence_root, draft)
            draft_path.write_text("{}\n", encoding="utf-8")
            with self.assertRaisesRegex(FinalizerError, "objective draft"):
                assert_draft_matches_manifest(draft_path, manifest)

    def test_final_pass_record_cannot_be_re_finalized(self) -> None:
        draft = draft_record()
        draft["decision"] = "PASS"
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            _, manifest = create_manifest(evidence_root, draft)
            with self.assertRaisesRegex(FinalizerError, "PENDING coexistence draft"):
                finalize_record(
                    draft,
                    SOURCE_SHA,
                    "2026-08-17T21:00:00Z",
                    manifest,
                    evidence_root,
                )

    def test_cli_has_no_free_form_manual_evidence_arguments(self) -> None:
        source = (
            ROOT / "scripts/finalize_esc_android_coexistence_evidence.py"
        ).read_text(encoding="utf-8")
        for removed in (
            "--session-isolation-evidence",
            "--safe-only-push-evidence",
            "--esc-only-push-evidence",
            "--independent-update-evidence",
            "--clear-data-uninstall-evidence",
            "--esc-firebase-reference",
            "--device-evidence",
            "--business-uat-evidence",
            "--coexistence-evidence",
            "--firebase-evidence",
        ):
            self.assertNotIn(removed, source)
        self.assertIn("--evidence-manifest", source)
        self.assertIn("--evidence-root", source)

    def test_tool_has_no_device_network_or_runtime_mutation_primitive(self) -> None:
        source = (
            ROOT / "scripts/finalize_esc_android_coexistence_evidence.py"
        ).read_text(encoding="utf-8")
        for forbidden in (
            "import subprocess",
            "subprocess.run",
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
