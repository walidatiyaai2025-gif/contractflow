#!/usr/bin/env python3
"""Regression contract for the ESC production-signed UAT candidate workflow."""

from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/esc-uat-candidate.yml"
RUNBOOK = ROOT / "docs/enterprise/ANDROID_UAT_CANDIDATE.md"
FOUNDATION = ROOT / ".github/workflows/esc-foundation.yml"


def require(text: str, marker: str, label: str) -> None:
    if marker not in text:
        raise AssertionError(f"{label} is missing required marker: {marker}")


def forbid(text: str, marker: str, label: str) -> None:
    if marker.lower() in text.lower():
        raise AssertionError(f"{label} must not contain: {marker}")


def main() -> int:
    workflow = WORKFLOW.read_text(encoding="utf-8")
    runbook = RUNBOOK.read_text(encoding="utf-8")
    foundation = FOUNDATION.read_text(encoding="utf-8")

    for marker in (
        "name: ESC Android UAT Candidate",
        "workflow_dispatch:",
        "contents: read",
        "github.ref == 'refs/heads/enterprise-safecontracts'",
        "environment: esc-production",
        "ref: ${{ github.sha }}",
        "branches/enterprise-safecontracts",
        "--jq '.protected'",
        "BRANCH_HEAD",
        '[[ "$BRANCH_HEAD" != "$GITHUB_SHA" ]]',
        "ESC_ANDROID_KEYSTORE_BASE64",
        "ESC_ANDROID_CERT_SHA256",
        "ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION_BASE64",
        "flutter build apk --flavor production --release",
        "--dart-define=ESC_ENV=production",
        "com.safecontracts.enterprise",
        "com.safecontracts.safecontracts_mobile",
        "verify_esc_android_isolation.py --esc-apk",
        "UAT_CANDIDATE_NOT_RELEASED",
        "release_eligible': False",
        "ESC_UAT_CANDIDATE.json",
        "EnterpriseSafeContracts-UAT-CANDIDATE-",
        "actions/upload-artifact@v4",
        "retention-days: 14",
    ):
        require(workflow, marker, "ESC UAT candidate workflow")

    # This workflow only creates the exact-source binary needed to perform UAT.
    # Stable retention, release publication and PASS/finalization stay separate.
    for marker in (
        "enterprise_verified_artifacts.py publish-apk",
        "gh release",
        "esc-mobile-latest",
        "EnterpriseSafeContracts-latest.apk",
        "coexistence_record_base64",
        "validate_esc_android_coexistence_evidence.py",
        "finalize_esc_android_coexistence_evidence.py",
        "build_esc_android_coexistence_evidence_bundle.py",
        "contents: write",
    ):
        forbid(workflow, marker, "ESC UAT candidate workflow")

    if re.search(r"\bstate\s*[:=]\s*['\"]?PASS", workflow, flags=re.IGNORECASE):
        raise AssertionError("ESC UAT candidate workflow must never manufacture PASS")

    for marker in (
        "UAT_CANDIDATE_NOT_RELEASED",
        "14 days",
        "enterprise-safecontracts",
        "branch protection",
        "production signing",
        "#421",
        "#522",
        "run_esc_android_coexistence_uat_windows.ps1",
        "publish-mobile-latest.yml",
        "not a release",
    ):
        require(runbook, marker, "ESC UAT candidate runbook")

    require(
        foundation,
        "python3 tests/python/esc_android_uat_candidate_workflow_test.py",
        "ESC Foundation workflow",
    )

    print(
        "ESC UAT candidate workflow contract passed: protected exact-source "
        "production signing produces only a short-lived non-release UAT artifact"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
