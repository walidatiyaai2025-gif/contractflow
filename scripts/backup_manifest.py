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

EXTERNAL_SECRET_EXCLUSIONS = (
    "firebase service-account JSON/private key material",
    "environment variables and secret-manager values",
    "database server credentials",
    "WordPress authentication salts and hosting credentials",
)


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    raise SystemExit(1)


def _latest_schema_version() -> str:
    source = MIGRATOR.read_text(encoding="utf-8")
    match = LATEST_VERSION_PATTERN.search(source)
    if match is None:
        fail("Migrator.php does not expose LATEST_VERSION")
    return match.group(1)


def _owned_tables() -> list[str]:
    tables: set[str] = set()
    migration_files = sorted(MIGRATION_DIR.glob("Migration*.php"))
    if not migration_files:
        fail("no SafeContracts migrations were found")
    for path in migration_files:
        source = path.read_text(encoding="utf-8")
        tables.update(TABLE_PATTERN.findall(source))
    return sorted(tables)


def build_manifest() -> dict[str, object]:
    """Return the production backup scope without reading live credentials."""
    migration_files = sorted(MIGRATION_DIR.glob("Migration*.php"))
    return {
        "manifest_version": 1,
        "schema_version": _latest_schema_version(),
        "migration_count": len(migration_files),
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
    checks += 1

    migration_count = manifest.get("migration_count")
    if not isinstance(migration_count, int) or migration_count < 12:
        fail("backup manifest does not cover the full migration chain")
    checks += 1

    tables = manifest.get("tables")
    if not isinstance(tables, list) or len(tables) < 12:
        fail("backup manifest contains too few SafeContracts-owned tables")
    if tables != sorted(set(tables)):
        fail("backup table list must be unique and deterministic")
    for table in tables:
        if not isinstance(table, str) or not re.fullmatch(r"safecontracts_[a-z0-9_]+", table):
            fail(f"invalid SafeContracts backup table name: {table!r}")
        checks += 1

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
