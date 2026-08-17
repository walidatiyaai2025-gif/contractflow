#!/usr/bin/env python3
from __future__ import annotations

from copy import deepcopy
from pathlib import Path
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
import sys
sys.path.insert(0, str(ROOT / "scripts"))

from build_esc_android_coexistence_evidence_bundle import (  # noqa: E402
    ARTIFACT_KEYS,
    EvidenceBundleError,
    build_manifest,
    verify_manifest,
)

SOURCE_SHA = "a" * 40


def create_evidence_tree(root: Path) -> dict[str, str]:
    paths: dict[str, str] = {}
    for index, key in enumerate(ARTIFACT_KEYS, start=1):
        relative = f"evidence/{index:02d}-{key}.txt"
        path = root / relative
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(f"{key} retained evidence\n", encoding="utf-8")
        paths[key] = relative
    return paths


class EscAndroidCoexistenceEvidenceBundleTests(unittest.TestCase):
    def test_build_and_reverify_bundle(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            manifest = build_manifest(
                evidence_root,
                SOURCE_SHA,
                "2026-08-17T20:00:00Z",
                paths,
            )
            verified = verify_manifest(manifest, evidence_root, SOURCE_SHA)
            self.assertEqual(set(verified["artifacts"]), set(ARTIFACT_KEYS))
            self.assertEqual(len(verified["bundle_sha256"]), 64)

    def test_modified_file_after_manifest_fails_closed(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            manifest = build_manifest(
                evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
            )
            (evidence_root / paths["safe_only_push"]).write_text(
                "tampered after manifest\n", encoding="utf-8"
            )
            with self.assertRaisesRegex(EvidenceBundleError, "mismatch"):
                verify_manifest(manifest, evidence_root, SOURCE_SHA)

    def test_deleted_file_after_manifest_fails_closed(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            manifest = build_manifest(
                evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
            )
            (evidence_root / paths["firebase_delivery"]).unlink()
            with self.assertRaisesRegex(EvidenceBundleError, "missing"):
                verify_manifest(manifest, evidence_root, SOURCE_SHA)

    def test_path_traversal_and_windows_absolute_paths_are_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            paths["session_isolation"] = "../outside.txt"
            with self.assertRaisesRegex(EvidenceBundleError, "traversal"):
                build_manifest(
                    evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
                )

            paths = create_evidence_tree(evidence_root)
            paths["session_isolation"] = r"C:\evidence\session.txt"
            with self.assertRaisesRegex(EvidenceBundleError, "safe POSIX|relative"):
                build_manifest(
                    evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
                )

    def test_empty_file_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            (evidence_root / paths["coexistence"]).write_bytes(b"")
            with self.assertRaisesRegex(EvidenceBundleError, "empty"):
                build_manifest(
                    evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
                )

    def test_duplicate_artifact_path_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            paths["safe_only_push"] = paths["session_isolation"]
            with self.assertRaisesRegex(EvidenceBundleError, "unique"):
                build_manifest(
                    evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
                )

    def test_manifest_tampering_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            manifest = build_manifest(
                evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
            )
            tampered = deepcopy(manifest)
            tampered["artifacts"]["device"]["size"] += 1
            with self.assertRaisesRegex(EvidenceBundleError, "mismatch"):
                verify_manifest(tampered, evidence_root, SOURCE_SHA)

    def test_source_mismatch_is_rejected(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            manifest = build_manifest(
                evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
            )
            with self.assertRaisesRegex(EvidenceBundleError, "source SHA mismatch"):
                verify_manifest(manifest, evidence_root, "b" * 40)

    def test_objective_draft_is_mandatory(self) -> None:
        with tempfile.TemporaryDirectory() as temp:
            evidence_root = Path(temp)
            paths = create_evidence_tree(evidence_root)
            paths.pop("objective_draft")
            with self.assertRaisesRegex(EvidenceBundleError, "artifact set"):
                build_manifest(
                    evidence_root, SOURCE_SHA, "2026-08-17T20:00:00Z", paths
                )

    def test_tool_has_no_runtime_mutation_primitive(self) -> None:
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
