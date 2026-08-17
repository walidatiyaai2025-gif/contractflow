#!/usr/bin/env python3
"""Finalize ESC Android coexistence evidence from explicit real-device UAT results.

This tool does not perform runtime UAT. It accepts only an exact-source PENDING draft
whose objective device checks already passed, requires explicit references for every
remaining environment-specific check, and writes a final PASS record only after the
existing coexistence validator accepts the complete record.
"""

from __future__ import annotations

import argparse
from copy import deepcopy
from datetime import datetime
import json
from pathlib import Path
import sys
from typing import Any

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


def finalize_record(
    draft: dict[str, Any],
    expected_source_sha: str,
    final_tested_at_utc: str,
    manual_evidence: dict[str, str],
    *,
    esc_firebase_reference: str,
    device_evidence: str,
    business_uat_evidence: str,
    coexistence_evidence: str,
    firebase_evidence: str,
) -> dict[str, Any]:
    expected_source_sha = source_sha(expected_source_sha)
    assert_draft_boundary(draft, expected_source_sha)

    if set(manual_evidence) != set(MANUAL_CHECKS):
        fail("manual evidence must contain exactly the remaining runtime checks")

    normalized_manual = {
        name: reference(manual_evidence[name], f"{name} evidence")
        for name in MANUAL_CHECKS
    }
    normalized_evidence = {
        "device": reference(device_evidence, "device evidence"),
        "business_uat": reference(business_uat_evidence, "business UAT evidence"),
        "coexistence": reference(coexistence_evidence, "coexistence evidence"),
        "firebase": reference(firebase_evidence, "Firebase evidence"),
    }
    firebase_reference = reference(
        esc_firebase_reference, "ESC Firebase identity reference"
    )
    finalized_at = tested_at_utc(final_tested_at_utc)

    record = deepcopy(draft)
    record["decision"] = "PASS"
    record["tested_at_utc"] = finalized_at
    record["esc"] = dict(record["esc"])
    record["esc"]["firebase_reference"] = firebase_reference
    record["evidence"] = normalized_evidence

    checks = dict(record["checks"])
    for name in MANUAL_CHECKS:
        checks[name] = {
            "status": "PASS",
            "evidence": normalized_manual[name],
        }
    record["checks"] = checks

    try:
        validate_record(record, expected_source_sha, normalized_evidence)
    except EvidenceError as exc:
        raise FinalizerError(f"final coexistence validation failed: {exc}") from exc
    return record


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--draft", type=Path, required=True)
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--tested-at-utc", required=True)
    parser.add_argument("--session-isolation-evidence", required=True)
    parser.add_argument("--safe-only-push-evidence", required=True)
    parser.add_argument("--esc-only-push-evidence", required=True)
    parser.add_argument("--independent-update-evidence", required=True)
    parser.add_argument("--clear-data-uninstall-evidence", required=True)
    parser.add_argument("--esc-firebase-reference", required=True)
    parser.add_argument("--device-evidence", required=True)
    parser.add_argument("--business-uat-evidence", required=True)
    parser.add_argument("--coexistence-evidence", required=True)
    parser.add_argument("--firebase-evidence", required=True)
    parser.add_argument("--output", type=Path, required=True)
    return parser


def write_record(path: Path, record: dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        json.dumps(record, indent=2, sort_keys=True) + "\n", encoding="utf-8"
    )


def main() -> int:
    args = build_parser().parse_args()
    manual_evidence = {
        "session_isolation": args.session_isolation_evidence,
        "safe_only_push": args.safe_only_push_evidence,
        "esc_only_push": args.esc_only_push_evidence,
        "independent_update": args.independent_update_evidence,
        "clear_data_uninstall_isolation": args.clear_data_uninstall_evidence,
    }
    try:
        draft = load_record(args.draft)
        record = finalize_record(
            draft,
            args.source_sha,
            args.tested_at_utc,
            manual_evidence,
            esc_firebase_reference=args.esc_firebase_reference,
            device_evidence=args.device_evidence,
            business_uat_evidence=args.business_uat_evidence,
            coexistence_evidence=args.coexistence_evidence,
            firebase_evidence=args.firebase_evidence,
        )
        write_record(args.output, record)
    except (EvidenceError, FinalizerError) as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1

    print(
        "ESC Android coexistence final evidence created only after existing final "
        f"validator accepted all eight PASS checks; output={args.output}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
