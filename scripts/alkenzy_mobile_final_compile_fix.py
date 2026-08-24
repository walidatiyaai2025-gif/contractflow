#!/usr/bin/env python3
from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{path}: expected 1 match, got {count}: {old!r}')
    p.write_text(text.replace(old, new, 1))


replace_once(
    'mobile/lib/features/reports/report_printing.dart',
    '      actionsAlignment: MainAxisAlignment.stretch,',
    '      actionsAlignment: MainAxisAlignment.start,',
)
replace_once(
    'mobile/lib/features/reports/report_printing.dart',
    "  final sheet = workbook.rename(defaultSheet ?? 'Sheet1', safeName);\n  sheet.appendRow(",
    "  final originalSheet = defaultSheet ?? 'Sheet1';\n"
    "  if (originalSheet != safeName) {\n"
    "    workbook.rename(originalSheet, safeName);\n"
    "  }\n"
    "  final sheet = workbook[safeName];\n"
    "  sheet.appendRow(",
)
replace_once(
    'mobile/lib/features/ui/safecontracts_theme.dart',
    '          borderSide: const BorderSide(color: palette.accent, width: 1.8),',
    '          borderSide: BorderSide(color: palette.accent, width: 1.8),',
)

# Clean only infos introduced by this feature set.
for path, marker in [
    ('mobile/lib/features/reports/report_printing.dart', "import 'package:cross_file/cross_file.dart';\n"),
    ('mobile/lib/features/ui/theme_palette.dart', "import 'package:flutter/foundation.dart';\n"),
]:
    p = Path(path)
    text = p.read_text()
    if marker in text:
        p.write_text(text.replace(marker, '', 1))
