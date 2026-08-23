import '../../core/api/api_client.dart';

final class MonthlyDashboardMoney {
  const MonthlyDashboardMoney(
      {required this.currencyCode, required this.units});

  final String currencyCode;
  final BigInt units;

  String get decimal4 {
    final negative = units.isNegative;
    final absolute = units.abs().toString().padLeft(5, '0');
    final whole = absolute.substring(0, absolute.length - 4);
    final fraction = absolute.substring(absolute.length - 4);
    return '${negative ? '-' : ''}$whole.$fraction';
  }

  String get display {
    final negative = units.isNegative;
    final absolute = units.abs().toString().padLeft(5, '0');
    final wholeRaw = absolute.substring(0, absolute.length - 4);
    final whole = wholeRaw.replaceAllMapped(
      RegExp(r'\B(?=(\d{3})+(?!\d))'),
      (_) => ',',
    );
    final fraction =
        absolute.substring(absolute.length - 4, absolute.length - 2);
    final sign = negative ? '− ' : '';
    return '$sign${currencyCode == '—' ? '' : '$currencyCode '}$whole.$fraction';
  }
}

final class MonthlyDirectionSummary {
  const MonthlyDirectionSummary({
    required this.paymentCount,
    required this.dueCount,
    required this.scheduled,
    required this.settled,
    required this.outstanding,
  });

  final int paymentCount;
  final int dueCount;
  final List<MonthlyDashboardMoney> scheduled;
  final List<MonthlyDashboardMoney> settled;
  final List<MonthlyDashboardMoney> outstanding;
}

final class MonthlyDashboardSnapshot {
  const MonthlyDashboardSnapshot({
    required this.year,
    required this.month,
    required this.customerContracts,
    required this.supplierContracts,
    required this.receivable,
    required this.payable,
    required this.generalAccount,
  });

  final int year;
  final int month;
  final int customerContracts;
  final int supplierContracts;
  final MonthlyDirectionSummary receivable;
  final MonthlyDirectionSummary payable;
  final List<MonthlyDashboardMoney> generalAccount;
}

final class MonthlyDashboardRepository {
  MonthlyDashboardRepository(this.client);

  static const int _pageSize = 100;
  static const int _maxPages = 5;

  final SafeContractsApiClient client;

  Future<MonthlyDashboardSnapshot> load({
    required int year,
    required int month,
  }) async {
    if (year < 2000 || year > 2100 || month < 1 || month > 12) {
      throw ArgumentError('Unsupported dashboard month.');
    }
    final start = _isoDate(DateTime(year, month, 1));
    final end = _isoDate(DateTime(year, month + 1, 0));
    final contractRows = await _paged('contracts', <String, String>{
      'sort': 'id',
      'order': 'desc',
    });
    final paymentRows = await _paged('payments', <String, String>{
      'due_from': start,
      'due_to': end,
      'sort': 'due_date',
      'order': 'asc',
    });

    var customerContracts = 0;
    var supplierContracts = 0;
    final baseByCurrency = <String, BigInt>{};
    for (final row in contractRows) {
      if (!_contractInMonth(row, year, month)) continue;
      final type = _text(row['counterparty_type']).toLowerCase();
      if (type == 'customer') customerContracts++;
      if (type == 'supplier') supplierContracts++;
      final currency = _currency(row['currency_code']);
      baseByCurrency[currency] = (baseByCurrency[currency] ?? BigInt.zero) +
          _moneyUnits(row['base_value']);
    }

    final receivable = _DirectionAccumulator();
    final payable = _DirectionAccumulator();
    final settledAllByCurrency = <String, BigInt>{};
    for (final row in paymentRows) {
      final direction = _text(row['financial_direction']).toLowerCase();
      final currency = _currency(row['currency_code']);
      final scheduled = _moneyUnits(row['original_amount']);
      final settled = _moneyUnits(row['paid_amount'] ?? row['settled_amount']);
      final outstanding = _moneyUnits(row['remaining_amount']);
      final target = direction == 'payable'
          ? payable
          : direction == 'receivable'
              ? receivable
              : null;
      if (target == null) continue;
      target.add(
        currency: currency,
        scheduled: scheduled,
        settled: settled,
        outstanding: outstanding,
      );
      settledAllByCurrency[currency] =
          (settledAllByCurrency[currency] ?? BigInt.zero) + settled;
    }

    final currencies = <String>{
      ...baseByCurrency.keys,
      ...settledAllByCurrency.keys,
    }.toList()
      ..sort();
    final general = <MonthlyDashboardMoney>[
      for (final currency in currencies)
        MonthlyDashboardMoney(
          currencyCode: currency,
          units: (baseByCurrency[currency] ?? BigInt.zero) -
              (settledAllByCurrency[currency] ?? BigInt.zero),
        ),
    ];

    return MonthlyDashboardSnapshot(
      year: year,
      month: month,
      customerContracts: customerContracts,
      supplierContracts: supplierContracts,
      receivable: receivable.snapshot(),
      payable: payable.snapshot(),
      generalAccount: general,
    );
  }

