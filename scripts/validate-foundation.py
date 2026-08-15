#!/usr/bin/env python3
"""Validate SafeContracts P0 repository, mobile and secret-safety conventions."""

from __future__ import annotations

import json
from pathlib import Path
import re
import subprocess
import sys

ROOT = Path(__file__).resolve().parents[1]

REQUIRED_PATHS = (
    "wordpress-plugin/safecontracts/safecontracts.php",
    "wordpress-plugin/safecontracts/src/Plugin.php",
    "tests/php/run.php",
    "docs/MASTER_PLAN.md",
    "docs/DEVELOPMENT_STANDARDS.md",
    "docs/ENVIRONMENT.md",
    "docs/PRODUCTION_ENVIRONMENT_BUILD.md",
    "AGENTS.md",
    "Last verified Plugin/README.md",
    "Last verified apk/README.md",
    "scripts/verified_artifacts.py",
    "scripts/package_plugin.py",
    "scripts/bootstrap_android.sh",
    "scripts/verify_production_health.py",
    "mobile/android-release/README.md",
    "mobile/android-release/app-build.gradle.kts",
    "mobile/pubspec.yaml",
    "mobile/analysis_options.yaml",
    "mobile/lib/main.dart",
    "mobile/lib/app.dart",
    "mobile/lib/core/config/app_environment.dart",
    "mobile/test/app_environment_test.dart",
    "mobile/config/local.example.json",
    ".github/workflows/quality-gates.yml",
    ".github/workflows/retain-verified-plugin.yml",
    "scripts/p10_validation_027_031.py",
    "docs/P10_FINAL_VALIDATION_027_031.md",
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

PHP_NAMESPACE = re.compile(
    r"^namespace SafeContracts(?:\\[A-Za-z_][A-Za-z0-9_]*)*;$",
    re.MULTILINE,
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
        if PHP_NAMESPACE.search(content) is None:
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

    android_template = (ROOT / "mobile/android-release/app-build.gradle.kts").read_text(encoding="utf-8")
    for marker in (
        "com.safecontracts.safecontracts_mobile",
        "SC_ANDROID_KEYSTORE_PATH",
        "SC_ANDROID_KEYSTORE_PASSWORD",
        "SC_ANDROID_KEY_ALIAS",
        "SC_ANDROID_KEY_PASSWORD",
        "Never fall back to debug signing",
        "compilerOptions",
    ):
        if marker not in android_template:
            fail(f"Android release template missing marker: {marker}")
    if 'id("kotlin-android")' in android_template:
        fail("Android release template must use Flutter/AGP built-in Kotlin, not kotlin-android")

    android_bootstrap = (ROOT / "scripts/bootstrap_android.sh").read_text(encoding="utf-8")
    for marker in (
        "android.permission.INTERNET",
        "Android release manifest is missing INTERNET permission",
    ):
        if marker not in android_bootstrap:
            fail(f"Android release bootstrap missing network marker: {marker}")
    return 14


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
        "dart format lib test",
        "git diff --exit-code -- lib test",
        "flutter analyze",
        "flutter test",
        "release-readiness:",
        "python3 scripts/backup_manifest.py --check",
        "python3 scripts/release_readiness.py --check",
        "python3 scripts/p10_validation_027_031.py --check",
        "release-candidates:",
        "SC_PRODUCTION_API_BASE_URL: https://cms.50sols.com/wp-json/safecontracts/v1/",
        "python3 scripts/verify_production_health.py",
        "python3 scripts/package_plugin.py build",
        "python3 scripts/package_plugin.py check",
        "bash scripts/bootstrap_android.sh",
        "flutter build apk --release",
        '--dart-define=SC_API_BASE_URL="$SC_PRODUCTION_API_BASE_URL"',
        "apksigner",
        "AAPT=",
        '"$AAPT" dump permissions "$APK"',
        "android.permission.INTERNET",
        "safecontracts-release-candidates",
    )
    missing = [command for command in required_commands if command not in workflow]
    if missing:
        fail("quality-gates workflow missing commands: " + ", ".join(missing))
    if "release-candidate.invalid" in workflow:
        fail("quality-gates must not build the active APK candidate against the obsolete .invalid endpoint")

    health_script = (ROOT / "scripts/verify_production_health.py").read_text(encoding="utf-8")
    for marker in (
        "/wp-json/safecontracts/v1/",
        '"service": "SafeContracts"',
        '"status": "ok"',
        '"api_version": "v1"',
        "def same_origin(",
        "rest_route",
    ):
        if marker not in health_script:
            fail(f"production health verifier missing marker: {marker}")

    retain = (ROOT / ".github/workflows/retain-verified-plugin.yml").read_text(encoding="utf-8")
    retain_markers = (
        'workflows: ["Quality Gates"]',
        "branches: [main]",
        "contents: write",
        "github.event.workflow_run.conclusion == 'success'",
        "python3 scripts/package_plugin.py build",
        "python3 scripts/verified_artifacts.py publish-plugin",
        "python3 scripts/verified_artifacts.py check --require-plugin",
        "git push origin HEAD:main",
    )
    missing_retain = [marker for marker in retain_markers if marker not in retain]
    if missing_retain:
        fail("verified plugin retention workflow missing markers: " + ", ".join(missing_retain))
    return len(required_commands) + 6 + len(retain_markers) + 1


def validate_artifact_policy() -> int:
    command = [sys.executable, str(ROOT / "scripts/verified_artifacts.py"), "check"]
    result = subprocess.run(command, cwd=ROOT, capture_output=True, text=True, check=False)
    if result.returncode != 0:
        detail = (result.stderr or result.stdout).strip()
        fail("verified artifact policy failed" + (f": {detail}" if detail else ""))
    return 1


def validate_plugin_packager() -> int:
    output = ROOT / "dist" / "foundation-plugin-check.zip"
    output.parent.mkdir(parents=True, exist_ok=True)
    command = [
        sys.executable,
        str(ROOT / "scripts/package_plugin.py"),
        "build",
        "--output",
        str(output),
    ]
    result = subprocess.run(command, cwd=ROOT, capture_output=True, text=True, check=False)
    if result.returncode != 0:
        detail = (result.stderr or result.stdout).strip()
        fail("deterministic plugin packaging failed" + (f": {detail}" if detail else ""))
    output.unlink(missing_ok=True)
    return 1


def main() -> int:
    checks = 0
    checks += validate_required_paths()
    checks += validate_gitignore()
    checks += validate_php_standards()
    checks += validate_mobile_boundary()
    checks += validate_example_config()
    checks += validate_ci_contract()
    checks += validate_artifact_policy()
    checks += validate_plugin_packager()
    print(f"SafeContracts foundation validation passed ({checks} checks).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
