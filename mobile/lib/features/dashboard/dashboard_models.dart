import '../../core/api/api_client.dart';

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

  Map<String, String> toQuery({
    bool includeContract = true,
    bool includeDueRange = true,
  }) {
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
      contractCount: _int(data['contract_count'], 'contract_count'),
      scheduledTotal: _string(data['scheduled_total'], '0.0000'),
      remainingTotal: _string(data['remaining_total'], '0.0000'),
      overdueExposure: _string(data['overdue_exposure'], '0.0000'),
      collectedTotal: _string(data['collected_total'], '0.0000'),
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
      id: _int(data['id'], 'customer.id'),
      name: _string(data['name'], ''),
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
      id: _int(data['id'], 'contract.id'),
      contractNumber: _string(data['contract_number'], ''),
      customerId: _int(data['customer_id'], 'contract.customer_id'),
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
    final customers = apiObjectList(data['customers'], 'dashboard.customers');
    final contracts = apiObjectList(data['contracts'], 'dashboard.contracts');
    return DashboardOverview(
      kpis: DashboardKpis.fromData(data['kpis']),
      customers: List<CustomerOption>.unmodifiable(
        customers.map(CustomerOption.fromData),
      ),
      contracts: List<ContractOption>.unmodifiable(
        contracts.map(ContractOption.fromData),
      ),
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
      id: _int(data['id'], 'contract.id'),
      type: DashboardRecordType.contract,
      title: _string(data['contract_number'], 'Contract'),
      status: _nullableString(data['status']),
      customerName: _nullableString(data['customer_name']),
      amount: _nullableString(data['base_value']),
    );
  }

  factory DashboardRecord.payment(Object? value) {
    final data = apiObjectMap(value, 'payments.item');
    final id = _int(data['id'], 'payment.id');
    return DashboardRecord(
      id: id,
      type: DashboardRecordType.payment,
      title: _nullableString(data['reference']) ?? 'Payment #$id',
      status: _nullableString(data['status']),
      date: _nullableString(data['due_date']),
      customerName: _nullableString(data['customer_name']),
      remainingAmount: _nullableString(data['remaining_amount']),
      amount: _nullableString(data['original_amount']),
    );
  }

  factory DashboardRecord.collection(Object? value) {
    final data = apiObjectMap(value, 'collections.item');
    final id = _int(data['id'], 'collection.id');
    return DashboardRecord(
      id: id,
      type: DashboardRecordType.collection,
      title: _nullableString(data['reference']) ?? 'Collection #$id',
      status: _nullableString(data['payment_status']),
      date: _nullableString(data['collection_date']),
      customerName: _nullableString(data['customer_name']),
      remainingAmount: _nullableString(data['remaining_amount']),
      amount: _nullableString(data['amount']),
    );
  }

  factory DashboardRecord.followUp(Object? value) {
    final data = apiObjectMap(value, 'followups.item');
    final id = _int(data['payment_id'], 'followup.payment_id');
    return DashboardRecord(
      id: id,
      type: DashboardRecordType.followUp,
      title: _nullableString(data['reference']) ?? 'Payment #$id',
      status: _nullableString(data['followup_state']) ??
          _nullableString(data['status']),
      date: _nullableString(data['due_date']),
      remainingAmount: _nullableString(data['remaining_amount']),
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

int _int(Object? value, String field) {
  if (value is int) {
    return value;
  }
  if (value is String) {
    final parsed = int.tryParse(value);
    if (parsed != null) {
      return parsed;
    }
  }
  throw FormatException('$field must be an integer.');
}

String _string(Object? value, String fallback) {
  if (value is String) {
    return value;
  }
  if (value is num) {
    return value.toString();
  }
  return fallback;
}

String? _nullableString(Object? value) {
  if (value == null) {
    return null;
  }
  if (value is String) {
    return value.isEmpty ? null : value;
  }
  if (value is num) {
    return value.toString();
  }
  return null;
}
