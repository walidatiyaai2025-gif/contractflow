#!/usr/bin/env python3
from __future__ import annotations

import os
import re
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MIGRATION_DIR = Path('wordpress-plugin/safecontracts/src/Database/Migrations')
MIGRATION_RE = re.compile(r'Migration(?P<num>\d{4})[^/]*\.php$')
REQUIRED_PLAN_HEADINGS = (
    '## Preconditions',
    '## Preflight',
    '## Backup and recovery checkpoint',
    '## Forward plan',
    '## Backfill and restart safety',
    '## Post-migration invariants',
    '## Rollback trigger',
    '## Rollback plan',
    '## Compatibility matrix',
    '## Destructive-change declaration',
)
DESTRUCTIVE_PATTERNS = (
    re.compile(r'\bDROP\s+TABLE\b', re.I),
    re.compile(r'\bDROP\s+COLUMN\b', re.I),
    re.compile(r'\bTRUNCATE\b', re.I),
    re.compile(r'\bRENAME\s+(?:TABLE|COLUMN)\b', re.I),
)


def git(*args: str) -> str:
    return subprocess.check_output(['git', *args], cwd=ROOT, text=True).strip()


def changed_files() -> list[Path]:
    base = os.environ.get('SAFE_CONTRACTS_BASE_SHA', '').strip()
    if not base:
        base = os.environ.get('GITHUB_BASE_SHA', '').strip()
    if not base:
        # Local/manual use: validate current repository contracts without diff-only rules.
        return []
    output = git('diff', '--name-only', f'{base}...HEAD')
    return [Path(line) for line in output.splitlines() if line.strip()]


def fail(errors: list[str], message: str) -> None:
    errors.append(message)


def validate_migrations(changed: list[Path], errors: list[str]) -> None:
    for path in changed:
        if path.parent != MIGRATION_DIR:
            continue
        match = MIGRATION_RE.search(path.name)
        if not match:
            fail(errors, f'Migration file has unsupported name: {path}')
            continue
        number = match.group('num')
        plan = ROOT / 'docs' / 'migrations' / f'Migration{number}.md'
        if not plan.exists():
            fail(errors, f'{path} requires rollback plan docs/migrations/Migration{number}.md')
            continue
        text = plan.read_text(encoding='utf-8')
        for heading in REQUIRED_PLAN_HEADINGS:
            if heading not in text:
                fail(errors, f'{plan.relative_to(ROOT)} missing required heading: {heading}')
        if re.search(r'## Rollback plan\s*(?:\n\s*){0,2}(?:TBD|TODO|none|n/a)\b', text, re.I):
            fail(errors, f'{plan.relative_to(ROOT)} must contain an actionable rollback plan')

        migration_text = (ROOT / path).read_text(encoding='utf-8', errors='replace')
        destructive = any(pattern.search(migration_text) for pattern in DESTRUCTIVE_PATTERNS)
        if destructive:
            declaration = re.search(
                r'## Destructive-change declaration\s*(.*?)(?:\n## |\Z)',
                text,
                re.S | re.I,
            )
            declaration_text = declaration.group(1).strip() if declaration else ''
            if not declaration_text or declaration_text.lower() == 'none':
                fail(errors, f'{path} appears destructive but its plan does not declare/justify the destructive change')
            required = ('production-owner approval', 'restore', 'compatibility')
            lowered = declaration_text.lower()
            for marker in required:
                if marker not in lowered:
                    fail(errors, f'{plan.relative_to(ROOT)} destructive declaration must include {marker!r} evidence')


def validate_permission_presentation(errors: list[str]) -> None:
    capabilities = ROOT / 'wordpress-plugin/safecontracts/src/Roles/Capabilities.php'
    presentation = ROOT / 'wordpress-plugin/safecontracts/src/Roles/CapabilityPresentation.php'
    users_page = ROOT / 'wordpress-plugin/safecontracts/src/Admin/UsersRolesPage.php'

    if not presentation.exists():
        fail(errors, 'CapabilityPresentation.php is required so internal permission codes are never the end-user label')
        return

    capability_text = capabilities.read_text(encoding='utf-8')
    presentation_text = presentation.read_text(encoding='utf-8')
    constants = re.findall(r'public const ([A-Z0-9_]+)\s*=\s*[\'\"]safecontracts_[^\'\"]+[\'\"]\s*;', capability_text)
    missing = [name for name in constants if f'Capabilities::{name} =>' not in presentation_text]
    if missing:
        fail(errors, 'Missing human-readable permission metadata for: ' + ', '.join(missing))

    page_text = users_page.read_text(encoding='utf-8')
    if '<code><?php echo esc_html($capability); ?></code>' in page_text:
        fail(errors, 'Users & Roles still exposes raw capability codes to end users')
    if "return '#' . $id" in page_text:
        fail(errors, 'Users & Roles still exposes numeric user IDs as the user-facing label')


def validate_standard(errors: list[str]) -> None:
    standard = ROOT / 'docs/PRODUCTION_CHANGE_SAFETY_STANDARD.md'
    template = ROOT / 'docs/migrations/TEMPLATE.md'
    if not standard.exists():
        fail(errors, 'Production change safety standard is missing')
    if not template.exists():
        fail(errors, 'Migration rollback template is missing')


def main() -> int:
    errors: list[str] = []
    changed = changed_files()
    validate_standard(errors)
    validate_migrations(changed, errors)
    validate_permission_presentation(errors)

    if errors:
        print('Alkenzy ADV production-change guard FAILED:')
        for error in errors:
            print(f' - {error}')
        return 1

    print('Alkenzy ADV production-change guard passed.')
    if changed:
        print(f'Validated {len(changed)} changed file(s) against production safety rules.')
    return 0


if __name__ == '__main__':
    sys.exit(main())
