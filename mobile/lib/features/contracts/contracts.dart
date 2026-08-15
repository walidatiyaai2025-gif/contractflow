import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

enum ContractsLoadState { idle, loading, ready, error }

enum ContractDetailLoadState {
  idle,
  loading,
  ready,
  notFound,
  forbidden,
  error,
}

enum ContractEditState {
  idle,
  saving,
  saved,
  validationError,
  forbidden,
  conflict,
  error,
}

final class ContractSortOption {
  const ContractSortOption({
    required this.label,
    required this.field,
    required this.order,
  });

  final String label;
  final String field;
  final String order;

  static const newest = ContractSortOption(label: 'Newest', field: 'id', order: 'desc');
  static const contractNumber = ContractSortOption(label: 'Contract number', field: 'contract_number', order: 'asc');
  static const startDate = ContractSortOption(label: 'Start date', field: 'start_date', order: 'desc');
  static const endDate = ContractSortOption(label: 'End date', field: 'end_date', order: 'desc');

  static const values = <ContractSortOption>[
    newest,
    contractNumber,
    startDate,
    endDate,
  ];
}

final class ContractsFilters {
  const ContractsFilters({this.customerId, this.status});

  final int? customerId;
  final String? status;

  ContractsFilters withCustomer(int? value) =>
      ContractsFilters(customerId: value, status: status);

  ContractsFilters withStatus(String? value) =>
      ContractsFilters(customerId: customerId, status: value);

  Map<String, String> toQuery() => <String, String>{
        if (customerId != null) 'customer_id': '$customerId',
        if (status != null && status!.isNotEmpty) 'status': status!,
      };
}

final class SafeContractsContract {
  const SafeContractsContract({
    required this.id,
    required this.contractNumber,
    required this.customerId,
    required this.customerName,
    required this.accountantUserId,
    required this.status,
    required this.startDate,
    required this.endDate,
    required this.baseValue,
    required this.isArchived,
  });

  final int id;
  final String contractNumber;
  final int customerId;
  final String? customerName;
  final int? accountantUserId;
  final String status;
  final String? startDate;
  final String? endDate;
  final String? baseValue;
  final bool isArchived;

  factory SafeContractsContract.fromData(Object? value) {
    final data = apiObjectMap(value, 'contract');
    return SafeContractsContract(
      id: _positiveInt(data['id'], 'contract.id'),
      contractNumber: _requiredText(data['contract_number'], 'contract.contract_number'),
      customerId: _positiveInt(data['customer_id'], 'contract.customer_id'),
      customerName: _optionalText(data['customer_name']),
      accountantUserId: _optionalPositiveInt(data['accountant_user_id'], 'contract.accountant_user_id'),
      status: _requiredText(data['status'], 'contract.status'),
      startDate: _optionalText(data['start_date']),
      endDate: _optionalText(data['end_date']),
      baseValue: _optionalScalarText(data['base_value']),
      isArchived: _boolish(data['is_archived'], 'contract.is_archived'),
    );
  }
}

final class ContractPage {
  const ContractPage({
    required this.contracts,
    required this.page,
    required this.perPage,
    required this.sort,
    required this.order,
    required this.hasMore,
    required this.boundedWindow,
    required this.scope,
  });

  final List<SafeContractsContract> contracts;
  final int page;
  final int perPage;
  final String sort;
  final String order;
  final bool hasMore;
  final int boundedWindow;
  final String? scope;

  factory ContractPage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'contracts.data');
    final contracts = rows.map(SafeContractsContract.fromData).toList(growable: false);
    final meta = envelope.meta;
    final page = _boundedInt(meta['page'], 'meta.page', minimum: 1, maximum: 5);
    final perPage = _boundedInt(meta['per_page'], 'meta.per_page', minimum: 1, maximum: 100);
    final boundedWindow = _boundedInt(meta['bounded_window'], 'meta.bounded_window', minimum: 1, maximum: 500);
    final sort = _requiredText(meta['sort'], 'meta.sort');
    if (!ContractSortOption.values.any((option) => option.field == sort)) {
      throw const FormatException('Contract sort metadata is invalid.');
    }
    final order = _requiredText(meta['order'], 'meta.order').toLowerCase();
    if (order != 'asc' && order != 'desc') {
      throw const FormatException('Contract order metadata is invalid.');
    }

    return ContractPage(
      contracts: List<SafeContractsContract>.unmodifiable(contracts),
      page: page,
      perPage: perPage,
      sort: sort,
      order: order,
      hasMore: _boolish(meta['has_more'], 'meta.has_more'),
      boundedWindow: boundedWindow,
      scope: _optionalText(meta['scope']),
    );
  }
}

final class ContractsRepository {
  ContractsRepository(this.client);

  final SafeContractsApiClient client;

