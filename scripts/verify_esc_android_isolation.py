#!/usr/bin/env python3
"""Fail closed when Enterprise Safe Contracts Android isolation regresses.

The default mode validates committed source/build contracts. Optional APK inputs add
binary package/signing verification before real-device coexistence UAT.
"""

from __future__ import annotations

import argparse
from pathlib import Path
import re
import shutil
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]
ESC_APPLICATION_ID = "com.safecontracts.enterprise"
SAFE_APPLICATION_ID = "com.safecontracts.safecontracts_mobile"
ESC_NOTIFICATION_CHANNEL = "enterprise_safe_contracts_alerts"
ESC_METHOD_CHANNEL = "enterprise_safecontracts/notifications"
ESC_DEEP_LINK_SCHEME = "esc-safecontracts"


class IsolationError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise IsolationError(message)


def read(relative: str) -> str:
    path = ROOT / relative
    if not path.is_file():
        fail(f"missing required ESC isolation file: {relative}")
    return path.read_text(encoding="utf-8")


def require(text: str, markers: tuple[str, ...], label: str) -> None:
    for marker in markers:
        if marker not in text:
            fail(f"{label} is missing isolation marker: {marker}")


def forbid(text: str, markers: tuple[str, ...], label: str) -> None:
    for marker in markers:
        if marker in text:
            fail(f"{label} contains forbidden Safe Contract marker: {marker}")


def validate_sources() -> int:
    gradle = read("mobile/android-release/app-build.gradle.kts")
    activity = read("mobile/android-release/MainActivity.kt")
    bootstrap = read("scripts/bootstrap_android.sh")
    app_environment = read("mobile/lib/core/config/app_environment.dart")
    config_example = read("mobile/config/local.example.json")
    mobile_readme = read("mobile/README.md")
    android_readme = read("mobile/android-release/README.md")
    publish_workflow = read(".github/workflows/publish-mobile-latest.yml")
    verified_artifacts = read("scripts/enterprise_verified_artifacts.py")

    require(
        gradle,
        (
            f'namespace = "{ESC_APPLICATION_ID}"',
            f'applicationId = "{ESC_APPLICATION_ID}"',
            'applicationIdSuffix = ".dev"',
            'applicationIdSuffix = ".staging"',
            'resValue("string", "app_name", "Enterprise Safe Contracts")',
            "ESC_ANDROID_KEYSTORE_PATH",
            "ESC_ANDROID_KEYSTORE_PASSWORD",
            "ESC_ANDROID_KEY_ALIAS",
            "ESC_ANDROID_KEY_PASSWORD",
        ),
        "ESC Gradle overlay",
    )
    forbid(
        gradle,
        (
            'namespace = "com.safecontracts.safecontracts_mobile"',
            'applicationId = "com.safecontracts.safecontracts_mobile"',
            "SC_ANDROID_KEYSTORE_PATH",
            "SC_ANDROID_KEYSTORE_PASSWORD",
            "SC_ANDROID_KEY_ALIAS",
            "SC_ANDROID_KEY_PASSWORD",
        ),
        "ESC Gradle overlay",
    )

    require(
        activity,
        (
            "package com.safecontracts.enterprise",
            ESC_NOTIFICATION_CHANNEL,
            ESC_METHOD_CHANNEL,
            "Enterprise Safe Contracts Alerts",
        ),
        "ESC MainActivity",
    )

    require(
        bootstrap,
        (
            "ESC_FIREBASE_ANDROID_CONFIG_DEV",
            "ESC_FIREBASE_ANDROID_CONFIG_STAGING",
            "ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION",
            "com.safecontracts.enterprise.dev",
            "com.safecontracts.enterprise.staging",
            ESC_APPLICATION_ID,
            SAFE_APPLICATION_ID,
            ESC_DEEP_LINK_SCHEME,
            ESC_NOTIFICATION_CHANNEL,
        ),
        "ESC Android bootstrap",
    )

    require(
        app_environment,
        (
            "String.fromEnvironment('ESC_ENV'",
            "'ESC_API_BASE_URL'",
            "ESC_API_BASE_URL must be an absolute URL.",
            "Production Enterprise Safe Contracts API must use HTTPS.",
        ),
        "ESC Flutter environment",
    )
    forbid(
        app_environment,
        (
            "String.fromEnvironment('SC_ENV'",
            "'SC_API_BASE_URL'",
        ),
        "ESC Flutter environment",
    )

    require(
        config_example,
        (
            '"ESC_ENV"',
            '"ESC_API_BASE_URL"',
        ),
        "ESC local config example",
    )
    forbid(
        config_example,
        (
            '"SC_ENV"',
            '"SC_API_BASE_URL"',
        ),
        "ESC local config example",
    )

    for label, text in (
        ("ESC mobile README", mobile_readme),
        ("ESC Android README", android_readme),
    ):
        require(
            text,
            (
                "ESC_ENV",
                "ESC_API_BASE_URL",
                "verify_esc_android_isolation.py",
            ),
            label,
        )

    require(
        publish_workflow,
        (
            "Publish Enterprise Safe Contracts Mobile Latest",
            "refs/heads/enterprise-safecontracts",
            "environment: esc-production",
            "ESC_ANDROID_KEYSTORE_BASE64",
            "ESC_ANDROID_CERT_SHA256",
            "ESC_FIREBASE_ANDROID_CONFIG_DEV_BASE64",
            "ESC_FIREBASE_ANDROID_CONFIG_STAGING_BASE64",
            "ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION_BASE64",
            "--flavor production --release",
            "--dart-define=ESC_ENV=production",
            "--dart-define=ESC_API_BASE_URL=",
            f"ESC_APPLICATION_ID: {ESC_APPLICATION_ID}",
            "EnterpriseSafeContracts-latest.apk",
            "esc-mobile-latest",
            "coexistence_evidence",
            "verify_esc_android_isolation.py",
        ),
        "ESC Android publish workflow",
    )
    forbid(
        publish_workflow,
        (
            "secrets.SC_ANDROID_",
            "--dart-define=SC_ENV=",
            "--dart-define=SC_API_BASE_URL=",
            "SafeContracts-Mobile.apk",
            "gh release create mobile-latest",
            "gh release delete mobile-latest",
            "environment: production\n",
        ),
        "ESC Android publish workflow",
    )

    require(
        verified_artifacts,
        (
            f'APPLICATION_ID = "{ESC_APPLICATION_ID}"',
            'APK_NAME = "EnterpriseSafeContracts-latest.apk"',
            'BRANCH = "enterprise-safecontracts"',
            "firebase_identity_verified",
            "coexistence_evidence",
        ),
        "ESC verified artifact policy",
    )

    if (ROOT / "mobile/android-release/google-services.json").exists():
        fail("ESC android-release must never contain a committed google-services.json")

    return 9


