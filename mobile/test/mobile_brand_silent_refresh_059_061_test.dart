import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/branding/safe_contracts_brand.dart';

void main() {
  test('Alkenzy ADV uses the supplied packaged identity', () {
    expect(SafeContractsBrand.name, 'Alkenzy ADV');
    expect(
      SafeContractsBrand.assetPath,
      'assets/brand/alkenzy_adv.png',
    );

    final bytes = File(SafeContractsBrand.assetPath).readAsBytesSync();
    expect(bytes.length, greaterThan(1024));
    expect(bytes.take(8), <int>[0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]);
  });

  test('automatic app-shell refresh uses silent refresh paths', () {
    final shell =
        File('lib/features/navigation/app_shell.dart').readAsStringSync();
    final silent =
        File('lib/features/refresh/silent_refresh.dart').readAsStringSync();

    expect(shell, contains('dashboardController.refreshSilently()'));
    expect(shell, contains('customersController.refreshSilently()'));
    expect(shell, contains('contractsController.refreshSilently()'));
    expect(shell, contains('notificationsController.refreshSilently()'));
    expect(shell, contains('profileController.refreshSilently()'));
    expect(silent, contains('keep the last good snapshot'));
    expect(silent, isNot(contains('state = DashboardLoadState.loading')));
  });

  test('filtered dashboard preserves and renders customer entity context', () {
    final controller = File('lib/features/dashboard/dashboard_controller.dart')
        .readAsStringSync();
    final contextScreen = File(
      'lib/features/dashboard/dashboard_context_screen.dart',
    ).readAsStringSync();

    expect(controller, contains('String? selectedCustomerName;'));
    expect(controller, contains('String? selectedContractNumber;'));
    expect(contextScreen, contains("'بيانات الجهة'"));
    expect(contextScreen, contains("'Dashboard entity'"));
    expect(
      contextScreen,
      contains(
        'All figures and indicators below are filtered for this entity.',
      ),
    );
  });
}
