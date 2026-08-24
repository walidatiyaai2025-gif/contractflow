import '../../core/api/api_client.dart';
import '../finance/finance.dart';
import '../payments/payments.dart';
import 'contracts.dart';

final class CounterpartyActivity {
  const CounterpartyActivity({
    required this.amount,
    required this.currencyCode,
    required this.date,
    required this.reference,
    required this.contractNumber,
  });

  final String amount;
  final String currencyCode;
  final String date;
  final String? reference;
  final String? contractNumber;

  factory CounterpartyActivity.fromData(Object? value) {
    final data = apiObjectMap(value, 'counterparty_activity');
    return CounterpartyActivity(
      amount: _moneyText(data['amount'], 'counterparty_activity.amount'),
      currencyCode: _currency(data['currency_code']),
      date: _requiredText(data['collection_date'], 'counterparty_activity.date'),
      reference: _optionalText(data['reference']),
      contractNumber: _optionalText(data['contract_number']),
    );
  }
}

final class CounterpartyBusinessSnapshot {
  const CounterpartyBusinessSnapshot({
    required this.contracts,
    required this.payments,
    required this.finance,
    required this.activity,
    required this.financeAuthorized,
  });

  final List<SafeContractsContract> contracts;
  final List<SafeContractsPayment> payments;
  final List<FinanceSummaryRow> finance;
  final List<CounterpartyActivity> activity;
  final bool financeAuthorized;
}

/// Read-only presentation adapter for Customer/Supplier detail screens.
///
/// All financial totals are consumed exactly as returned by the server. The
/// client never merges currencies or derives an alternative authoritative
/// balance.
final class CounterpartyBusinessSnapshotRepository {
  const CounterpartyBusinessSnapshotRepository(this.client);

  final SafeContractsApiClient client;

  Future<CounterpartyBusinessSnapshot> load({
    required String counterpartyType,
    required int counterpartyId,
  }) async {
    final type = counterpartyType.trim().toLowerCase();
    if (type != 'customer' && type != 'supplier') {
      throw ArgumentError.value(
        counterpartyType,
        'counterpartyType',
        'Counterparty type must be customer or supplier.',
      );
    }
    if (counterpartyId <= 0) {
      throw ArgumentError.value(
        counterpartyId,
        'counterpartyId',
        'Counterparty ID must be positive.',
      );
    }

    final contractEnvelope = await client.get(
      'contracts',
      query: <String, String>{
        'counterparty_type': type,
        'counterparty_id': '$counterpartyId',
        'page': '1',
        'per_page': '100',
        'sort': 'end_date',
        'order': 'asc',
      },
    );
    final contracts = ContractPage.fromEnvelope(contractEnvelope).contracts;

    final paymentEnvelope = await client.get(
      'payments',
      query: <String, String>{
        'counterparty_type': type,
        'counterparty_id': '$counterpartyId',
        'financial_direction': type == 'supplier' ? 'payable' : 'receivable',
        'page': '1',
        'per_page': '100',
        'sort': 'due_date',
        'order': 'asc',
      },
    );
    final payments = PaymentPage.fromEnvelope(paymentEnvelope).payments;

    var financeAuthorized = true;
    var finance = const <FinanceSummaryRow>[];
    try {
      final financeEnvelope = await client.get(
        'finance/summary',
        query: <String, String>{
          'counterparty_type': type,
          'counterparty_id': '$counterpartyId',
          'financial_direction':
              type == 'supplier' ? 'payable' : 'receivable',
          'page': '1',
          'per_page': '100',
          'sort': 'financial_direction',
          'order': 'asc',
        },
      );
      finance = List<FinanceSummaryRow>.unmodifiable(
        apiObjectList(financeEnvelope.data, 'counterparty_finance.data')
            .map(FinanceSummaryRow.fromData),
      );
    } on SafeContractsApiException catch (error) {
      if (error.statusCode != 403) rethrow;
      financeAuthorized = false;
    }

    var activity = const <CounterpartyActivity>[];
    try {
      final activityEnvelope = await client.get(
        'collections',
        query: <String, String>{
          'counterparty_type': type,
          'counterparty_id': '$counterpartyId',
          'financial_direction': type == 'supplier' ? 'payable' : 'receivable',
          'page': '1',
          'per_page': '12',
          'sort': 'collection_date',
          'order': 'desc',
        },
      );
      activity = List<CounterpartyActivity>.unmodifiable(
        apiObjectList(activityEnvelope.data, 'counterparty_activity.data')
            .map(CounterpartyActivity.fromData),
      );
    } on SafeContractsApiException catch (error) {
      if (error.statusCode != 403) rethrow;
    }

    return CounterpartyBusinessSnapshot(
      contracts: contracts,
      payments: payments,
      finance: finance,
      activity: activity,
      financeAuthorized: financeAuthorized,
    );
  }
}

String _requiredText(Object? value, String field) {
  if (value is String && value.trim().isNotEmpty) return value.trim();
  if (value is num) return value.toString();
  throw FormatException('$field must be present.');
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException('Optional counterparty text must be a string.');
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

String _moneyText(Object? value, String field) {
  final text = switch (value) {
    final String value => value.trim(),
    final num value => value.toString(),
    _ => '',
  };
  if (text.isEmpty || !RegExp(r'^-?\d+(?:\.\d{1,4})?$').hasMatch(text)) {
    throw FormatException('$field must be a decimal amount.');
  }
  return text;
}

String _currency(Object? value) {
  final normalized = _requiredText(value, 'currency_code').toUpperCase();
  if (normalized != 'UNSET' && !RegExp(r'^[A-Z]{3}$').hasMatch(normalized)) {
    throw const FormatException('currency_code is invalid.');
  }
  return normalized;
}
