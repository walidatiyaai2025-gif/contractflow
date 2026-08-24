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

const _supportedContractFilterStatuses = <String>{
  'draft',
  'active',
  'completed',
  'cancelled',
};

const _supportedCounterpartyTypes = <String>{'customer', 'supplier'};

final class ContractSortOption {
  const ContractSortOption({
    required this.label,
    required this.field,
    required this.order,
  });

  final String label;
  final String field;
  final String order;

  static const newest = ContractSortOption(
    label: 'Newest',
    field: 'id',
    order: 'desc',
  );
  static const contractNumber = ContractSortOption(
    label: 'Contract number',
    field: 'contract_number',
    order: 'asc',
  );
  static const startDate = ContractSortOption(
    label: 'Start date',
    field: 'start_date',
    order: 'desc',
  );
  static const endDate = ContractSortOption(
    label: 'End date',
    field: 'end_date',
    order: 'desc',
  );

  static const values = <ContractSortOption>[
    newest,
    contractNumber,
    startDate,
    endDate,
  ];
}

final class ContractsFilters {
  const ContractsFilters({
    this.customerId,
    this.counterpartyType,
    this.counterpartyId,
    this.status,
  });

  final int? customerId;
  final String? counterpartyType;
  final int? counterpartyId;
  final String? status;

  ContractsFilters withCustomer(int? value) => ContractsFilters(
    customerId: value,
    counterpartyType: value == null ? counterpartyType : 'customer',
    counterpartyId:
        value ?? (counterpartyType == 'customer' ? counterpartyId : null),
    status: status,
  );

  ContractsFilters withCounterpartyType(String? value) =>
      ContractsFilters(counterpartyType: value, status: status);

  ContractsFilters withCounterparty({required String type, required int id}) =>
      ContractsFilters(
        customerId: type == 'customer' ? id : null,
        counterpartyType: type,
        counterpartyId: id,
        status: status,
      );

  ContractsFilters withStatus(String? value) => ContractsFilters(
    customerId: customerId,
    counterpartyType: counterpartyType,
    counterpartyId: counterpartyId,
    status: value,
  );

  int get activeCount {
    var count = 0;
    if (counterpartyType != null && counterpartyType!.isNotEmpty) count++;
    if (customerId != null) count++;
    if (status != null && status!.isNotEmpty) count++;
    return count;
  }

  void validate() {
    if (customerId != null && customerId! <= 0) {
      throw ArgumentError.value(customerId, 'customerId', 'Invalid customer.');
    }
    if (counterpartyType != null &&
        !_supportedCounterpartyTypes.contains(counterpartyType)) {
      throw ArgumentError.value(
        counterpartyType,
        'counterpartyType',
        'Unsupported counterparty type.',
      );
    }
    if (counterpartyId != null && counterpartyId! <= 0) {
      throw ArgumentError.value(
        counterpartyId,
        'counterpartyId',
        'Invalid counterparty.',
      );
    }
    if (customerId != null &&
        counterpartyType != null &&
        counterpartyType != 'customer') {
      throw ArgumentError('Customer filter conflicts with counterparty type.');
    }
    if (status != null &&
        status!.isNotEmpty &&
        !_supportedContractFilterStatuses.contains(status)) {
      throw ArgumentError.value(
        status,
        'status',
        'Unsupported contract status.',
      );
    }
  }

  Map<String, String> toQuery() {
    validate();
    return <String, String>{
      if (customerId != null) 'customer_id': '$customerId',
      'counterparty_type': ?counterpartyType,
      if (counterpartyId != null) 'counterparty_id': '$counterpartyId',
      if (status != null && status!.isNotEmpty) 'status': status!,
    };
  }
}

final class ContractDraft {
  const ContractDraft({
    required this.contractNumber,
    required this.counterpartyType,
    required this.counterpartyId,
    required this.baseValue,
    this.currencyCode,
    this.accountantUserId,
    this.notes,
  });

  final String contractNumber;
  final String counterpartyType;
  final int counterpartyId;
  final String baseValue;
  final String? currencyCode;
  final int? accountantUserId;
  final String? notes;

  Map<String, Object?> toPayload() => <String, Object?>{
    'contract_number': contractNumber.trim(),
    'counterparty_type': counterpartyType.trim().toLowerCase(),
    'counterparty_id': counterpartyId,
    'base_value': baseValue.trim(),
    if (_payloadText(currencyCode) != null)
      'currency_code': _payloadText(currencyCode)!.toUpperCase(),
    if (accountantUserId != null) 'accountant_user_id': accountantUserId,
    if (_payloadText(notes) != null) 'notes': _payloadText(notes),
  };
}

