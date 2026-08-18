import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/branding/safe_contracts_brand.dart';

void main() {
  test('Enterprise Safe Contracts uses the supplied packaged identity', () {
    expect(SafeContractsBrand.name, 'Enterprise Safe Contracts');
    expect(
      SafeContractsBrand.assetPath,
      'assets/brand/safe_contracts_identity.jpg',
    );

    final bytes = File(SafeContractsBrand.assetPath).readAsBytesSync();
    expect(bytes.length, greaterThan(1024));
    expect(bytes.take(3), <int>[0xff, 0xd8, 0xff]);
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