  Future<ContractPage> loadPage({
    required int page,
    required int perPage,
    required ContractsFilters filters,
    required ContractSortOption sort,
  }) async {
    if (page < 1 || page > 5) {
      throw ArgumentError('Contract page must be between 1 and 5.');
    }
    if (perPage < 1 || perPage > 100) {
      throw ArgumentError('Contract page size must be between 1 and 100.');
    }
    if (!ContractSortOption.values.contains(sort)) {
      throw ArgumentError('Unsupported contract sort.');
    }

    final query = filters.toQuery()
      ..addAll(<String, String>{
        'page': '$page',
        'per_page': '$perPage',
        'sort': sort.field,
        'order': sort.order,
      });
    final envelope = await client.get('contracts', query: query);
    return ContractPage.fromEnvelope(envelope);
  }

  Future<SafeContractsContract> loadContract(int id) async {
    if (id <= 0) {
      throw ArgumentError('Contract ID must be positive.');
    }
    final envelope = await client.get('contracts/$id');
    return SafeContractsContract.fromData(envelope.data);
  }

  Future<void> editContract(
    int id, {
    required String operation,
    required Map<String, Object?> fields,
  }) async {
    if (id <= 0) {
      throw ArgumentError('Contract ID must be positive.');
    }
    final envelope = await client.post(
      'contracts/$id/edit',
      body: <String, Object?>{'operation': operation, ...fields},
    );
    final data = apiObjectMap(envelope.data, 'contract_edit.data');
    final returnedId = _positiveInt(data['contract_id'], 'contract_edit.contract_id');
    final returnedOperation = _requiredText(data['operation'], 'contract_edit.operation');
    if (returnedId != id || returnedOperation != operation) {
      throw const FormatException('Contract edit acknowledgement does not match the request.');
    }
  }
}

final class ContractsController extends ChangeNotifier {
  ContractsController({
    required this.repository,
    required int pageSize,
    required this.canAccess,
    required this.canEditContract,
  }) : pageSize = pageSize.clamp(1, 100).toInt();

  static const supportedContractStatuses = <String>{
    'draft',
    'active',
    'completed',
    'cancelled',
  };

  final ContractsRepository repository;
  final int pageSize;
  final bool canAccess;
  final bool canEditContract;

  ContractsLoadState state = ContractsLoadState.idle;
  ContractPage? currentPage;
  ContractsFilters filters = const ContractsFilters();
  ContractSortOption sort = ContractSortOption.newest;
  String? errorMessage;

  ContractDetailLoadState detailState = ContractDetailLoadState.idle;
  int? selectedContractId;
  SafeContractsContract? selectedContract;
  String? detailErrorMessage;

  ContractEditState editState = ContractEditState.idle;
  String? editErrorMessage;
  String? lastEditOperation;

  Future<void> ensureLoaded() async {
    if (state == ContractsLoadState.idle) {
      await loadPage(1);
    }
  }

  Future<void> loadPage(int page) async {
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Contract access is not authorized for this session.';
      state = ContractsLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || page > 5) {
      return;
    }

