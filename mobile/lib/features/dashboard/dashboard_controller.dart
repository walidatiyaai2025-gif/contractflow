import 'package:flutter/foundation.dart';

import '../config/mobile_config.dart';
import 'dashboard_models.dart';
import 'dashboard_repository.dart';

enum DashboardLoadState { idle, loading, ready, error }

final class DashboardController extends ChangeNotifier {
  DashboardController({required this.repository, required this.config});

  final DashboardRepository repository;
  final SafeContractsMobileConfig config;

  DashboardLoadState state = DashboardLoadState.idle;
  DashboardFilters filters = const DashboardFilters();
  DashboardOverview? overview;
  DashboardLists? lists;
  List<ContractOption> availableContracts = const <ContractOption>[];
  String? selectedCustomerName;
  String? selectedContractNumber;
  String? errorMessage;

  Future<void> load() async {
    await _reload(clearExisting: true);
  }

  Future<void> refresh() async {
    await _reload();
  }

  Future<void> selectCustomer(int? customerId) async {
    final currentCustomers = overview?.customers ?? const <CustomerOption>[];
    if (customerId != null &&
        !currentCustomers.any((option) => option.id == customerId)) {
      throw ArgumentError.value(customerId, 'customerId', 'Unknown customer.');
    }
    selectedCustomerName = customerId == null
        ? null
        : currentCustomers
              .where((option) => option.id == customerId)
              .map((option) => option.name)
              .firstOrNull;
    selectedContractNumber = null;
    filters = filters.withCustomer(customerId);
    availableContracts = const <ContractOption>[];
    overview = null;
    lists = null;
    state = DashboardLoadState.loading;
    errorMessage = null;
    notifyListeners();

    try {
      final options = await repository.loadContractOptions(customerId);
      await _reload(contractOptions: options, clearExisting: true);
    } on Object catch (error) {
      availableContracts = const <ContractOption>[];
      overview = null;
      lists = null;
      state = DashboardLoadState.error;
      errorMessage = error.toString();
      notifyListeners();
    }
  }

  Future<void> selectContract(int? contractId) async {
    if (contractId != null &&
        !availableContracts.any((option) => option.id == contractId)) {
      throw ArgumentError.value(contractId, 'contractId', 'Unknown contract.');
    }
    selectedContractNumber = contractId == null
        ? null
        : availableContracts
              .where((option) => option.id == contractId)
              .map((option) => option.contractNumber)
              .firstOrNull;
    filters = filters.withContract(contractId);
    await _reload(contractOptions: availableContracts, clearExisting: true);
  }

  Future<void> selectStatus(String? status) async {
    final normalized = status?.trim().toLowerCase();
    if (normalized != null &&
        normalized.isNotEmpty &&
        !dashboardSupportedStatuses.contains(normalized)) {
      throw ArgumentError.value(status, 'status', 'Unsupported status.');
    }
    filters = filters.withStatus(
      normalized == null || normalized.isEmpty ? null : normalized,
    );
    await _reload(contractOptions: availableContracts, clearExisting: true);
  }

  Future<void> setDueRange(String? from, String? to) async {
    final candidate = filters.withDueRange(from, to);
    candidate.validate();
    filters = candidate;
    await _reload(contractOptions: availableContracts, clearExisting: true);
  }

  Future<void> _reload({
    List<ContractOption>? contractOptions,
    bool clearExisting = false,
  }) async {
    if (clearExisting) {
      overview = null;
      lists = null;
    }
    state = DashboardLoadState.loading;
    errorMessage = null;
    notifyListeners();

    try {
      final nextOverview = await repository.loadOverview(filters);
      final nextLists = await repository.loadLists(
        filters,
        pageSize: config.defaultPageSize,
      );
      overview = nextOverview;
      lists = nextLists;
      availableContracts = contractOptions ?? nextOverview.contracts;
      if (filters.customerId != null) {
        selectedCustomerName =
            nextOverview.customers
                .where((option) => option.id == filters.customerId)
                .map((option) => option.name)
                .firstOrNull ??
            selectedCustomerName;
      } else {
        selectedCustomerName = null;
      }
      if (filters.contractId != null) {
        selectedContractNumber =
            availableContracts
                .where((option) => option.id == filters.contractId)
                .map((option) => option.contractNumber)
                .firstOrNull ??
            selectedContractNumber;
      } else {
        selectedContractNumber = null;
      }
      state = DashboardLoadState.ready;
    } on Object catch (error) {
      if (clearExisting) {
        overview = null;
        lists = null;
      }
      errorMessage = error.toString();
      state = DashboardLoadState.error;
    }
    notifyListeners();
  }
}

extension _DashboardFirstOrNull<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
