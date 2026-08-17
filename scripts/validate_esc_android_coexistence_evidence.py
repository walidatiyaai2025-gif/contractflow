#!/usr/bin/env python3
"""Validate machine-readable ESC Android coexistence UAT evidence.

This gate does not perform device testing. It validates that a separately recorded
human/device UAT result is complete, explicitly PASS, tied to the exact source SHA,
and preserves Safe Contract / Enterprise Safe Contracts identity separation.
"""

from __future__ import annotations

import argparse
from datetime import datetime
import hashlib
import json
from pathlib import Path
import re
import sys
from typing import Any

ESC_APPLICATION_ID = "com.safecontracts.enterprise"
SAFE_APPLICATION_ID = "com.safecontracts.safecontracts_mobile"
SHA40_RE = re.compile(r"^[0-9a-f]{40}$", re.IGNORECASE)
SHA256_RE = re.compile(r"^[0-9a-f]{64}$", re.IGNORECASE)
REQUIRED_CHECKS = (
    "dual_install",
    "independent_launch",
    "session_isolation",
    "safe_only_push",
    "esc_only_push",
    "deep_link_isolation",
    "independent_update",
    "clear_data_uninstall_isolation",
)
EVIDENCE_KEYS = (
    "device",
    "business_uat",
    "coexistence",
    "firebase",
)


class EvidenceError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise EvidenceError(message)


def text(value: Any, label: str, minimum: int = 3) -> str:
    if not isinstance(value, str):
        fail(f"{label} must be a string")
    normalized = value.strip()
    if len(normalized) < minimum:
        fail(f"{label} is required")
    return normalized


def object_value(value: Any, label: str) -> dict[str, Any]:
    if not isinstance(value, dict):
        fail(f"{label} must be an object")
    return value


def sha40(value: Any, label: str) -> str:
    normalized = text(value, label).lower()
    if SHA40_RE.fullmatch(normalized) is None:
        fail(f"{label} must be a full 40-character Git SHA")
    return normalized


def sha256(value: Any, label: str) -> str:
    normalized = text(value, label).replace(":", "").lower()
    if SHA256_RE.fullmatch(normalized) is None:
        fail(f"{label} must be a SHA-256 digest")
    return normalized


def utc_timestamp(value: Any) -> str:
    normalized = text(value, "tested_at_utc")
    if not normalized.endswith("Z"):
        fail("tested_at_utc must use UTC and end with Z")
    try:
        datetime.fromisoformat(normalized[:-1] + "+00:00")
    except ValueError as exc:
        raise EvidenceError("tested_at_utc must be a valid ISO-8601 timestamp") from exc
    return normalized


def canonical_record_sha256(record: dict[str, Any]) -> str:
    payload = json.dumps(
        record,
        ensure_ascii=False,
        sort_keys=True,
        separators=(",", ":"),
    ).encode("utf-8")
    return hashlib.sha256(payload).hexdigest()


