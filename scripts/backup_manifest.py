#!/usr/bin/env python3
"""Build and validate the SafeContracts backup/restore data manifest."""

from __future__ import annotations

import argparse
import json
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
MIGRATION_DIR = ROOT / "wordpress-plugin/safecontracts/src/Database/Migrations"
MIGRATOR = ROOT / "wordpress-plugin/safecontracts/src/Database/Migrator.php"

TABLE_PATTERN = re.compile(
    r"\$wpdb->prefix\s*\.\s*['\"](safecontracts_[a-z0-9_]+)['\"]"
)
LATEST_VERSION_PATTERN = re.compile(
    r"public const LATEST_VERSION\s*=\s*['\"]([^'\"]+)['\"]\s*;"
)
MIGRATION_ENTRY_PATTERN = re.compile(
    r"['\"](\d+\.\d+\.\d+)['\"]\s*=>\s*(Migration\d+[A-Za-z0-9_]*)::class"
)

EXTERNAL_SECRET_EXCLUSIONS = (
    "firebase service-account JSON/private key material",
    "environment variables and secret-manager values",
    "database server credentials",
    "WordPress authentication salts and hosting credentials",
)


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    raise SystemExit(1)


def _migrator_source() -> str:
    if not MIGRATOR.exists():
        fail("Migrator.php is missing")
    return MIGRATOR.read_text(encoding="utf-8")


def _latest_schema_version() -> str:
    source = _migrator_source()
    match = LATEST_VERSION_PATTERN.search(source)
    if match is None:
        fail("Migrator.php does not expose LATEST_VERSION")
    return match.group(1)


def registered_migrations() -> list[tuple[str, str]]:
    """Return the ordered migration registry used by Migrator itself."""
    entries = MIGRATION_ENTRY_PATTERN.findall(_migrator_source())
    if not entries:
        fail("Migrator.php does not register any migrations")
    versions = [version for version, _ in entries]
    classes = [migration_class for _, migration_class in entries]
    if len(versions) != len(set(versions)):
        fail("Migrator.php contains duplicate migration versions")
    if len(classes) != len(set(classes)):
        fail("Migrator.php contains duplicate migration classes")
    return entries


def _registered_migration_sources() -> list[Path]:
    entries = registered_migrations()
    registered_classes = {migration_class for _, migration_class in entries}
    actual_classes = {path.stem for path in MIGRATION_DIR.glob("Migration*.php")}
    if actual_classes != registered_classes:
        missing = sorted(registered_classes - actual_classes)
        orphaned = sorted(actual_classes - registered_classes)
        details = []
        if missing:
            details.append("missing sources: " + ", ".join(missing))
        if orphaned:
            details.append("unregistered sources: " + ", ".join(orphaned))
        fail("migration source/registry mismatch (" + "; ".join(details) + ")")

    sources = []
    for _, migration_class in entries:
        path = MIGRATION_DIR / f"{migration_class}.php"
        if not path.exists():
            fail(f"registered migration source is missing: {migration_class}")
        sources.append(path)
    return sources


def _owned_tables() -> list[str]:
    tables: set[str] = set()
    for path in _registered_migration_sources():
        source = path.read_text(encoding="utf-8")
        tables.update(TABLE_PATTERN.findall(source))
    return sorted(tables)


def build_manifest() -> dict[str, object]:
    """Return the production backup scope without reading live credentials."""
    entries = registered_migrations()
    return {
        "manifest_version": 1,
        "schema_version": _latest_schema_version(),
        "migration_count": len(entries),
        "tables": _owned_tables(),
        "wordpress_rows": {
            "options": "option_name LIKE 'safecontracts_%'",
            "user_meta": "meta_key LIKE 'safecontracts_%'",
        },
        "external_secret_exclusions": list(EXTERNAL_SECRET_EXCLUSIONS),
    }


def validate_manifest(manifest: dict[str, object]) -> int:
    checks = 0
    if manifest.get("manifest_version") != 1:
        fail("unsupported backup manifest version")
    checks += 1

    schema_version = manifest.get("schema_version")
    if not isinstance(schema_version, str) or not re.fullmatch(r"\d+\.\d+\.\d+", schema_version):
        fail("backup manifest schema version is invalid")
    if schema_version != _latest_schema_version():
        fail("backup manifest schema version does not match Migrator::LATEST_VERSION")
    checks += 2

    migration_count = manifest.get("migration_count")
    registered_count = len(registered_migrations())
    if not isinstance(migration_count, int) or migration_count != registered_count:
        fail("backup manifest migration count does not match the registered chain")
    if registered_count < 12:
        fail("backup manifest does not cover the full migration chain")
    checks += 2

    expected_tables = _owned_tables()
    tables = manifest.get("tables")
    if not isinstance(tables, list) or len(tables) < 12:
        fail("backup manifest contains too few SafeContracts-owned tables")
    if tables != expected_tables:
        fail("backup table list does not exactly match registered migration ownership")
    if tables != sorted(set(tables)):
        fail("backup table list must be unique and deterministic")
    for table in tables:
        if not isinstance(table, str) or not re.fullmatch(r"safecontracts_[a-z0-9_]+", table):
            fail(f"invalid SafeContracts backup table name: {table!r}")
        checks += 1
    checks += 2

    rows = manifest.get("wordpress_rows")
    if not isinstance(rows, dict):
        fail("WordPress backup row selectors are missing")
    if rows.get("options") != "option_name LIKE 'safecontracts_%'":
        fail("SafeContracts option backup selector is not fail-closed")
    if rows.get("user_meta") != "meta_key LIKE 'safecontracts_%'":
        fail("SafeContracts user-meta backup selector is not fail-closed")
    checks += 2

    exclusions = manifest.get("external_secret_exclusions")
    if not isinstance(exclusions, list) or len(exclusions) < 4:
        fail("external secret exclusions are incomplete")
    lowered = " ".join(str(value).lower() for value in exclusions)
    for marker in ("private key", "environment", "database", "salt"):
        if marker not in lowered:
            fail(f"backup secret exclusion is missing marker: {marker}")
        checks += 1

    return checks


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument(
        "--check",
        action="store_true",
        help="validate the manifest and print a compact success message",
    )
    args = parser.parse_args()

    manifest = build_manifest()
    checks = validate_manifest(manifest)
    if args.check:
        print(
            "SafeContracts backup manifest verified "
            f"({len(manifest['tables'])} tables, schema {manifest['schema_version']}, {checks} checks)."
        )
    else:
        print(json.dumps(manifest, indent=2, sort_keys=True))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
