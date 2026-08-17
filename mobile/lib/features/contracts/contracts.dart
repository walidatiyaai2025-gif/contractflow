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
  const ContractsFilters({this.customerId, this.status});

  final int? customerId;
  final String? status;

  ContractsFilters withCustomer(int? value) =>
      ContractsFilters(customerId: value, status: status);
  ContractsFilters withStatus(String? value) =>
      ContractsFilters(customerId: customerId, status: value);

  void validate() {
    if (customerId != null && customerId! <= 0) {
      throw ArgumentError.value(customerId, 'customerId', 'Invalid customer.');
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
      if (status != null && status!.isNotEmpty) 'status': status!,
    };
  }
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
    final type = _optionalText(data['counterparty_type'])?.toLowerCase() ??
        (legacyCustomerId != null ? 'customer' : '');
    if (type != 'customer' && type != 'supplier') {
      throw const FormatException('contract.counterparty_type is invalid.');
    }
    final counterpartyId = _optionalPositiveInt(
          data['counterparty_id'],
          'contract.counterparty_id',
        ) ??
        (type == 'customer' ? legacyCustomerId : null);
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
    final currency =
        (_optionalText(data['currency_code']) ?? 'UNSET').toUpperCase();
    if (currency != 'UNSET' && !RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
      throw const FormatException('contract.currency_code is invalid.');
    }

    return SafeContractsContract(
      id: _positiveInt(data['id'], 'contract.id'),
      contractNumber: _requiredText(
        data['contract_number'],
        'contract.contract_number',
      ),
      customerId:
          type == 'customer' ? (legacyCustomerId ?? counterpartyId) : null,
      customerName: _optionalText(data['customer_name']),
      counterpartyType: type,
      counterpartyId: counterpartyId,
      counterpartyName: _optionalText(data['counterparty_name']) ??
          _optionalText(data['customer_name']),
      financialDirection: direction,
      currencyCode: currency,
      accountantUserId: _optionalPositiveInt(
        data['accountant_user_id'],
        'contract.accountant_user_id',
      ),
      status: _requiredText(data['status'], 'contract.status'),
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
    final contracts =
        rows.map(SafeContractsContract.fromData).toList(growable: false);
    final ids = <int>{};
    for (final contract in contracts) {
      if (!ids.add(contract.id)) {
        throw const FormatException('contracts contain duplicate IDs.');
      }
    }
    final meta = envelope.meta;
    final page = _boundedInt(meta['page'], 'meta.page', minimum: 1, maximum: 5);
    final perPage = _boundedInt(
      meta['per_page'],
      'meta.per_page',
      minimum: 1,
      maximum: 100,
    );
    final boundedWindow = _boundedInt(
      meta['bounded_window'],
      'meta.bounded_window',
      minimum: 1,
      maximum: 500,
    );
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
    return ContractPage(
      contracts: List<SafeContractsContract>.unmodifiable(contracts),
      page: page,
      perPage: perPage,
      sort: sort,
      order: order,
      hasMore: _boolish(meta['has_more'], 'meta.has_more'),
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
  }) async {
    if (page < 1 || page > 5)
      throw ArgumentError('Contract page must be between 1 and 5.');
    if (perPage < 1 || perPage > 100)
      throw ArgumentError('Contract page size must be between 1 and 100.');
    if (!ContractSortOption.values.contains(sort))
      throw ArgumentError('Unsupported contract sort.');
    filters.validate();
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
    if (id <= 0) throw ArgumentError('Contract ID must be positive.');
    final envelope = await client.get('contracts/$id');
    return SafeContractsContract.fromData(envelope.data);
  }
}

final class ContractsController extends ChangeNotifier {
  ContractsController({
    required this.repository,
    required int pageSize,
    required this.canAccess,
    required this.canEditContract,
  }) : pageSize = pageSize.clamp(1, 100).toInt();

  static const supportedContractStatuses = _supportedContractFilterStatuses;

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

  Future<void> ensureLoaded() async {
    if (state == ContractsLoadState.idle) await loadPage(1);
  }

  Future<void> loadPage(int page) async {
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Contract access is not authorized for this session.';
      state = ContractsLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || page > 5) return;
    currentPage = null;
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
      currentPage = null;
      errorMessage = error.message;
      state = ContractsLoadState.error;
    } on Object catch (error) {
      currentPage = null;
      errorMessage = error.toString();
      state = ContractsLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refresh() => loadPage(currentPage?.page ?? 1);

  Future<void> refreshSilently() async {
    if (!canAccess) return;
    try {
      currentPage = await repository.loadPage(
        page: currentPage?.page ?? 1,
        perPage: pageSize,
        filters: filters,
        sort: sort,
      );
      state = ContractsLoadState.ready;
      errorMessage = null;
    } on Object {
      // Keep the last authorized snapshot on silent refresh failure.
    }
  }

  Future<void> previousPage() async {
    final page = currentPage?.page ?? 1;
    if (page > 1) await loadPage(page - 1);
  }

  Future<void> nextPage() async {
    final value = currentPage;
    if (value != null && value.hasMore && value.page < 5)
      await loadPage(value.page + 1);
  }

  Future<void> selectCustomer(int? customerId) async {
    if (customerId != null && customerId <= 0)
      throw ArgumentError.value(customerId, 'customerId', 'Invalid customer.');
    filters = filters.withCustomer(customerId);
    await loadPage(1);
  }

  Future<void> selectStatus(String? status) async {
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

  Future<void> selectSort(ContractSortOption nextSort) async {
    if (!ContractSortOption.values.contains(nextSort))
      throw ArgumentError.value(nextSort, 'nextSort', 'Unsupported sort.');
    if (identical(sort, nextSort) && currentPage != null) return;
    sort = nextSort;
    await loadPage(1);
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
  if (parsed == null || parsed <= 0)
    throw FormatException('$field must be a positive integer.');
  return parsed;
}

int? _optionalPositiveInt(Object? value, String field) {
  if (value == null || value == '') return null;
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
  if (parsed == null || parsed < minimum || parsed > maximum)
    throw FormatException('$field is outside the supported range.');
  return parsed;
}

String _requiredText(Object? value, String field) {
  if (value is! String || value.trim().isEmpty)
    throw FormatException('$field must be a non-empty string.');
  final normalized = value.trim();
  if (normalized.length > 256) throw FormatException('$field is too long.');
  return normalized;
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String)
    throw const FormatException('Contract text field must be string or null.');
  final normalized = value.trim();
  if (normalized.length > 256)
    throw const FormatException('Contract text field is too long.');
  return normalized.isEmpty ? null : normalized;
}

String? _optionalIsoDate(Object? value, String field) {
  final text = _optionalText(value);
  if (text == null) return null;
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(text);
  if (match == null) throw FormatException('$field must use YYYY-MM-DD.');
  final parsed = DateTime.tryParse(text);
  if (parsed == null ||
      parsed.year != int.parse(match.group(1)!) ||
      parsed.month != int.parse(match.group(2)!) ||
      parsed.day != int.parse(match.group(3)!)) {
    throw FormatException('$field is invalid.');
  }
  return text;
}

String? _optionalMoneyText(Object? value, String field) {
  if (value == null || value == '') return null;
  if (value is! String)
    throw FormatException('$field must be an exact server money string.');
  final normalized = value.trim();
  if (!RegExp(r'^\d+(?:\.\d{1,4})?$').hasMatch(normalized))
    throw FormatException('$field is invalid.');
  return normalized;
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