final class SafeContractsContract {
  const SafeContractsContract({
    required this.id,
    required this.contractNumber,
    required this.customerId,
    required this.customerName,
    required this.counterpartyType,
    required this.counterpartyId,
    required this.counterpartyName,
    required this.financialDirection,
    required this.currencyCode,
    required this.accountantUserId,
    required this.status,
    required this.startDate,
    required this.endDate,
    required this.baseValue,
    required this.isArchived,
  });

  final int id;
  final String contractNumber;
  final int? customerId;
  final String? customerName;
  final String counterpartyType;
  final int counterpartyId;
  final String? counterpartyName;
  final String financialDirection;
  final String currencyCode;
  final int? accountantUserId;
  final String status;
  final String? startDate;
  final String? endDate;
  final String? baseValue;
  final bool isArchived;

  bool get isSupplier => counterpartyType == 'supplier';
  bool get isCustomer => counterpartyType == 'customer';
  String get displayCounterparty =>
      counterpartyName ?? customerName ?? '#$counterpartyId';

  factory SafeContractsContract.fromData(Object? value) {
    final data = apiObjectMap(value, 'contract');
    final legacyCustomerId = _optionalPositiveInt(
      data['customer_id'],
      'contract.customer_id',
    );
    final legacySupplierId = _optionalPositiveInt(
      data['supplier_id'],
      'contract.supplier_id',
    );
    if (legacyCustomerId != null && legacySupplierId != null) {
      throw const FormatException(
        'contract has conflicting customer and supplier IDs.',
      );
    }
    final type =
        _optionalText(data['counterparty_type'])?.toLowerCase() ??
        (legacyCustomerId != null
            ? 'customer'
            : legacySupplierId != null
            ? 'supplier'
            : '');
    if (!_supportedCounterpartyTypes.contains(type)) {
      throw const FormatException('contract.counterparty_type is invalid.');
    }
    final counterpartyId =
        _optionalPositiveInt(
          data['counterparty_id'],
          'contract.counterparty_id',
        ) ??
        (type == 'customer' ? legacyCustomerId : legacySupplierId);
    if (counterpartyId == null) {
      throw const FormatException('contract.counterparty_id is required.');
    }
    final direction =
        _optionalText(data['financial_direction'])?.toLowerCase() ??
        (type == 'supplier' ? 'payable' : 'receivable');
    if ((type == 'supplier' && direction != 'payable') ||
        (type == 'customer' && direction != 'receivable')) {
      throw const FormatException(
        'contract.financial_direction conflicts with counterparty type.',
      );
    }
    final currency = (_optionalText(data['currency_code']) ?? 'UNSET')
        .toUpperCase();
    if (currency != 'UNSET' && !RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
      throw const FormatException('contract.currency_code is invalid.');
    }

    return SafeContractsContract(
      id: _positiveInt(data['id'], 'contract.id'),
      contractNumber: _requiredText(
        data['contract_number'],
        'contract.contract_number',
      ),
      customerId: type == 'customer'
          ? (legacyCustomerId ?? counterpartyId)
          : null,
      customerName: _optionalText(data['customer_name']),
      counterpartyType: type,
      counterpartyId: counterpartyId,
      counterpartyName:
          _optionalText(data['counterparty_name']) ??
          (type == 'supplier'
              ? _optionalText(data['supplier_name'])
              : _optionalText(data['customer_name'])),
      financialDirection: direction,
      currencyCode: currency,
      accountantUserId: _optionalPositiveInt(
        data['accountant_user_id'],
        'contract.accountant_user_id',
      ),
      status: _requiredText(data['status'], 'contract.status').toLowerCase(),
      startDate: _optionalIsoDate(data['start_date'], 'contract.start_date'),
      endDate: _optionalIsoDate(data['end_date'], 'contract.end_date'),
      baseValue: _optionalMoneyText(data['base_value'], 'contract.base_value'),
      isArchived: _boolish(data['is_archived'], 'contract.is_archived'),
    );
  }
}

final class ContractPage {
  const ContractPage({
    required this.contracts,
    required this.page,
    required this.perPage,
    required this.total,
    required this.totalPages,
    required this.sort,
    required this.order,
    required this.hasMore,
    required this.boundedWindow,
    required this.scope,
  });

  final List<SafeContractsContract> contracts;
  final int page;
  final int perPage;
  final int total;
  final int totalPages;
  final String sort;
  final String order;
  final bool hasMore;
  final int boundedWindow;
  final String? scope;

