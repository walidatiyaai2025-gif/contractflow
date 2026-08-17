#!/usr/bin/env python3
"""Collect a non-destructive real-device draft for ESC coexistence UAT.

The harness proves same-device dual installation, independent launcher/process
startup, and ESC-only custom deep-link resolution for Safe Contract and Enterprise
Safe Contracts. It deliberately leaves session, FCM, update, and data-lifecycle
scenarios PENDING. The output is therefore never a final UAT PASS record by itself.
"""

from __future__ import annotations

import argparse
from datetime import datetime, timezone
import json
from pathlib import Path
import re
import sys
from typing import Sequence

from collect_esc_android_coexistence_preflight import (
    PreflightError,
    Runner,
    collect_preflight,
    run_text,
)
from validate_esc_android_coexistence_evidence import (
    ESC_APPLICATION_ID,
    REQUIRED_CHECKS,
    SAFE_APPLICATION_ID,
)

ESC_DEEP_LINK_URI = "esc-safecontracts://contracts/1"
ANDROID_VIEW_ACTION = "android.intent.action.VIEW"
ANDROID_BROWSABLE_CATEGORY = "android.intent.category.BROWSABLE"
_COMPONENT_RE = re.compile(
    r"^(?P<package>[A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+)/(?P<activity>[A-Za-z0-9_.$]+)$"
)


class SessionError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise SessionError(message)


def launch_package(
    serial: str,
    package_id: str,
    *,
    adb: str = "adb",
    runner: Runner = run_text,
) -> dict[str, str]:
    launch_output = runner(
        [
            adb,
            "-s",
            serial,
            "shell",
            "monkey",
            "-p",
            package_id,
            "-c",
            "android.intent.category.LAUNCHER",
            "1",
        ]
    ).strip()
    if "Events injected: 1" not in launch_output:
        fail(f"launcher did not confirm one injected event for {package_id}")

    pid_output = runner(
        [adb, "-s", serial, "shell", "pidof", package_id]
    ).strip()
    pids = [token for token in pid_output.split() if token.isdigit()]
    if not pids:
        fail(f"package process was not observable after launch: {package_id}")
    return {
        "package_id": package_id,
        "pid": pids[0],
        "launcher_result": "Events injected: 1",
    }


def parse_components(output: str, label: str) -> list[tuple[str, str]]:
    components: list[tuple[str, str]] = []
    for raw_line in output.splitlines():
        line = raw_line.strip()
        if not line:
            continue
        match = _COMPONENT_RE.fullmatch(line)
        if match is None:
            fail(f"{label} returned an unparseable activity component: {line}")
        components.append((match.group("package"), line))
    if not components:
        fail(f"{label} returned no activity resolver")
    return components


def verify_deep_link_isolation(
    serial: str,
    *,
    adb: str = "adb",
    runner: Runner = run_text,
) -> dict[str, object]:
    query_output = runner(
        [
            adb,
            "-s",
            serial,
            "shell",
            "cmd",
            "package",
            "query-activities",
            "--brief",
            "-a",
            ANDROID_VIEW_ACTION,
            "-c",
            ANDROID_BROWSABLE_CATEGORY,
            "-d",
            ESC_DEEP_LINK_URI,
        ]
    ).strip()
    components = parse_components(query_output, "ESC deep-link resolver query")
    resolver_packages = sorted({package for package, _component in components})
    if resolver_packages != [ESC_APPLICATION_ID]:
        fail(
            "ESC-only deep-link scheme has a non-ESC resolver: "
            + ", ".join(resolver_packages)
        )

    launch_output = runner(
        [
            adb,
            "-s",
            serial,
            "shell",
            "am",
            "start",
            "-W",
            "-a",
            ANDROID_VIEW_ACTION,
            "-c",
            ANDROID_BROWSABLE_CATEGORY,
            "-d",
            ESC_DEEP_LINK_URI,
        ]
    ).strip()
    if re.search(r"(?mi)^Status:\s*ok\s*$", launch_output) is None:
        fail("ESC deep-link VIEW launch did not report Status: ok")

    activity_match = re.search(
        r"(?mi)^Activity:\s*([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+)/([A-Za-z0-9_.$]+)\s*$",
        launch_output,
    )
    if activity_match is None:
        activity_match = re.search(
            r"\bcmp=([A-Za-z0-9_]+(?:\.[A-Za-z0-9_]+)+)/([A-Za-z0-9_.$]+)",
            launch_output,
        )
    if activity_match is None:
        fail("ESC deep-link VIEW launch did not expose the launched activity component")

    launched_package = activity_match.group(1)
    launched_activity = f"{launched_package}/{activity_match.group(2)}"
    if launched_package != ESC_APPLICATION_ID:
        fail(
            "ESC-only deep-link VIEW launch resolved outside ESC: "
            f"{launched_activity}"
        )

    return {
        "uri": ESC_DEEP_LINK_URI,
        "resolver_packages": resolver_packages,
        "resolver_components": [component for _package, component in components],
        "launched_package": launched_package,
        "launched_activity": launched_activity,
        "launch_status": "ok",
    }


