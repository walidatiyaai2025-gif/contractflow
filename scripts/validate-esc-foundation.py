#!/usr/bin/env python3
"""Validate Enterprise Safe Contracts branch separation/foundation invariants."""

from __future__ import annotations

import json
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
PUBLIC_URL = "https://esc.50sols.com/"
REQUIRED_FILES = (
    "ENTERPRISE_SAFE_CONTRACTS_MASTER_PLAN.txt",
    "docs/enterprise/ARCHITECTURE.md",
    "docs/enterprise/MULTITENANCY_SECURITY.md",
    "docs/enterprise/PRODUCT_DESIGN_LANDING.md",
    "docs/enterprise/MOBILE_IDENTITY_APK.md",
    "docs/enterprise/ROADMAP.md",
    "docs/enterprise/FEATURE_REGISTRY.json",
    ".github/ISSUE_TEMPLATE/esc-task.md",
)
ALLOWED_LIFECYCLES = {"Development", "Internal Preview", "Beta", "Public", "Deprecated"}
REQUIRED_FEATURE_KEYS = {
    "code",
    "title",
    "category",
    "lifecycle",
    "tenant_aware",
    "admin",
    "mobile",
    "public_marketing",
    "plan",
}


def fail(message: str) -> None:
    raise SystemExit(f"ESC foundation validation failed: {message}")


def read_text(relative: str) -> str:
    path = ROOT / relative
    if not path.is_file():
        fail(f"missing required file: {relative}")
    return path.read_text(encoding="utf-8")


def main() -> None:
    for relative in REQUIRED_FILES:
        if not (ROOT / relative).is_file():
            fail(f"missing required file: {relative}")

    master = read_text("ENTERPRISE_SAFE_CONTRACTS_MASTER_PLAN.txt")
    agents = read_text("AGENTS.md")
    mobile = read_text("docs/enterprise/MOBILE_IDENTITY_APK.md")
    landing = read_text("docs/enterprise/PRODUCT_DESIGN_LANDING.md")

    required_master_markers = (
        PUBLIC_URL,
        "CRITICAL PRODUCT SEPARATION RULE",
        "FULL IMPACT RULE",
        "ANDROID / APK COEXISTENCE",
        "LANDING PAGE",
        "enterprise-safecontracts",
    )
    for marker in required_master_markers:
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

    if "com.safecontracts.enterprise" not in mobile:
        fail("mobile identity document missing ESC package baseline")
    if PUBLIC_URL not in landing:
        fail("landing/design document missing official public URL")

    registry_path = ROOT / "docs/enterprise/FEATURE_REGISTRY.json"
    try:
        registry = json.loads(registry_path.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        fail(f"feature registry is invalid JSON: {exc}")

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

    issue_template = read_text(".github/ISSUE_TEMPLATE/esc-task.md")
    for marker in ("Full Impact Review", "Safe Contract separation verified", "ESC separation declaration"):
        if marker not in issue_template:
            fail(f"ESC issue template missing marker: {marker}")

    print("ESC foundation validation passed")


if __name__ == "__main__":
    main()
