import '../../core/api/api_client.dart';
import '../dashboard/dashboard_models.dart';
import 'operations_models.dart';

final class MobileOperationsRepository {
  MobileOperationsRepository(this.client);

  final SafeContractsApiClient client;

  Future<List<CustomerRecord>> customers({int pageSize = 50}) async {
    final response = await client.get(
      'customers',
      query: <String, String>{
        'page': '1',
        'per_page': pageSize.clamp(1, 100).toString(),
        'sort': 'name',
        'order': 'asc',
      },
    );
    return _list(response, 'customers', CustomerRecord.fromData);
  }

  Future<CustomerRecord> customer(int id) async {
    final response = await client.get('customers/$id');
    return CustomerRecord.fromData(response.data);
  }

  Future<List<ContractRecord>> contracts(
    DashboardFilters filters, {
    int pageSize = 50,
  }) async {
    final response = await client.get(
      'contracts',
      query: <String, String>{
        ...filters.toQuery(includeDueRange: false),
        'page': '1',
        'per_page': pageSize.clamp(1, 100).toString(),
        'sort': 'id',
        'order': 'desc',
      },
    );
    return _list(response, 'contracts', ContractRecord.fromData);
  }

  Future<ContractRecord> contract(int id) async {
    final response = await client.get('contracts/$id');
    return ContractRecord.fromData(response.data);
  }

  Future<void> editContractNumber(int id, String contractNumber) async {
    await client.post(
      'contracts/$id/light-edit',
      body: <String, Object?>{'contract_number': contractNumber.trim()},
    );
  }

  Future<void> editContractDates(int id, String startDate, String endDate) async {
    await client.post(
      'contracts/$id/light-edit',
      body: <String, Object?>{
        'start_date': startDate.trim(),
        'end_date': endDate.trim(),
      },
    );
  }

  Future<List<PaymentRecord>> payments(
    DashboardFilters filters, {
    int pageSize = 50,
  }) async {
    final response = await client.get(
      'payments',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '1',
        'per_page': pageSize.clamp(1, 100).toString(),
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    return _list(response, 'payments', PaymentRecord.fromData);
  }

  Future<PaymentRecord> payment(int id) async {
    final response = await client.get('payments/$id');
    return PaymentRecord.fromData(response.data);
  }

  Future<void> updateExpectedPaymentDate(int id, String? expectedDate) async {
    await client.post(
      'payments/$id/light-edit',
      body: <String, Object?>{
        'expected_payment_date': expectedDate?.trim() ?? '',
      },
    );
  }

  Future<List<CollectionRecord>> collections(
    DashboardFilters filters, {
    int pageSize = 50,
  }) async {
    final response = await client.get(
      'collections',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '1',
        'per_page': pageSize.clamp(1, 100).toString(),
        'sort': 'collection_date',
        'order': 'desc',
      },
    );
    return _list(response, 'collections', CollectionRecord.fromData);
  }

  Future<List<PaymentMethodRecord>> paymentMethods() async {
    final response = await client.get('payment-methods');
    final methods = _list(
      response,
      'payment_methods',
      PaymentMethodRecord.fromData,
    );
    return List<PaymentMethodRecord>.unmodifiable(
      methods.where((method) => method.isActive),
    );
  }

  Future<int> recordCollection({
    required int paymentId,
    required String amount,
    required String collectionDate,
    required int paymentMethodId,
    String? reference,
    int? proofMediaId,
  }) async {
    final response = await client.post(
      'collections',
      body: <String, Object?>{
        'payment_id': paymentId,
        'amount': amount.trim(),
        'collection_date': collectionDate.trim(),
        'payment_method_id': paymentMethodId,
        if (reference != null && reference.trim().isNotEmpty)
          'reference': reference.trim(),
        if (proofMediaId != null) 'proof_media_id': proofMediaId,
      },
    );
    final data = apiObjectMap(response.data, 'collection_create.data');
    return readInt(data['collection_id'], 'collection_create.collection_id');
  }

  Future<List<FollowUpQueueRecord>> followUpQueue(
    DashboardFilters filters, {
    int pageSize = 50,
  }) async {
    final response = await client.get(
      'followups',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '1',
        'per_page': pageSize.clamp(1, 100).toString(),
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    return _list(response, 'followups', FollowUpQueueRecord.fromData);
  }

  Future<List<FollowUpHistoryRecord>> followUpHistory(
    int paymentId, {
    int pageSize = 50,
  }) async {
    final response = await client.get(
      'payments/$paymentId/followups',
      query: <String, String>{
        'page': '1',
        'per_page': pageSize.clamp(1, 100).toString(),
      },
    );
    return _list(response, 'followup_history', FollowUpHistoryRecord.fromData);
  }

  Future<int> recordFollowUp({
    required int paymentId,
    required String action,
    String? note,
    String? date,
  }) async {
    final response = await client.post(
      'payments/$paymentId/followups/action',
      body: <String, Object?>{
        'action': action.trim().toLowerCase(),
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
        if (date != null && date.trim().isNotEmpty) 'date': date.trim(),
      },
    );
    final data = apiObjectMap(response.data, 'followup_create.data');
    return readInt(data['followup_id'], 'followup_create.followup_id');
  }
}

List<T> _list<T>(
  ApiEnvelope response,
  String field,
  T Function(Object?) parser,
) {
  final items = apiObjectList(response.data, '$field.data');
  return List<T>.unmodifiable(items.map<T>(parser));
}