def validate_record(
    record: dict[str, Any],
    expected_source_sha: str,
    expected_evidence: dict[str, str] | None = None,
) -> dict[str, str]:
    if record.get("schema_version") != 1:
        fail("schema_version must be 1")
    if text(record.get("decision"), "decision").upper() != "PASS":
        fail("coexistence UAT decision must be PASS")

    source = sha40(record.get("source_sha"), "source_sha")
    expected_source = sha40(expected_source_sha, "expected source SHA")
    if source != expected_source:
        fail(f"UAT source SHA mismatch: record={source}, expected={expected_source}")

    text(record.get("tester"), "tester")
    utc_timestamp(record.get("tested_at_utc"))

    device = object_value(record.get("device"), "device")
    text(device.get("reference"), "device.reference")
    text(device.get("android_version"), "device.android_version", minimum=1)

    safe = object_value(record.get("safe_contract"), "safe_contract")
    esc = object_value(record.get("esc"), "esc")
    if text(safe.get("application_id"), "safe_contract.application_id") != SAFE_APPLICATION_ID:
        fail(f"Safe Contract application_id must be {SAFE_APPLICATION_ID}")
    if text(esc.get("application_id"), "esc.application_id") != ESC_APPLICATION_ID:
        fail(f"ESC application_id must be {ESC_APPLICATION_ID}")

    safe_apk = sha256(safe.get("apk_sha256"), "safe_contract.apk_sha256")
    esc_apk = sha256(esc.get("apk_sha256"), "esc.apk_sha256")
    safe_signer = sha256(safe.get("signer_sha256"), "safe_contract.signer_sha256")
    esc_signer = sha256(esc.get("signer_sha256"), "esc.signer_sha256")
    text(safe.get("version"), "safe_contract.version", minimum=1)
    text(esc.get("version"), "esc.version", minimum=1)
    text(esc.get("firebase_reference"), "esc.firebase_reference")

    if safe_apk == esc_apk:
        fail("Safe Contract and ESC APK SHA-256 digests must differ")
    if safe_signer == esc_signer:
        fail("Safe Contract and ESC signing certificate SHA-256 digests must differ")

    checks = object_value(record.get("checks"), "checks")
    extra_checks = set(checks) - set(REQUIRED_CHECKS)
    missing_checks = set(REQUIRED_CHECKS) - set(checks)
    if missing_checks:
        fail("missing required coexistence checks: " + ", ".join(sorted(missing_checks)))
    if extra_checks:
        fail("unexpected coexistence checks: " + ", ".join(sorted(extra_checks)))
    for name in REQUIRED_CHECKS:
        check = object_value(checks.get(name), f"checks.{name}")
        if text(check.get("status"), f"checks.{name}.status").upper() != "PASS":
            fail(f"checks.{name}.status must be PASS")
        text(check.get("evidence"), f"checks.{name}.evidence")

    evidence = object_value(record.get("evidence"), "evidence")
    normalized_evidence = {
        key: text(evidence.get(key), f"evidence.{key}") for key in EVIDENCE_KEYS
    }
    if expected_evidence is not None:
        for key in EVIDENCE_KEYS:
            expected = text(expected_evidence.get(key), f"expected evidence.{key}")
            if normalized_evidence[key] != expected:
                fail(
                    f"evidence.{key} does not match the publish input "
                    f"({normalized_evidence[key]!r} != {expected!r})"
                )

    return normalized_evidence


def load_record(path: Path) -> dict[str, Any]:
    if not path.is_file() or path.stat().st_size == 0:
        fail(f"UAT evidence record is missing or empty: {path}")
    try:
        record = json.loads(path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise EvidenceError(f"UAT evidence record is invalid JSON: {exc}") from exc
    if not isinstance(record, dict):
        fail("UAT evidence record root must be an object")
    return record


def load_and_validate(
    path: Path,
    expected_source_sha: str,
    expected_evidence: dict[str, str] | None = None,
) -> dict[str, str]:
    return validate_record(load_record(path), expected_source_sha, expected_evidence)


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--record", type=Path, required=True)
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--device-evidence")
    parser.add_argument("--uat-evidence")
    parser.add_argument("--coexistence-evidence")
    parser.add_argument("--firebase-evidence")
    return parser


def main() -> int:
    args = build_parser().parse_args()
    supplied = {
        "device": args.device_evidence,
        "business_uat": args.uat_evidence,
        "coexistence": args.coexistence_evidence,
        "firebase": args.firebase_evidence,
    }
    any_supplied = any(value is not None for value in supplied.values())
    all_supplied = all(value is not None for value in supplied.values())
    if any_supplied and not all_supplied:
        print("FAIL: either provide all publish evidence references or none", file=sys.stderr)
        return 1
    try:
        record = load_record(args.record)
        evidence = validate_record(
            record,
            args.source_sha,
            supplied if all_supplied else None,
        )
    except EvidenceError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    print(
        "ESC Android coexistence evidence passed: exact-source PASS record; "
        f"record_sha256={canonical_record_sha256(record)}, "
        f"device={evidence['device']}, coexistence={evidence['coexistence']}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
