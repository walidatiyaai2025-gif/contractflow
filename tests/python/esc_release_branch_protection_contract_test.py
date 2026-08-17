#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = ROOT / ".github/workflows/publish-mobile-latest.yml"


class EscReleaseBranchProtectionContractTests(unittest.TestCase):
    def setUp(self) -> None:
        self.source = WORKFLOW.read_text(encoding="utf-8")

    def test_publish_is_exact_source_and_requires_protected_branch(self) -> None:
        required = (
            "Require protected ESC integration branch",
            'ref: ${{ github.sha }}',
            'branches/enterprise-safecontracts',
            "--jq '.protected'",
            "--jq '.commit.sha'",
            'if [[ "$PROTECTED" != "true" ]]',
            'if [[ "$BRANCH_HEAD" != "$GITHUB_SHA" ]]',
            '--target "$GITHUB_SHA"',
        )
        for marker in required:
            self.assertIn(marker, self.source, marker)

    def test_verified_apk_is_retained_without_protected_branch_write(self) -> None:
        required = (
            "Retain verified ESC APK workflow artifact",
            "actions/upload-artifact@v4",
            "enterprise-safe-contracts-mobile-${{ github.sha }}-${{ github.run_id }}",
            "Last verified Enterprise apk/EnterpriseSafeContracts-latest.apk",
            "Last verified Enterprise apk/EnterpriseSafeContracts-latest.apk.sha256",
            "Last verified Enterprise apk/VERIFIED.json",
            "if-no-files-found: error",
            "retention-days: 90",
            "gh release create esc-mobile-latest",
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

    def test_protection_check_precedes_secret_material(self) -> None:
        protection = self.source.index("Require protected ESC integration branch")
        production_material = self.source.index("Require ESC-only production material")
        build = self.source.index("Build production-signed ESC APK")
        self.assertLess(protection, production_material)
        self.assertLess(protection, build)


if __name__ == "__main__":
    unittest.main()
