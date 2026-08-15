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

  static const supportedStatuses = <String>{
    'draft',
    'active',
    'completed',
    'cancelled',
    'upcoming',
    'due_soon',
    'due',
    'overdue',
    'partially_paid',
    'paid',
  };

  final DashboardRepository repository;
  final SafeContractsMobileConfig config;

  DashboardLoadState state = DashboardLoadState.idle;
  DashboardFilters filters = const DashboardFilters();
  DashboardOverview? overview;
  DashboardLists? lists;
  List<ContractOption> availableContracts = const <ContractOption>[];
  String? errorMessage;

  Future<void> load() async {
    await _reload();
  }

  Future<void> refresh() async {
    await _reload();
  }

  Future<void> selectCustomer(int? customerId) async {
    if (customerId != null &&
        !(overview?.customers.any((option) => option.id == customerId) ?? false)) {
      throw ArgumentError.value(customerId, 'customerId', 'Unknown customer.');
    }
    filters = filters.withCustomer(customerId);
    notifyListeners();

    try {
      final options = await repository.loadContractOptions(customerId);
      availableContracts = options;
      await _reload(contractOptions: options);
    } on Object catch (error) {
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
    await _reload(contractOptions: availableContracts);
  }

  Future<void> selectStatus(String? status) async {
    final normalized = status?.trim().toLowerCase();
    if (normalized != null &&
        normalized.isNotEmpty &&
        !supportedStatuses.contains(normalized)) {
      throw ArgumentError.value(status, 'status', 'Unsupported status.');
    }
    filters = filters.withStatus(
      normalized == null || normalized.isEmpty ? null : normalized,
    );
    await _reload(contractOptions: availableContracts);
  }

  Future<void> setDueRange(String? from, String? to) async {
    _validateDate(from, 'from');
    _validateDate(to, 'to');
    if (from != null && to != null && from.compareTo(to) > 0) {
      throw ArgumentError('Due date range is reversed.');
    }
    filters = filters.withDueRange(from, to);
    await _reload(contractOptions: availableContracts);
  }

  Future<void> _reload({List<ContractOption>? contractOptions}) async {
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
      errorMessage = error.toString();
      state = DashboardLoadState.error;
    }
    notifyListeners();
  }
}

void _validateDate(String? value, String field) {
  if (value == null) {
    return;
  }
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(value);
  if (match == null) {
    throw ArgumentError('$field due date must use YYYY-MM-DD.');
  }
  final year = int.parse(match.group(1)!);
  final month = int.parse(match.group(2)!);
  final day = int.parse(match.group(3)!);
  final parsed = DateTime.tryParse(value);
  if (parsed == null ||
      parsed.year != year ||
      parsed.month != month ||
      parsed.day != day) {
    throw ArgumentError('$field due date is invalid.');
  }
}
