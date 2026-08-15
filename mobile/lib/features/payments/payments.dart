import '../../core/api/api_client.dart';
import '../dashboard/dashboard_models.dart';

final class SafeContractsPayment {
  const SafeContractsPayment({
    required this.id,
    required this.contractId,
    required this.sequenceNo,
    required this.dueDate,
    required this.originalAmount,
    required this.paidAmount,
    required this.remainingAmount,
    required this.status,
    required this.contractIsArchived,
    this.contractNumber,
    this.customerId,
    this.customerName,
    this.accountantUserId,
    this.reference,
    this.expectedPaymentDate,
  });

  final int id;
  final int contractId;
  final String? contractNumber;
  final int? customerId;
  final String? customerName;
  final int? accountantUserId;
  final int sequenceNo;
  final String? reference;
  final String dueDate;
  final String? expectedPaymentDate;
  final String originalAmount;
  final String paidAmount;
  final String remainingAmount;
  final String status;
  final bool contractIsArchived;

  factory SafeContractsPayment.fromData(Object? value) {
    final data = apiObjectMap(value, 'payment');
    return SafeContractsPayment(
      id: _positiveInt(data['id'], 'payment.id'),
      contractId: _positiveInt(data['contract_id'], 'payment.contract_id'),
      contractNumber: _optionalText(data['contract_number']),
      customerId:
          _optionalPositiveInt(data['customer_id'], 'payment.customer_id'),
      customerName: _optionalText(data['customer_name']),
      accountantUserId: _optionalPositiveInt(
        data['accountant_user_id'],
        'payment.accountant_user_id',
      ),
      sequenceNo: _positiveInt(data['sequence_no'], 'payment.sequence_no'),
      reference: _optionalText(data['reference']),
      dueDate: _requiredText(data['due_date'], 'payment.due_date'),
      expectedPaymentDate: _optionalText(data['expected_payment_date']),
      originalAmount:
          _scalarText(data['original_amount'], 'payment.original_amount'),
      paidAmount: _scalarText(data['paid_amount'], 'payment.paid_amount'),
      remainingAmount:
          _scalarText(data['remaining_amount'], 'payment.remaining_amount'),
      status: _requiredText(data['status'], 'payment.status'),
      contractIsArchived: _boolish(
          data['contract_is_archived'], 'payment.contract_is_archived'),
    );
  }
}

final class PaymentPage {
  const PaymentPage({
    required this.payments,
    required this.page,
    required this.perPage,
    required this.hasMore,
    required this.sort,
    required this.order,
  });

  final List<SafeContractsPayment> payments;
  final int page;
  final int perPage;
  final bool hasMore;
  final String sort;
  final String order;

  factory PaymentPage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'payments.data');
    final meta = envelope.meta;
    final payments = rows.map(SafeContractsPayment.fromData).toList();
    final ids = <int>{};
    for (final payment in payments) {
      if (!ids.add(payment.id)) {
        throw const FormatException('payments contain duplicate IDs.');
      }
    }
    return PaymentPage(
      payments: List<SafeContractsPayment>.unmodifiable(payments),
      page: _boundedInt(meta['page'], 'meta.page', 1, 5),
      perPage: _boundedInt(meta['per_page'], 'meta.per_page', 1, 100),
      hasMore: _boolish(meta['has_more'], 'meta.has_more'),
      sort: _requiredText(meta['sort'], 'meta.sort'),
      order: _order(meta['order']),
    );
  }
}

final class PaymentsRepository {
  PaymentsRepository(this.client);

  final SafeContractsApiClient client;

  Future<PaymentPage> loadPage({
    required int page,
    required int perPage,
    required DashboardFilters filters,
  }) async {
    if (page < 1 || page > 5) {
      throw ArgumentError('Payment page must be between 1 and 5.');
    }
    filters.validate();
    final envelope = await client.get(
      'payments',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '$page',
        'per_page': '${perPage.clamp(1, 100)}',
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    final result = PaymentPage.fromEnvelope(envelope);
    if (result.sort != 'due_date' || result.order != 'asc') {
      throw const FormatException(
          'Payment paging metadata is not deterministic.');
    }
    return result;
  }

  Future<SafeContractsPayment> loadPayment(int id) async {
    if (id <= 0) throw ArgumentError('Payment ID must be positive.');
    final envelope = await client.get('payments/$id');
    final payment = SafeContractsPayment.fromData(envelope.data);
    if (payment.id != id) {
      throw const FormatException(
          'Payment detail ID does not match the request.');
    }
    return payment;
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
  if (value == null || value == '') return null;
  return _positiveInt(value, field);
}

int _boundedInt(Object? value, String field, int min, int max) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed < min || parsed > max) {
    throw FormatException('$field is outside the supported range.');
  }
  return parsed;
}

String _requiredText(Object? value, String field) {
  if (value is String && value.trim().isNotEmpty) return value.trim();
  if (value is num) return value.toString();
  throw FormatException('$field must be present.');
}

String _scalarText(Object? value, String field) {
  if (value is String) return value;
  if (value is num) return value.toString();
  throw FormatException('$field must be scalar.');
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException(
        'Optional payment text must be string or null.');
  }
  final text = value.trim();
  return text.isEmpty ? null : text;
}

String _order(Object? value) {
  final order = _requiredText(value, 'meta.order').toLowerCase();
  if (order != 'asc' && order != 'desc') {
    throw const FormatException('Payment order metadata is invalid.');
  }
  return order;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true || 1 || '1' => true,
    false || 0 || '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
