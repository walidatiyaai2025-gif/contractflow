#!/usr/bin/env python3
"""Build and verify a content-addressed ESC Android coexistence evidence bundle.

This utility never performs device/runtime UAT. It binds the exact objective UAT
draft plus already-collected evidence files to one ESC source SHA so later PASS
finalization can re-hash retained files and fail closed on deletion, modification,
path escape, source drift, draft substitution, or manifest tampering.
"""

from __future__ import annotations

import argparse
from datetime import datetime
import hashlib
import json
from pathlib import Path, PurePosixPath, PureWindowsPath
import re
import sys
from typing import Any

ARTIFACT_KEYS = (
    "objective_draft",
    "session_isolation",
    "safe_only_push",
    "esc_only_push",
    "independent_update",
    "clear_data_uninstall_isolation",
    "esc_firebase_identity",
    "device",
    "business_uat",
    "coexistence",
    "firebase_delivery",
)
SHA40_RE = re.compile(r"^[0-9a-f]{40}$")
SHA256_RE = re.compile(r"^[0-9a-f]{64}$")
MANIFEST_KEYS = {
    "schema_version",
    "source_sha",
    "collected_at_utc",
    "artifacts",
    "bundle_sha256",
}
ARTIFACT_ENTRY_KEYS = {"path", "size", "sha256"}


class EvidenceBundleError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise EvidenceBundleError(message)


def source_sha(value: Any) -> str:
    if not isinstance(value, str):
        fail("source SHA must be a string")
    normalized = value.strip().lower()
    if SHA40_RE.fullmatch(normalized) is None:
        fail("source SHA must be a full 40-character Git SHA")
    return normalized


def utc_timestamp(value: Any, label: str = "collected-at-utc") -> str:
    if not isinstance(value, str):
        fail(f"{label} must be a string")
    normalized = value.strip()
    if not normalized.endswith("Z"):
        fail(f"{label} must be UTC and end with Z")
    try:
        datetime.fromisoformat(normalized[:-1] + "+00:00")
    except ValueError as exc:
        raise EvidenceBundleError(
            f"{label} must be a valid ISO-8601 timestamp"
        ) from exc
    return normalized


def relative_path(value: Any, label: str) -> str:
    if not isinstance(value, str):
        fail(f"{label} path must be a string")
    normalized = value.strip()
    if not normalized or "\x00" in normalized or "\\" in normalized:
        fail(f"{label} path must be a safe POSIX relative path")
    if PurePosixPath(normalized).is_absolute() or PureWindowsPath(normalized).is_absolute():
        fail(f"{label} path must be relative to the evidence root")
    parts = normalized.split("/")
    if any(part in {"", ".", ".."} for part in parts):
        fail(f"{label} path must not contain empty, dot, or traversal segments")
    if PureWindowsPath(normalized).drive:
        fail(f"{label} path must not contain a drive prefix")
    return PurePosixPath(normalized).as_posix()


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(block)
    return digest.hexdigest()


def evidence_file(root: Path, relative: str, label: str) -> Path:
    if not root.is_dir():
        fail(f"evidence root is missing or not a directory: {root}")
    root_resolved = root.resolve()
    candidate = root.joinpath(*PurePosixPath(relative).parts)
    try:
        resolved = candidate.resolve(strict=True)
    except FileNotFoundError as exc:
        raise EvidenceBundleError(f"{label} evidence file is missing: {relative}") from exc
    try:
        resolved.relative_to(root_resolved)
    except ValueError as exc:
        raise EvidenceBundleError(
            f"{label} evidence file escapes the evidence root: {relative}"
        ) from exc
    if not resolved.is_file():
        fail(f"{label} evidence path is not a regular file: {relative}")
    if resolved.stat().st_size <= 0:
        fail(f"{label} evidence file is empty: {relative}")
    return resolved


