#!/usr/bin/env python3
"""Validate ALKENZY ADV's canonical version and forward-only release lineage."""

from __future__ import annotations

import os
import re
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
PLUGIN_PATH = "wordpress-plugin/safecontracts/safecontracts.php"
README_PATH = "wordpress-plugin/safecontracts/readme.txt"
MOBILE_PATH = "mobile/pubspec.yaml"
BASELINE_PATH = "docs/mobile-redesign/ALKENZY_ADV_RELEASE_BASELINE.md"
CONSTITUTION_PATH = "docs/plugin-redesign/PLUGIN_UI_CONSTITUTION.md"
AGENTS_PATH = "AGENTS.md"
FUNCTIONAL_SOURCE = "9171f1c357822f9118eb8058aab6fb145c475fc3"
APPROVED_BASELINE_VERSION = "0.3.6"
APPROVED_BASELINE_BUILD = 10
RELEASE_PREFIXES = (
    "assets/design/",
    "mobile/",
    "wordpress-plugin/safecontracts/",
    "wordpress-theme/",
)
RELEASE_FILES = {
    ".github/workflows/quality-gates.yml",
    "scripts/bootstrap_android.sh",
    "scripts/package_plugin.py",
}


class VersionPolicyError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise VersionPolicyError(message)


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def match(pattern: str, text: str, label: str, flags: int = 0) -> str:
    found = re.search(pattern, text, flags)
    if found is None:
        fail(f"unable to read {label}")
    return found.group(1)


def semver(value: str) -> tuple[int, int, int]:
    parts = value.split(".")
    if len(parts) != 3 or any(not part.isdigit() for part in parts):
        fail(f"invalid semantic version: {value}")
    return tuple(int(part) for part in parts)  # type: ignore[return-value]


def plugin_version(text: str) -> str:
    header = match(r"^\s*\*\s*Version:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$", text, "plugin header version", re.MULTILINE)
    constant = match(r"define\('SAFECONTRACTS_VERSION',\s*'([0-9]+\.[0-9]+\.[0-9]+)'\);", text, "plugin runtime version")
    if header != constant:
        fail(f"plugin header {header} and SAFECONTRACTS_VERSION {constant} disagree")
    return header


def mobile_version(text: str) -> tuple[str, int]:
    found = re.search(r"^version:\s*([0-9]+\.[0-9]+\.[0-9]+)\+([0-9]+)\s*$", text, re.MULTILINE)
    if found is None:
        fail("unable to read mobile version/build")
    return found.group(1), int(found.group(2))


def git(*args: str) -> str:
    result = subprocess.run(
        ["git", *args],
        cwd=ROOT,
        check=False,
        capture_output=True,
        text=True,
    )
    if result.returncode != 0:
        fail(result.stderr.strip() or f"git {' '.join(args)} failed")
    return result.stdout


def git_show(commit: str, path: str) -> str:
    return git("show", f"{commit}:{path}")


def validate_current() -> tuple[str, int, int]:
    checks = 0
    plugin = plugin_version(read(PLUGIN_PATH))
    mobile, build = mobile_version(read(MOBILE_PATH))
    if plugin != mobile:
        fail(f"plugin {plugin} and mobile {mobile}+{build} must share one product version")
    checks += 3

    stable = match(r"^Stable tag:\s*([0-9]+\.[0-9]+\.[0-9]+)\s*$", read(README_PATH), "plugin stable tag", re.MULTILINE)
    if stable != plugin:
        fail(f"plugin stable tag {stable} does not match {plugin}")
    checks += 1

    baseline_full = f"{APPROVED_BASELINE_VERSION}+{APPROVED_BASELINE_BUILD}"
    required_markers = {
        BASELINE_PATH: (f"Approved unified release: `{baseline_full}`", f"Exact approved functional source commit: `{FUNCTIONAL_SOURCE}`"),
        CONSTITUTION_PATH: (f"APPROVED PRODUCT RELEASE: {baseline_full}", f"APPROVED FUNCTIONAL SOURCE: {FUNCTIONAL_SOURCE}"),
        AGENTS_PATH: (f"release is **`{baseline_full}`**", f"exact approved functional source commit `{FUNCTIONAL_SOURCE}`"),
    }
    for path, markers in required_markers.items():
        content = read(path)
        for marker in markers:
            if marker not in content:
                fail(f"{path} is missing approved-release marker: {marker}")
            checks += 1

    shell = read("wordpress-plugin/safecontracts/src/Admin/AdminShell.php")
    for marker in ("add_filter('admin_footer_text'", "add_filter('update_footer'", "SAFECONTRACTS_VERSION"):
        if marker not in shell:
            fail(f"approved version footer is missing: {marker}")
        checks += 1

    git("merge-base", "--is-ancestor", FUNCTIONAL_SOURCE, "HEAD")
    checks += 1
    if semver(plugin) < semver(APPROVED_BASELINE_VERSION) or build < APPROVED_BASELINE_BUILD:
        fail(f"current candidate {plugin}+{build} regresses approved baseline {baseline_full}")
    checks += 2
    return plugin, build, checks


def validate_forward_only(current_plugin: str, current_build: int) -> int:
    base = os.environ.get("ALKENZY_RELEASE_BASE_SHA", "").strip()
    if not base or set(base) == {"0"}:
        return 0

    git("cat-file", "-e", f"{base}^{{commit}}")
    changed = [line for line in git("diff", "--name-only", f"{base}...HEAD").splitlines() if line]
    release_changes = [
        path for path in changed
        if path in RELEASE_FILES or any(path.startswith(prefix) for prefix in RELEASE_PREFIXES)
    ]
    if not release_changes:
        return 1

    base_plugin = plugin_version(git_show(base, PLUGIN_PATH))
    base_mobile, base_build = mobile_version(git_show(base, MOBILE_PATH))
    if semver(current_plugin) <= semver(base_plugin):
        fail(f"production changes require plugin version above base {base_plugin}")
    if semver(current_plugin) <= semver(base_mobile):
        fail(f"production changes require mobile version above base {base_mobile}+{base_build}")
    if current_build <= base_build:
        fail(f"production changes require mobile build above base build {base_build}")
    return 4


def main() -> int:
    try:
        plugin, build, checks = validate_current()
        checks += validate_forward_only(plugin, build)
    except VersionPolicyError as exc:
        print(f"FAIL: {exc}")
        return 1
    print(f"ALKENZY ADV release version policy passed ({checks} checks, {plugin}+{build}).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
