#!/usr/bin/env python3
"""Final fail-closed SafeContracts roadmap/release validation for SC-P10-032."""

from __future__ import annotations

import argparse
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

TOTAL_RE = re.compile(
    r"\| \*\*TOTAL\*\* \|\s*\| \*\*(\d+)\*\* \| \*\*(\d+)\*\* \| \*\*(\d+)\*\* \| \*\*(\d+)\*\* \| \*\*([0-9.]+)%\*\* \|"
)
PHASE_RE = re.compile(
    r"\| (P9|P10) \|.*?\| (\d+) \| (\d+) \| (\d+) \| (\d+) \| ([0-9.]+)% \|"
)

REQUIRED_PATHS = (
    "wordpress-plugin/safecontracts/src/Rest/MobileMutationController.php",
    "mobile/lib/features/payments/collection_entry_dialog.dart",
    "mobile/lib/features/followups/followups.dart",
    "mobile/lib/features/followups/followups_screen.dart",
    "tests/php/rest_mobile_mutations_016_019.php",
    "tests/php/p9_validation_038_044.php",
    "mobile/test/mobile_validation_038_044_test.dart",
    "docs/P9_FINAL_VALIDATION_038_044.md",
    "docs/FINAL_PRODUCTION_RELEASE_VALIDATION.md",
)


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    raise SystemExit(1)


def read(relative: str) -> str:
    path = ROOT / relative
    if not path.exists():
        fail(f"missing final release path: {relative}")
    return path.read_text(encoding="utf-8")


def validate_project_status() -> int:
    status = read("docs/PROJECT_STATUS.md")
    if "GitHub Issues found: 284/284" not in status:
        fail("all 284 roadmap task IDs must be materialized before final release validation")

    match = TOTAL_RE.search(status)
    if match is None:
        fail("unable to parse SafeContracts TOTAL project status row")
    planned, todo, in_progress, done = map(int, match.groups()[:4])
    if planned != 284:
        fail(f"roadmap total changed unexpectedly: {planned}")
    if todo != 0:
        fail(f"final closeout still has unassigned To Do tasks: {todo}")
    if in_progress + done != planned:
        fail("Done + In Progress does not reconcile to the fixed roadmap total")
    if done < 272:
        fail("final release validation cannot run before the established 272-task baseline")

    phases = {m.group(1): tuple(map(int, m.groups()[1:5])) for m in PHASE_RE.finditer(status)}
    expected = {"P9": 50, "P10": 32}
    for phase, count in expected.items():
        if phase not in phases:
            fail(f"missing {phase} status row")
        phase_planned, phase_todo, phase_progress, phase_done = phases[phase]
        if phase_planned != count or phase_todo != 0:
            fail(f"{phase} final closeout is not fully materialized/assigned")
        if phase_progress + phase_done != count:
            fail(f"{phase} status does not reconcile to {count} planned tasks")
    return 10


def validate_closeout_evidence() -> int:
    checks = 0
    for relative in REQUIRED_PATHS:
        if not (ROOT / relative).exists():
            fail(f"missing closeout evidence: {relative}")
        checks += 1

    runner = read("scripts/test-php.sh")
    for marker in (
        'rest_mobile_mutations_016_019.php',
        'p9_validation_038_044.php',
    ):
        if marker not in runner:
            fail(f"backend Quality Gate is missing final P9 evidence: {marker}")
        checks += 1

    workflow = read(".github/workflows/quality-gates.yml")
    for marker in (
        "release-readiness:",
        "needs: [repository-standards, backend-foundation, mobile-foundation]",
        "python3 scripts/release_readiness.py --check",
        "python3 scripts/p10_validation_027_031.py --check",
        "python3 scripts/final_release_validation.py --check",
    ):
        if marker not in workflow:
            fail(f"final Quality Gate wiring is missing: {marker}")
        checks += 1

    final_doc = read("docs/FINAL_PRODUCTION_RELEASE_VALIDATION.md")
    for marker in (
        "SC-P10-032",
        "284/284",
        "real production environment",
        "real Firebase delivery",
        "real target Android device",
        "business-owner/UAT sign-off",
    ):
        if marker not in final_doc:
            fail(f"final release evidence boundary is missing: {marker}")
        checks += 1
    return checks


def validate_mobile_authority_boundary() -> int:
    source = "\n".join(
        read(path)
        for path in (
            "mobile/lib/features/payments/payments.dart",
            "mobile/lib/features/payments/collection_entry_dialog.dart",
            "mobile/lib/features/followups/followups.dart",
        )
    )
    for forbidden in ("double.parse(", "num.parse("):
        if forbidden in source:
            fail(f"mobile final closeout reintroduced floating-point financial authority: {forbidden}")

    required = (
        "payments/$id/expected-date",
        "collections/record",
        "reference-data",
        "payments/$paymentId/followups/record",
    )
    for marker in required:
        if marker not in source:
            fail(f"mobile final closeout is missing server-authoritative endpoint: {marker}")
    return len(required) + 2


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()

    sections = {
        "project_status": validate_project_status(),
        "closeout_evidence": validate_closeout_evidence(),
        "mobile_authority": validate_mobile_authority_boundary(),
    }
    total = sum(sections.values())
    if args.check:
        print(
            "SafeContracts final production release validation passed "
            f"({total} checks across {len(sections)} sections)."
        )
    else:
        print({"checks": total, "sections": sections})
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