  Future<List<Map<String, Object?>>> _paged(
    String endpoint,
    Map<String, String> query,
  ) async {
    final rows = <Map<String, Object?>>[];
    for (var page = 1; page <= _maxPages; page++) {
      final envelope = await client.get(
        endpoint,
        query: <String, String>{
          ...query,
          'page': '$page',
          'per_page': '$_pageSize',
        },
      );
      final pageRows = apiObjectList(envelope.data, '$endpoint.data')
          .map((value) => apiObjectMap(value, '$endpoint.item'))
          .toList(growable: false);
      rows.addAll(pageRows);
      if (pageRows.length < _pageSize) break;
    }
    return rows;
  }

  bool _contractInMonth(Map<String, Object?> row, int year, int month) {
    final raw = _text(row['start_date']).isNotEmpty
        ? _text(row['start_date'])
        : _text(row['created_at']);
    if (raw.length < 7) return false;
    final expected =
        '${year.toString().padLeft(4, '0')}-${month.toString().padLeft(2, '0')}';
    return raw.startsWith(expected);
  }
}

final class _DirectionAccumulator {
  int paymentCount = 0;
  int dueCount = 0;
  final Map<String, BigInt> scheduled = <String, BigInt>{};
  final Map<String, BigInt> settled = <String, BigInt>{};
  final Map<String, BigInt> outstanding = <String, BigInt>{};

  void add({
    required String currency,
    required BigInt scheduled,
    required BigInt settled,
    required BigInt outstanding,
  }) {
    paymentCount++;
    if (outstanding > BigInt.zero) dueCount++;
    this.scheduled[currency] =
        (this.scheduled[currency] ?? BigInt.zero) + scheduled;
    this.settled[currency] = (this.settled[currency] ?? BigInt.zero) + settled;
    this.outstanding[currency] =
        (this.outstanding[currency] ?? BigInt.zero) + outstanding;
  }

  MonthlyDirectionSummary snapshot() => MonthlyDirectionSummary(
        paymentCount: paymentCount,
        dueCount: dueCount,
        scheduled: _moneyList(scheduled),
        settled: _moneyList(settled),
        outstanding: _moneyList(outstanding),
      );
}

List<MonthlyDashboardMoney> _moneyList(Map<String, BigInt> values) {
  final currencies = values.keys.toList()..sort();
  return <MonthlyDashboardMoney>[
    for (final currency in currencies)
      MonthlyDashboardMoney(
        currencyCode: currency,
        units: values[currency] ?? BigInt.zero,
      ),
  ];
}

BigInt _moneyUnits(Object? value) {
  final text = _text(value);
  if (text.isEmpty) return BigInt.zero;
  if (!RegExp(r'^\d+(?:\.\d{1,4})?$').hasMatch(text)) {
    throw const FormatException('Dashboard money value is invalid.');
  }
  final parts = text.split('.');
  final whole = parts[0];
  final fraction = (parts.length == 1 ? '' : parts[1]).padRight(4, '0');
  return BigInt.parse('$whole${fraction.substring(0, 4)}');
}

String _currency(Object? value) {
  final valueText = _text(value).toUpperCase();
  return RegExp(r'^[A-Z]{3}$').hasMatch(valueText) ? valueText : '—';
}

String _text(Object? value) => value is String ? value.trim() : '';

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';