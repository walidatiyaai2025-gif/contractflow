import '../../core/api/api_client.dart';
import '../dashboard/dashboard_models.dart';
import 'mobile_records.dart';

final class MobileRecordsRepository {
  MobileRecordsRepository(this.client);

  final SafeContractsApiClient client;

  Future<List<CustomerRecord>> customers({required int pageSize}) async {
    final response = await client.get(
      'customers',
      query: <String, String>{
        'page': '1',
        'per_page': _pageSize(pageSize),
        'sort': 'name',
        'order': 'asc',
      },
    );
    return _list(response, 'customers.data', CustomerRecord.fromData);
  }

  Future<CustomerRecord> customer(int id) async {
    final response = await client.get('customers/$id');
    return CustomerRecord.fromData(response.data);
  }

  Future<List<ContractRecord>> contracts(
    DashboardFilters filters, {
    required int pageSize,
  }) async {
    final response = await client.get(
      'contracts',
      query: <String, String>{
        ...filters.toQuery(includeDueRange: false),
        'page': '1',
        'per_page': _pageSize(pageSize),
        'sort': 'id',
        'order': 'desc',
      },
    );
    return _list(response, 'contracts.data', ContractRecord.fromData);
  }

  Future<ContractRecord> contract(int id) async {
    final response = await client.get('contracts/$id');
    return ContractRecord.fromData(response.data);
  }

  Future<void> editContractLight(
    int id, {
    String? contractNumber,
    bool updateDates = false,
    String? startDate,
    String? endDate,
  }) async {
    final body = <String, Object?>{
      if (contractNumber != null) 'contract_number': contractNumber,
      if (updateDates) 'start_date': startDate,
      if (updateDates) 'end_date': endDate,
    };
    if (body.isEmpty) {
      throw ArgumentError('At least one contract light-edit field is required.');
    }
    await client.patchJson('contracts/$id/light', body: body);
  }

  Future<List<PaymentRecord>> payments(
    DashboardFilters filters, {
    required int pageSize,
  }) async {
    final response = await client.get(
      'payments',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '1',
        'per_page': _pageSize(pageSize),
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    return _list(response, 'payments.data', PaymentRecord.fromData);
  }

  Future<PaymentRecord> payment(int id) async {
    final response = await client.get('payments/$id');
    return PaymentRecord.fromData(response.data);
  }

  Future<void> updateExpectedPaymentDate(int id, String? date) async {
    await client.patchJson(
      'payments/$id/expected-date',
      body: <String, Object?>{'expected_payment_date': date},
    );
  }

  Future<List<PaymentMethodOption>> paymentMethods() async {
    final response = await client.get('reference-data');
    final data = apiObjectMap(response.data, 'reference_data.data');
    final methods = apiObjectList(
      data['payment_methods'],
      'reference_data.payment_methods',
    );
    return List<PaymentMethodOption>.unmodifiable(
      methods.map(PaymentMethodOption.fromData),
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
    final response = await client.postJson(
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
    return CollectionReceipt.fromData(response.data);
  }

  Future<List<FollowUpQueueRecord>> followUps(
    DashboardFilters filters, {
    required int pageSize,
  }) async {
    final response = await client.get(
      'followups',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '1',
        'per_page': _pageSize(pageSize),
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    return _list(response, 'followups.data', FollowUpQueueRecord.fromData);
  }

  Future<List<FollowUpHistoryRecord>> followUpHistory(
    int paymentId, {
    required int pageSize,
  }) async {
    final response = await client.get(
      'payments/$paymentId/followups',
      query: <String, String>{
        'page': '1',
        'per_page': _pageSize(pageSize),
        'sort': 'created_at',
        'order': 'desc',
      },
    );
    return _list(
      response,
      'followup_history.data',
      FollowUpHistoryRecord.fromData,
    );
  }

  Future<FollowUpReceipt> recordFollowUp({
    required int paymentId,
    required String operation,
    String? note,
    String? promisedDate,
    String? deferredUntil,
  }) async {
    final response = await client.postJson(
      'payments/$paymentId/followups/record',
      body: <String, Object?>{
        'operation': operation,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
        if (promisedDate != null) 'promised_date': promisedDate,
        if (deferredUntil != null) 'deferred_until': deferredUntil,
      },
    );
    return FollowUpReceipt.fromData(response.data);
  }
}

List<T> _list<T>(
  ApiEnvelope response,
  String field,
  T Function(Object?) parser,
) {
  final items = apiObjectList(response.data, field);
  return List<T>.unmodifiable(items.map(parser));
}

String _pageSize(int value) => value.clamp(10, 100).toInt().toString();
