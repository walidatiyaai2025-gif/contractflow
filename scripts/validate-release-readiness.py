#!/usr/bin/env python3
"""Fail-closed SafeContracts V1 release-evidence validator.

This validator inspects repository contracts and file presence only. It never reads
runtime environment variables, WordPress option values, credentials, tokens, or
external secret-manager content.
"""

from __future__ import annotations

import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ERRORS: list[str] = []


def require_file(relative: str) -> Path:
    path = ROOT / relative
    if not path.is_file():
        ERRORS.append(f"missing required release file: {relative}")
    return path


def read_contract(relative: str) -> str:
    path = require_file(relative)
    if not path.is_file():
        return ""
    return path.read_text(encoding="utf-8")


def semver_key(value: str) -> tuple[int, int, int]:
    parts = value.split(".")
    if len(parts) != 3 or not all(part.isdigit() for part in parts):
        raise ValueError(value)
    return tuple(int(part) for part in parts)  # type: ignore[return-value]


REQUIRED_DOCS = [
    "docs/ENVIRONMENT.md",
    "docs/API_V1.md",
    "docs/RECOVERY_RUNBOOK.md",
    "docs/UAT_V1.md",
    "docs/P10_HARDENING_011_020.md",
]
for document in REQUIRED_DOCS:
    require_file(document)

for script in [
    "scripts/test-php.sh",
    "scripts/validate-foundation.py",
    "scripts/validate-release-readiness.py",
]:
    require_file(script)

for test_file in [
    "tests/php/p10_security_financial_001_005.php",
    "tests/php/p10_ops_verification_006_010.php",
    "tests/php/p10_release_hardening_011_016.php",
    "tests/php/p10_validation_017_020.php",
    "mobile/test/p10_rtl_accessibility_012_test.dart",
]:
    require_file(test_file)

migrator = read_contract("wordpress-plugin/safecontracts/src/Database/Migrator.php")
latest_match = re.search(r"LATEST_VERSION\s*=\s*'([^']+)'", migrator)
versions = re.findall(r"'([0-9]+\.[0-9]+\.[0-9]+)'\s*=>\s*Migration", migrator)
if latest_match is None or not versions:
    ERRORS.append("Migrator.php: unable to resolve current migration contract")
else:
    latest = latest_match.group(1)
    try:
        highest = max(versions, key=semver_key)
    except ValueError:
        ERRORS.append("Migrator.php: invalid semantic migration version")
    else:
        if latest != highest:
            ERRORS.append(
                f"Migrator.php: LATEST_VERSION {latest} does not match highest migration {highest}"
            )

recovery = read_contract("wordpress-plugin/safecontracts/src/Support/RecoveryManifest.php")
for table in [
    "safecontracts_contracts",
    "safecontracts_scheduled_payments",
    "safecontracts_payment_collections",
    "safecontracts_audit_log",
    "safecontracts_notification_deliveries",
    "safecontracts_import_runs",
]:
    if f"'{table}'" not in recovery:
        ERRORS.append(f"RecoveryManifest.php: missing critical table contract {table}")
for option in [
    "safecontracts_db_version",
    "safecontracts_db_migrated_at",
    "safecontracts_installed_at",
    "safecontracts_plugin_version",
    "safecontracts_general_settings",
    "safecontracts_mobile_configuration",
    "safecontracts_firebase_public_config",
    "safecontracts_firebase_credential_reference",
    "safecontracts_notification_read_ids",
]:
    if f"'{option}'" not in recovery:
        ERRORS.append(f"RecoveryManifest.php: missing recovery key {option}")

runner = read_contract("scripts/test-php.sh")
for php_test in [
    "p10_security_financial_001_005.php",
    "p10_ops_verification_006_010.php",
    "p10_release_hardening_011_016.php",
    "p10_validation_017_020.php",
]:
    if php_test not in runner:
        ERRORS.append(f"scripts/test-php.sh: does not execute {php_test}")

workflow = read_contract(".github/workflows/quality-gates.yml")
for command in [
    "python3 scripts/validate-foundation.py",
    "python3 scripts/validate-release-readiness.py",
    "./scripts/test-php.sh",
    "dart format lib test",
    "flutter analyze",
    "flutter test",
]:
    if command not in workflow:
        ERRORS.append(f"quality-gates.yml: missing release command {command}")

uat = read_contract("docs/UAT_V1.md")
for scenario in [
    "UAT-ADMIN-01",
    "UAT-MANAGER-01",
    "UAT-ACCOUNTANT-01",
    "UAT-VIEWER-01",
    "UAT-COLLECTION-01",
    "UAT-NOTIFY-01",
    "UAT-IMPORT-01",
    "UAT-EXPORT-01",
    "UAT-RECOVERY-01",
]:
    if scenario not in uat:
        ERRORS.append(f"docs/UAT_V1.md: missing scenario {scenario}")

for evidence_file in ["docs/RECOVERY_RUNBOOK.md", "docs/UAT_V1.md", "docs/P10_HARDENING_011_020.md"]:
    content = read_contract(evidence_file)
    for placeholder in ["TODO", "TBD", "<REPLACE_ME>"]:
        if placeholder in content:
            ERRORS.append(f"{evidence_file}: release-blocking placeholder {placeholder} remains")

if ERRORS:
    print("SafeContracts release readiness FAILED:")
    for error in ERRORS:
        print(f"- {error}")
    sys.exit(1)

print("SafeContracts release readiness evidence passed.")
