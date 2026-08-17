#!/usr/bin/env python3
"""Finalize ESC Android coexistence evidence from a verified evidence bundle.

This tool does not perform runtime UAT. It accepts only an exact-source PENDING draft
whose objective device checks already passed, re-verifies every retained evidence file
against a content-addressed manifest, derives every final evidence reference from that
verified bundle, and writes PASS only after the existing coexistence validator accepts
the complete record.
"""

from __future__ import annotations

import argparse
from copy import deepcopy
from datetime import datetime
import json
from pathlib import Path
import sys
from typing import Any

from build_esc_android_coexistence_evidence_bundle import (
    EvidenceBundleError,
    artifact_reference,
    load_manifest,
    verify_manifest,
)
from validate_esc_android_coexistence_evidence import (
    ESC_APPLICATION_ID,
    EvidenceError,
    REQUIRED_CHECKS,
    SHA40_RE,
    load_record,
    validate_record,
)

OBJECTIVE_CHECKS = (
    "dual_install",
    "independent_launch",
    "deep_link_isolation",
)
MANUAL_CHECKS = (
    "session_isolation",
    "safe_only_push",
    "esc_only_push",
    "independent_update",
    "clear_data_uninstall_isolation",
)
CHECK_ARTIFACTS = {
    "dual_install": "coexistence",
    "independent_launch": "coexistence",
    "session_isolation": "session_isolation",
    "safe_only_push": "safe_only_push",
    "esc_only_push": "esc_only_push",
    "deep_link_isolation": "coexistence",
    "independent_update": "independent_update",
    "clear_data_uninstall_isolation": "clear_data_uninstall_isolation",
}
TOP_LEVEL_ARTIFACTS = {
    "device": "device",
    "business_uat": "business_uat",
    "coexistence": "coexistence",
    "firebase": "firebase_delivery",
}
PLACEHOLDER_MARKERS = (
    "pending",
    "replace_with",
    "not_recorded",
    "not recorded",
    "not_executed",
    "not executed",
    "todo",
    "tbd",
)


class FinalizerError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise FinalizerError(message)


def reference(value: Any, label: str) -> str:
    if not isinstance(value, str):
        fail(f"{label} must be a string")
    normalized = value.strip()
    if len(normalized) < 3:
        fail(f"{label} is required")
    lowered = normalized.lower()
    if any(marker in lowered for marker in PLACEHOLDER_MARKERS):
        fail(f"{label} must reference completed evidence, not a placeholder")
    return normalized


def source_sha(value: str) -> str:
    normalized = value.strip().lower()
    if SHA40_RE.fullmatch(normalized) is None:
        fail("source SHA must be a full 40-character Git SHA")
    return normalized


def tested_at_utc(value: str) -> str:
    normalized = value.strip()
    if not normalized.endswith("Z"):
        fail("tested-at-utc must be UTC and end with Z")
    try:
        datetime.fromisoformat(normalized[:-1] + "+00:00")
    except ValueError as exc:
        raise FinalizerError("tested-at-utc must be a valid ISO-8601 timestamp") from exc
    return normalized


def check_object(record: dict[str, Any], name: str) -> dict[str, Any]:
    checks = record.get("checks")
    if not isinstance(checks, dict):
        fail("draft checks must be an object")
    if set(checks) != set(REQUIRED_CHECKS):
        fail("draft must contain exactly the required coexistence checks")
    check = checks.get(name)
    if not isinstance(check, dict):
        fail(f"draft checks.{name} must be an object")
    return check


