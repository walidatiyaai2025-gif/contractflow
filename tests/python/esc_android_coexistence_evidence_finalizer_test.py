#!/usr/bin/env python3
from __future__ import annotations

from copy import deepcopy
from pathlib import Path
import tempfile
import sys
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

from build_esc_android_coexistence_evidence_bundle import (  # noqa: E402
    ARTIFACT_KEYS,
    artifact_reference,
    build_manifest,
)
from finalize_esc_android_coexistence_evidence import (  # noqa: E402
    CHECK_ARTIFACTS,
    FinalizerError,
    TOP_LEVEL_ARTIFACTS,
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


def create_bundle(root: Path) -> dict[str, object]:
    paths: dict[str, str] = {}
    for index, key in enumerate(ARTIFACT_KEYS, start=1):
        relative = f"evidence/{index:02d}-{key}.txt"
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(f"retained real-device evidence for {key}\n", encoding="utf-8")
        paths[key] = relative
    return build_manifest(
        root,
        SOURCE_SHA,
        "2026-08-17T18:50:00Z",
        paths,
    )


def finalize(
    draft: dict[str, object],
    manifest: dict[str, object],
    root: Path,
):
    return finalize_record(
        draft,
        SOURCE_SHA,
        "2026-08-17T19:00:00Z",
        manifest,
        root,
    )


class EscAndroidCoexistenceEvidenceFinalizerTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp_dir = tempfile.TemporaryDirectory()
        self.root = Path(self.temp_dir.name)
        self.manifest = create_bundle(self.root)

    def tearDown(self) -> None:
        self.temp_dir.cleanup()

    def test_verified_bundle_produces_validator_accepted_pass(self) -> None:
        draft = draft_record()
        record = finalize(draft, self.manifest, self.root)

        self.assertEqual(record["decision"], "PASS")
        self.assertEqual(record["source_sha"], SOURCE_SHA)
        self.assertEqual(record["tested_at_utc"], "2026-08-17T19:00:00Z")
        self.assertEqual(record["safe_contract"], draft["safe_contract"])
        self.assertEqual(record["device"], draft["device"])
        self.assertEqual(
            record["evidence_bundle"]["sha256"],
            self.manifest["bundle_sha256"],
        )
        self.assertEqual(
            record["esc"]["firebase_reference"],
            artifact_reference(self.manifest, "esc_firebase_identity"),
        )

        for name, artifact_key in CHECK_ARTIFACTS.items():
            self.assertEqual(record["checks"][name]["status"], "PASS")
            self.assertEqual(
                record["checks"][name]["evidence"],
                artifact_reference(self.manifest, artifact_key),
            )

        expected_evidence = {
            name: artifact_reference(self.manifest, artifact_key)
            for name, artifact_key in TOP_LEVEL_ARTIFACTS.items()
        }
        self.assertEqual(record["evidence"], expected_evidence)
        validate_record(record, SOURCE_SHA, expected_evidence)

    def test_file_modification_after_bundle_creation_blocks_pass(self) -> None:
        entry = self.manifest["artifacts"]["session_isolation"]
        path = self.root / entry["path"]
        original = path.read_bytes()
        path.write_bytes(b"Z" * len(original))

        with self.assertRaisesRegex(FinalizerError, "SHA-256 mismatch"):
            finalize(draft_record(), self.manifest, self.root)

    def test_file_deletion_after_bundle_creation_blocks_pass(self) -> None:
        entry = self.manifest["artifacts"]["esc_only_push"]
        (self.root / entry["path"]).unlink()

        with self.assertRaisesRegex(FinalizerError, "is missing"):
            finalize(draft_record(), self.manifest, self.root)

    def test_manifest_source_sha_mismatch_blocks_pass(self) -> None:
        tampered = deepcopy(self.manifest)
        tampered["source_sha"] = "b" * 40

        with self.assertRaisesRegex(FinalizerError, "source SHA mismatch"):
            finalize(draft_record(), tampered, self.root)

    def test_source_sha_mismatch_in_draft_fails_closed(self) -> None:
        draft = draft_record()
        draft["source_sha"] = "b" * 40
        with self.assertRaisesRegex(FinalizerError, "draft source SHA mismatch"):
            finalize(draft, self.manifest, self.root)

    def test_missing_objective_pass_fails_closed(self) -> None:
        draft = draft_record()
        draft["checks"]["deep_link_isolation"]["status"] = "PENDING"
        with self.assertRaisesRegex(FinalizerError, "deep_link_isolation must already be PASS"):
            finalize(draft, self.manifest, self.root)

    def test_final_pass_record_cannot_be_re_finalized(self) -> None:
        draft = draft_record()
        draft["decision"] = "PASS"
        with self.assertRaisesRegex(FinalizerError, "PENDING coexistence draft"):
            finalize(draft, self.manifest, self.root)

    def test_free_form_evidence_cli_inputs_are_removed(self) -> None:
        source = (ROOT / "scripts/finalize_esc_android_coexistence_evidence.py").read_text(
            encoding="utf-8"
        )
        self.assertIn('"--evidence-manifest"', source)
        self.assertIn('"--evidence-root"', source)
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
