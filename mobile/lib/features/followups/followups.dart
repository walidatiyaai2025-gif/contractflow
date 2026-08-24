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
      dueDate: _requiredDateText(data['due_date'], 'followup.due_date'),
      expectedPaymentDate: _optionalDateText(
        data['expected_payment_date'],
        'followup.expected_payment_date',
      ),
      remainingAmount: _moneyText(
        data['remaining_amount'],
        'followup.remaining_amount',
      ),
      paymentStatus: _requiredText(data['status'], 'followup.status'),
      followUpState: _optionalText(data['followup_state']),
    );
  }
}

final class FollowUpQueuePage {
  const FollowUpQueuePage({
    required this.items,
    required this.page,
    required this.perPage,
    required this.hasMore,
  });

  final List<FollowUpQueueItem> items;
  final int page;
  final int perPage;
  final bool hasMore;

  factory FollowUpQueuePage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'followups.data');
    final meta = envelope.meta;
    final items = rows.map(FollowUpQueueItem.fromData).toList();
    final ids = <int>{};
    for (final item in items) {
      if (!ids.add(item.paymentId)) {
        throw const FormatException(
          'follow-up queue contains duplicate payment IDs.',
        );
      }
    }
    final sort = _requiredText(meta['sort'], 'meta.sort');
    final order = _requiredText(meta['order'], 'meta.order').toLowerCase();
    if (sort != 'due_date' || order != 'asc') {
      throw const FormatException(
        'Follow-up queue ordering is not deterministic.',
      );
    }
    return FollowUpQueuePage(
      items: List<FollowUpQueueItem>.unmodifiable(items),
      page: _boundedInt(meta['page'], 'meta.page', 1, 5),
      perPage: _boundedInt(meta['per_page'], 'meta.per_page', 1, 100),
      hasMore: _boolish(meta['has_more'], 'meta.has_more'),
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
      paymentId: _positiveInt(
        data['payment_id'],
        'followup_history.payment_id',
      ),
      state: _requiredText(data['state'], 'followup_history.state'),
      note: _boundedOptionalText(data['note'], 'followup_history.note', 5000),
      promisedDate: _optionalDateText(
        data['promised_date'],
        'followup_history.promised_date',
      ),
      deferredUntil: _optionalDateText(
        data['deferred_until'],
        'followup_history.deferred_until',
      ),
      createdAt: _requiredText(
        data['created_at'],
        'followup_history.created_at',
      ),
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
      paymentId: _positiveInt(
        data['payment_id'],
        'followup_receipt.payment_id',
      ),
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
    if (page < 1 || page > 5) {
      throw ArgumentError('Follow-up page must be between 1 and 5.');
    }
    filters.validate();
    final envelope = await client.get(
      'followups',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '$page',
        'per_page': '${perPage.clamp(1, 100)}',
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    return FollowUpQueuePage.fromEnvelope(envelope);
  }

  Future<List<FollowUpHistoryItem>> loadHistory(
    int paymentId, {
    required int perPage,
  }) async {
    if (paymentId <= 0) throw ArgumentError('Payment ID must be positive.');
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
    final history = rows.map(FollowUpHistoryItem.fromData).toList();
    final ids = <int>{};
    for (final item in history) {
      if (item.paymentId != paymentId) {
        throw const FormatException('Follow-up history payment ID mismatch.');
      }
      if (!ids.add(item.id)) {
        throw const FormatException(
          'Follow-up history contains duplicate IDs.',
        );
      }
    }
    if (_requiredText(envelope.meta['sort'], 'meta.sort') != 'created_at' ||
        _requiredText(envelope.meta['order'], 'meta.order').toLowerCase() !=
            'desc') {
      throw const FormatException(
        'Follow-up history ordering is not deterministic.',
      );
    }
    return List<FollowUpHistoryItem>.unmodifiable(history);
  }

  Future<FollowUpReceipt> record({
    required int paymentId,
    required String operation,
    String? note,
    String? promisedDate,
    String? deferredUntil,
  }) async {
    if (paymentId <= 0) throw ArgumentError('Payment ID must be positive.');
    final normalizedOperation = operation.trim().toLowerCase();
    const supported = <String>{'note', 'promise', 'issue', 'defer', 'escalate'};
    if (!supported.contains(normalizedOperation)) {
      throw ArgumentError('Follow-up operation is not supported.');
    }
    final normalizedNote = _inputOptionalText(note, 5000, 'note');
    final normalizedPromised = _inputNullableDate(
      promisedDate,
      'promised date',
    );
    final normalizedDeferred = _inputNullableDate(
      deferredUntil,
      'deferred date',
    );

    switch (normalizedOperation) {
      case 'note':
      case 'issue':
      case 'escalate':
        if (normalizedNote == null) {
          throw ArgumentError('A note is required for this follow-up action.');
        }
        if (normalizedPromised != null || normalizedDeferred != null) {
          throw ArgumentError(
            'This follow-up action does not accept date fields.',
          );
        }
        break;
      case 'promise':
        if (normalizedPromised == null || normalizedDeferred != null) {
          throw ArgumentError('Promise requires promised date only.');
        }
        break;
      case 'defer':
        if (normalizedDeferred == null || normalizedPromised != null) {
          throw ArgumentError('Defer requires deferred date only.');
        }
        break;
    }

    final envelope = await client.post(
      'payments/$paymentId/followups/record',
      body: <String, Object?>{
        'operation': normalizedOperation,
        'note': ?normalizedNote,
        'promised_date': ?normalizedPromised,
        'deferred_until': ?normalizedDeferred,
      },
    );
    final receipt = FollowUpReceipt.fromData(envelope.data);
    if (receipt.paymentId != paymentId) {
      throw const FormatException('Follow-up receipt payment ID mismatch.');
    }
    return receipt;
  }
}

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) {
    throw FormatException('$field must be positive.');
  }
  return parsed;
}