  factory ContractPage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'contracts.data');
    final contracts = rows
        .map(SafeContractsContract.fromData)
        .toList(growable: false);
    final ids = <int>{};
    for (final contract in contracts) {
      if (!ids.add(contract.id)) {
        throw const FormatException('contracts contain duplicate IDs.');
      }
    }
    final meta = envelope.meta;
    final boundedWindow = _boundedInt(
      meta['bounded_window'],
      'meta.bounded_window',
      minimum: 1,
      maximum: 500,
    );
    final perPage = _boundedInt(
      meta['per_page'],
      'meta.per_page',
      minimum: 1,
      maximum: 100,
    );
    final maxPageForPageSize = (1000000 ~/ perPage) + 1;
    final page = _boundedInt(
      meta['page'],
      'meta.page',
      minimum: 1,
      maximum: maxPageForPageSize,
    );
    final total = _boundedInt(
      meta['total'],
      'meta.total',
      minimum: 0,
      maximum: 1000000000,
    );
    final totalPages = _boundedInt(
      meta['total_pages'],
      'meta.total_pages',
      minimum: 1,
      maximum: 1000000000,
    );
    final expectedTotalPages = total == 0
        ? 1
        : ((total + perPage - 1) ~/ perPage);
    if (totalPages != expectedTotalPages) {
      throw const FormatException(
        'Contract pagination metadata is inconsistent.',
      );
    }
    if (page > totalPages && total > 0) {
      throw const FormatException(
        'Contract page exceeds authoritative total pages.',
      );
    }
    final sort = _requiredText(meta['sort'], 'meta.sort');
    if (!ContractSortOption.values.any((option) => option.field == sort)) {
      throw const FormatException('Contract sort metadata is invalid.');
    }
    final order = _requiredText(meta['order'], 'meta.order').toLowerCase();
    if (order != 'asc' && order != 'desc') {
      throw const FormatException('Contract order metadata is invalid.');
    }
    final scope = _optionalText(meta['scope']);
    if (scope != null && scope != 'all' && scope != 'assigned') {
      throw const FormatException('Contract scope metadata is invalid.');
    }
    final hasMore = _boolish(meta['has_more'], 'meta.has_more');
    if (hasMore != (page < totalPages)) {
      throw const FormatException(
        'Contract has_more metadata is inconsistent.',
      );
    }
    return ContractPage(
      contracts: List<SafeContractsContract>.unmodifiable(contracts),
      page: page,
      perPage: perPage,
      total: total,
      totalPages: totalPages,
      sort: sort,
      order: order,
      hasMore: hasMore,
      boundedWindow: boundedWindow,
      scope: scope,
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
    String search = '',
  }) async {
    if (perPage < 1 || perPage > 100) {
      throw ArgumentError('Contract page size must be between 1 and 100.');
    }
    if (page < 1 || (page - 1) * perPage > 1000000) {
      throw ArgumentError(
        'Contract page exceeds the bounded server query window.',
      );
    }
    if (!ContractSortOption.values.contains(sort)) {
      throw ArgumentError('Unsupported contract sort.');
    }
    final normalizedSearch = search.trim();
    if (normalizedSearch.length > 100) {
      throw ArgumentError('Contract search must not exceed 100 characters.');
    }
    filters.validate();
    final query = filters.toQuery()
      ..addAll(<String, String>{
        'page': '$page',
        'per_page': '$perPage',
        'sort': sort.field,
        'order': sort.order,
        if (normalizedSearch.isNotEmpty) 'search': normalizedSearch,
      });
    final envelope = await client.get('contracts', query: query);
    return ContractPage.fromEnvelope(envelope);
  }

  Future<SafeContractsContract> loadContract(int id) async {
    if (id <= 0) throw ArgumentError('Contract ID must be positive.');
    final envelope = await client.get('contracts/$id');
    final contract = SafeContractsContract.fromData(envelope.data);
    if (contract.id != id) {
      throw const FormatException('Contract detail ID does not match request.');
    }
    return contract;
  }

  Future<SafeContractsContract> create(ContractDraft draft) async {
    _validateDraft(draft);
    final envelope = await client.post('contracts', body: draft.toPayload());
    final data = apiObjectMap(envelope.data, 'contract_create');
    final id = _positiveInt(data['id'], 'contract_create.id');
    return loadContract(id);
  }

  void _validateDraft(ContractDraft draft) {
    final number = draft.contractNumber.trim();
    if (number.isEmpty || number.length > 100) {
      throw ArgumentError(
        'Contract number is required and must not exceed 100 characters.',
      );
    }
    final type = draft.counterpartyType.trim().toLowerCase();
    if (!_supportedCounterpartyTypes.contains(type)) {
      throw ArgumentError('Contract counterparty type is invalid.');
    }
    if (draft.counterpartyId <= 0) {
      throw ArgumentError('Contract counterparty is required.');
    }
    final value = num.tryParse(draft.baseValue.trim());
    if (value == null || value <= 0) {
      throw ArgumentError('Contract base value must be greater than zero.');
    }
    final currency = _payloadText(draft.currencyCode)?.toUpperCase();
    if (currency != null && !RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
      throw ArgumentError('Contract currency must be a 3-letter code.');
    }
    if (draft.accountantUserId != null && draft.accountantUserId! <= 0) {
      throw ArgumentError('Accountant ID must be positive.');
    }
  }
}

