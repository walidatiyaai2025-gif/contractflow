import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_repository.dart';
import '../navigation/navigation_policy.dart';
import '../session/session_controller.dart';

enum MobileBootstrapState { idle, loading, ready, blocked, error }

final class MobileBootstrapController extends ChangeNotifier {
  MobileBootstrapController(this.client);

  final SafeContractsApiClient client;

  MobileBootstrapState state = MobileBootstrapState.idle;
  SessionController? sessionController;
  MobileConfigController? configController;
  DashboardController? dashboardController;
  MobileNavigationPolicy? navigationPolicy;
  String? message;
  bool usingConfigDefaults = false;

  Future<void> bootstrap() async {
    state = MobileBootstrapState.loading;
    message = null;
    usingConfigDefaults = false;
    notifyListeners();

    final nextSessionController = SessionController(client);
    await nextSessionController.bootstrap();
    sessionController = nextSessionController;
    final session = nextSessionController.session;
    if (nextSessionController.state != SessionState.authenticated ||
        session == null) {
      message = nextSessionController.errorMessage ??
          'SafeContracts mobile access requires an authorized session.';
      state = nextSessionController.state == SessionState.error
          ? MobileBootstrapState.error
          : MobileBootstrapState.blocked;
      notifyListeners();
      return;
    }

    final nextConfigController = MobileConfigController(client);
    await nextConfigController.load();
    configController = nextConfigController;
    usingConfigDefaults = nextConfigController.state == MobileConfigState.error;

    final config = nextConfigController.config;
    navigationPolicy = MobileNavigationPolicy.resolve(session, config);
    final dashboard = DashboardController(
      repository: DashboardRepository(client),
      config: config,
    );
    dashboardController = dashboard;
    await dashboard.load();

    state = MobileBootstrapState.ready;
    notifyListeners();
  }

  void signOutLocalState() {
    sessionController?.reset();
    dashboardController?.dispose();
    dashboardController = null;
    navigationPolicy = null;
    state = MobileBootstrapState.blocked;
    message = 'Local SafeContracts session state was cleared.';
    notifyListeners();
  }

  @override
  void dispose() {
    sessionController?.dispose();
    configController?.dispose();
    dashboardController?.dispose();
    super.dispose();
  }
}
