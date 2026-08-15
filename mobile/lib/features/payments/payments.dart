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
      customerId: _optionalPositiveInt(data['customer_id'], 'payment.customer_id'),
      customerName: _optionalText(data['customer_name']),
      accountantUserId: _optionalPositiveInt(
        data['accountant_user_id'],
        'payment.accountant_user_id',
      ),
      sequenceNo: _positiveInt(data['sequence_no'], 'payment.sequence_no'),
      reference: _optionalText(data['reference']),
      dueDate: _requiredText(data['due_date'], 'payment.due_date'),
      expectedPaymentDate: _optionalText(data['expected_payment_date']),
      originalAmount: _scalarText(data['original_amount'], 'payment.original_amount'),
      paidAmount: _scalarText(data['paid_amount'], 'payment.paid_amount'),
      remainingAmount:
          _scalarText(data['remaining_amount'], 'payment.remaining_amount'),
      status: _requiredText(data['status'], 'payment.status'),
      contractIsArchived:
          _boolish(data['contract_is_archived'], 'payment.contract_is_archived'),
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
    return PaymentPage(
      payments: List<SafeContractsPayment>.unmodifiable(
        rows.map(SafeContractsPayment.fromData),
      ),
      page: _boundedInt(meta['page'], 'meta.page', 1, 5),
      perPage: _boundedInt(meta['per_page'], 'meta.per_page', 1, 100),
      hasMore: _boolish(meta['has_more'], 'meta.has_more'),
      sort: _requiredText(meta['sort'], 'meta.sort'),
      order: _requiredText(meta['order'], 'meta.order'),
    );
  }
}

final class PaymentMethodOption {
  const PaymentMethodOption({
    required this.id,
    required this.code,
    required this.name,
    required this.displayOrder,
  });

  final int id;
  final String code;
  final String name;
  final int displayOrder;

  factory PaymentMethodOption.fromData(Object? value) {
    final data = apiObjectMap(value, 'payment_method');
    return PaymentMethodOption(
      id: _positiveInt(data['id'], 'payment_method.id'),
      code: _requiredText(data['code'], 'payment_method.code'),
      name: _requiredText(data['name'], 'payment_method.name'),
      displayOrder:
          _nonNegativeInt(data['display_order'], 'payment_method.display_order'),
    );
  }
}

final class CollectionReceipt {
  const CollectionReceipt({required this.id, required this.paymentId});

  final int id;
  final int paymentId;

  factory CollectionReceipt.fromData(Object? value) {
    final data = apiObjectMap(value, 'collection_receipt');
    return CollectionReceipt(
      id: _positiveInt(data['id'], 'collection_receipt.id'),
      paymentId:
          _positiveInt(data['payment_id'], 'collection_receipt.payment_id'),
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
    return PaymentPage.fromEnvelope(envelope);
  }

  Future<SafeContractsPayment> loadPayment(int id) async {
    if (id <= 0) throw ArgumentError('Payment ID must be positive.');
    final envelope = await client.get('payments/$id');
    return SafeContractsPayment.fromData(envelope.data);
  }

  Future<void> updateExpectedPaymentDate(int id, String? date) async {
    await client.patchJson(
      'payments/$id/expected-date',
      body: <String, Object?>{'expected_payment_date': date},
    );
  }

  Future<List<PaymentMethodOption>> paymentMethods() async {
    final envelope = await client.get('reference-data');
    final data = apiObjectMap(envelope.data, 'reference_data.data');
    final rows = apiObjectList(
      data['payment_methods'],
      'reference_data.payment_methods',
    );
    return List<PaymentMethodOption>.unmodifiable(
      rows.map(PaymentMethodOption.fromData),
    );
  }

  Future<CollectionReceipt> recordCollection({
    required int paymentId,
    required String amount,
    required String collectionDate,
    required int paymentMethodId,
    String? reference,
    int? proofMediaId,
  }) async {
    final envelope = await client.postJson(
      'collections/record',
      body: <String, Object?>{
        'payment_id': paymentId,
        'amount': amount,
        'collection_date': collectionDate,
        'payment_method_id': paymentMethodId,
        if (reference != null && reference.trim().isNotEmpty)
          'reference': reference.trim(),
        if (proofMediaId != null) 'proof_media_id': proofMediaId,
      },
    );
    return CollectionReceipt.fromData(envelope.data);
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

int _nonNegativeInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed < 0) {
    throw FormatException('$field must be non-negative.');
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
    throw const FormatException('Optional payment text must be string or null.');
  }
  final text = value.trim();
  return text.isEmpty ? null : text;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true || 1 || '1' => true,
    false || 0 || '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
