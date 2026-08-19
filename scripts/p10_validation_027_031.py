#!/usr/bin/env python3
"""Validate SafeContracts P10 audit/accessibility/backup/migration/UAT baseline."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
import re
import sys

from backup_manifest import build_manifest, registered_migrations, validate_manifest

ROOT = Path(__file__).resolve().parents[1]

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

BASELINE_UAT = {
    "UAT-001": ("safecontracts_system_admin", "contract-lifecycle"),
    "UAT-002": ("safecontracts_accountant", "assigned-scope"),
    "UAT-003": ("safecontracts_accountant", "collection-settlement"),
    "UAT-004": ("safecontracts_accountant", "followup-workflow"),
    "UAT-005": ("safecontracts_manager", "report-export"),
    "UAT-006": ("safecontracts_viewer", "read-only-boundary"),
    "UAT-007": ("safecontracts_accountant", "mobile-notification-deeplink"),
    "UAT-008": ("safecontracts_system_admin", "upgrade-backup-restore"),
}

ALLOWED_UAT_ROLES = {
    "safecontracts_system_admin",
    "safecontracts_manager",
    "safecontracts_accountant",
    "safecontracts_viewer",
}

LATEST_VERSION_PATTERN = re.compile(
    r"public const LATEST_VERSION\s*=\s*['\"](\d+\.\d+\.\d+)['\"]\s*;"
)
MIGRATION_NUMBER_PATTERN = re.compile(r"^Migration(\d{4})[A-Za-z0-9_]*$")


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    raise SystemExit(1)


def _read(relative: str) -> str:
    path = ROOT / relative
    if not path.exists():
        fail(f"missing P10 validation path: {relative}")
    return path.read_text(encoding="utf-8")


def validate_audit_completeness() -> int:
    source = _read("wordpress-plugin/safecontracts/src/Audit/AuditRecorder.php")
    start = source.find("private const EVENTS = [")
    end = source.find("];", start)
    if start < 0 or end < 0:
        fail("AuditRecorder event registry is missing")
    registry = source[start:end]
    events = tuple(re.findall(r"'(safecontracts_[a-z0-9_]+)'", registry))
    if events != REQUIRED_AUDIT_EVENTS:
        fail("AuditRecorder critical event registry changed without P10 baseline review")
    if len(events) != len(set(events)):
        fail("AuditRecorder critical event registry contains duplicates")

    checks = len(events) + 2
    for event in events:
        if source.count(f"'{event}'") < 2:
            fail(f"audit event is registered but not mapped: {event}")
        checks += 1

    safety_markers = (
        "token|secret|password|credential|authorization",
        "private[_-]?key",
        "service[_-]?account",
        "storage[_-]?key",
        "sha256",
        "tmp[_-]?name",
        "workbook[_-]?(content|bytes|path)",
        "is_array($value) ? self::sanitize($value) : $value",
    )
    for marker in safety_markers:
        if marker not in source:
            fail(f"audit recursive secret-sanitization marker is missing: {marker}")
        checks += 1
    return checks


def validate_rtl_accessibility() -> int:
    admin_shell = _read("wordpress-plugin/safecontracts/src/Admin/AdminShell.php")
    responsive_css = _read(
        "wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-responsive.css"
    )
    mobile_layout = _read("mobile/lib/features/ui/mobile_layout.dart")
    mobile_states = _read("mobile/lib/features/ui/mobile_states.dart")
    mobile_tests = _read("mobile/test/mobile_final_validation_047_050_test.dart")

    markers = (
        (admin_shell, 'dir="auto"', "admin direction boundary"),
        (admin_shell, 'aria-hidden="true"', "decorative admin mark"),
        (
            responsive_css,
            ":where(a, button, input, select, textarea):focus-visible",
            "keyboard focus coverage",
        ),
        (responsive_css, "outline: 3px solid currentColor", "visible focus outline"),
        (responsive_css, '[dir="rtl"]', "admin RTL selector"),
        (responsive_css, "@media (max-width: 782px)", "admin mobile breakpoint"),
        (responsive_css, "@media (max-width: 480px)", "admin narrow breakpoint"),
        (mobile_layout, "normalized.startsWith('ar-')", "Arabic hyphen locale"),
        (mobile_layout, "normalized.startsWith('ar_')", "Arabic underscore locale"),
        (mobile_layout, "TextDirection.rtl", "mobile RTL direction"),
        (mobile_layout, "assert(maxWidth > 0)", "adaptive layout bound"),
        (mobile_states, "Semantics(", "semantic state wrapper"),
        (mobile_states, "liveRegion:", "live state feedback"),
        (mobile_states, "ExcludeSemantics(", "decorative icon exclusion"),
        (mobile_states, "mobileStateAllowsRetry", "retry policy"),
        (mobile_tests, "'ar-KW'", "Arabic Kuwait regression"),
        (mobile_tests, "'AR_eg'", "Arabic underscore regression"),
        (mobile_tests, "SafeContractsBreakpoint.narrow", "narrow breakpoint regression"),
        (mobile_tests, "SafeContractsBreakpoint.medium", "medium breakpoint regression"),
        (mobile_tests, "SafeContractsBreakpoint.wide", "wide breakpoint regression"),
        (mobile_tests, "MobileStateKind.forbidden", "forbidden state regression"),
        (mobile_tests, "find.byType(Semantics)", "widget semantics regression"),
    )
    for source, marker, description in markers:
        if marker not in source:
            fail(f"RTL/accessibility validation is missing {description}: {marker}")
    return len(markers)


def validate_backup_restore() -> int:
    manifest = build_manifest()
    checks = validate_manifest(manifest)

    entries = registered_migrations()
    if manifest.get("migration_count") != len(entries):
        fail("backup manifest migration count drifted from Migrator registry")
    if manifest.get("schema_version") != entries[-1][0]:
        fail("backup manifest schema version is not the final registered migration")

    runbook = _read("docs/BACKUP_RESTORE_RUNBOOK.md")
    required_evidence = (
        "Backup/snapshot identifier and UTC timestamp",
        "Generated SafeContracts backup manifest",
        "Pre-backup and post-restore row counts",
        "Restored schema version",
        "Quality Gates run identifier",
        "UAT-008 result and reviewer/sign-off identity",
    )
    for marker in required_evidence:
        if marker not in runbook:
            fail(f"backup/restore acceptance evidence is missing: {marker}")
        checks += 1

    forbidden_restore_patterns = (
        "Firebase service-account JSON/private keys",
        "Environment variables or secret-manager values",
        "Database server credentials",
        "WordPress authentication salts or hosting credentials",
    )
    for marker in forbidden_restore_patterns:
        if marker not in runbook:
            fail(f"backup secret boundary is missing: {marker}")
        checks += 1
    return checks + 2


def validate_migration_upgrade() -> int:
    source = _read("wordpress-plugin/safecontracts/src/Database/Migrator.php")
    entries = registered_migrations()
    if len(entries) < 12:
        fail("migration chain is shorter than the production baseline")

    expected_versions = [f"1.{minor}.0" for minor in range(len(entries))]
    versions = [version for version, _ in entries]
    if versions != expected_versions:
        fail(
            "migration chain must be contiguous from 1.0.0 "
            f"(expected {expected_versions}, got {versions})"
        )

    checks = 2
    for position, (version, migration_class) in enumerate(entries, start=1):
        class_match = MIGRATION_NUMBER_PATTERN.fullmatch(migration_class)
        if class_match is None or int(class_match.group(1)) != position:
            fail(
                "migration class numbering is not aligned with registry order: "
                f"{version} -> {migration_class}"
            )
        path = (
            ROOT
            / "wordpress-plugin/safecontracts/src/Database/Migrations"
            / f"{migration_class}.php"
        )
        migration_source = path.read_text(encoding="utf-8")
        migration_contract = re.compile(
            rf"final\s+class\s+{re.escape(migration_class)}\s+implements\s+(?:Migration|ProductionMigration)\b"
        )
        if migration_contract.search(migration_source) is None:
            fail(f"migration contract changed: {migration_class}")
        checks += 2

    latest_match = LATEST_VERSION_PATTERN.search(source)
    if latest_match is None or latest_match.group(1) != versions[-1]:
        fail("Migrator::LATEST_VERSION is not bound to the final registered migration")
    checks += 1

    for marker in (
        "version_compare($current, self::LATEST_VERSION, '<')",
        "version_compare($current, $version, '>=')",
        "update_option(self::VERSION_OPTION, $version, false)",
    ):
        if marker not in source:
            fail(f"migration upgrade/idempotence marker is missing: {marker}")
        checks += 1

    runtime_test = _read("tests/php/p10_release_readiness_011_016.php")
    if runtime_test.count("$migrator->migrate();") < 2:
        fail("migration runtime regression no longer tests repeated migration")
    if "latest-version migration is idempotent on a second run" not in runtime_test:
        fail("migration runtime regression no longer asserts idempotence")
    return checks + 2


def validate_uat_scenarios() -> int:
    payload = json.loads(_read("ops/uat-scenarios.json"))
    if payload.get("schema_version") != 1:
        fail("UAT schema version changed without validation update")
    scenarios = payload.get("scenarios")
    if not isinstance(scenarios, list):
        fail("UAT scenarios must be a list")

    by_id: dict[str, dict[str, object]] = {}
    checks = 2
    for scenario in scenarios:
        if not isinstance(scenario, dict):
            fail("each UAT scenario must be an object")
        scenario_id = scenario.get("id")
        if not isinstance(scenario_id, str) or not re.fullmatch(r"UAT-\d{3}", scenario_id):
            fail(f"invalid UAT scenario id: {scenario_id!r}")
        if scenario_id in by_id:
            fail(f"duplicate UAT scenario id: {scenario_id}")
        by_id[scenario_id] = scenario

        role = scenario.get("role")
        if not isinstance(role, str) or role not in ALLOWED_UAT_ROLES:
            fail(f"UAT scenario {scenario_id} uses unknown role: {role!r}")
        for field in ("preconditions", "steps", "expected", "evidence"):
            values = scenario.get(field)
            if not isinstance(values, list) or not values:
                fail(f"UAT scenario {scenario_id} has no {field}")
            if any(not isinstance(value, str) or not value.strip() for value in values):
                fail(f"UAT scenario {scenario_id} contains invalid {field}")
            checks += 1
        evidence = scenario.get("evidence")
        if not isinstance(evidence, list) or len(evidence) < 2:
            fail(f"UAT scenario {scenario_id} needs at least two evidence items")
        checks += 3

    missing = sorted(set(BASELINE_UAT) - set(by_id))
    if missing:
        fail("UAT production baseline is missing scenarios: " + ", ".join(missing))

    for scenario_id, (expected_role, expected_flow) in BASELINE_UAT.items():
        scenario = by_id[scenario_id]
        if scenario.get("role") != expected_role:
            fail(f"{scenario_id} role changed from the production baseline")
        if scenario.get("flow") != expected_flow:
            fail(f"{scenario_id} flow changed from the production baseline")
        checks += 2

    restore_evidence = by_id["UAT-008"].get("evidence")
    restore_text = " | ".join(str(value).lower() for value in restore_evidence or [])
    for marker in ("backup manifest", "row counts", "schema version", "quality gates run id"):
        if marker not in restore_text:
            fail(f"UAT-008 restore evidence is missing: {marker}")
        checks += 1
    return checks


def validate_ci_wiring() -> int:
    workflow = _read(".github/workflows/quality-gates.yml")
    foundation = _read("scripts/validate-foundation.py")
    command = "python3 scripts/p10_validation_027_031.py --check"
    if command not in workflow:
        fail("Quality Gates do not execute P10 final validation")
    if command not in foundation:
        fail("repository standards do not protect P10 final validation wiring")
    if "needs: [repository-standards, backend-foundation, mobile-foundation]" not in workflow:
        fail("P10 final validation is not downstream of all primary quality gates")
    return 3


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true")
    args = parser.parse_args()

    sections = {
        "SC-P10-027 audit": validate_audit_completeness(),
        "SC-P10-028 rtl_accessibility": validate_rtl_accessibility(),
        "SC-P10-029 backup_restore": validate_backup_restore(),
        "SC-P10-030 migration_upgrade": validate_migration_upgrade(),
        "SC-P10-031 uat": validate_uat_scenarios(),
        "ci_wiring": validate_ci_wiring(),
    }
    total = sum(sections.values())
    if args.check:
        print(
            "SafeContracts P10 validation SC-P10-027..031 passed "
            f"({total} checks across {len(sections)} sections)."
        )
    else:
        print(json.dumps({"checks": total, "sections": sections}, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
