import '../contracts/contracts.dart';
import '../customers/customers.dart';
import '../dashboard/dashboard_controller.dart';
import '../notifications/notifications.dart';
import '../profile/profile.dart';

extension DashboardSilentRefresh on DashboardController {
  Future<void> refreshSilently() async {
    if (state == DashboardLoadState.loading ||
        overview == null ||
        lists == null) {
      return;
    }
    try {
      final nextOverview = await repository.loadOverview(filters);
      final nextLists = await repository.loadLists(
        filters,
        pageSize: config.defaultPageSize,
      );
      overview = nextOverview;
      lists = nextLists;
      availableContracts = nextOverview.contracts;
      if (filters.customerId != null) {
        for (final customer in nextOverview.customers) {
          if (customer.id == filters.customerId) {
            selectedCustomerName = customer.name;
            break;
          }
        }
      }
      if (filters.contractId != null) {
        for (final contract in availableContracts) {
          if (contract.id == filters.contractId) {
            selectedContractNumber = contract.contractNumber;
            break;
          }
        }
      }
      errorMessage = null;
      state = DashboardLoadState.ready;
      // ChangeNotifier exposes this publicly; this extension intentionally
      // notifies only after fresh data is ready so no loading frame is shown.
      // ignore: invalid_use_of_protected_member
      notifyListeners();
    } on Object {
      // Background refresh is deliberately silent: keep the last good snapshot.
    }
  }
}

extension CustomersSilentRefresh on CustomersController {
  Future<void> refreshSilently() async {
    final page = currentPage;
    if (!canAccess || state == CustomersLoadState.loading || page == null)
      return;
    try {
      currentPage = await repository.loadPage(
        page: page.page,
        perPage: pageSize,
        order: order,
      );
      errorMessage = null;
      state = CustomersLoadState.ready;
      // ignore: invalid_use_of_protected_member
      notifyListeners();
    } on Object {
      // Preserve the visible page and do not surface background transport noise.
    }
  }
}

extension ContractsSilentRefresh on ContractsController {
  Future<void> refreshSilently() async {
    final page = currentPage;
    if (!canAccess || state == ContractsLoadState.loading || page == null)
      return;
    try {
      currentPage = await repository.loadPage(
        page: page.page,
        perPage: pageSize,
        filters: filters,
        sort: sort,
      );
      errorMessage = null;
      state = ContractsLoadState.ready;
      // ignore: invalid_use_of_protected_member
      notifyListeners();
    } on Object {
      // Preserve the last good contract page on automatic refresh failure.
    }
  }
}

extension NotificationsSilentRefresh on NotificationsController {
  Future<void> refreshSilently() async {
    final page = currentPage;
    if (!canAccess || state == NotificationsLoadState.loading || page == null) {
      return;
    }
    try {
      currentPage = await repository.loadPage(
        page: page.page,
        perPage: pageSize,
      );
      errorMessage = null;
      state = NotificationsLoadState.ready;
      // ignore: invalid_use_of_protected_member
      notifyListeners();
    } on Object {
      // Keep the existing inbox snapshot if a background refresh fails.
    }
  }
}

extension ProfileSilentRefresh on ProfileController {
  Future<void> refreshSilently() async {
    if (state == ProfileDeviceLoadState.loading || snapshot == null) return;
    try {
      snapshot = await repository.loadDevices();
      errorMessage = null;
      state = ProfileDeviceLoadState.ready;
      // ignore: invalid_use_of_protected_member
      notifyListeners();
    } on Object {
      // Device/profile refresh should not replace a usable screen with an error.
    }
  }
}
