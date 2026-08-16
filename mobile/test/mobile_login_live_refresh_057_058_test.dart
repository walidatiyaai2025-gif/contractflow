import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  String source(String relativePath) {
    final file = File(relativePath);
    expect(file.existsSync(), isTrue, reason: 'Missing source: $relativePath');
    return file.readAsStringSync();
  }

  test('post-auth login is replaced by a blocking bootstrap splash', () {
    final login = source('lib/features/auth/login_screen.dart');

    expect(login, contains('bool _bootstrapping = false;'));
    expect(login, contains('setState(() => _bootstrapping = true);'));
    expect(login, contains('await widget.controller.submit('));
    expect(
      login.indexOf('setState(() => _bootstrapping = true);'),
      lessThan(login.indexOf('await widget.controller.submit(')),
    );
    expect(login, contains('await widget.onAuthenticated();'));
    expect(login, contains('if (_bootstrapping)'));
    expect(login, contains('_BlockingBootstrapSplash'));
    expect(login, contains('PopScope('));
    expect(login, contains('canPop: false'));
  });

  test('bootstrap preloads authorized shared data before marking ready', () {
    final bootstrap =
        source('lib/features/bootstrap/mobile_bootstrap_controller.dart');

    expect(bootstrap, contains('bool _bootstrapInFlight = false;'));
    expect(bootstrap, contains('if (_bootstrapInFlight) return;'));
    expect(bootstrap, contains('await Future.wait<void>'));
    expect(bootstrap, contains('dashboard.load()'));
    expect(bootstrap, contains('customers.ensureLoaded()'));
    expect(bootstrap, contains('contracts.ensureLoaded()'));
    expect(bootstrap, contains('notifications.ensureLoaded()'));
    expect(bootstrap, contains('profile.ensureLoaded()'));
    expect(
      bootstrap.indexOf('await Future.wait<void>'),
      lessThan(bootstrap.indexOf('state = MobileBootstrapState.ready;')),
    );
  });

  test('authenticated shell silently refreshes active foreground surface', () {
    final shell = source('lib/features/navigation/app_shell.dart');
    final silent = source('lib/features/refresh/silent_refresh.dart');

    expect(shell, contains('with WidgetsBindingObserver'));
    expect(shell, contains('Duration(seconds: 12)'));
    expect(shell, contains('Timer.periodic'));
    expect(shell, contains('didChangeAppLifecycleState'));
    expect(shell, contains('AppLifecycleState.resumed'));
    expect(shell, contains('bool _liveRefreshInFlight = false;'));
    expect(
      shell,
      contains('if (!mounted || !_foreground || _liveRefreshInFlight)'),
    );
    expect(shell, contains('widget.dashboardController.refreshSilently()'));
    expect(shell, contains('widget.customersController.refreshSilently()'));
    expect(shell, contains('widget.contractsController.refreshSilently()'));
    expect(shell, contains('widget.notificationsController.refreshSilently()'));
    expect(shell, contains('widget.profileController.refreshSilently()'));
    expect(shell, contains('shellSnapshotChanged'));
    expect(shell, contains('setState(() {})'));
    expect(shell, contains('_selectDestination'));
    expect(shell, contains('refreshRevision: _liveRefreshRevision'));

    expect(silent, isNot(contains('DashboardLoadState.loading')));
    expect(silent, isNot(contains('CustomersLoadState.loading =')));
    expect(silent, contains('keep the last good snapshot'));
    expect(silent, contains('background transport noise'));
  });

  test('payments and follow-up preserve visible data during live refresh', () {
    final payments = source('lib/features/payments/payments_screen.dart');
    final followUps = source('lib/features/followups/followups_screen.dart');

    for (final screen in <String>[payments, followUps]) {
      expect(screen, contains('final int refreshRevision;'));
      expect(
        screen,
        contains('oldWidget.refreshRevision != widget.refreshRevision'),
      );
      expect(screen, contains('background: true'));
      expect(
        screen,
        contains('final keepVisible = background && _page != null;'),
      );
      expect(screen, contains('if (!keepVisible)'));
      expect(screen, contains('if (keepVisible) return;'));
    }
  });
}
