import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('device UI regression safeguards remain in source', () {
    final shell =
        File('lib/features/navigation/app_shell.dart').readAsStringSync();
    final dashboard =
        File('lib/features/dashboard/dashboard_screen.dart').readAsStringSync();

    expect(shell, contains('overflow: TextOverflow.clip'));
    expect(shell, contains('FloatingActionButtonLocation.endFloat'));
    expect(shell,
        contains('indicatorColor: Colors.white.withValues(alpha: 0.14)'));
    expect(shell, contains('destination == _selected'));
    expect(dashboard, contains('height: 70'));
    expect(
      dashboard,
      contains('Expanded(child: _CompactKpiCard(item: items[index]))'),
    );
    expect(dashboard, isNot(contains('final columns = compact ? 2 : 4')));
  });
}
