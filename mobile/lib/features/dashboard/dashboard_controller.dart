import 'package:flutter/foundation.dart';

import '../config/mobile_config.dart';
import 'dashboard_models.dart';
import 'dashboard_repository.dart';

enum DashboardLoadState { idle, loading, ready, error }

final class DashboardController extends ChangeNotifier {
  DashboardController({
    required this.repository,
    required this.config,
  });

  final DashboardRepository repository;
  final SafeContractsMobileConfig config;

  DashboardLoadState state = DashboardLoadState.idle;
  DashboardFilters filters = const DashboardFilters();
  DashboardOverview? overview;
  DashboardLists? lists;
  List<ContractOption> availableContracts = const <ContractOption>[];
  String? errorMessage;

  Future<void> load() async {
    await _reload(clearExisting: true);
  }

  Future<void> refresh() async {
    await _reload();
  }

  Future<void> selectCustomer(int? customerId) async {
    if (customerId != null &&
        !(overview?.customers.any((option) => option.id == customerId) ??
            false)) {
      throw ArgumentError.value(customerId, 'customerId', 'Unknown customer.');
    }
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
    filters = filters.withContract(contractId);
    await _reload(
      contractOptions: availableContracts,
      clearExisting: true,
    );
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
    await _reload(
      contractOptions: availableContracts,
      clearExisting: true,
    );
  }

  Future<void> setDueRange(String? from, String? to) async {
    final candidate = filters.withDueRange(from, to);
    candidate.validate();
    filters = candidate;
    await _reload(
      contractOptions: availableContracts,
      clearExisting: true,
    );
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
