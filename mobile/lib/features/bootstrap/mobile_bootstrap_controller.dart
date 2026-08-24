import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../config/mobile_config.dart';
import '../contracts/contracts.dart';
import '../customers/customers.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_repository.dart';
import '../export/mobile_excel_export.dart';
import '../finance/finance.dart';
import '../navigation/navigation_policy.dart';
import '../notifications/notifications.dart';
import '../profile/profile.dart';
import '../session/session_controller.dart';
import '../suppliers/suppliers.dart';

enum MobileBootstrapState { idle, loading, ready, blocked, error }

final class MobileBootstrapController extends ChangeNotifier {
  MobileBootstrapController(this.client);

  static const customerCreateCapability = 'safecontracts_create_customers';
  static const customerEditCapability = 'safecontracts_edit_customers';
  static const contractEditCapability = 'safecontracts_edit_contracts';
  static const supplierCreateCapability = 'safecontracts_create_suppliers';
  static const supplierEditCapability = 'safecontracts_edit_suppliers';
  static const supplierArchiveCapability = 'safecontracts_archive_suppliers';
  static const viewPayablesCapability = 'safecontracts_view_payables';
  static const viewReceivablesCapability = 'safecontracts_view_receivables';

  final SafeContractsApiClient client;

  MobileBootstrapState state = MobileBootstrapState.idle;
  SessionController? sessionController;
  MobileConfigController? configController;
  DashboardController? dashboardController;
  CustomersController? customersController;
  SuppliersController? suppliersController;
  ContractsController? contractsController;
  FinanceController? financeController;
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
      usingConfigDefaults =
          nextConfigController.state == MobileConfigState.error;

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
        canCreate: session.can(customerCreateCapability),
        canEdit: session.can(customerEditCapability),
      );
      customersController = customers;
      final suppliers = SuppliersController(
        repository: SuppliersRepository(client),
        canAccess: policy.destinations.contains(MobileDestination.suppliers),
        canCreate: session.can(supplierCreateCapability),
        canEdit: session.can(supplierEditCapability),
        canArchive: session.can(supplierArchiveCapability),
      );
      suppliersController = suppliers;
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
      final finance = FinanceController(
        repository: FinanceRepository(client),
        canViewPayables: session.can(viewPayablesCapability),
        canViewReceivables: session.can(viewReceivablesCapability),
      );
      financeController = finance;
      final notifications = NotificationsController(
        repository: NotificationsRepository(client),
        pageSize: config.defaultPageSize,
        canAccess:
            policy.destinations.contains(MobileDestination.notifications),
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
        if (suppliers.canAccess) suppliers.ensureLoaded(),
        if (contracts.canAccess) contracts.ensureLoaded(),
        if (finance.canAccess) finance.ensureLoaded(),
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
    suppliersController?.dispose();
    contractsController?.dispose();
    financeController?.dispose();
    notificationsController?.dispose();
    profileController?.dispose();
    excelExportController?.dispose();
    dashboardController = null;
    customersController = null;
    suppliersController = null;
    contractsController = null;
    financeController = null;
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
    suppliersController?.dispose();
    contractsController?.dispose();
    financeController?.dispose();
    notificationsController?.dispose();
    profileController?.dispose();
    excelExportController?.dispose();
    super.dispose();
  }
}
