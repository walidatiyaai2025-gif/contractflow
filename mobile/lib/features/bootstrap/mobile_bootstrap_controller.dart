import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../config/mobile_config.dart';
import '../contracts/contracts.dart';
import '../customers/customers.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_repository.dart';
import '../export/mobile_excel_export.dart';
import '../navigation/navigation_policy.dart';
import '../notifications/notifications.dart';
import '../profile/profile.dart';
import '../session/session_controller.dart';

enum MobileBootstrapState { idle, loading, ready, blocked, error }

final class MobileBootstrapController extends ChangeNotifier {
  MobileBootstrapController(this.client);

  static const contractEditCapability = 'safecontracts_edit_contracts';

  final SafeContractsApiClient client;

  MobileBootstrapState state = MobileBootstrapState.idle;
  SessionController? sessionController;
  MobileConfigController? configController;
  DashboardController? dashboardController;
  CustomersController? customersController;
  ContractsController? contractsController;
  NotificationsController? notificationsController;
  ProfileController? profileController;
  MobileExcelExportController? excelExportController;
  MobileNavigationPolicy? navigationPolicy;
  String? message;
  bool usingConfigDefaults = false;
  bool _bootstrapInFlight = false;

  Future<void> bootstrap() async {
    if (_bootstrapInFlight) return;
    _bootstrapInFlight = true;
    try {
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
      final policy = MobileNavigationPolicy.resolve(session, config);
      navigationPolicy = policy;
      final dashboard = DashboardController(
        repository: DashboardRepository(client),
        config: config,
      );
      dashboardController = dashboard;
      final customers = CustomersController(
        repository: CustomersRepository(client),
        pageSize: config.defaultPageSize,
        canAccess: policy.destinations.contains(MobileDestination.customers),
      );
      customersController = customers;
      final canAccessContracts =
          policy.destinations.contains(MobileDestination.contracts);
      final contracts = ContractsController(
        repository: ContractsRepository(client),
        pageSize: config.defaultPageSize,
        canAccess: canAccessContracts,
        canEditContract:
            canAccessContracts && session.can(contractEditCapability),
      );
      contractsController = contracts;
      final notifications = NotificationsController(
        repository: NotificationsRepository(client),
        pageSize: config.defaultPageSize,
        canAccess: policy.destinations.contains(MobileDestination.notifications),
      );
      notificationsController = notifications;
      final profile = ProfileController(ProfileRepository(client));
      profileController = profile;
      excelExportController = MobileExcelExportController(
        repository: MobileExcelExportRepository(client),
        filtersProvider: () => dashboard.filters,
        canExport: policy.destinations.contains(MobileDestination.export),
      );

      await Future.wait<void>(<Future<void>>[
        dashboard.load(),
        if (customers.canAccess) customers.ensureLoaded(),
        if (contracts.canAccess) contracts.ensureLoaded(),
        if (notifications.canAccess) notifications.ensureLoaded(),
        profile.ensureLoaded(),
      ]);

      state = MobileBootstrapState.ready;
      notifyListeners();
    } finally {
      _bootstrapInFlight = false;
    }
  }

  void signOutLocalState() {
    sessionController?.reset();
    dashboardController?.dispose();
    customersController?.dispose();
    contractsController?.dispose();
    notificationsController?.dispose();
    profileController?.dispose();
    excelExportController?.dispose();
    dashboardController = null;
    customersController = null;
    contractsController = null;
    notificationsController = null;
    profileController = null;
    excelExportController = null;
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
    customersController?.dispose();
    contractsController?.dispose();
    notificationsController?.dispose();
    profileController?.dispose();
    excelExportController?.dispose();
    super.dispose();
  }
}
