#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/publish-mobile-latest.yml"


class EscReleaseBranchProtectionContractTests(unittest.TestCase):
    def setUp(self) -> None:
        self.source = WORKFLOW.read_text(encoding="utf-8")

    def test_publish_is_exact_source_and_requires_live_schema_v2_audit(self) -> None:
        required = (
            "Require live schema-v2 ESC branch protection",
            'ref: ${{ github.sha }}',
            "ESC_GITHUB_ADMIN_READ_TOKEN",
            "rules/branches/enterprise-safecontracts",
            "branches/enterprise-safecontracts/protection",
            "audit_esc_branch_protection.py",
            "--branch-json",
            "--rules-json",
            "--protection-json",
            "schema_version') != 2",
            "decision') != 'PASS'",
            "administrator_enforcement_verified",
            "conversation_resolution_required",
            'if [[ "$BRANCH_HEAD" != "$GITHUB_SHA" ]]',
            '--target "$GITHUB_SHA"',
        )
        for marker in required:
            self.assertIn(marker, self.source, marker)

    def test_live_audit_precedes_secret_material_and_build(self) -> None:
        protection = self.source.index("Require live schema-v2 ESC branch protection")
        production_material = self.source.index("Require ESC-only production material")
        materialize = self.source.index("Materialize isolated ESC signing and Firebase files")
        build = self.source.index("Build production-signed ESC APK")
        publish = self.source.index("Publish ESC-only stable GitHub Release")
        self.assertLess(protection, production_material)
        self.assertLess(protection, materialize)
        self.assertLess(protection, build)
        self.assertLess(protection, publish)

    def test_admin_read_credential_cannot_mutate_repository_protection(self) -> None:
        start = self.source.index("Require live schema-v2 ESC branch protection")
        end = self.source.index("- uses: subosito/flutter-action@v2", start)
        protection_step = self.source[start:end]
        self.assertIn('export GH_TOKEN="$ADMIN_READ_TOKEN"', protection_step)
        for marker in (
            "--method PUT",
            "--method PATCH",
            "--method DELETE",
            "gh api --method",
            "branches/enterprise-safecontracts/protection -X",
        ):
            self.assertNotIn(marker, protection_step, marker)

    def test_verified_apk_and_live_audit_are_retained_without_branch_write(self) -> None:
        required = (
            "Retain verified ESC APK workflow artifact",
            "actions/upload-artifact@v4",
            "enterprise-safe-contracts-mobile-${{ github.sha }}-${{ github.run_id }}",
            "Last verified Enterprise apk/EnterpriseSafeContracts-latest.apk",
            "Last verified Enterprise apk/EnterpriseSafeContracts-latest.apk.sha256",
            "Last verified Enterprise apk/VERIFIED.json",
            "Last verified Enterprise apk/ESC_BRANCH_PROTECTION_AUDIT.json",
            "Last verified Enterprise apk/ESC_BRANCH_PROTECTION_AUDIT.json.sha256",
            '--branch-protection-audit "$ESC_BRANCH_PROTECTION_AUDIT"',
            "ESC_BRANCH_PROTECTION_AUDIT_SHA256",
            "gh release create esc-mobile-latest",
            "Branch protection audit SHA-256",
            "if-no-files-found: error",
            "retention-days: 90",
        )
        for marker in required:
            self.assertIn(marker, self.source, marker)

        forbidden = (
            "git push origin HEAD:enterprise-safecontracts",
            "git pull --rebase origin enterprise-safecontracts",
            'git add "Last verified Enterprise apk"',
            'git commit -m "ESC-P0-005 retain verified Enterprise Android APK',
        )
        for marker in forbidden:
            self.assertNotIn(marker, self.source, marker)


if __name__ == "__main__":
    unittest.main()
