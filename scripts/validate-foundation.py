#!/usr/bin/env python3
"""Validate SafeContracts P0 repository, mobile and secret-safety conventions."""

from __future__ import annotations

import json
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]

REQUIRED_PATHS = (
    "wordpress-plugin/safecontracts/safecontracts.php",
    "wordpress-plugin/safecontracts/src/Plugin.php",
    "tests/php/run.php",
    "docs/MASTER_PLAN.md",
    "docs/DEVELOPMENT_STANDARDS.md",
    "docs/ENVIRONMENT.md",
    "mobile/pubspec.yaml",
    "mobile/analysis_options.yaml",
    "mobile/lib/main.dart",
    "mobile/lib/app.dart",
    "mobile/lib/core/config/app_environment.dart",
    "mobile/test/app_environment_test.dart",
    "mobile/config/local.example.json",
    ".github/workflows/quality-gates.yml",
)

REQUIRED_GITIGNORE_ENTRIES = (
    ".env",
    ".env.*",
    "mobile/.dart_tool/",
    "mobile/build/",
    "mobile/config/*.json",
    "!mobile/config/*.example.json",
)

FORBIDDEN_SECRET_KEY = re.compile(
    r"(password|passwd|secret|private[_-]?key|access[_-]?token|refresh[_-]?token|client[_-]?secret)",
    re.IGNORECASE,
)


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    raise SystemExit(1)


def validate_required_paths() -> int:
    missing = [path for path in REQUIRED_PATHS if not (ROOT / path).exists()]
    if missing:
        fail("missing required foundation paths: " + ", ".join(missing))
    return len(REQUIRED_PATHS)


def validate_gitignore() -> int:
    content = (ROOT / ".gitignore").read_text(encoding="utf-8")
    missing = [entry for entry in REQUIRED_GITIGNORE_ENTRIES if entry not in content]
    if missing:
        fail(".gitignore missing safety entries: " + ", ".join(missing))
    return len(REQUIRED_GITIGNORE_ENTRIES)


def validate_php_standards() -> int:
    source_files = sorted((ROOT / "wordpress-plugin/safecontracts/src").rglob("*.php"))
    if not source_files:
        fail("no SafeContracts PHP source files found")

    checks = 0
    for path in source_files:
        content = path.read_text(encoding="utf-8")
        if "declare(strict_types=1);" not in content:
            fail(f"PHP source must declare strict types: {path.relative_to(ROOT)}")
        if "namespace SafeContracts\\" not in content:
            fail(f"PHP source must use SafeContracts namespace: {path.relative_to(ROOT)}")
        checks += 2

    entry = (ROOT / "wordpress-plugin/safecontracts/safecontracts.php").read_text(encoding="utf-8")
    if "if (! defined('ABSPATH'))" not in entry:
        fail("plugin entry file must guard direct execution")
    return checks + 1


def validate_mobile_boundary() -> int:
    pubspec = (ROOT / "mobile/pubspec.yaml").read_text(encoding="utf-8")
    if "sdk: flutter" not in pubspec:
        fail("mobile foundation must declare Flutter SDK dependency")

    environment = (ROOT / "mobile/lib/core/config/app_environment.dart").read_text(encoding="utf-8")
    for marker in ("SC_ENV", "SC_API_BASE_URL", "Production SafeContracts API must use HTTPS"):
        if marker not in environment:
            fail(f"mobile environment foundation missing marker: {marker}")
    return 4


def validate_example_config() -> int:
    checks = 0
    for path in sorted((ROOT / "mobile/config").glob("*.example.json")):
        payload = json.loads(path.read_text(encoding="utf-8"))
        if not isinstance(payload, dict):
            fail(f"example config must be a JSON object: {path.relative_to(ROOT)}")
        for key, value in payload.items():
            if FORBIDDEN_SECRET_KEY.search(str(key)):
                fail(f"secret-like key is forbidden in committed example config: {key}")
            if not isinstance(value, (str, int, float, bool)) and value is not None:
                fail(f"example config contains unsupported value type for {key}")
            checks += 1
    if checks == 0:
        fail("at least one mobile example config value is required")
    return checks


def validate_ci_contract() -> int:
    workflow = (ROOT / ".github/workflows/quality-gates.yml").read_text(encoding="utf-8")
    required_commands = (
        "python3 scripts/validate-foundation.py",
        "./scripts/test-php.sh",
        "dart format --output=none --set-exit-if-changed lib test",
        "flutter analyze",
        "flutter test",
    )
    missing = [command for command in required_commands if command not in workflow]
    if missing:
        fail("quality-gates workflow missing commands: " + ", ".join(missing))
    return len(required_commands)


def main() -> int:
    checks = 0
    checks += validate_required_paths()
    checks += validate_gitignore()
    checks += validate_php_standards()
    checks += validate_mobile_boundary()
    checks += validate_example_config()
    checks += validate_ci_contract()
    print(f"SafeContracts foundation validation passed ({checks} checks).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
