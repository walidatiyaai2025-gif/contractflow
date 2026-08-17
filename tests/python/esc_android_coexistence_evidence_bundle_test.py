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
    EvidenceBundleError,
    artifact_reference,
    build_manifest,
    canonical_bundle_sha256,
    verify_manifest,
)

SOURCE_SHA = "a" * 40


def create_evidence(root: Path) -> dict[str, str]:
    paths: dict[str, str] = {}
    for index, key in enumerate(ARTIFACT_KEYS, start=1):
        relative = f"retained/{index:02d}-{key}.txt"
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(f"verified evidence for {key}\n", encoding="utf-8")
        paths[key] = relative
    return paths


class EscAndroidCoexistenceEvidenceBundleTests(unittest.TestCase):
    def setUp(self) -> None:
        self.temp_dir = tempfile.TemporaryDirectory()
        self.root = Path(self.temp_dir.name)
        self.paths = create_evidence(self.root)
        self.manifest = build_manifest(
            self.root,
            SOURCE_SHA,
            "2026-08-17T20:00:00Z",
            self.paths,
        )

    def tearDown(self) -> None:
        self.temp_dir.cleanup()

    def test_exact_source_non_empty_files_build_and_verify_canonical_bundle(self) -> None:
        verified = verify_manifest(self.manifest, self.root, SOURCE_SHA)

        self.assertEqual(verified, self.manifest)
        self.assertEqual(
            verified["bundle_sha256"],
            canonical_bundle_sha256(verified),
        )
        self.assertEqual(set(verified["artifacts"]), set(ARTIFACT_KEYS))
        for key in ARTIFACT_KEYS:
            entry = verified["artifacts"][key]
            self.assertGreater(entry["size"], 0)
            self.assertEqual(len(entry["sha256"]), 64)
            reference = artifact_reference(verified, key)
            self.assertIn(verified["bundle_sha256"], reference)
            self.assertIn(entry["sha256"], reference)
            self.assertIn(entry["path"], reference)

    def test_file_modification_after_manifest_creation_fails_closed(self) -> None:
        path = self.root / self.paths["session_isolation"]
        original = path.read_bytes()
        path.write_bytes(b"X" * len(original))

        with self.assertRaisesRegex(EvidenceBundleError, "SHA-256 mismatch"):
            verify_manifest(self.manifest, self.root, SOURCE_SHA)

    def test_file_deletion_after_manifest_creation_fails_closed(self) -> None:
        (self.root / self.paths["safe_only_push"]).unlink()

        with self.assertRaisesRegex(EvidenceBundleError, "is missing"):
            verify_manifest(self.manifest, self.root, SOURCE_SHA)

    def test_empty_file_fails_closed_during_bundle_creation(self) -> None:
        (self.root / self.paths["firebase_delivery"]).write_bytes(b"")

        with self.assertRaisesRegex(EvidenceBundleError, "is empty"):
            build_manifest(
                self.root,
                SOURCE_SHA,
                "2026-08-17T20:00:00Z",
                self.paths,
            )

    def test_missing_or_unexpected_artifact_key_fails_closed(self) -> None:
        missing = dict(self.paths)
        missing.pop("business_uat")
        with self.assertRaisesRegex(EvidenceBundleError, "required evidence keys exactly"):
            build_manifest(
                self.root,
                SOURCE_SHA,
                "2026-08-17T20:00:00Z",
                missing,
            )

        extra = dict(self.paths)
        extra["unexpected"] = self.paths["device"]
        with self.assertRaisesRegex(EvidenceBundleError, "required evidence keys exactly"):
            build_manifest(
                self.root,
                SOURCE_SHA,
                "2026-08-17T20:00:00Z",
                extra,
            )

    def test_absolute_and_traversal_paths_fail_closed(self) -> None:
        absolute = dict(self.paths)
        absolute["device"] = str((self.root / self.paths["device"]).resolve())
        with self.assertRaisesRegex(EvidenceBundleError, "relative to the evidence root"):
            build_manifest(
                self.root,
                SOURCE_SHA,
                "2026-08-17T20:00:00Z",
                absolute,
            )

        traversal = dict(self.paths)
        traversal["device"] = "../outside.txt"
        with self.assertRaisesRegex(EvidenceBundleError, "traversal"):
            build_manifest(
                self.root,
                SOURCE_SHA,
                "2026-08-17T20:00:00Z",
                traversal,
            )

    def test_manifest_path_tampering_fails_even_with_recomputed_bundle_hash(self) -> None:
        tampered = deepcopy(self.manifest)
        tampered["artifacts"]["device"]["path"] = "../device.txt"
        tampered["bundle_sha256"] = canonical_bundle_sha256(tampered)

        with self.assertRaisesRegex(EvidenceBundleError, "traversal"):
            verify_manifest(tampered, self.root, SOURCE_SHA)

    def test_manifest_source_tampering_fails_even_with_recomputed_bundle_hash(self) -> None:
        tampered = deepcopy(self.manifest)
        tampered["source_sha"] = "b" * 40
        tampered["bundle_sha256"] = canonical_bundle_sha256(tampered)

        with self.assertRaisesRegex(EvidenceBundleError, "source SHA mismatch"):
            verify_manifest(tampered, self.root, SOURCE_SHA)

    def test_bundle_digest_tampering_fails_closed(self) -> None:
        tampered = deepcopy(self.manifest)
        tampered["bundle_sha256"] = "0" * 64

        with self.assertRaisesRegex(EvidenceBundleError, "bundle SHA-256 mismatch"):
            verify_manifest(tampered, self.root, SOURCE_SHA)

    def test_malformed_artifact_digest_fails_closed(self) -> None:
        tampered = deepcopy(self.manifest)
        tampered["artifacts"]["coexistence"]["sha256"] = "NOT-A-HASH"
        tampered["bundle_sha256"] = canonical_bundle_sha256(tampered)

        with self.assertRaisesRegex(EvidenceBundleError, "lowercase SHA-256 digest"):
            verify_manifest(tampered, self.root, SOURCE_SHA)

    def test_manifest_artifact_key_tampering_fails_closed(self) -> None:
        tampered = deepcopy(self.manifest)
        tampered["artifacts"]["extra"] = tampered["artifacts"].pop("device")
        tampered["bundle_sha256"] = canonical_bundle_sha256(tampered)

        with self.assertRaisesRegex(EvidenceBundleError, "exactly the required artifact keys"):
            verify_manifest(tampered, self.root, SOURCE_SHA)

    def test_bundle_builder_has_no_device_network_or_runtime_mutation_primitive(self) -> None:
        source = (
            ROOT / "scripts/build_esc_android_coexistence_evidence_bundle.py"
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
