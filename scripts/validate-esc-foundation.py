#!/usr/bin/env python3
"""Validate Enterprise Safe Contracts branch separation/foundation invariants."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PUBLIC_URL = "https://esc.50sols.com/"
APPLICATION_ID = "com.safecontracts.enterprise"
REQUIRED_FILES = (
    "ENTERPRISE_SAFE_CONTRACTS_MASTER_PLAN.txt",
    "docs/enterprise/ARCHITECTURE.md",
    "docs/enterprise/MULTITENANCY_SECURITY.md",
    "docs/enterprise/PRODUCT_DESIGN_LANDING.md",
    "docs/enterprise/MOBILE_IDENTITY_APK.md",
    "docs/enterprise/DESIGN_SYSTEM.md",
    "docs/enterprise/ROADMAP.md",
    "docs/enterprise/FEATURE_REGISTRY.json",
    "assets/enterprise/theme/tokens.json",
    "mobile/android-release/app-build.gradle.kts",
    "mobile/android-release/MainActivity.kt",
    "mobile/android-release/enterprise-launcher.xml",
    "mobile/android-release/README.md",
    "scripts/bootstrap_android.sh",
    "scripts/enterprise_verified_artifacts.py",
    "Last verified Enterprise Plugin/README.md",
    "Last verified Enterprise apk/README.md",
    ".github/ISSUE_TEMPLATE/esc-task.md",
)
ALLOWED_LIFECYCLES = {"Development", "Internal Preview", "Beta", "Public", "Deprecated"}
REQUIRED_FEATURE_KEYS = {
    "code", "title", "category", "lifecycle", "tenant_aware", "admin", "mobile", "public_marketing", "plan"
}


def fail(message: str) -> None:
    raise SystemExit(f"ESC foundation validation failed: {message}")


def read_text(relative: str) -> str:
    path = ROOT / relative
    if not path.is_file():
        fail(f"missing required file: {relative}")
    return path.read_text(encoding="utf-8")


def read_json(relative: str) -> dict:
    try:
        value = json.loads(read_text(relative))
    except json.JSONDecodeError as exc:
        fail(f"invalid JSON in {relative}: {exc}")
    if not isinstance(value, dict):
        fail(f"{relative} must contain a JSON object")
    return value


def main() -> None:
    for relative in REQUIRED_FILES:
        if not (ROOT / relative).is_file():
            fail(f"missing required file: {relative}")

    # A Safe Contract Firebase file is intentionally removed from the ESC branch.
    if (ROOT / "mobile/android-release/google-services.json").exists():
        fail("committed Safe Contract google-services.json must not exist in ESC android-release")

    master = read_text("ENTERPRISE_SAFE_CONTRACTS_MASTER_PLAN.txt")
    agents = read_text("AGENTS.md")
    mobile_doc = read_text("docs/enterprise/MOBILE_IDENTITY_APK.md")
    landing = read_text("docs/enterprise/PRODUCT_DESIGN_LANDING.md")
    design = read_text("docs/enterprise/DESIGN_SYSTEM.md")
    gradle = read_text("mobile/android-release/app-build.gradle.kts")
    activity = read_text("mobile/android-release/MainActivity.kt")
    bootstrap = read_text("scripts/bootstrap_android.sh")
    release_script = read_text("scripts/enterprise_verified_artifacts.py")

    for marker in (
        PUBLIC_URL,
        "CRITICAL PRODUCT SEPARATION RULE",
        "FULL IMPACT RULE",
        "ANDROID / APK COEXISTENCE",
        "LANDING PAGE",
        "enterprise-safecontracts",
    ):
        if marker not in master:
            fail(f"master plan missing marker: {marker}")

    for marker in (
        "Enterprise Safe Contracts branch instructions",
        PUBLIC_URL,
        "Safe Contract and ESC must be simultaneously installable",
        "Never merge, port, copy, expose or backport ESC functionality",
    ):
        if marker not in agents:
            fail(f"AGENTS.md missing ESC guardrail: {marker}")

    if APPLICATION_ID not in mobile_doc:
        fail("mobile identity document missing ESC package baseline")
    if PUBLIC_URL not in landing or PUBLIC_URL not in design:
        fail("landing/design docs must reference the official public URL")

    for marker in (
        f'namespace = "{APPLICATION_ID}"',
        f'applicationId = "{APPLICATION_ID}"',
        'applicationIdSuffix = ".dev"',
        'applicationIdSuffix = ".staging"',
        "ESC_ANDROID_KEYSTORE_PATH",
    ):
        if marker not in gradle:
            fail(f"ESC Android Gradle contract missing marker: {marker}")

    for marker in (
        "package com.safecontracts.enterprise",
        "enterprise_safecontracts/notifications",
        "enterprise_safe_contracts_alerts",
    ):
        if marker not in activity:
            fail(f"ESC MainActivity missing identity marker: {marker}")

    for marker in (
        "ESC_FIREBASE_ANDROID_CONFIG_DEV",
        "ESC_FIREBASE_ANDROID_CONFIG_STAGING",
        "ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION",
        "com.safecontracts.enterprise.dev",
        "com.safecontracts.enterprise.staging",
        "com.safecontracts.enterprise",
        "esc-safecontracts",
    ):
        if marker not in bootstrap:
            fail(f"ESC Android bootstrap missing isolation marker: {marker}")

    tokens = read_json("assets/enterprise/theme/tokens.json")
    if tokens.get("product") != "Enterprise Safe Contracts":
        fail("design tokens product mismatch")
    for key in ("brand", "semantic", "dark", "typography", "spacing_px", "radius_px", "breakpoints_px", "accessibility"):
        if key not in tokens:
            fail(f"design tokens missing {key}")
    if tokens.get("accessibility", {}).get("focus_visible_required") is not True:
        fail("design tokens must require visible focus")

    registry = read_json("docs/enterprise/FEATURE_REGISTRY.json")
    if registry.get("product_code") != "esc":
        fail("feature registry product_code must be 'esc'")
    if registry.get("public_url") != PUBLIC_URL:
        fail("feature registry public_url does not match official ESC URL")
    features = registry.get("features")
    if not isinstance(features, list) or not features:
        fail("feature registry must contain features")
    seen_codes: set[str] = set()
    for feature in features:
        if not isinstance(feature, dict):
            fail("feature registry entries must be objects")
        missing = REQUIRED_FEATURE_KEYS - set(feature)
        if missing:
            fail(f"feature missing keys {sorted(missing)}: {feature.get('code', '<unknown>')}")
        code = feature["code"]
        if not isinstance(code, str) or not code.strip():
            fail("feature code must be a non-empty string")
        if code in seen_codes:
            fail(f"duplicate feature code: {code}")
        seen_codes.add(code)
        if feature["lifecycle"] not in ALLOWED_LIFECYCLES:
            fail(f"invalid lifecycle for {code}: {feature['lifecycle']}")
        if feature["public_marketing"] and feature["lifecycle"] not in {"Public", "Beta"}:
            fail(f"{code} cannot be marketed publicly while lifecycle={feature['lifecycle']}")

    for marker in (
        "Last verified Enterprise Plugin",
        "Last verified Enterprise apk",
        "EnterpriseSafeContracts-latest.zip",
        "EnterpriseSafeContracts-latest.apk",
        APPLICATION_ID,
        "coexistence_evidence",
        "firebase_identity_verified",
    ):
        if marker not in release_script:
            fail(f"Enterprise artifact policy missing marker: {marker}")

    issue_template = read_text(".github/ISSUE_TEMPLATE/esc-task.md")
    for marker in ("Full Impact Review", "Safe Contract separation verified", "ESC separation declaration"):
        if marker not in issue_template:
            fail(f"ESC issue template missing marker: {marker}")

    print("ESC foundation validation passed")


if __name__ == "__main__":
    main()
