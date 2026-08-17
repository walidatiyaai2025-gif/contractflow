#!/usr/bin/env python3
"""Static regression for the ESC Windows physical-device UAT operator runner."""

from __future__ import annotations

from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[2]
RUNNER = ROOT / "scripts/run_esc_android_coexistence_uat_windows.ps1"
DOC = ROOT / "docs/enterprise/ANDROID_COEXISTENCE_WINDOWS_UAT_RUNNER.md"
WORKFLOW = ROOT / ".github/workflows/esc-foundation.yml"


def require(text: str, marker: str, label: str) -> None:
    if marker not in text:
        raise AssertionError(f"{label} is missing required marker: {marker}")


def forbid_regex(text: str, pattern: str, label: str) -> None:
    if re.search(pattern, text, flags=re.IGNORECASE | re.MULTILINE):
        raise AssertionError(f"{label} violates Windows UAT runner safety boundary: {pattern}")


def main() -> int:
    runner = RUNNER.read_text(encoding="utf-8")
    doc = DOC.read_text(encoding="utf-8")
    workflow = WORKFLOW.read_text(encoding="utf-8")

    for marker in (
        "collect_esc_android_coexistence_uat_session.py",
        "com.safecontracts.safecontracts_mobile",
        "com.safecontracts.enterprise",
        "PENDING_REAL_DEVICE_UAT",
        "objective_draft.json",
        "device_snapshot.txt",
        "manual_evidence_requirements.json",
        "runner_summary.json",
        "session_isolation",
        "safe_only_push",
        "esc_only_push",
        "independent_update",
        "clear_data_uninstall_isolation",
        'finalization = "NOT_RUN"',
        'evidence_bundle = "NOT_RUN"',
        "EvidenceRoot must be empty to prevent evidence overwrite",
    ):
        require(runner, marker, "Windows UAT runner")

    # The operator runner may collect the existing non-destructive objective session
    # plus read-only ADB metadata. Runtime mutations and final PASS production stay
    # in the separately reviewed procedures/tools.
    for pattern in (
        r"['\"]install['\"]",
        r"['\"]uninstall['\"]",
        r"['\"]clear['\"]",
        r"finalize_esc_android_coexistence_evidence\.py",
        r"build_esc_android_coexistence_evidence_bundle\.py",
        r"\bdecision\s*=\s*['\"]PASS['\"]",
        r"\bstatus\s*=\s*['\"]PASS['\"]",
        r"Invoke-Expression",
        r"Start-Process",
    ):
        forbid_regex(runner, pattern, "Windows UAT runner")

    # Evidence filenames are requirements only; the runner must not manufacture
    # those manual artifacts itself.
    if re.search(r"(?:Set-Content|WriteAll|Out-File).*manual[\\/]", runner, re.IGNORECASE):
        raise AssertionError("Windows UAT runner must not create manual evidence artifacts")

    for marker in (
        "run_esc_android_coexistence_uat_windows.ps1",
        "objective_draft.json",
        "manual_evidence_requirements.json",
        "PENDING_REAL_DEVICE_UAT",
        "does not execute",
        "existing content-addressed bundle",
        "existing finalizer",
    ):
        require(doc, marker, "Windows UAT operator runbook")

    for marker in (
        "python3 tests/python/esc_android_coexistence_windows_runner_contract_test.py",
        "Parse ESC Windows physical-device UAT runner",
        "System.Management.Automation.Language.Parser",
    ):
        require(workflow, marker, "ESC Foundation workflow")

    print(
        "ESC Windows UAT runner contract passed: non-destructive objective/read-only "
        "collection only; manual runtime evidence and finalization remain separate "
        "and pending"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