    state = ContractsLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      currentPage = await repository.loadPage(
        page: page,
        perPage: pageSize,
        filters: filters,
        sort: sort,
      );
      state = ContractsLoadState.ready;
    } on SafeContractsApiException catch (error) {
      errorMessage = error.message;
      state = ContractsLoadState.error;
    } on Object catch (error) {
      errorMessage = error.toString();
      state = ContractsLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refresh() async => loadPage(currentPage?.page ?? 1);

  Future<void> previousPage() async {
    final page = currentPage?.page ?? 1;
    if (page > 1) {
      await loadPage(page - 1);
    }
  }

  Future<void> nextPage() async {
    final value = currentPage;
    if (value != null && value.hasMore && value.page < 5) {
      await loadPage(value.page + 1);
    }
  }

  Future<void> selectCustomer(int? customerId) async {
    if (customerId != null && customerId <= 0) {
      throw ArgumentError.value(customerId, 'customerId', 'Invalid customer.');
    }
    filters = filters.withCustomer(customerId);
    await loadPage(1);
  }

  Future<void> selectStatus(String? status) async {
    final normalized = status?.trim().toLowerCase();
    if (normalized != null &&
        normalized.isNotEmpty &&
        !supportedContractStatuses.contains(normalized)) {
      throw ArgumentError.value(status, 'status', 'Unsupported contract status.');
    }
    filters = filters.withStatus(normalized == null || normalized.isEmpty ? null : normalized);
    await loadPage(1);
  }

  Future<void> selectSort(ContractSortOption nextSort) async {
    if (!ContractSortOption.values.contains(nextSort)) {
      throw ArgumentError.value(nextSort, 'nextSort', 'Unsupported sort.');
    }
    if (identical(sort, nextSort) && currentPage != null) {
      return;
    }
    sort = nextSort;
    await loadPage(1);
  }

  Future<void> openContract(int id) async {
    if (!canAccess || id <= 0) {
      selectedContractId = id > 0 ? id : null;
      selectedContract = null;
      detailErrorMessage = 'Contract access is not authorized for this session.';
      detailState = ContractDetailLoadState.forbidden;
      notifyListeners();
      return;
    }

    selectedContractId = id;
    selectedContract = null;
    detailErrorMessage = null;
    detailState = ContractDetailLoadState.loading;
    notifyListeners();
    try {
      final contract = await repository.loadContract(id);
      if (selectedContractId != id) {
        return;
      }
      selectedContract = contract;
      detailState = ContractDetailLoadState.ready;
    } on SafeContractsApiException catch (error) {
      if (selectedContractId != id) {
        return;
      }
      selectedContract = null;
      detailErrorMessage = error.message;
      if (error.statusCode == 404) {
        detailState = ContractDetailLoadState.notFound;
      } else if (error.statusCode == 403) {
        detailState = ContractDetailLoadState.forbidden;
      } else {
        detailState = ContractDetailLoadState.error;
      }
    } on Object catch (error) {
      if (selectedContractId != id) {
        return;
      }
      selectedContract = null;
      detailErrorMessage = error.toString();
      detailState = ContractDetailLoadState.error;
    }
    notifyListeners();
  }

  Future<bool> editContractNumber(int id, String contractNumber) => _edit(
        id,
        operation: 'contract_number',
        fields: <String, Object?>{'contract_number': contractNumber.trim()},
      );

  Future<bool> editDates(int id, String? startDate, String? endDate) => _edit(
        id,
        operation: 'dates',
        fields: <String, Object?>{
          'start_date': _blankToNull(startDate),
          'end_date': _blankToNull(endDate),
        },
      );

  Future<bool> editBaseValue(int id, String baseValue) => _edit(
        id,
        operation: 'base_value',
        fields: <String, Object?>{'base_value': baseValue.trim()},
      );

  Future<bool> editStatus(int id, String status) => _edit(
        id,
        operation: 'status',
        fields: <String, Object?>{'status': status.trim().toLowerCase()},
      );

  Future<bool> _edit(
    int id, {
    required String operation,
    required Map<String, Object?> fields,
  }) async {
    lastEditOperation = operation;
    editErrorMessage = null;
    if (!canAccess || !canEditContract) {
      editState = ContractEditState.forbidden;
      editErrorMessage = 'Contract editing is not authorized for this session.';
      notifyListeners();
      return false;
    }

    editState = ContractEditState.saving;
    notifyListeners();
    try {
      await repository.editContract(id, operation: operation, fields: fields);
      editState = ContractEditState.saved;
      notifyListeners();
      await openContract(id);
      return true;
    } on SafeContractsApiException catch (error) {
      editErrorMessage = error.message;
      editState = switch (error.statusCode) {
        400 => ContractEditState.validationError,
        403 => ContractEditState.forbidden,
        409 => ContractEditState.conflict,
        _ => ContractEditState.error,
      };
    } on Object catch (error) {
      editErrorMessage = error.toString();
      editState = ContractEditState.error;
    }
    notifyListeners();
    return false;
  }

  void resetEditState() {
    editState = ContractEditState.idle;
    editErrorMessage = null;
    lastEditOperation = null;
    notifyListeners();
  }

  void clearContractDetail({int? expectedId}) {
    if (expectedId != null && selectedContractId != expectedId) {
      return;
    }
    selectedContractId = null;
    selectedContract = null;
    detailErrorMessage = null;
    detailState = ContractDetailLoadState.idle;
    resetEditState();
  }
}

String? _blankToNull(String? value) {
  final normalized = value?.trim() ?? '';
  return normalized.isEmpty ? null : normalized;
}

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) {
    throw FormatException('$field must be a positive integer.');
  }
  return parsed;
}

int? _optionalPositiveInt(Object? value, String field) {
  if (value == null || value == '') {
    return null;
  }
  return _positiveInt(value, field);
}

int _boundedInt(
  Object? value,
  String field, {
  required int minimum,
  required int maximum,
}) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed < minimum || parsed > maximum) {
    throw FormatException('$field is outside the supported range.');
  }
  return parsed;
}

String _requiredText(Object? value, String field) {
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('$field must be a non-empty string.');
  }
  return value.trim();
}

String? _optionalText(Object? value) {
  if (value == null) {
    return null;
  }
  if (value is! String) {
    throw const FormatException('Contract text field must be string or null.');
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

String? _optionalScalarText(Object? value) {
  if (value == null) {
    return null;
  }
  if (value is String) {
    final normalized = value.trim();
    return normalized.isEmpty ? null : normalized;
  }
  if (value is num) {
    return value.toString();
  }
  throw const FormatException('Contract scalar field is invalid.');
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true => true,
    false => false,
    1 => true,
    0 => false,
    '1' => true,
    '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