int _boundedInt(Object? value, String field, int min, int max) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed < min || parsed > max) {
    throw FormatException('$field is outside range.');
  }
  return parsed;
}

String _requiredText(Object? value, String field) {
  if (value is String && value.trim().isNotEmpty) return value.trim();
  if (value is num) return value.toString();
  throw FormatException('$field must be present.');
}

String _moneyText(Object? value, String field) {
  if (value is! String || !RegExp(r'^\d+\.\d{4}$').hasMatch(value)) {
    throw FormatException('$field must be an exact four-decimal money string.');
  }
  return value;
}

String _requiredDateText(Object? value, String field) {
  if (value is! String || !_validDate(value)) {
    throw FormatException('$field must be a valid date.');
  }
  return value.trim();
}

String? _optionalDateText(Object? value, String field) {
  if (value == null || value == '') return null;
  if (value is! String || !_validDate(value)) {
    throw FormatException('$field must be a valid date or null.');
  }
  return value.trim();
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException(
      'Optional follow-up text must be string or null.',
    );
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

String? _boundedOptionalText(Object? value, String field, int maxLength) {
  final text = _optionalText(value);
  if (text != null && text.length > maxLength) {
    throw FormatException('$field is too long.');
  }
  return text;
}

String? _inputOptionalText(String? value, int maxLength, String field) {
  final text = value?.trim() ?? '';
  if (text.isEmpty) return null;
  if (text.length > maxLength) throw ArgumentError('$field is too long.');
  return text;
}

String? _inputNullableDate(String? value, String field) {
  final text = value?.trim() ?? '';
  if (text.isEmpty) return null;
  if (!_validDate(text)) {
    throw ArgumentError('$field must use valid YYYY-MM-DD.');
  }
  return text;
}

bool _validDate(String value) {
  final normalized = value.trim();
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(normalized);
  if (match == null) return false;
  final parsed = DateTime.tryParse(normalized);
  return parsed != null &&
      parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true || 1 || '1' => true,
    false || 0 || '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
