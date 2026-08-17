#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[2]
SCRIPT = ROOT / "scripts/configure_esc_branch_protection.ps1"


class EscBranchProtectionApplyHelperContractTests(unittest.TestCase):
    def setUp(self) -> None:
        self.source = SCRIPT.read_text(encoding="utf-8")

    def test_targets_only_expected_repository_and_branch(self) -> None:
        required = (
            "$ExpectedRepository = 'walidatiyaai2025-gif/contractflow'",
            "$ExpectedBranch = 'enterprise-safecontracts'",
            "Refusing repository",
            "Refusing branch",
        )
        for marker in required:
            self.assertIn(marker, self.source, marker)

    def test_defaults_to_preview_and_requires_explicit_apply(self) -> None:
        self.assertIn("[switch]$Apply", self.source)
        self.assertIn("if (-not $Apply)", self.source)
        self.assertIn("PREVIEW ONLY: no GitHub settings were changed.", self.source)
        self.assertIn("Re-run with -Apply", self.source)

    def test_required_controls_are_in_payload(self) -> None:
        required = (
            "strict = $true",
            "context = 'esc-foundation'",
            "context = 'esc-mobile'",
            "enforce_admins = $true",
            "required_pull_request_reviews",
            "required_approving_review_count = 0",
            "require_last_push_approval = $false",
            "allow_force_pushes = $false",
            "allow_deletions = $false",
            "required_conversation_resolution = $true",
        )
        for marker in required:
            self.assertIn(marker, self.source, marker)

    def test_pr_only_policy_does_not_require_external_reviewer(self) -> None:
        self.assertIn("required_pull_request_reviews", self.source)
        self.assertIn("required_approving_review_count = 0", self.source)
        self.assertIn("dismiss_stale_reviews = $false", self.source)
        self.assertIn("require_last_push_approval = $false", self.source)
        self.assertNotIn("required_approving_review_count = 1", self.source)
        self.assertNotIn("require_last_push_approval = $true", self.source)

    def test_uses_approved_gh_session_without_secret_material(self) -> None:
        required = (
            "gh auth status",
            "--method PUT",
            "branches/$Branch/protection",
            "X-GitHub-Api-Version: 2026-03-10",
        )
        for marker in required:
            self.assertIn(marker, self.source, marker)

        forbidden = (
            "GITHUB_TOKEN",
            "GH_TOKEN",
            "PERSONAL_ACCESS_TOKEN",
            "Authorization: Bearer",
            "Set-Content Env:",
        )
        for marker in forbidden:
            self.assertNotIn(marker, self.source, marker)

    def test_post_apply_capture_and_audit_are_mandatory(self) -> None:
        required = (
            "rules/branches/$Branch",
            "branches/$Branch/protection",
            "branch.json",
            "rules.json",
            "protection.json",
            "audit_esc_branch_protection.py",
            "esc-branch-protection-audit.json",
            "Protection was applied, but verification failed.",
            "PASS: protection applied and independently audited.",
        )
        for marker in required:
            self.assertIn(marker, self.source, marker)

    def test_evidence_directory_cannot_be_overwritten(self) -> None:
        self.assertIn("if (Test-Path -LiteralPath $EvidenceRoot)", self.source)
        self.assertIn("EvidenceRoot already exists", self.source)

    def test_no_destructive_branch_operation_is_present(self) -> None:
        forbidden = (
            "git push --force",
            "git push -f",
            "gh api --method DELETE",
            "git branch -D enterprise-safecontracts",
            "git push origin --delete enterprise-safecontracts",
        )
        for marker in forbidden:
            self.assertNotIn(marker, self.source, marker)


if __name__ == "__main__":
    unittest.main()