def pending_check(reason: str) -> dict[str, str]:
    return {"status": "PENDING", "evidence": reason}


def collect_session(
    safe_apk: Path,
    esc_apk: Path,
    source_sha: str,
    device_serial: str,
    tester: str,
    *,
    tested_at_utc: str | None = None,
    apkanalyzer: str = "apkanalyzer",
    apksigner: str = "apksigner",
    adb: str = "adb",
    runner: Runner = run_text,
) -> dict[str, object]:
    tester = tester.strip()
    if len(tester) < 3:
        fail("tester is required")
    serial = device_serial.strip()
    if not serial:
        fail("device serial is required")

    try:
        preflight = collect_preflight(
            safe_apk,
            esc_apk,
            source_sha,
            device_serial=serial,
            apkanalyzer=apkanalyzer,
            apksigner=apksigner,
            adb=adb,
            runner=runner,
        )
    except PreflightError as exc:
        raise SessionError(str(exc)) from exc

    device = preflight.get("device")
    if not isinstance(device, dict) or device.get("dual_install_observed") is not True:
        fail("preflight did not prove same-device dual installation")

    safe_launch = launch_package(
        serial, SAFE_APPLICATION_ID, adb=adb, runner=runner
    )
    esc_launch = launch_package(
        serial, ESC_APPLICATION_ID, adb=adb, runner=runner
    )
    deep_link = verify_deep_link_isolation(serial, adb=adb, runner=runner)

    timestamp = tested_at_utc or datetime.now(timezone.utc).isoformat().replace(
        "+00:00", "Z"
    )
    pending_runtime = (
        "Not executed by the non-destructive session harness; explicit real-device "
        "UAT evidence is still required."
    )
    checks: dict[str, dict[str, str]] = {
        name: pending_check(pending_runtime) for name in REQUIRED_CHECKS
    }
    checks["dual_install"] = {
        "status": "PASS",
        "evidence": (
            f"ADB package paths confirmed both application IDs on device {serial}."
        ),
    }
    checks["independent_launch"] = {
        "status": "PASS",
        "evidence": (
            f"Safe Contract launched as PID {safe_launch['pid']}; ESC launched as "
            f"PID {esc_launch['pid']} on device {serial}."
        ),
    }
    checks["deep_link_isolation"] = {
        "status": "PASS",
        "evidence": (
            f"{ESC_DEEP_LINK_URI} resolved only to {ESC_APPLICATION_ID} and "
            f"am start -W launched {deep_link['launched_activity']}."
        ),
    }

    safe = dict(preflight["safe_contract"])
    esc = dict(preflight["esc"])
    esc["firebase_reference"] = "PENDING_REAL_DEVICE_FIREBASE_UAT"

    return {
        "schema_version": 1,
        "decision": "PENDING",
        "source_sha": preflight["source_sha"],
        "tester": tester,
        "tested_at_utc": timestamp,
        "device": device,
        "safe_contract": safe,
        "esc": esc,
        "checks": checks,
        "evidence": {
            "device": (
                f"ADB device {serial}; dual install, launcher processes, and ESC-only "
                "deep-link resolution observed"
            ),
            "business_uat": "PENDING business-owner runtime UAT sign-off",
            "coexistence": "PENDING remaining runtime coexistence scenarios",
            "firebase": "PENDING Safe-only and ESC-only FCM delivery evidence",
        },
        "objective_session": {
            "safe_contract_launch": safe_launch,
            "esc_launch": esc_launch,
            "deep_link_isolation": deep_link,
            "note": (
                "This draft only proves objective dual-install, independent-launch, "
                "and ESC deep-link-isolation checks. It must not be published as "
                "final UAT PASS."
            ),
        },
    }


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--safe-apk", type=Path, required=True)
    parser.add_argument("--esc-apk", type=Path, required=True)
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--device-serial", required=True)
    parser.add_argument("--tester", required=True)
    parser.add_argument("--output", type=Path, required=True)
    parser.add_argument("--apkanalyzer", default="apkanalyzer")
    parser.add_argument("--apksigner", default="apksigner")
    parser.add_argument("--adb", default="adb")
    return parser


def write_record(path: Path, record: dict[str, object]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(
        json.dumps(record, indent=2, sort_keys=True) + "\n", encoding="utf-8"
    )


def main() -> int:
    args = build_parser().parse_args()
    try:
        record = collect_session(
            args.safe_apk,
            args.esc_apk,
            args.source_sha,
            args.device_serial,
            args.tester,
            apkanalyzer=args.apkanalyzer,
            apksigner=args.apksigner,
            adb=args.adb,
        )
        write_record(args.output, record)
    except SessionError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    print(
        "ESC Android UAT draft captured: dual_install=PASS, "
        "independent_launch=PASS, deep_link_isolation=PASS, remaining=PENDING; "
        f"output={args.output}"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
