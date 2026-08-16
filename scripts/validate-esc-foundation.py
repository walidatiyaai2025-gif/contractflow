#!/usr/bin/env python3
"""Validate Enterprise Safe Contracts branch separation/foundation invariants."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PUBLIC_URL = "https://esc.50sols.com/"
APPLICATION_ID = "com.safecontracts.enterprise"
SAFE_APPLICATION_ID = "com.safecontracts.safecontracts_mobile"
REQUIRED_FILES = (
    "ENTERPRISE_SAFE_CONTRACTS_MASTER_PLAN.txt",
    "docs/enterprise/ARCHITECTURE.md",
    "docs/enterprise/MULTITENANCY_SECURITY.md",
    "docs/enterprise/PRODUCT_DESIGN_LANDING.md",
    "docs/enterprise/MOBILE_IDENTITY_APK.md",
    "docs/enterprise/ANDROID_COEXISTENCE_UAT.md",
    "docs/enterprise/DESIGN_SYSTEM.md",
    "docs/enterprise/TENANT_DATA_OWNERSHIP.md",
    "docs/enterprise/ROADMAP.md",
    "docs/enterprise/FEATURE_REGISTRY.json",
    "assets/enterprise/theme/tokens.json",
    "mobile/android-release/app-build.gradle.kts",
    "mobile/android-release/MainActivity.kt",
    "mobile/android-release/enterprise-launcher.xml",
    "mobile/android-release/README.md",
    "mobile/lib/core/config/app_environment.dart",
    "mobile/config/local.example.json",
    "scripts/bootstrap_android.sh",
    "scripts/verify_esc_android_isolation.py",
    "scripts/enterprise_tenant_backfill.php",
    "scripts/enterprise_verified_artifacts.py",
    "Last verified Enterprise Plugin/README.md",
    "Last verified Enterprise apk/README.md",
    ".github/ISSUE_TEMPLATE/esc-task.md",
    ".github/workflows/publish-mobile-latest.yml",
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


def require_markers(text: str, markers: tuple[str, ...], label: str) -> None:
    for marker in markers:
        if marker not in text:
            fail(f"{label} missing marker: {marker}")


def forbid_markers(text: str, markers: tuple[str, ...], label: str) -> None:
    for marker in markers:
        if marker in text:
            fail(f"{label} contains forbidden marker: {marker}")


def main() -> None:
    for relative in REQUIRED_FILES:
        if not (ROOT / relative).is_file():
            fail(f"missing required file: {relative}")

    if (ROOT / "mobile/android-release/google-services.json").exists():
        fail("committed google-services.json must not exist in ESC android-release")

    master = read_text("ENTERPRISE_SAFE_CONTRACTS_MASTER_PLAN.txt")
    agents = read_text("AGENTS.md")
    mobile_doc = read_text("docs/enterprise/MOBILE_IDENTITY_APK.md")
    coexistence_uat = read_text("docs/enterprise/ANDROID_COEXISTENCE_UAT.md")
    landing = read_text("docs/enterprise/PRODUCT_DESIGN_LANDING.md")
    design = read_text("docs/enterprise/DESIGN_SYSTEM.md")
    ownership = read_text("docs/enterprise/TENANT_DATA_OWNERSHIP.md")
    gradle = read_text("mobile/android-release/app-build.gradle.kts")
    activity = read_text("mobile/android-release/MainActivity.kt")
    app_environment = read_text("mobile/lib/core/config/app_environment.dart")
    config_example = read_text("mobile/config/local.example.json")
    bootstrap = read_text("scripts/bootstrap_android.sh")
    android_isolation = read_text("scripts/verify_esc_android_isolation.py")
    publish_workflow = read_text(".github/workflows/publish-mobile-latest.yml")
    backfill = read_text("scripts/enterprise_tenant_backfill.php")
    release_script = read_text("scripts/enterprise_verified_artifacts.py")

    require_markers(
        master,
        (
            PUBLIC_URL,
            "CRITICAL PRODUCT SEPARATION RULE",
            "FULL IMPACT RULE",
            "ANDROID / APK COEXISTENCE",
            "LANDING PAGE",
            "enterprise-safecontracts",
        ),
        "master plan",
    )

    require_markers(
        agents,
        (
            "Enterprise Safe Contracts branch instructions",
            PUBLIC_URL,
            "Safe Contract and ESC must be simultaneously installable",
            "Never merge, port, copy, expose or backport ESC functionality",
        ),
        "AGENTS.md ESC guardrails",
    )

    require_markers(
        mobile_doc,
        (
            APPLICATION_ID,
            "independent",
            "Coexistence validation",
            "EnterpriseSafeContracts-latest.apk",
        ),
        "mobile identity document",
    )
    if PUBLIC_URL not in landing or PUBLIC_URL not in design:
        fail("landing/design docs must reference the official public URL")

    require_markers(
        coexistence_uat,
        (
            APPLICATION_ID,
            SAFE_APPLICATION_ID,
            "verify_esc_android_isolation.py",
            "enterprise_safe_contracts_alerts",
            "esc-safecontracts://",
            "Session and local-state isolation",
            "Firebase / notification isolation",
            "Independent update lineage",
            "Independent uninstall behavior",
            "coexistence_evidence",
            "esc-mobile-latest",
        ),
        "Android coexistence UAT runbook",
    )

    require_markers(
        ownership,
        (
            "expand → backfill → verify → enforce",
            "Migration `1.16.0`",
            "CoreTenantOwnershipBackfill::report()",
            "Do not use the default-tenant command as a substitute for a real mapping decision.",
            "Safe Contract separation",
        ),
        "tenant ownership runbook",
    )

    require_markers(
        backfill,
        (
            "--wp-root=",
            "--tenant-id",
            "--apply",
            "--verify",
            "CoreTenantOwnershipBackfill",
        ),
        "tenant backfill command",
    )

    require_markers(
        gradle,
        (
            f'namespace = "{APPLICATION_ID}"',
            f'applicationId = "{APPLICATION_ID}"',
            'applicationIdSuffix = ".dev"',
            'applicationIdSuffix = ".staging"',
            "ESC_ANDROID_KEYSTORE_PATH",
            "ESC_ANDROID_KEYSTORE_PASSWORD",
            "ESC_ANDROID_KEY_ALIAS",
            "ESC_ANDROID_KEY_PASSWORD",
        ),
        "ESC Android Gradle contract",
    )
    forbid_markers(
        gradle,
        (
            f'namespace = "{SAFE_APPLICATION_ID}"',
            f'applicationId = "{SAFE_APPLICATION_ID}"',
            "SC_ANDROID_KEYSTORE_PATH",
            "SC_ANDROID_KEYSTORE_PASSWORD",
        ),
        "ESC Android Gradle contract",
    )

    require_markers(
        activity,
        (
            "package com.safecontracts.enterprise",
            "enterprise_safecontracts/notifications",
            "enterprise_safe_contracts_alerts",
        ),
        "ESC MainActivity",
    )

    require_markers(
        bootstrap,
        (
            "ESC_FIREBASE_ANDROID_CONFIG_DEV",
            "ESC_FIREBASE_ANDROID_CONFIG_STAGING",
            "ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION",
            "com.safecontracts.enterprise.dev",
            "com.safecontracts.enterprise.staging",
            APPLICATION_ID,
            SAFE_APPLICATION_ID,
            "esc-safecontracts",
            "enterprise_safe_contracts_alerts",
        ),
        "ESC Android bootstrap",
    )

    require_markers(
        app_environment,
        (
            "String.fromEnvironment('ESC_ENV'",
            "'ESC_API_BASE_URL'",
            "Production Enterprise Safe Contracts API must use HTTPS.",
        ),
        "ESC Flutter environment",
    )
    forbid_markers(
        app_environment,
        (
            "String.fromEnvironment('SC_ENV'",
            "'SC_API_BASE_URL'",
        ),
        "ESC Flutter environment",
    )

    require_markers(config_example, ('"ESC_ENV"', '"ESC_API_BASE_URL"'), "ESC config example")
    forbid_markers(config_example, ('"SC_ENV"', '"SC_API_BASE_URL"'), "ESC config example")

    require_markers(
        android_isolation,
        (
            APPLICATION_ID,
            SAFE_APPLICATION_ID,
            "ESC_NOTIFICATION_CHANNEL",
            "ESC_DEEP_LINK_SCHEME",
            "--esc-apk",
            "--safe-apk",
            "apksigner",
            "signing lineages are distinct",
        ),
        "ESC Android isolation gate",
    )

    require_markers(
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
            "verify_esc_android_isolation.py",
            "enterprise_verified_artifacts.py publish-apk",
            "Last verified Enterprise apk",
            "EnterpriseSafeContracts-latest.apk",
            "esc-mobile-latest",
            "coexistence_evidence",
        ),
        "ESC Android publish workflow",
    )
    forbid_markers(
        publish_workflow,
        (
            "secrets.SC_ANDROID_",
            "--dart-define=SC_ENV=",
            "--dart-define=SC_API_BASE_URL=",
            "SafeContracts-Mobile.apk",
            "gh release create mobile-latest",
            "gh release delete mobile-latest",
        ),
        "ESC Android publish workflow",
    )

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

    require_markers(
        release_script,
        (
            "Last verified Enterprise Plugin",
            "Last verified Enterprise apk",
            "EnterpriseSafeContracts-latest.zip",
            "EnterpriseSafeContracts-latest.apk",
            APPLICATION_ID,
            "coexistence_evidence",
            "firebase_identity_verified",
        ),
        "Enterprise artifact policy",
    )

    issue_template = read_text(".github/ISSUE_TEMPLATE/esc-task.md")
    require_markers(
        issue_template,
        ("Full Impact Review", "Safe Contract separation verified", "ESC separation declaration"),
        "ESC issue template",
    )

    print("ESC foundation validation passed")


if __name__ == "__main__":
    main()