final class ContractsController extends ChangeNotifier {
  ContractsController({
    required this.repository,
    required int pageSize,
    required this.canAccess,
    required this.canEditContract,
    this.canCreateContract = false,
  }) : pageSize = pageSize.clamp(1, 100).toInt();

  static const supportedContractStatuses = _supportedContractFilterStatuses;

  final ContractsRepository repository;
  final int pageSize;
  final bool canAccess;
  final bool canEditContract;
  final bool canCreateContract;

  ContractsLoadState state = ContractsLoadState.idle;
  ContractPage? currentPage;
  ContractsFilters filters = const ContractsFilters();
  ContractSortOption sort = ContractSortOption.newest;
  String searchQuery = '';
  String? errorMessage;
  ContractDetailLoadState detailState = ContractDetailLoadState.idle;
  int? selectedContractId;
  SafeContractsContract? selectedContract;
  String? detailErrorMessage;
  bool mutationInFlight = false;
  bool _pageRequestInFlight = false;

  bool get pageRequestInFlight => _pageRequestInFlight;
  int get activeFilterCount => filters.activeCount;

  Future<void> ensureLoaded() async {
    if (state == ContractsLoadState.idle) await loadPage(1);
  }

  Future<void> loadPage(int page) async {
    if (_pageRequestInFlight) return;
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Contract access is not authorized for this session.';
      state = ContractsLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || (page - 1) * pageSize > 1000000) return;
    _pageRequestInFlight = true;
    final previousPage = currentPage;
    state = ContractsLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      currentPage = await repository.loadPage(
        page: page,
        perPage: pageSize,
        filters: filters,
        sort: sort,
        search: searchQuery,
      );
      state = ContractsLoadState.ready;
    } on SafeContractsApiException catch (error) {
      currentPage = previousPage;
      errorMessage = error.message;
      state = ContractsLoadState.error;
    } on Object catch (error) {
      currentPage = previousPage;
      errorMessage = error.toString();
      state = ContractsLoadState.error;
    } finally {
      _pageRequestInFlight = false;
      notifyListeners();
    }
  }

  Future<void> refresh() => loadPage(currentPage?.page ?? 1);

  Future<void> refreshSilently() async {
    if (!canAccess || _pageRequestInFlight) return;
    try {
      currentPage = await repository.loadPage(
        page: currentPage?.page ?? 1,
        perPage: pageSize,
        filters: filters,
        sort: sort,
        search: searchQuery,
      );
      state = ContractsLoadState.ready;
      errorMessage = null;
      notifyListeners();
    } on Object {
      // Keep the last authorized snapshot on silent refresh failure.
    }
  }

  Future<void> previousPage() async {
    if (_pageRequestInFlight) return;
    final page = currentPage?.page ?? 1;
    if (page > 1) await loadPage(page - 1);
  }

  Future<void> nextPage() async {
    if (_pageRequestInFlight) return;
    final value = currentPage;
    if (value != null && value.hasMore && value.page < value.totalPages) {
      await loadPage(value.page + 1);
    }
  }

  Future<void> selectSearch(String value) async {
    if (_pageRequestInFlight) return;
    final normalized = value.trim();
    if (normalized.length > 100) {
      throw ArgumentError.value(value, 'value', 'Search is too long.');
    }
    if (searchQuery == normalized && currentPage != null) return;
    searchQuery = normalized;
    await loadPage(1);
  }

  Future<void> selectCustomer(int? customerId) async {
    if (_pageRequestInFlight) return;
    if (customerId != null && customerId <= 0) {
      throw ArgumentError.value(customerId, 'customerId', 'Invalid customer.');
    }
    filters = filters.withCustomer(customerId);
    await loadPage(1);
  }

  Future<void> selectCounterpartyType(String? value) async {
    if (_pageRequestInFlight) return;
    final normalized = value?.trim().toLowerCase();
    if (normalized != null &&
        normalized.isNotEmpty &&
        !_supportedCounterpartyTypes.contains(normalized)) {
      throw ArgumentError.value(
        value,
        'value',
        'Unsupported counterparty type.',
      );
    }
    filters = filters.withCounterpartyType(
      normalized == null || normalized.isEmpty ? null : normalized,
    );
    await loadPage(1);
  }

  Future<void> selectStatus(String? status) async {
    if (_pageRequestInFlight) return;
    final normalized = status?.trim().toLowerCase();
    if (normalized != null &&
        normalized.isNotEmpty &&
        !_supportedContractFilterStatuses.contains(normalized)) {
      throw ArgumentError.value(
        status,
        'status',
        'Unsupported contract status.',
      );
    }
    filters = filters.withStatus(
      normalized == null || normalized.isEmpty ? null : normalized,
    );
    await loadPage(1);
  }

  Future<void> clearFilters() async {
    if (_pageRequestInFlight || filters.activeCount == 0) return;
    filters = const ContractsFilters();
    await loadPage(1);
  }

  Future<void> selectSort(ContractSortOption nextSort) async {
    if (_pageRequestInFlight) return;
    if (!ContractSortOption.values.contains(nextSort)) {
      throw ArgumentError.value(nextSort, 'nextSort', 'Unsupported sort.');
    }
    if (identical(sort, nextSort) && currentPage != null) return;
    sort = nextSort;
    await loadPage(1);
  }

  Future<SafeContractsContract> createContract(ContractDraft draft) async {
    if (!canCreateContract) {
      throw StateError('Contract creation is not authorized.');
    }
    mutationInFlight = true;
    notifyListeners();
    try {
      final contract = await repository.create(draft);
      await loadPage(1);
      await openContract(contract.id);
      return contract;
    } finally {
      mutationInFlight = false;
      notifyListeners();
    }
  }

  Future<void> openContract(int id) async {
    if (id <= 0) {
      selectedContractId = null;
      selectedContract = null;
      detailErrorMessage = 'Contract ID is invalid.';
      detailState = ContractDetailLoadState.error;
      notifyListeners();
      return;
    }
    if (!canAccess) {
      selectedContractId = id;
      selectedContract = null;
      detailErrorMessage =
          'Contract access is not authorized for this session.';
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
      if (selectedContractId != id) return;
      selectedContract = contract;
      detailState = ContractDetailLoadState.ready;
    } on SafeContractsApiException catch (error) {
      if (selectedContractId != id) return;
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
      if (selectedContractId != id) return;
      selectedContract = null;
      detailErrorMessage = error.toString();
      detailState = ContractDetailLoadState.error;
    }
    notifyListeners();
  }

  void clearContractDetail({int? expectedId}) {
    if (expectedId != null && selectedContractId != expectedId) return;
    selectedContractId = null;
    selectedContract = null;
    detailErrorMessage = null;
    detailState = ContractDetailLoadState.idle;
    notifyListeners();
  }
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
  if (value == null || value == '' || value == 0 || value == '0') return null;
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
  if (value is String && value.trim().isNotEmpty) return value.trim();
  if (value is num) return value.toString();
  throw FormatException('$field must be a non-empty string.');
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException(
      'Optional contract text must be string or null.',
    );
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

String? _payloadText(String? value) {
  final normalized = value?.trim();
  return normalized == null || normalized.isEmpty ? null : normalized;
}

String? _optionalIsoDate(Object? value, String field) {
  final text = _optionalText(value);
  if (text == null) return null;
  if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(text)) {
    throw FormatException('$field must be YYYY-MM-DD.');
  }
  final parts = text.split('-').map(int.parse).toList(growable: false);
  final parsed = DateTime.utc(parts[0], parts[1], parts[2]);
  if (parsed.year != parts[0] ||
      parsed.month != parts[1] ||
      parsed.day != parts[2]) {
    throw FormatException('$field must be a real calendar date.');
  }
  return text;
}

String? _optionalMoneyText(Object? value, String field) {
  if (value == null || value == '') return null;
  if (value is! String) {
    throw FormatException('$field must be an exact decimal money string.');
  }
  final text = value.trim();
  if (text.isEmpty || !RegExp(r'^\d+(?:\.\d{1,4})?$').hasMatch(text)) {
    throw FormatException('$field must be a non-negative decimal amount.');
  }
  return text;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true || 1 || '1' => true,
    false || 0 || '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
