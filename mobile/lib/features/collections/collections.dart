import '../../core/api/api_client.dart';
import '../dashboard/dashboard_models.dart';

final class SafeContractsCollection {
  const SafeContractsCollection({
    required this.id,
    required this.paymentId,
    required this.contractId,
    required this.financialDirection,
    required this.currencyCode,
    required this.amount,
    required this.collectionDate,
    this.paymentMethodName,
    this.reference,
    this.paymentReference,
    this.contractNumber,
    this.counterpartyName,
    this.dueDate,
    this.paymentStatus,
    this.remainingAmount,
  });

  final int id;
  final int paymentId;
  final int contractId;
  final String financialDirection;
  final String currencyCode;
  final String amount;
  final String collectionDate;
  final String? paymentMethodName;
  final String? reference;
  final String? paymentReference;
  final String? contractNumber;
  final String? counterpartyName;
  final String? dueDate;
  final String? paymentStatus;
  final String? remainingAmount;

  bool get isPayable => financialDirection == 'payable';

  factory SafeContractsCollection.fromData(Object? value) {
    final data = apiObjectMap(value, 'collection');
    return SafeContractsCollection(
      id: _positiveInt(data['id'], 'collection.id'),
      paymentId: _positiveInt(data['payment_id'], 'collection.payment_id'),
      contractId: _positiveInt(data['contract_id'], 'collection.contract_id'),
      financialDirection: _direction(data['financial_direction']),
      currencyCode: _currency(data['currency_code']),
      amount: _money(data['amount'], 'collection.amount'),
      collectionDate:
          _date(data['collection_date'], 'collection.collection_date'),
      paymentMethodName: _optionalText(data['payment_method_name']),
      reference: _optionalText(data['reference']),
      paymentReference: _optionalText(data['payment_reference']),
      contractNumber: _optionalText(data['contract_number']),
      counterpartyName: _optionalText(data['counterparty_name']) ??
          _optionalText(data['supplier_name']) ??
          _optionalText(data['customer_name']),
      dueDate: _optionalDate(data['due_date'], 'collection.due_date'),
      paymentStatus: _optionalText(data['payment_status']),
      remainingAmount: data['remaining_amount'] == null
          ? null
          : _money(data['remaining_amount'], 'collection.remaining_amount'),
    );
  }
}

final class CollectionPage {
  const CollectionPage({
    required this.collections,
    required this.page,
    required this.perPage,
    required this.hasMore,
    required this.sort,
    required this.order,
  });

  final List<SafeContractsCollection> collections;
  final int page;
  final int perPage;
  final bool hasMore;
  final String sort;
  final String order;

  factory CollectionPage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'collections.data');
    final collections =
        rows.map(SafeContractsCollection.fromData).toList(growable: false);
    final ids = <int>{};
    for (final collection in collections) {
      if (!ids.add(collection.id)) {
        throw const FormatException('collections contain duplicate IDs.');
      }
    }
    final meta = envelope.meta;
    return CollectionPage(
      collections: List<SafeContractsCollection>.unmodifiable(collections),
      page: _boundedInt(meta['page'], 'meta.page', 1, 1000000),
      perPage: _boundedInt(meta['per_page'], 'meta.per_page', 1, 100),
      hasMore: _boolish(meta['has_more'], 'meta.has_more'),
      sort: _requiredText(meta['sort'], 'meta.sort'),
      order: _order(meta['order']),
    );
  }
}

final class CollectionsRepository {
  CollectionsRepository(this.client);

  final SafeContractsApiClient client;

  Future<CollectionPage> loadPage({
    required int page,
    required int perPage,
    required DashboardFilters filters,
  }) async {
    if (page < 1) throw ArgumentError('Collection page must be positive.');
    filters.validate();
    final envelope = await client.get(
      'collections',
      query: <String, String>{
        ...filters.toQuery(),
        'page': '$page',
        'per_page': '${perPage.clamp(1, 100)}',
        'sort': 'collection_date',
        'order': 'desc',
      },
    );
    final result = CollectionPage.fromEnvelope(envelope);
    if (result.sort != 'collection_date' || result.order != 'desc') {
      throw const FormatException(
        'Collection paging metadata is not deterministic.',
      );
    }
    return result;
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

String? _optionalText(Object? value) {
  if (value == null || value == '') return null;
  if (value is! String) {
    throw const FormatException(
        'Optional collection text must be string or null.');
  }
  final text = value.trim();
  return text.isEmpty ? null : text;
}

String _money(Object? value, String field) {
  if (value is! String ||
      !RegExp(r'^\d+(?:\.\d{1,4})?$').hasMatch(value.trim()) ||
      value.length > 40) {
    throw FormatException(
        '$field must be a non-negative decimal money string.');
  }
  return value.trim();
}

String _direction(Object? value) {
  final direction =
      _requiredText(value, 'collection.financial_direction').toLowerCase();
  if (direction != 'receivable' && direction != 'payable') {
    throw const FormatException('collection.financial_direction is invalid.');
  }
  return direction;
}

String _currency(Object? value) {
  final currency =
      _requiredText(value, 'collection.currency_code').toUpperCase();
  if (!RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
    throw const FormatException('collection.currency_code is invalid.');
  }
  return currency;
}

String _date(Object? value, String field) {
  final text = _requiredText(value, field);
  if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(text)) {
    throw FormatException('$field must use YYYY-MM-DD.');
  }
  final parsed = DateTime.tryParse(text);
  if (parsed == null || parsed.toIso8601String().substring(0, 10) != text) {
    throw FormatException('$field must be a real calendar date.');
  }
  return text;
}

String? _optionalDate(Object? value, String field) {
  if (value == null || value == '') return null;
  return _date(value, field);
}

String _order(Object? value) {
  final order = _requiredText(value, 'meta.order').toLowerCase();
  if (order != 'asc' && order != 'desc') {
    throw const FormatException('Collection order metadata is invalid.');
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
