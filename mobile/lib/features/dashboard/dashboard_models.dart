import '../../core/api/api_client.dart';

const dashboardSupportedStatuses = <String>{
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

final class DashboardFilters {
  const DashboardFilters({
    this.customerId,
    this.contractId,
    this.status,
    this.dueFrom,
    this.dueTo,
  });

  final int? customerId;
  final int? contractId;
  final String? status;
  final String? dueFrom;
  final String? dueTo;

  DashboardFilters withCustomer(int? value) {
    return DashboardFilters(
      customerId: value,
      status: status,
      dueFrom: dueFrom,
      dueTo: dueTo,
    );
  }

  DashboardFilters withContract(int? value) {
    return DashboardFilters(
      customerId: customerId,
      contractId: value,
      status: status,
      dueFrom: dueFrom,
      dueTo: dueTo,
    );
  }

  DashboardFilters withStatus(String? value) {
    return DashboardFilters(
      customerId: customerId,
      contractId: contractId,
      status: value,
      dueFrom: dueFrom,
      dueTo: dueTo,
    );
  }

  DashboardFilters withDueRange(String? from, String? to) {
    return DashboardFilters(
      customerId: customerId,
      contractId: contractId,
      status: status,
      dueFrom: from,
      dueTo: to,
    );
  }

  void validate() {
    if (customerId != null && customerId! <= 0) {
      throw ArgumentError.value(customerId, 'customerId', 'Must be positive.');
    }
    if (contractId != null && contractId! <= 0) {
      throw ArgumentError.value(contractId, 'contractId', 'Must be positive.');
    }
    if (status != null &&
        status!.isNotEmpty &&
        !dashboardSupportedStatuses.contains(status)) {
      throw ArgumentError.value(status, 'status', 'Unsupported dashboard status.');
    }
    _validateIsoDate(dueFrom, 'dueFrom');
    _validateIsoDate(dueTo, 'dueTo');
    if (dueFrom != null && dueTo != null && dueFrom!.compareTo(dueTo!) > 0) {
      throw ArgumentError('Dashboard due date range is reversed.');
    }
  }

  Map<String, String> toQuery({
    bool includeContract = true,
    bool includeDueRange = true,
  }) {
    validate();
    return <String, String>{
      if (customerId != null) 'customer_id': customerId.toString(),
      if (includeContract && contractId != null)
        'contract_id': contractId.toString(),
      if (status != null && status!.isNotEmpty) 'status': status!,
      if (includeDueRange && dueFrom != null) 'due_from': dueFrom!,
      if (includeDueRange && dueTo != null) 'due_to': dueTo!,
    };
  }
}

final class DashboardKpis {
  const DashboardKpis({
    required this.contractCount,
    required this.scheduledTotal,
    required this.remainingTotal,
    required this.overdueExposure,
    required this.collectedTotal,
  });

  final int contractCount;
  final String scheduledTotal;
  final String remainingTotal;
  final String overdueExposure;
  final String collectedTotal;

  factory DashboardKpis.fromData(Object? value) {
    final data = apiObjectMap(value, 'dashboard.kpis');
    return DashboardKpis(
      contractCount: _nonNegativeInt(
        data['contract_count'],
        'dashboard.kpis.contract_count',
      ),
      scheduledTotal: _moneyText(
        data['scheduled_total'],
        'dashboard.kpis.scheduled_total',
      ),
      remainingTotal: _moneyText(
        data['remaining_total'],
        'dashboard.kpis.remaining_total',
      ),
      overdueExposure: _moneyText(
        data['overdue_exposure'],
        'dashboard.kpis.overdue_exposure',
      ),
      collectedTotal: _moneyText(
        data['collected_total'],
        'dashboard.kpis.collected_total',
      ),
    );
  }
}

final class CustomerOption {
  const CustomerOption({required this.id, required this.name});

  final int id;
  final String name;

  factory CustomerOption.fromData(Object? value) {
    final data = apiObjectMap(value, 'dashboard.customer');
    return CustomerOption(
      id: _positiveInt(data['id'], 'customer.id'),
      name: _requiredText(data['name'], 'customer.name'),
    );
  }
}

final class ContractOption {
  const ContractOption({
    required this.id,
    required this.contractNumber,
    required this.customerId,
  });

  final int id;
  final String contractNumber;
  final int customerId;

  factory ContractOption.fromData(Object? value) {
    final data = apiObjectMap(value, 'dashboard.contract');
    return ContractOption(
      id: _positiveInt(data['id'], 'contract.id'),
      contractNumber: _requiredText(
        data['contract_number'],
        'contract.contract_number',
      ),
      customerId: _positiveInt(data['customer_id'], 'contract.customer_id'),
    );
  }
}

final class DashboardOverview {
  const DashboardOverview({
    required this.kpis,
    required this.customers,
    required this.contracts,
  });

  final DashboardKpis kpis;
  final List<CustomerOption> customers;
  final List<ContractOption> contracts;

  factory DashboardOverview.fromData(Object? value) {
    final data = apiObjectMap(value, 'dashboard.data');
    final rawCustomers = apiObjectList(data['customers'], 'dashboard.customers');
    final rawContracts = apiObjectList(data['contracts'], 'dashboard.contracts');
    final customers = rawCustomers.map(CustomerOption.fromData).toList();
    final contracts = rawContracts.map(ContractOption.fromData).toList();
    _ensureUniqueIds(
      customers.map((item) => item.id),
      'dashboard customer options',
    );
    _ensureUniqueIds(
      contracts.map((item) => item.id),
      'dashboard contract options',
    );
    return DashboardOverview(
      kpis: DashboardKpis.fromData(data['kpis']),
      customers: List<CustomerOption>.unmodifiable(customers),
      contracts: List<ContractOption>.unmodifiable(contracts),
    );
  }
}

enum DashboardRecordType { contract, payment, collection, followUp }

final class DashboardRecord {
  const DashboardRecord({
    required this.id,
    required this.type,
    required this.title,
    this.status,
    this.date,
    this.customerName,
    this.remainingAmount,
    this.amount,
  });

  final int id;
  final DashboardRecordType type;
  final String title;
  final String? status;
  final String? date;
  final String? customerName;
  final String? remainingAmount;
  final String? amount;

  factory DashboardRecord.contract(Object? value) {
    final data = apiObjectMap(value, 'contracts.item');
    return DashboardRecord(
      id: _positiveInt(data['id'], 'contract.id'),
      type: DashboardRecordType.contract,
      title: _requiredText(data['contract_number'], 'contract.contract_number'),
      status: _optionalText(data['status'], 'contract.status'),
      customerName: _optionalText(
        data['customer_name'],
        'contract.customer_name',
      ),
      amount: _optionalMoneyText(data['base_value'], 'contract.base_value'),
    );
  }

  factory DashboardRecord.payment(Object? value) {
    final data = apiObjectMap(value, 'payments.item');
    final id = _positiveInt(data['id'], 'payment.id');
    return DashboardRecord(
      id: id,
      type: DashboardRecordType.payment,
      title: _optionalText(data['reference'], 'payment.reference') ??
          'Payment #$id',
      status: _optionalText(data['status'], 'payment.status'),
      date: _optionalDate(data['due_date'], 'payment.due_date'),
      customerName: _optionalText(
        data['customer_name'],
        'payment.customer_name',
      ),
      remainingAmount: _optionalMoneyText(
        data['remaining_amount'],
        'payment.remaining_amount',
      ),
      amount: _optionalMoneyText(
        data['original_amount'],
        'payment.original_amount',
      ),
    );
  }

  factory DashboardRecord.collection(Object? value) {
    final data = apiObjectMap(value, 'collections.item');
    final id = _positiveInt(data['id'], 'collection.id');
    return DashboardRecord(
      id: id,
      type: DashboardRecordType.collection,
      title: _optionalText(data['reference'], 'collection.reference') ??
          'Collection #$id',
      status: _optionalText(
        data['payment_status'],
        'collection.payment_status',
      ),
      date: _optionalDate(
        data['collection_date'],
        'collection.collection_date',
      ),
      customerName: _optionalText(
        data['customer_name'],
        'collection.customer_name',
      ),
      remainingAmount: _optionalMoneyText(
        data['remaining_amount'],
        'collection.remaining_amount',
      ),
      amount: _optionalMoneyText(data['amount'], 'collection.amount'),
    );
  }

  factory DashboardRecord.followUp(Object? value) {
    final data = apiObjectMap(value, 'followups.item');
    final id = _positiveInt(data['payment_id'], 'followup.payment_id');
    return DashboardRecord(
      id: id,
      type: DashboardRecordType.followUp,
      title: _optionalText(data['reference'], 'followup.reference') ??
          'Payment #$id',
      status: _optionalText(data['followup_state'], 'followup.followup_state') ??
          _optionalText(data['status'], 'followup.status'),
      date: _optionalDate(data['due_date'], 'followup.due_date'),
      remainingAmount: _optionalMoneyText(
        data['remaining_amount'],
        'followup.remaining_amount',
      ),
    );
  }
}

final class DashboardLists {
  const DashboardLists({
    required this.contracts,
    required this.payments,
    required this.collections,
    required this.followUps,
  });

  final List<DashboardRecord> contracts;
  final List<DashboardRecord> payments;
  final List<DashboardRecord> collections;
  final List<DashboardRecord> followUps;

  bool get isEmpty =>
      contracts.isEmpty &&
      payments.isEmpty &&
      collections.isEmpty &&
      followUps.isEmpty;
}

int _positiveInt(Object? value, String field) {
  final parsed = _parseInt(value);
  if (parsed == null || parsed <= 0) {
    throw FormatException('$field must be a positive integer.');
  }
  return parsed;
}

int _nonNegativeInt(Object? value, String field) {
  final parsed = _parseInt(value);
  if (parsed == null || parsed < 0) {
    throw FormatException('$field must be a non-negative integer.');
  }
  return parsed;
}

int? _parseInt(Object? value) {
  if (value is int) {
    return value;
  }
  if (value is String && RegExp(r'^\d+$').hasMatch(value)) {
    return int.tryParse(value);
  }
  return null;
}

String _requiredText(Object? value, String field) {
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('$field must be a non-empty string.');
  }
  return value.trim();
}

