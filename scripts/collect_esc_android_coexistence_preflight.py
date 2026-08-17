#!/usr/bin/env python3
"""Collect objective APK/device preflight for ESC coexistence UAT."""
from __future__ import annotations

import argparse
import hashlib
import json
from pathlib import Path
import re
import subprocess
import sys
from typing import Callable, Sequence

from validate_esc_android_coexistence_evidence import (
    ESC_APPLICATION_ID,
    SAFE_APPLICATION_ID,
    SHA40_RE,
)

SIGNER_RE = re.compile(
    r"Signer #1 certificate SHA-256 digest:\s*([0-9a-fA-F:]{64,95})"
)
SHA256_RE = re.compile(r"^[0-9a-f]{64}$")
Runner = Callable[[Sequence[str]], str]


class PreflightError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise PreflightError(message)


def run_text(command: Sequence[str]) -> str:
    try:
        result = subprocess.run(
            list(command), check=False, capture_output=True, text=True
        )
    except FileNotFoundError as exc:
        raise PreflightError(f"required tool not found: {command[0]}") from exc
    if result.returncode:
        detail = (result.stderr or result.stdout).strip()
        raise PreflightError(
            f"command failed ({result.returncode}): {' '.join(command)}"
            + (f": {detail}" if detail else "")
        )
    return result.stdout.strip()


def validate_source_sha(value: str) -> str:
    value = value.strip().lower()
    if SHA40_RE.fullmatch(value) is None:
        fail("source SHA must be a full 40-character Git SHA")
    return value


def file_sha256(path: Path) -> str:
    if not path.is_file() or path.stat().st_size <= 0:
        fail(f"APK is missing or empty: {path}")
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def parse_signer(output: str) -> str:
    match = SIGNER_RE.search(output)
    if match is None:
        fail("apksigner output is missing the signer SHA-256 digest")
    digest = match.group(1).replace(":", "").lower()
    if SHA256_RE.fullmatch(digest) is None:
        fail("apksigner returned a malformed signer SHA-256 digest")
    return digest


def inspect_apk(
    apk: Path,
    *,
    apkanalyzer: str = "apkanalyzer",
    apksigner: str = "apksigner",
    runner: Runner = run_text,
) -> dict[str, str]:
    parts = runner([apkanalyzer, "apk", "summary", str(apk)]).split(maxsplit=2)
    if len(parts) != 3 or not all(parts):
        fail(f"apkanalyzer returned malformed APK summary for {apk}")
    application_id, version_code, version_name = parts
    return {
        "application_id": application_id,
        "version_name": version_name,
        "version_code": version_code,
        "version": f"{version_name}+{version_code}",
        "apk_sha256": file_sha256(apk),
        "signer_sha256": parse_signer(
            runner([apksigner, "verify", "--print-certs", str(apk)])
        ),
    }


def package_installed(
    serial: str, package_id: str, *, adb: str, runner: Runner
) -> bool:
    output = runner([adb, "-s", serial, "shell", "pm", "path", package_id])
    return any(line.startswith("package:") for line in output.splitlines())


def inspect_device(
    serial: str, *, adb: str = "adb", runner: Runner = run_text
) -> dict[str, object]:
    serial = serial.strip()
    if not serial:
        fail("device serial is empty")
    props = {}
    for key, label in (
        ("ro.product.manufacturer", "manufacturer"),
        ("ro.product.model", "model"),
        ("ro.build.version.release", "android_version"),
        ("ro.build.version.sdk", "api_level"),
    ):
        value = runner([adb, "-s", serial, "shell", "getprop", key]).strip()
        if not value:
            fail(f"device {label} is empty")
        props[label] = value
    missing = [
        package
        for package in (SAFE_APPLICATION_ID, ESC_APPLICATION_ID)
        if not package_installed(serial, package, adb=adb, runner=runner)
    ]
    if missing:
        fail(
            "selected device does not contain both coexistence packages; missing: "
            + ", ".join(missing)
        )
    return {
        "reference": serial,
        **props,
        "safe_contract_installed": True,
        "esc_installed": True,
        "dual_install_observed": True,
    }


def collect_preflight(
    safe_apk: Path,
    esc_apk: Path,
    source_sha: str,
    *,
    device_serial: str | None = None,
    apkanalyzer: str = "apkanalyzer",
    apksigner: str = "apksigner",
    adb: str = "adb",
    runner: Runner = run_text,
) -> dict[str, object]:
    source_sha = validate_source_sha(source_sha)
    safe = inspect_apk(
        safe_apk, apkanalyzer=apkanalyzer, apksigner=apksigner, runner=runner
    )
    esc = inspect_apk(
        esc_apk, apkanalyzer=apkanalyzer, apksigner=apksigner, runner=runner
    )
    if safe["application_id"] != SAFE_APPLICATION_ID:
        fail(f"Safe Contract application id must be {SAFE_APPLICATION_ID}")
    if esc["application_id"] != ESC_APPLICATION_ID:
        fail(f"ESC application id must be {ESC_APPLICATION_ID}")
    if safe["apk_sha256"] == esc["apk_sha256"]:
        fail("Safe Contract and ESC APK SHA-256 digests must differ")
    if safe["signer_sha256"] == esc["signer_sha256"]:
        fail("Safe Contract and ESC signing certificate SHA-256 digests must differ")

    device = (
        inspect_device(device_serial, adb=adb, runner=runner)
        if device_serial is not None
        else None
    )
    return {
        "preflight_schema_version": 1,
        "status": "PENDING_REAL_DEVICE_UAT",
        "source_sha": source_sha,
        "safe_contract": safe,
        "esc": esc,
        "device": device,
        "objective_checks": {
            "exact_application_ids": True,
            "distinct_application_ids": True,
            "distinct_apk_sha256": True,
            "distinct_signing_certificates": True,
            "same_device_dual_install": (
                True if device is not None else "NOT_COLLECTED"
            ),
        },
        "runtime_uat": {
            "final_decision": "NOT_RECORDED",
            "required_external_gate": "validate_esc_android_coexistence_evidence.py",
            "note": (
                "Preflight cannot prove launch/session/push/deep-link/update/"
                "clear-data isolation and is never final UAT PASS."
            ),
        },
    }


def parser() -> argparse.ArgumentParser:
    result = argparse.ArgumentParser(description=__doc__)
    result.add_argument("--safe-apk", type=Path, required=True)
    result.add_argument("--esc-apk", type=Path, required=True)
    result.add_argument("--source-sha", required=True)
    result.add_argument("--output", type=Path, required=True)
    result.add_argument("--device-serial")
    result.add_argument("--apkanalyzer", default="apkanalyzer")
    result.add_argument("--apksigner", default="apksigner")
    result.add_argument("--adb", default="adb")
    return result


def main() -> int:
    args = parser().parse_args()
    try:
        record = collect_preflight(
            args.safe_apk,
            args.esc_apk,
            args.source_sha,
            device_serial=args.device_serial,
            apkanalyzer=args.apkanalyzer,
            apksigner=args.apksigner,
            adb=args.adb,
        )
        args.output.parent.mkdir(parents=True, exist_ok=True)
        args.output.write_text(
            json.dumps(record, indent=2, sort_keys=True) + "\n", encoding="utf-8"
        )
    except PreflightError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    state = "dual-install-observed" if record["device"] else "device-not-collected"
    print(
        "ESC Android coexistence preflight passed objective checks; "
        f"{state}; status=PENDING_REAL_DEVICE_UAT; output={args.output}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
