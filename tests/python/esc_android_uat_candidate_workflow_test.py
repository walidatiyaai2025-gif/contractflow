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
        "ESC_GITHUB_ADMIN_READ_TOKEN",
        "Require live schema-v3 ESC branch enforcement and exact head",
        "branches/enterprise-safecontracts/protection",
        "rules/branches/enterprise-safecontracts",
        "audit_esc_branch_protection.py",
        "--protection-json",
        "schema_version') != 3",
        "required_status_check_sources_verified",
        "expected_status_check_app_slug",
        "expected_status_check_app_id",
        "observed_required_check_source_ids",
        "administrator_enforcement_verified",
        "conversation_resolution_required",
        "ESC_BRANCH_PROTECTION_AUDIT_PATH",
        "ESC_BRANCH_PROTECTION_AUDIT_SHA256",
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
        "branch_protection_audit_schema_version': 3",
        "branch_protection_audit_decision': 'PASS'",
        "branch_protection_audit_sha256",
        "ESC_BRANCH_PROTECTION_AUDIT.json",
        "ESC_UAT_CANDIDATE.json",
        "EnterpriseSafeContracts-UAT-CANDIDATE-",
        "actions/upload-artifact@v4",
        "retention-days: 14",
    ):
        require(workflow, marker, "ESC UAT candidate workflow")

    audit_gate = workflow.index("Require live schema-v3 ESC branch enforcement and exact head")
    signing_material = workflow.index("Require ESC-only production signing and Firebase material")
    materialize = workflow.index("Materialize isolated ESC signing and Firebase files")
    if not audit_gate < signing_material < materialize:
        raise AssertionError(
            "live schema-v3 branch audit must complete before signing/Firebase secret validation/materialization"
        )

    admin_token = workflow.index("ESC_GITHUB_ADMIN_READ_TOKEN")
    audit_call = workflow.index("python3 scripts/audit_esc_branch_protection.py")
    if not audit_gate <= admin_token < audit_call < signing_material:
        raise AssertionError("admin-read credential must be used only in the pre-signing audit gate")

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
        "Administration: write",
        "branches/enterprise-safecontracts/protection --method PUT",
        "--method PUT",
        "--method DELETE",
        "app_id = -1",
    ):
        forbid(workflow, marker, "ESC UAT candidate workflow")

    if re.search(r"\bstate\s*[:=]\s*['\"]?PASS", workflow, flags=re.IGNORECASE):
        raise AssertionError("ESC UAT candidate workflow must never manufacture coexistence PASS")

    for marker in (
        "UAT_CANDIDATE_NOT_RELEASED",
        "14 days",
        "enterprise-safecontracts",
        "branch protection",
        "schema v3",
        "GitHub Actions",
        "source-pinned",
        "ESC_GITHUB_ADMIN_READ_TOKEN",
        "Administration: read",
        "production signing",
        "#421",
        "#522",
        "run_esc_android_coexistence_uat_windows.ps1",
        "publish-mobile-latest.yml",
        "not a release",
    ):
        require(runbook, marker, "ESC UAT candidate runbook")

    for marker in (
        "Administration: write",
        "repository write permission",
        "any source",
    ):
        forbid(runbook, marker, "ESC UAT candidate runbook")

    require(
        foundation,
        "python3 tests/python/esc_android_uat_candidate_workflow_test.py",
        "ESC Foundation workflow",
    )

    print(
        "ESC UAT candidate workflow contract passed: exact-head live schema-v3 "
        "branch enforcement and GitHub Actions source pinning are required before "
        "production signing, with the audit digest bound into candidate provenance"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