def canonical_bundle_sha256(manifest: dict[str, Any]) -> str:
    payload = {key: value for key, value in manifest.items() if key != "bundle_sha256"}
    encoded = json.dumps(
        payload,
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")
    return hashlib.sha256(encoded).hexdigest()


def build_manifest(
    evidence_root: Path,
    expected_source_sha: str,
    collected_at_utc: str,
    artifact_paths: dict[str, str],
) -> dict[str, Any]:
    source = source_sha(expected_source_sha)
    collected = utc_timestamp(collected_at_utc)
    if set(artifact_paths) != set(ARTIFACT_KEYS):
        missing = sorted(set(ARTIFACT_KEYS) - set(artifact_paths))
        extra = sorted(set(artifact_paths) - set(ARTIFACT_KEYS))
        detail = []
        if missing:
            detail.append("missing=" + ",".join(missing))
        if extra:
            detail.append("unexpected=" + ",".join(extra))
        fail("artifact set must match the required evidence keys exactly: " + "; ".join(detail))

    artifacts: dict[str, dict[str, Any]] = {}
    seen_paths: set[str] = set()
    for key in ARTIFACT_KEYS:
        rel = relative_path(artifact_paths[key], key)
        if rel in seen_paths:
            fail(f"artifact paths must be unique; duplicate path: {rel}")
        seen_paths.add(rel)
        path = evidence_file(evidence_root, rel, key)
        artifacts[key] = {
            "path": rel,
            "size": path.stat().st_size,
            "sha256": sha256_file(path),
        }

    manifest: dict[str, Any] = {
        "schema_version": 1,
        "source_sha": source,
        "collected_at_utc": collected,
        "artifacts": artifacts,
    }
    manifest["bundle_sha256"] = canonical_bundle_sha256(manifest)
    return manifest


def verify_manifest(
    manifest: dict[str, Any],
    evidence_root: Path,
    expected_source_sha: str,
) -> dict[str, Any]:
    if set(manifest) != MANIFEST_KEYS:
        fail("manifest must contain exactly the required top-level keys")
    if manifest.get("schema_version") != 1:
        fail("manifest schema_version must be 1")

    source = source_sha(manifest.get("source_sha"))
    expected = source_sha(expected_source_sha)
    if source != expected:
        fail(f"manifest source SHA mismatch: manifest={source}, expected={expected}")
    collected = utc_timestamp(manifest.get("collected_at_utc"))

    artifacts = manifest.get("artifacts")
    if not isinstance(artifacts, dict):
        fail("manifest artifacts must be an object")
    if set(artifacts) != set(ARTIFACT_KEYS):
        fail("manifest artifacts must contain exactly the required artifact keys")

    normalized_artifacts: dict[str, dict[str, Any]] = {}
    seen_paths: set[str] = set()
    for key in ARTIFACT_KEYS:
        entry = artifacts.get(key)
        if not isinstance(entry, dict) or set(entry) != ARTIFACT_ENTRY_KEYS:
            fail(f"manifest artifact {key} must contain exactly path, size, sha256")
        rel = relative_path(entry.get("path"), key)
        if rel in seen_paths:
            fail(f"manifest artifact paths must be unique; duplicate path: {rel}")
        seen_paths.add(rel)

        size = entry.get("size")
        if isinstance(size, bool) or not isinstance(size, int) or size <= 0:
            fail(f"manifest artifact {key} size must be a positive integer")
        digest = entry.get("sha256")
        if not isinstance(digest, str) or SHA256_RE.fullmatch(digest) is None:
            fail(f"manifest artifact {key} sha256 must be a lowercase SHA-256 digest")

        path = evidence_file(evidence_root, rel, key)
        actual_size = path.stat().st_size
        if actual_size != size:
            fail(
                f"manifest artifact {key} size mismatch: manifest={size}, actual={actual_size}"
            )
        actual_digest = sha256_file(path)
        if actual_digest != digest:
            fail(
                f"manifest artifact {key} SHA-256 mismatch: "
                f"manifest={digest}, actual={actual_digest}"
            )
        normalized_artifacts[key] = {
            "path": rel,
            "size": size,
            "sha256": digest,
        }

    bundle_digest = manifest.get("bundle_sha256")
    if not isinstance(bundle_digest, str) or SHA256_RE.fullmatch(bundle_digest) is None:
        fail("manifest bundle_sha256 must be a lowercase SHA-256 digest")

    normalized = {
        "schema_version": 1,
        "source_sha": source,
        "collected_at_utc": collected,
        "artifacts": normalized_artifacts,
        "bundle_sha256": bundle_digest,
    }
    actual_bundle_digest = canonical_bundle_sha256(normalized)
    if actual_bundle_digest != bundle_digest:
        fail(
            "manifest bundle SHA-256 mismatch: "
            f"manifest={bundle_digest}, actual={actual_bundle_digest}"
        )
    return normalized


def load_manifest(path: Path) -> dict[str, Any]:
    if not path.is_file() or path.stat().st_size <= 0:
        fail(f"evidence manifest is missing or empty: {path}")
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise EvidenceBundleError(f"evidence manifest is invalid JSON: {exc}") from exc
    if not isinstance(value, dict):
        fail("evidence manifest root must be an object")
    return value


def artifact_reference(manifest: dict[str, Any], key: str) -> str:
    if key not in ARTIFACT_KEYS:
        fail(f"unknown evidence artifact key: {key}")
    entry = manifest["artifacts"][key]
    return (
        f"esc-evidence-bundle:sha256:{manifest['bundle_sha256']}"
        f"/artifact:{key}/sha256:{entry['sha256']}/path:{entry['path']}"
    )


def parse_artifacts(values: list[str]) -> dict[str, str]:
    parsed: dict[str, str] = {}
    for value in values:
        key, separator, path = value.partition("=")
        key = key.strip()
        if not separator or not key or not path.strip():
            fail("--artifact values must use KEY=RELATIVE_PATH")
        if key in parsed:
            fail(f"duplicate --artifact key: {key}")
        parsed[key] = path.strip()
    return parsed


def write_manifest(path: Path, manifest: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        json.dumps(manifest, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--evidence-root", type=Path, required=True)
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--collected-at-utc", required=True)
    parser.add_argument(
        "--artifact",
        action="append",
        default=[],
        metavar="KEY=RELATIVE_PATH",
        help="Required once for each fixed coexistence evidence key.",
    )
    parser.add_argument("--output", type=Path, required=True)
    return parser


def main() -> int:
    args = build_parser().parse_args()
    try:
        manifest = build_manifest(
            args.evidence_root,
            args.source_sha,
            args.collected_at_utc,
            parse_artifacts(args.artifact),
        )
        verify_manifest(manifest, args.evidence_root, args.source_sha)
        write_manifest(args.output, manifest)
    except EvidenceBundleError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1

    print(
        "ESC Android coexistence evidence bundle created and verified: "
        f"bundle_sha256={manifest['bundle_sha256']}, output={args.output}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
