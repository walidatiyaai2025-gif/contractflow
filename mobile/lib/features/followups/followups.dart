import '../../core/api/api_client.dart';
import '../dashboard/dashboard_models.dart';

final class FollowUpQueueItem {
  const FollowUpQueueItem({
    required this.paymentId,
    required this.contractId,
    required this.dueDate,
    required this.remainingAmount,
    required this.paymentStatus,
    this.reference,
    this.expectedPaymentDate,
    this.followUpState,
  });

  final int paymentId;
  final int contractId;
  final String? reference;
  final String dueDate;
  final String? expectedPaymentDate;
  final String remainingAmount;
  final String paymentStatus;
  final String? followUpState;

  factory FollowUpQueueItem.fromData(Object? value) {
    final data = apiObjectMap(value, 'followup_queue');
    return FollowUpQueueItem(
      paymentId: _positiveInt(data['payment_id'], 'followup.payment_id'),
      contractId: _positiveInt(data['contract_id'], 'followup.contract_id'),
      reference: _optionalText(data['reference']),
      dueDate: _requiredText(data['due_date'], 'followup.due_date'),
      expectedPaymentDate: _optionalText(data['expected_payment_date']),
      remainingAmount: _scalarText(data['remaining_amount'], 'followup.remaining_amount'),
      paymentStatus: _requiredText(data['status'], 'followup.status'),
      followUpState: _optionalText(data['followup_state']),
    );
  }
}

final class FollowUpQueuePage {
  const FollowUpQueuePage({required this.items, required this.page, required this.hasMore});
  final List<FollowUpQueueItem> items;
  final int page;
  final bool hasMore;

  factory FollowUpQueuePage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'followups.data');
    return FollowUpQueuePage(
      items: List<FollowUpQueueItem>.unmodifiable(rows.map(FollowUpQueueItem.fromData)),
      page: _boundedInt(envelope.meta['page'], 'meta.page', 1, 5),
      hasMore: _boolish(envelope.meta['has_more'], 'meta.has_more'),
    );
  }
}

final class FollowUpHistoryItem {
  const FollowUpHistoryItem({
    required this.id,
    required this.paymentId,
    required this.state,
    required this.createdAt,
    this.note,
    this.promisedDate,
    this.deferredUntil,
  });

  final int id;
  final int paymentId;
  final String state;
  final String? note;
  final String? promisedDate;
  final String? deferredUntil;
  final String createdAt;

  factory FollowUpHistoryItem.fromData(Object? value) {
    final data = apiObjectMap(value, 'followup_history');
    return FollowUpHistoryItem(
      id: _positiveInt(data['id'], 'followup_history.id'),
      paymentId: _positiveInt(data['payment_id'], 'followup_history.payment_id'),
      state: _requiredText(data['state'], 'followup_history.state'),
      note: _optionalText(data['note']),
      promisedDate: _optionalText(data['promised_date']),
      deferredUntil: _optionalText(data['deferred_until']),
      createdAt: _requiredText(data['created_at'], 'followup_history.created_at'),
    );
  }
}

final class FollowUpReceipt {
  const FollowUpReceipt({required this.id, required this.paymentId});
  final int id;
  final int paymentId;

  factory FollowUpReceipt.fromData(Object? value) {
    final data = apiObjectMap(value, 'followup_receipt');
    return FollowUpReceipt(
      id: _positiveInt(data['id'], 'followup_receipt.id'),
      paymentId: _positiveInt(data['payment_id'], 'followup_receipt.payment_id'),
    );
  }
}

final class FollowUpsRepository {
  FollowUpsRepository(this.client);
  final SafeContractsApiClient client;

  Future<FollowUpQueuePage> loadQueue({
    required int page,
    required int perPage,
    required DashboardFilters filters,
  }) async {
    final envelope = await client.get(
      'followups',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '${page.clamp(1, 5)}',
        'per_page': '${perPage.clamp(1, 100)}',
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    return FollowUpQueuePage.fromEnvelope(envelope);
  }

  Future<List<FollowUpHistoryItem>> loadHistory(int paymentId, {required int perPage}) async {
    final envelope = await client.get(
      'payments/$paymentId/followups',
      query: <String, String>{
        'page': '1',
        'per_page': '${perPage.clamp(1, 100)}',
        'sort': 'created_at',
        'order': 'desc',
      },
    );
    final rows = apiObjectList(envelope.data, 'followup_history.data');
    return List<FollowUpHistoryItem>.unmodifiable(rows.map(FollowUpHistoryItem.fromData));
  }

  Future<FollowUpReceipt> record({
    required int paymentId,
    required String operation,
    String? note,
    String? promisedDate,
    String? deferredUntil,
  }) async {
    final envelope = await client.postJson(
      'payments/$paymentId/followups/record',
      body: <String, Object?>{
        'operation': operation,
        if (note != null && note.trim().isNotEmpty) 'note': note.trim(),
        if (promisedDate != null) 'promised_date': promisedDate,
        if (deferredUntil != null) 'deferred_until': deferredUntil,
      },
    );
    return FollowUpReceipt.fromData(envelope.data);
  }
}

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) throw FormatException('$field must be positive.');
  return parsed;
}

int _boundedInt(Object? value, String field, int min, int max) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed < min || parsed > max) throw FormatException('$field is outside range.');
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
  if (value is! String) throw const FormatException('Optional follow-up text must be string or null.');
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true || 1 || '1' => true,
    false || 0 || '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
