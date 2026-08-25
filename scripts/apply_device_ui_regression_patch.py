from pathlib import Path


def replace_once(path: Path, old: str, new: str, label: str) -> None:
    text = path.read_text(encoding="utf-8")
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{label}: expected one match, found {count}")
    path.write_text(text.replace(old, new, 1), encoding="utf-8")


shell = Path("mobile/lib/features/navigation/app_shell.dart")
dashboard = Path("mobile/lib/features/dashboard/dashboard_screen.dart")

old_title = """            Expanded(
              child: Text.rich(
                TextSpan(
                  children: [
                    const TextSpan(
                      text: SafeContractsBrand.name,
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    TextSpan(
                      text: '  •  ${_label(l10n, _selected)}',
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.76),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ],
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
"""
new_title = """            Expanded(
              child: FittedBox(
                fit: BoxFit.scaleDown,
                alignment: AlignmentDirectional.centerStart,
                child: Text.rich(
                  TextSpan(
                    children: [
                      const TextSpan(
                        text: SafeContractsBrand.name,
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      TextSpan(
                        text: '  •  ${_label(l10n, _selected)}',
                        style: TextStyle(
                          color: Colors.white.withValues(alpha: 0.76),
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                    ],
                  ),
                  maxLines: 1,
                  overflow: TextOverflow.clip,
                ),
              ),
            ),
"""
replace_once(shell, old_title, new_title, "app bar responsive title")

old_drawer = """          ...widget.policy.destinations.map(
            (destination) => NavigationDrawerDestination(
              icon: Icon(_icon(destination)),
              selectedIcon: Icon(
                _icon(destination),
                color: SafeContractsVisual.navy,
              ),
              label: Text(_label(l10n, destination)),
            ),
          ),
"""
new_drawer = """          ...widget.policy.destinations.map(
            (destination) => NavigationDrawerDestination(
              icon: Icon(
                _icon(destination),
                color: Colors.white.withValues(alpha: 0.84),
              ),
              selectedIcon: const IconTheme(
                data: IconThemeData(color: Colors.white),
                child: SizedBox.shrink(),
              ).child == null
                  ? Icon(_icon(destination), color: Colors.white)
                  : Icon(_icon(destination), color: Colors.white),
              label: Text(
                _label(l10n, destination),
                style: TextStyle(
                  color: Colors.white.withValues(
                    alpha: destination == _selected ? 1.0 : 0.84,
                  ),
                  fontWeight: destination == _selected
                      ? FontWeight.w800
                      : FontWeight.w600,
                ),
              ),
            ),
          ),
"""
# Keep selected icon implementation simple after applying the state-aware label.
new_drawer = new_drawer.replace("""              selectedIcon: const IconTheme(\n                data: IconThemeData(color: Colors.white),\n                child: SizedBox.shrink(),\n              ).child == null\n                  ? Icon(_icon(destination), color: Colors.white)\n                  : Icon(_icon(destination), color: Colors.white),\n""", """              selectedIcon: Icon(\n                _icon(destination),\n                color: Colors.white,\n              ),\n""")
replace_once(shell, old_drawer, new_drawer, "drawer contrast")

replace_once(
    shell,
    "      drawer: NavigationDrawer(\n        backgroundColor: SafeContractsVisual.navyDeep,\n",
    "      drawer: NavigationDrawer(\n        backgroundColor: SafeContractsVisual.navyDeep,\n        indicatorColor: Colors.white.withValues(alpha: 0.14),\n",
    "drawer selected indicator",
)
replace_once(
    shell,
    "      floatingActionButtonLocation: FloatingActionButtonLocation.centerDocked,\n",
    "      floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,\n",
    "quick add placement",
)

old_kpi_host = """                SizedBox(
                  height: 70,
                  child: _CompactKpiRow(
                    kpis: overview.kpis,
                    currency: currency,
                  ),
                ),
"""
new_kpi_host = """                _CompactKpiRow(
                  kpis: overview.kpis,
                  currency: currency,
                ),
"""
replace_once(dashboard, old_kpi_host, new_kpi_host, "dashboard KPI host")

old_kpi_layout = """    return Row(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        for (var index = 0; index < items.length; index++) ...[
          Expanded(child: _CompactKpiCard(item: items[index])),
          if (index != items.length - 1) const SizedBox(width: 5),
        ],
      ],
    );
"""
new_kpi_layout = """    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 620;
        final columns = compact ? 2 : 4;
        final spacing = compact ? 8.0 : 6.0;
        final cardWidth =
            (constraints.maxWidth - (spacing * (columns - 1))) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final item in items)
              SizedBox(
                width: cardWidth,
                height: compact ? 94 : 76,
                child: _CompactKpiCard(item: item),
              ),
          ],
        );
      },
    );
"""
replace_once(dashboard, old_kpi_layout, new_kpi_layout, "dashboard responsive KPI layout")

# Slightly increase mobile card label readability now that cards have real width.
replace_once(
    dashboard,
    "                  fontSize: 8.5,\n                  height: 1.05,\n",
    "                  fontSize: 10.5,\n                  height: 1.12,\n",
    "dashboard KPI label readability",
)

regression_test = Path("mobile/test/device_ui_regression_source_test.dart")
regression_test.write_text(
    """import 'dart:io';\n\nimport 'package:flutter_test/flutter_test.dart';\n\nvoid main() {\n  test('device UI regression safeguards remain in source', () {\n    final shell = File('lib/features/navigation/app_shell.dart').readAsStringSync();\n    final dashboard =\n        File('lib/features/dashboard/dashboard_screen.dart').readAsStringSync();\n\n    expect(shell, contains('overflow: TextOverflow.clip'));\n    expect(shell, contains('FloatingActionButtonLocation.endFloat'));\n    expect(shell, contains('indicatorColor: Colors.white.withValues(alpha: 0.14)'));\n    expect(shell, contains('destination == _selected'));\n    expect(dashboard, contains('constraints.maxWidth < 620'));\n    expect(dashboard, contains('height: compact ? 94 : 76'));\n  });\n}\n""",
    encoding="utf-8",
)

print("device UI regression patch applied")