String? _optionalText(Object? value, String field) {
  if (value == null) {
    return null;
  }
  if (value is! String) {
    throw FormatException('$field must be a string or null.');
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

String _moneyText(Object? value, String field) {
  if (value is! String || !RegExp(r'^\d+(?:\.\d{1,4})?$').hasMatch(value)) {
    throw FormatException('$field must be a non-negative decimal string.');
  }
  return value;
}

String? _optionalMoneyText(Object? value, String field) {
  if (value == null) {
    return null;
  }
  return _moneyText(value, field);
}

String? _optionalDate(Object? value, String field) {
  if (value == null) {
    return null;
  }
  if (value is! String) {
    throw FormatException('$field must use YYYY-MM-DD or null.');
  }
  try {
    _validateIsoDate(value, field);
  } on ArgumentError {
    throw FormatException('$field is not a valid YYYY-MM-DD date.');
  }
  return value;
}

void _validateIsoDate(String? value, String field) {
  if (value == null) {
    return;
  }
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(value);
  if (match == null) {
    throw ArgumentError('$field must use YYYY-MM-DD.');
  }
  final year = int.parse(match.group(1)!);
  final month = int.parse(match.group(2)!);
  final day = int.parse(match.group(3)!);
  final parsed = DateTime.tryParse(value);
  if (parsed == null ||
      parsed.year != year ||
      parsed.month != month ||
      parsed.day != day) {
    throw ArgumentError('$field is not a valid calendar date.');
  }
}

void _ensureUniqueIds(Iterable<int> ids, String field) {
  final seen = <int>{};
  for (final id in ids) {
    if (!seen.add(id)) {
      throw FormatException('$field contains a duplicate ID.');
    }
  }
}
