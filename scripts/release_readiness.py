#!/usr/bin/env python3
"""Fail-closed release-readiness verification for the active product line."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
import re
import sys

from backup_manifest import build_manifest, validate_manifest

ROOT = Path(__file__).resolve().parents[1]

REQUIRED_PATHS = (
    "docs/BACKUP_RESTORE_RUNBOOK.md",
    "docs/PRODUCTION_RELEASE_READINESS.md",
    "ops/uat-scenarios.json",
    "scripts/backup_manifest.py",
    "tests/php/p10_release_readiness_011_016.php",
    "mobile/test/mobile_final_validation_047_050_test.dart",
)

REQUIRED_AUDIT_EVENTS = (
    "safecontracts_contract_base_value_changed",
    "safecontracts_contract_financial_item_added",
    "safecontracts_contract_adjustment_added",
    "safecontracts_payment_settled",
    "safecontracts_contract_customer_assigned",
    "safecontracts_contract_accountant_assigned",
    "safecontracts_contract_status_changed",
    "safecontracts_contract_dates_changed",
    "safecontracts_payment_status_changed",
    "safecontracts_payment_dates_changed",
    "safecontracts_followup_recorded",
    "safecontracts_export_completed",
    "safecontracts_import_uploaded",
    "safecontracts_import_discovered",
    "safecontracts_import_mapping_saved",
    "safecontracts_import_validated",
    "safecontracts_import_completed",
)

REQUIRED_UAT_ROLES = {
    "safecontracts_system_admin",
    "safecontracts_manager",
    "safecontracts_accountant",
    "safecontracts_viewer",
}

REQUIRED_UAT_FLOWS = {
    "contract-lifecycle",
    "assigned-scope",
    "collection-settlement",
    "followup-workflow",
    "report-export",
    "read-only-boundary",
    "mobile-notification-deeplink",
    "upgrade-backup-restore",
}

MIGRATION_ENTRY = re.compile(
    r"['\"](\d+\.\d+\.\d+)['\"]\s*=>\s*(Migration\d+[A-Za-z0-9_]*)::class"
)
LATEST_VERSION = re.compile(
    r"public const LATEST_VERSION\s*=\s*['\"](\d+\.\d+\.\d+)['\"]\s*;"
)


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    raise SystemExit(1)


def _read(relative: str) -> str:
    path = ROOT / relative
    if not path.exists():
        fail(f"missing release-readiness path: {relative}")
    return path.read_text(encoding="utf-8")


def _version_tuple(value: str) -> tuple[int, int, int]:
    parts = value.split(".")
    if len(parts) != 3 or any(not part.isdigit() for part in parts):
        fail(f"invalid migration version: {value}")
    return tuple(int(part) for part in parts)  # type: ignore[return-value]


def validate_required_paths() -> int:
    missing = [path for path in REQUIRED_PATHS if not (ROOT / path).exists()]
    if missing:
        fail("missing release-readiness paths: " + ", ".join(missing))
    return len(REQUIRED_PATHS)


def validate_audit_completeness() -> int:
    source = _read("wordpress-plugin/safecontracts/src/Audit/AuditRecorder.php")
    checks = 0
    for event in REQUIRED_AUDIT_EVENTS:
        if event not in source:
            fail(f"critical audit event is not registered: {event}")
        checks += 1

    required_safety_markers = (
        "self::sanitize($context)",
        "token|secret|password|credential|authorization",
        "private[_-]?key",
        "service[_-]?account",
    )
    for marker in required_safety_markers:
        if marker not in source:
            fail(f"audit sanitization contract is missing marker: {marker}")
        checks += 1
    return checks


def validate_migration_chain() -> int:
    source = _read("wordpress-plugin/safecontracts/src/Database/Migrator.php")
    entries = MIGRATION_ENTRY.findall(source)
    if len(entries) < 12:
        fail("migration chain is shorter than the production baseline")

    versions = [version for version, _ in entries]
    sorted_versions = sorted(versions, key=_version_tuple)
    if versions != sorted_versions or len(versions) != len(set(versions)):
        fail("migration versions must be unique and monotonically ordered")

    latest_match = LATEST_VERSION.search(source)
    if latest_match is None:
        fail("Migrator::LATEST_VERSION is missing")
    latest = latest_match.group(1)
    if latest != versions[-1]:
        fail(
            "Migrator::LATEST_VERSION does not match the newest registered migration "
            f"({latest} != {versions[-1]})"
        )

    checks = 3
    for _, migration_class in entries:
        path = (
            ROOT
            / "wordpress-plugin/safecontracts/src/Database/Migrations"
            / f"{migration_class}.php"
        )
        if not path.exists():
            fail(f"registered migration source is missing: {migration_class}")
        migration_source = path.read_text(encoding="utf-8")
        if f"final class {migration_class} implements Migration" not in migration_source:
            fail(f"migration does not implement the Migration contract: {migration_class}")
        checks += 1

    for marker in (
        "update_option(self::VERSION_OPTION, $version, false)",
        "safecontracts_db_migrated_at",
        "safecontracts_database_migrated",
    ):
        if marker not in source:
            fail(f"migration progression evidence is missing marker: {marker}")
        checks += 1
    return checks


def validate_accessibility_contract() -> int:
    admin_shell = _read("wordpress-plugin/safecontracts/src/Admin/AdminShell.php")
    responsive_css = _read(
        "wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-responsive.css"
    )
    mobile_layout = _read("mobile/lib/features/ui/mobile_layout.dart")
    mobile_states = _read("mobile/lib/features/ui/mobile_states.dart")

    markers = (
        (admin_shell, 'dir="auto"', "admin direction boundary"),
        (admin_shell, 'aria-hidden="true"', "decorative admin mark semantics"),
        (responsive_css, '[dir="rtl"]', "admin RTL selectors"),
        (responsive_css, "@media (max-width: 782px)", "admin responsive breakpoint"),
        (responsive_css, ":focus-visible", "keyboard focus visibility"),
        (mobile_layout, "safeContractsIsRtlLanguage", "mobile RTL locale helper"),
        (mobile_layout, "startsWith('ar-')", "Arabic locale variant handling"),
        (mobile_layout, "TextDirection.rtl", "mobile RTL direction"),
        (mobile_layout, "assert(maxWidth > 0)", "bounded adaptive layout"),
        (mobile_states, "Semantics(", "mobile semantic state wrapper"),
        (mobile_states, "liveRegion:", "mobile live state feedback"),
        (mobile_states, "ExcludeSemantics(", "decorative icon semantics"),
        (mobile_states, "mobileStateAllowsRetry", "fail-closed retry policy"),
    )
    for source, marker, description in markers:
        if marker not in source:
            fail(f"accessibility contract missing {description}: {marker}")
    return len(markers)


def validate_backup_contract() -> int:
    manifest = build_manifest()
    checks = validate_manifest(manifest)
    runbook = _read("docs/BACKUP_RESTORE_RUNBOOK.md")
    for marker in (
        "safecontracts-backup-manifest.json",
        "safecontracts_%",
        "service-account JSON/private keys",
        "UAT-008",
        "row counts",
    ):
        if marker not in runbook:
            fail(f"backup/restore runbook is missing marker: {marker}")
        checks += 1
    return checks


def validate_uat_contract() -> int:
    payload = json.loads(_read("ops/uat-scenarios.json"))
    if payload.get("schema_version") != 1:
        fail("unsupported UAT scenario schema version")
    scenarios = payload.get("scenarios")
    if not isinstance(scenarios, list) or len(scenarios) < 8:
        fail("UAT manifest must contain the full production scenario baseline")

    ids: set[str] = set()
    roles: set[str] = set()
    flows: set[str] = set()
    checks = 2
    for scenario in scenarios:
        if not isinstance(scenario, dict):
            fail("UAT scenario must be an object")
        scenario_id = scenario.get("id")
        role = scenario.get("role")
        flow = scenario.get("flow")
        if not isinstance(scenario_id, str) or not re.fullmatch(r"UAT-\d{3}", scenario_id):
            fail(f"invalid UAT scenario id: {scenario_id!r}")
        if scenario_id in ids:
            fail(f"duplicate UAT scenario id: {scenario_id}")
        if not isinstance(role, str) or not role.startswith("safecontracts_"):
            fail(f"invalid UAT role for {scenario_id}")
        if not isinstance(flow, str) or not flow:
            fail(f"invalid UAT flow for {scenario_id}")
        for field in ("preconditions", "steps", "expected", "evidence"):
            values = scenario.get(field)
            if not isinstance(values, list) or not values:
                fail(f"UAT scenario {scenario_id} is missing {field}")
            if any(not isinstance(value, str) or not value.strip() for value in values):
                fail(f"UAT scenario {scenario_id} contains invalid {field}")
            checks += 1
        ids.add(scenario_id)
        roles.add(role)
        flows.add(flow)
        checks += 3

    if not REQUIRED_UAT_ROLES.issubset(roles):
        fail("UAT role coverage is incomplete")
    if not REQUIRED_UAT_FLOWS.issubset(flows):
        fail("UAT flow coverage is incomplete")
    return checks + 2


def validate_ci_release_gate() -> int:
    safe_workflow = ROOT / ".github/workflows/quality-gates.yml"
    php_runner = _read("scripts/test-php.sh")
    release_doc = _read("docs/PRODUCTION_RELEASE_READINESS.md")
    checks = 0

    if safe_workflow.exists():
        workflow = safe_workflow.read_text(encoding="utf-8")
        workflow_markers = (
            "release-readiness:",
            "needs: [repository-standards, backend-foundation, mobile-foundation]",
            "python3 scripts/backup_manifest.py --check",
            "python3 scripts/release_readiness.py --check",
        )
        for marker in workflow_markers:
            if marker not in workflow:
                fail(f"Quality Gates are missing release marker: {marker}")
            checks += 1
    else:
        foundation = _read(".github/workflows/esc-foundation.yml")
        publish = _read(".github/workflows/publish-mobile-latest.yml")
        foundation_markers = (
            "ESC Foundation Gate",
            "python3 scripts/validate-esc-foundation.py",
            "python3 scripts/verify_esc_android_isolation.py",
            "python3 scripts/enterprise_verified_artifacts.py check",
            "./scripts/test-php.sh",
            "flutter analyze",
            "flutter test",
        )
        publish_markers = (
            "refs/heads/enterprise-safecontracts",
            "environment: esc-production",
            "--flavor production --release",
            "--dart-define=ESC_ENV=production",
            "EnterpriseSafeContracts-latest.apk",
            "esc-mobile-latest",
            "coexistence_evidence",
        )
        for marker in foundation_markers:
            if marker not in foundation:
                fail(f"ESC Foundation Gate is missing release marker: {marker}")
            checks += 1
        for marker in publish_markers:
            if marker not in publish:
                fail(f"ESC publish gate is missing release marker: {marker}")
            checks += 1

    if 'p10_release_readiness_011_016.php' not in php_runner:
        fail("backend gate does not execute P10 release-readiness regression")
    checks += 1

    for marker in (
        "SC-P10-011",
        "SC-P10-012",
        "SC-P10-013",
        "SC-P10-014",
        "SC-P10-015",
        "SC-P10-016",
        "not production-ready",
    ):
        if marker not in release_doc:
            fail(f"release-readiness documentation is missing marker: {marker}")
        checks += 1
    return checks


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()

    sections = {
        "paths": validate_required_paths(),
        "audit": validate_audit_completeness(),
        "migrations": validate_migration_chain(),
        "accessibility": validate_accessibility_contract(),
        "backup_restore": validate_backup_contract(),
        "uat": validate_uat_contract(),
        "ci_release_gate": validate_ci_release_gate(),
    }
    total = sum(sections.values())
    if args.check:
        print(
            "Release readiness passed "
            f"({total} checks across {len(sections)} sections)."
        )
    else:
        print(json.dumps({"checks": total, "sections": sections}, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
