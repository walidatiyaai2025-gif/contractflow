#!/usr/bin/env python3
from pathlib import Path
import sys


def pre() -> None:
    p = Path('scripts/alkenzy_mobile_user_final_patch.py')
    text = p.read_text()
    old = '''insert_before(
    path,
    "  @override\\n  Widget build(BuildContext context) {\\n",
    "  Future<void> _offerBiometricEnrollment() async {\\n"'''
    new = '''insert_before(
    path,
    "  @override\\n  Widget build(BuildContext context) {\\n    final l10n = context.scL10n;\\n    if (_bootstrapping) {\\n",
    "  Future<void> _offerBiometricEnrollment() async {\\n"'''
    if old not in text:
        raise SystemExit('Unable to normalize unique login state build marker.')
    text = text.replace(old, new, 1)
    replacements = [
        (
            'replace_once(path, "        backgroundColor: SafeContractsVisual.navy,", "        backgroundColor: palette.primary,")',
            'write(path, read(path).replace("        backgroundColor: SafeContractsVisual.navy,", "        backgroundColor: palette.primary,", 1))',
        ),
        (
            'replace_once(path, "          foregroundColor: SafeContractsVisual.navy,", "          foregroundColor: palette.primary,")',
            'write(path, read(path).replace("          foregroundColor: SafeContractsVisual.navy,", "          foregroundColor: palette.primary,", 1))',
        ),
    ]
    for old_marker, new_marker in replacements:
        if old_marker not in text:
            raise SystemExit(f'Unable to normalize theme marker: {old_marker}')
        text = text.replace(old_marker, new_marker, 1)
    p.write_text(text)


def post() -> None:
    reports = Path('mobile/lib/features/reports/reports_screen.dart')
    text = reports.read_text()
    marker = ".map((row) => Map<String, Object?>.from(row))"
    if text.count(marker) == 2:
        text = text.replace(
            marker,
            ".map((row) => apiObjectMap(row, 'reports.${definition.id}.row'))",
        )
        reports.write_text(text)
    elif marker in text:
        raise SystemExit('Unexpected reports row mapping count.')

    guide = Path('mobile/lib/features/help/mobile_user_guide_screen.dart')
    text = guide.read_text()
    if 'MobileDestination.reports => const _GuideEntry(' not in text:
        marker = "    MobileDestination.profile => const _GuideEntry("
        if marker not in text:
            raise SystemExit('Profile guide marker missing.')
        addition = """    MobileDestination.reports => const _GuideEntry(
        destination: MobileDestination.reports,
        title: 'Reports',
        icon: Icons.assessment_outlined,
        purpose:
            'Reports provides authorized printable views of customers, contracts, payments and operational data.',
        steps: <String>[
          'Choose the report that matches the business data you need.',
          'Review the loaded rows, then export them as PDF, Word or Excel.',
        ],
      ),
"""
        guide.write_text(text.replace(marker, addition + marker, 1))


if __name__ == '__main__':
    if len(sys.argv) != 2 or sys.argv[1] not in {'pre', 'post'}:
        raise SystemExit('Usage: alkenzy_mobile_final_prepare.py pre|post')
    if sys.argv[1] == 'pre':
        pre()
    else:
        post()