def resolve_tool(explicit: str | None, name: str) -> str:
    if explicit:
        path = Path(explicit).expanduser()
        if not path.is_file():
            fail(f"{name} does not exist: {path}")
        return str(path)
    detected = shutil.which(name)
    if not detected:
        fail(f"{name} is required for APK verification; pass --{name} explicitly")
    return detected


def run(command: list[str]) -> str:
    completed = subprocess.run(
        command,
        check=False,
        stdout=subprocess.PIPE,
        stderr=subprocess.STDOUT,
        text=True,
    )
    if completed.returncode != 0:
        fail(f"command failed ({' '.join(command)}): {completed.stdout.strip()}")
    return completed.stdout


def apk_package(aapt: str, apk: Path) -> str:
    if not apk.is_file() or apk.stat().st_size == 0:
        fail(f"APK is missing or empty: {apk}")
    output = run([aapt, "dump", "badging", str(apk)])
    match = re.search(r"(?m)^package: name='([^']+)'", output)
    if match is None:
        fail(f"could not read package id from APK: {apk}")
    return match.group(1)


def signer_sha256(apksigner: str, apk: Path) -> str:
    output = run([apksigner, "verify", "--verbose", "--print-certs", str(apk)])
    match = re.search(
        r"(?mi)^Signer #1 certificate SHA-256 digest:\s*([0-9a-f:]+)\s*$",
        output,
    )
    if match is None:
        fail(f"could not read signing certificate SHA-256 from APK: {apk}")
    return match.group(1).replace(":", "").lower()


def validate_apks(
    esc_apk: Path | None,
    safe_apk: Path | None,
    aapt_arg: str | None,
    apksigner_arg: str | None,
) -> int:
    if esc_apk is None and safe_apk is None:
        return 0
    if esc_apk is None:
        fail("--safe-apk requires --esc-apk")

    aapt = resolve_tool(aapt_arg, "aapt")
    esc_package = apk_package(aapt, esc_apk)
    if esc_package != ESC_APPLICATION_ID:
        fail(
            f"ESC APK package mismatch: expected {ESC_APPLICATION_ID}, found {esc_package}"
        )

    checks = 1
    if safe_apk is None:
        print(f"ESC APK identity verified: {esc_package}")
        return checks

    safe_package = apk_package(aapt, safe_apk)
    if safe_package != SAFE_APPLICATION_ID:
        fail(
            f"Safe Contract APK package mismatch: expected {SAFE_APPLICATION_ID}, "
            f"found {safe_package}"
        )
    if safe_package == esc_package:
        fail("Safe Contract and ESC APKs advertise the same package id")

    apksigner = resolve_tool(apksigner_arg, "apksigner")
    esc_signer = signer_sha256(apksigner, esc_apk)
    safe_signer = signer_sha256(apksigner, safe_apk)
    if esc_signer == safe_signer:
        fail("Safe Contract and ESC APKs reuse the same signing certificate")

    print(
        "Binary coexistence preflight passed: "
        f"Safe Contract={safe_package}, ESC={esc_package}, signing lineages are distinct"
    )
    return checks + 3


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--esc-apk", type=Path)
    parser.add_argument("--safe-apk", type=Path)
    parser.add_argument("--aapt")
    parser.add_argument("--apksigner")
    return parser


def main() -> int:
    args = build_parser().parse_args()
    try:
        checks = validate_sources()
        checks += validate_apks(
            args.esc_apk,
            args.safe_apk,
            args.aapt,
            args.apksigner,
        )
    except IsolationError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    print(f"ESC Android isolation gate passed ({checks} check groups)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