def assert_draft_boundary(record: dict[str, Any], expected_source_sha: str) -> None:
    if record.get("schema_version") != 1:
        fail("draft schema_version must be 1")
    if str(record.get("decision", "")).strip().upper() != "PENDING":
        fail("input must be a PENDING coexistence draft, not a final PASS record")

    actual_source = source_sha(str(record.get("source_sha", "")))
    if actual_source != expected_source_sha:
        fail(
            f"draft source SHA mismatch: record={actual_source}, expected={expected_source_sha}"
        )

    for name in OBJECTIVE_CHECKS:
        check = check_object(record, name)
        if str(check.get("status", "")).strip().upper() != "PASS":
            fail(f"objective draft check {name} must already be PASS")
        reference(check.get("evidence"), f"draft checks.{name}.evidence")

    for name in MANUAL_CHECKS:
        check = check_object(record, name)
        if str(check.get("status", "")).strip().upper() != "PENDING":
            fail(f"runtime draft check {name} must still be PENDING before finalization")

    esc = record.get("esc")
    if not isinstance(esc, dict) or esc.get("application_id") != ESC_APPLICATION_ID:
        fail("draft ESC application identity is invalid")


def derived_references(manifest: dict[str, Any]) -> dict[str, Any]:
    return {
        "checks": {
            name: artifact_reference(manifest, artifact_key)
            for name, artifact_key in CHECK_ARTIFACTS.items()
        },
        "evidence": {
            name: artifact_reference(manifest, artifact_key)
            for name, artifact_key in TOP_LEVEL_ARTIFACTS.items()
        },
        "firebase_reference": artifact_reference(manifest, "esc_firebase_identity"),
    }


def finalize_record(
    draft: dict[str, Any],
    expected_source_sha: str,
    final_tested_at_utc: str,
    evidence_manifest: dict[str, Any],
    evidence_root: Path,
) -> dict[str, Any]:
    expected_source_sha = source_sha(expected_source_sha)
    assert_draft_boundary(draft, expected_source_sha)
    finalized_at = tested_at_utc(final_tested_at_utc)

    try:
        manifest = verify_manifest(
            evidence_manifest,
            evidence_root,
            expected_source_sha,
        )
    except EvidenceBundleError as exc:
        raise FinalizerError(f"evidence bundle verification failed: {exc}") from exc

    references = derived_references(manifest)
    record = deepcopy(draft)
    record["decision"] = "PASS"
    record["tested_at_utc"] = finalized_at
    record["esc"] = dict(record["esc"])
    record["esc"]["firebase_reference"] = references["firebase_reference"]
    record["evidence"] = references["evidence"]
    record["evidence_bundle"] = {
        "sha256": manifest["bundle_sha256"],
        "source_sha": manifest["source_sha"],
        "collected_at_utc": manifest["collected_at_utc"],
    }

    checks = dict(record["checks"])
    for name in REQUIRED_CHECKS:
        checks[name] = {
            "status": "PASS",
            "evidence": references["checks"][name],
        }
    record["checks"] = checks

    try:
        validate_record(record, expected_source_sha, references["evidence"])
    except EvidenceError as exc:
        raise FinalizerError(f"final coexistence validation failed: {exc}") from exc
    return record


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--draft", type=Path, required=True)
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--tested-at-utc", required=True)
    parser.add_argument("--evidence-manifest", type=Path, required=True)
    parser.add_argument("--evidence-root", type=Path, required=True)
    parser.add_argument("--output", type=Path, required=True)
    return parser


def write_record(path: Path, record: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        json.dumps(record, indent=2, sort_keys=True) + "\n", encoding="utf-8"
    )


def main() -> int:
    args = build_parser().parse_args()
    try:
        draft = load_record(args.draft)
        manifest = load_manifest(args.evidence_manifest)
        record = finalize_record(
            draft,
            args.source_sha,
            args.tested_at_utc,
            manifest,
            args.evidence_root,
        )
        write_record(args.output, record)
    except (EvidenceError, EvidenceBundleError, FinalizerError) as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1

    print(
        "ESC Android coexistence final evidence created only after retained files "
        "were re-hashed and the existing final validator accepted all eight PASS "
        f"checks; bundle_sha256={record['evidence_bundle']['sha256']}, output={args.output}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
