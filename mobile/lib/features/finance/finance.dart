import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

enum FinanceLoadState { idle, loading, ready, error }

final class FinanceSummaryRow {
  const FinanceSummaryRow({
    required this.direction,
    required this.currencyCode,
    required this.obligationCount,
    required this.originalTotal,
    required this.settledTotal,
    required this.outstandingTotal,
    required this.overdueTotal,
    required this.overdueCount,
    required this.dueTodayTotal,
    required this.dueTodayCount,
    required this.due7Total,
    required this.due7Count,
    required this.due30Total,
    required this.due30Count,
    required this.upcomingTotal,
  });

  final String direction;
  final String currencyCode;
  final int obligationCount;
  final String originalTotal;
  final String settledTotal;
  final String outstandingTotal;
  final String overdueTotal;
  final int overdueCount;
  final String dueTodayTotal;
  final int dueTodayCount;
  final String due7Total;
  final int due7Count;
  final String due30Total;
  final int due30Count;
  final String upcomingTotal;

  factory FinanceSummaryRow.fromData(Object? value) {
    final data = apiObjectMap(value, 'finance.summary');
    return FinanceSummaryRow(
      direction: _direction(data['financial_direction']),
      currencyCode: _currency(data['currency_code']),
      obligationCount: _nonNegativeInt(data['obligation_count'], 'obligation_count'),
      originalTotal: _money(data['original_total'], 'original_total'),
      settledTotal: _money(data['settled_total'], 'settled_total'),
      outstandingTotal: _money(data['outstanding_total'], 'outstanding_total'),
      overdueTotal: _money(data['overdue_total'], 'overdue_total'),
      overdueCount: _nonNegativeInt(data['overdue_count'], 'overdue_count'),
      dueTodayTotal: _money(data['due_today_total'], 'due_today_total'),
      dueTodayCount:
          _nonNegativeInt(data['due_today_count'], 'due_today_count'),
      due7Total: _money(data['due_7_total'], 'due_7_total'),
      due7Count: _nonNegativeInt(data['due_7_count'], 'due_7_count'),
      due30Total: _money(data['due_30_total'], 'due_30_total'),
      due30Count: _nonNegativeInt(data['due_30_count'], 'due_30_count'),
      upcomingTotal: _money(data['upcoming_total'], 'upcoming_total'),
    );
  }
}

final class FinanceAgingRow {
  const FinanceAgingRow({
    required this.direction,
    required this.currencyCode,
    required this.bucket,
    required this.obligationCount,
    required this.outstandingTotal,
  });

  final String direction;
  final String currencyCode;
  final String bucket;
  final int obligationCount;
  final String outstandingTotal;

  factory FinanceAgingRow.fromData(Object? value) {
    final data = apiObjectMap(value, 'finance.aging');
    final bucket = _requiredText(data['aging_bucket'], 'aging_bucket');
    if (!const <String>{'current', '1-30', '31-60', '61-90', '90+'}
        .contains(bucket)) {
      throw const FormatException('finance aging bucket is invalid.');
    }
    return FinanceAgingRow(
      direction: _direction(data['financial_direction']),
      currencyCode: _currency(data['currency_code']),
      bucket: bucket,
      obligationCount: _nonNegativeInt(data['obligation_count'], 'obligation_count'),
      outstandingTotal: _money(data['outstanding_total'], 'outstanding_total'),
    );
  }
}

final class FinanceCashFlowRow {
  const FinanceCashFlowRow({
    required this.dueDate,
    required this.direction,
    required this.currencyCode,
    required this.kind,
    required this.obligationCount,
    required this.expectedAmount,
  });

  final String dueDate;
  final String direction;
  final String currencyCode;
  final String kind;
  final int obligationCount;
  final String expectedAmount;

  factory FinanceCashFlowRow.fromData(Object? value) {
    final data = apiObjectMap(value, 'finance.cash_flow');
    final kind = _requiredText(data['cash_flow_kind'], 'cash_flow_kind');
    if (!const <String>{'inflow', 'outflow'}.contains(kind)) {
      throw const FormatException('finance cash_flow_kind is invalid.');
    }
    return FinanceCashFlowRow(
      dueDate: _date(data['due_date'], 'due_date'),
      direction: _direction(data['financial_direction']),
      currencyCode: _currency(data['currency_code']),
      kind: kind,
      obligationCount: _nonNegativeInt(data['obligation_count'], 'obligation_count'),
      expectedAmount: _money(data['expected_amount'], 'expected_amount'),
    );
  }
}

final class FinanceActionItem {
  const FinanceActionItem({
    required this.kind,
    required this.direction,
    required this.currencyCode,
    required this.count,
    required this.amount,
    required this.priority,
  });

  final String kind;
  final String direction;
  final String currencyCode;
  final int count;
  final String amount;
  final String priority;

  factory FinanceActionItem.fromData(Object? value) {
    final data = apiObjectMap(value, 'finance.action_center');
    final kind = _requiredText(data['kind'], 'action.kind');
    final priority = _requiredText(data['priority'], 'action.priority');
    return FinanceActionItem(
      kind: kind,
      direction: _direction(data['direction']),
      currencyCode: _currency(data['currency_code']),
      count: _nonNegativeInt(data['count'], 'action.count'),
      amount: _money(data['amount'], 'action.amount'),
      priority: priority,
    );
  }
}

final class FinanceObligation {
  const FinanceObligation({
    required this.id,
    required this.contractId,
    required this.contractNumber,
    required this.counterpartyType,
    required this.counterpartyId,
    required this.counterpartyName,
    required this.direction,
    required this.currencyCode,
    required this.sequenceNo,
    required this.reference,
    required this.dueDate,
    required this.expectedPaymentDate,
    required this.originalAmount,
    required this.settledAmount,
    required this.remainingAmount,
    required this.status,
    required this.agingBucket,
  });

  final int id;
  final int contractId;
  final String contractNumber;
  final String counterpartyType;
  final int counterpartyId;
  final String counterpartyName;
  final String direction;
  final String currencyCode;
  final int sequenceNo;
  final String? reference;
  final String dueDate;
  final String? expectedPaymentDate;
  final String originalAmount;
  final String settledAmount;
  final String remainingAmount;
  final String status;
  final String agingBucket;

  factory FinanceObligation.fromData(Object? value) {
    final data = apiObjectMap(value, 'finance.obligation');
    final type = _requiredText(data['counterparty_type'], 'counterparty_type');
    if (!const <String>{'customer', 'supplier'}.contains(type)) {
      throw const FormatException('finance counterparty_type is invalid.');
    }
    return FinanceObligation(
      id: _positiveInt(data['id'], 'obligation.id'),
      contractId: _positiveInt(data['contract_id'], 'obligation.contract_id'),
      contractNumber:
          _requiredText(data['contract_number'], 'obligation.contract_number'),
      counterpartyType: type,
      counterpartyId:
          _positiveInt(data['counterparty_id'], 'obligation.counterparty_id'),
      counterpartyName:
          _requiredText(data['counterparty_name'], 'obligation.counterparty_name'),
      direction: _direction(data['financial_direction']),
      currencyCode: _currency(data['currency_code']),
      sequenceNo: _positiveInt(data['sequence_no'], 'obligation.sequence_no'),
      reference: _optionalText(data['reference']),
      dueDate: _date(data['due_date'], 'obligation.due_date'),
      expectedPaymentDate:
          _optionalDate(data['expected_payment_date'], 'expected_payment_date'),
      originalAmount: _money(data['original_amount'], 'original_amount'),
      settledAmount: _money(data['settled_amount'], 'settled_amount'),
      remainingAmount: _money(data['remaining_amount'], 'remaining_amount'),
      status: _requiredText(data['status'], 'obligation.status').toLowerCase(),
      agingBucket:
          _requiredText(data['aging_bucket'], 'obligation.aging_bucket'),
    );
  }
}

final class FinanceOverview {
  const FinanceOverview({
    required this.directions,
    required this.summary,
    required this.aging,
    required this.cashFlow,
    required this.actionCenter,
    required this.workQueuePreview,
  });

  final List<String> directions;
  final List<FinanceSummaryRow> summary;
  final List<FinanceAgingRow> aging;
  final List<FinanceCashFlowRow> cashFlow;
  final List<FinanceActionItem> actionCenter;
  final List<FinanceObligation> workQueuePreview;

  factory FinanceOverview.fromData(Object? value) {
    final data = apiObjectMap(value, 'finance.overview');
    final directions = _stringList(data['directions'], 'finance.directions');
    for (final direction in directions) {
      if (!const <String>{'payable', 'receivable'}.contains(direction)) {
        throw const FormatException('finance directions contain an invalid value.');
      }
    }
    return FinanceOverview(
      directions: List<String>.unmodifiable(directions),
      summary: _models(data['summary'], FinanceSummaryRow.fromData, 'summary'),
      aging: _models(data['aging'], FinanceAgingRow.fromData, 'aging'),
      cashFlow:
          _models(data['cash_flow'], FinanceCashFlowRow.fromData, 'cash_flow'),
      actionCenter: _models(
        data['action_center'],
        FinanceActionItem.fromData,
        'action_center',
      ),
      workQueuePreview: _models(
        data['work_queue_preview'],
        FinanceObligation.fromData,
        'work_queue_preview',
      ),
    );
  }
}

final class FinanceRepository {
  FinanceRepository(this.client);

  final SafeContractsApiClient client;

  Future<FinanceOverview> loadOverview({
    String direction = '',
    String currencyCode = '',
    String status = '',
    String agingBucket = '',
  }) async {
    final envelope = await client.get(
      'finance/overview',
      query: _query(
        direction: direction,
        currencyCode: currencyCode,
        status: status,
        agingBucket: agingBucket,
      ),
    );
    return FinanceOverview.fromData(envelope.data);
  }

  Future<List<FinanceObligation>> loadObligations({
    String direction = '',
    String currencyCode = '',
    String status = '',
    String agingBucket = '',
    int limit = 100,
  }) async {
    final envelope = await client.get(
      'finance/obligations',
      query: <String, String>{
        ..._query(
          direction: direction,
          currencyCode: currencyCode,
          status: status,
          agingBucket: agingBucket,
        ),
        'limit': '${limit.clamp(1, 200)}',
      },
    );
    final values = apiObjectList(envelope.data, 'finance.obligations');
    return List<FinanceObligation>.unmodifiable(
      values.map(FinanceObligation.fromData),
    );
  }

  Map<String, String> _query({
    required String direction,
    required String currencyCode,
    required String status,
    required String agingBucket,
  }) =>
      <String, String>{
        if (direction.trim().isNotEmpty) 'direction': direction.trim(),
        if (currencyCode.trim().isNotEmpty)
          'currency_code': currencyCode.trim().toUpperCase(),
        if (status.trim().isNotEmpty) 'status': status.trim(),
        if (agingBucket.trim().isNotEmpty) 'aging_bucket': agingBucket.trim(),
      };
}

final class FinanceController extends ChangeNotifier {
  FinanceController({
    required this.repository,
    required this.canViewPayables,
    required this.canViewReceivables,
  });

  final FinanceRepository repository;
  final bool canViewPayables;
  final bool canViewReceivables;

  FinanceLoadState state = FinanceLoadState.idle;
  FinanceOverview? overview;
  List<FinanceObligation> obligations = const <FinanceObligation>[];
  String direction = '';
  String currencyCode = '';
  String status = '';
  String agingBucket = '';
  String? errorMessage;

  bool get canAccess => canViewPayables || canViewReceivables;

  List<String> get allowedDirections => <String>[
        if (canViewReceivables) 'receivable',
        if (canViewPayables) 'payable',
      ];

  Future<void> ensureLoaded() async {
    if (state == FinanceLoadState.idle) await refresh();
  }

  Future<void> refresh() async {
    if (!canAccess) {
      state = FinanceLoadState.error;
      overview = null;
      obligations = const <FinanceObligation>[];
      errorMessage = 'Finance access is not authorized for this session.';
      notifyListeners();
      return;
    }
    if (direction.isNotEmpty && !allowedDirections.contains(direction)) {
      direction = '';
    }
    state = FinanceLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      final values = await Future.wait<Object>([
        repository.loadOverview(
          direction: direction,
          currencyCode: currencyCode,
          status: status,
          agingBucket: agingBucket,
        ),
        repository.loadObligations(
          direction: direction,
          currencyCode: currencyCode,
          status: status,
          agingBucket: agingBucket,
          limit: 100,
        ),
      ]);
      overview = values[0] as FinanceOverview;
      obligations = values[1] as List<FinanceObligation>;
      state = FinanceLoadState.ready;
    } on SafeContractsApiException catch (error) {
      errorMessage = error.message;
      state = FinanceLoadState.error;
    } on Object catch (error) {
      errorMessage = error.toString();
      state = FinanceLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refreshSilently() async {
    if (!canAccess) return;
    try {
      final next = await repository.loadOverview(
        direction: direction,
        currencyCode: currencyCode,
        status: status,
        agingBucket: agingBucket,
      );
      overview = next;
      state = FinanceLoadState.ready;
      errorMessage = null;
    } on Object {
      // Keep the last authorized snapshot if background refresh fails.
    }
  }

  Future<void> setDirection(String value) async {
    final normalized = value.trim().toLowerCase();
    if (normalized.isNotEmpty && !allowedDirections.contains(normalized)) return;
    direction = normalized;
    currencyCode = '';
    status = '';
    agingBucket = '';
    await refresh();
  }

  Future<void> setCurrency(String value) async {
    currencyCode = value.trim().toUpperCase();
    await refresh();
  }

  Future<void> setStatus(String value) async {
    status = value.trim().toLowerCase();
    await refresh();
  }

  Future<void> setAgingBucket(String value) async {
    agingBucket = value.trim();
    await refresh();
  }

  Future<void> applyAction(FinanceActionItem item) async {
    if (!allowedDirections.contains(item.direction)) return;
    direction = item.direction;
    currencyCode = item.currencyCode == 'UNSET' ? 'UNSET' : item.currencyCode;
    agingBucket = '';
    status = item.kind == 'overdue' ? 'overdue' : '';
    await refresh();
  }

  void clearFilters() {
    direction = '';
    currencyCode = '';
    status = '';
    agingBucket = '';
    notifyListeners();
  }
}

List<T> _models<T>(Object? value, T Function(Object?) parse, String field) {
  final items = apiObjectList(value, 'finance.$field');
  return List<T>.unmodifiable(items.map(parse));
}

List<String> _stringList(Object? value, String field) {
  if (value is! List<Object?>) {
    throw FormatException('$field must be a list.');
  }
  return value.map((item) => _requiredText(item, field).toLowerCase()).toList();
}

String _direction(Object? value) {
  final normalized = _requiredText(value, 'financial_direction').toLowerCase();
  if (!const <String>{'payable', 'receivable'}.contains(normalized)) {
    throw const FormatException('financial_direction is invalid.');
  }
  return normalized;
}

String _currency(Object? value) {
  final normalized = _requiredText(value, 'currency_code').toUpperCase();
  if (normalized != 'UNSET' && !RegExp(r'^[A-Z]{3}$').hasMatch(normalized)) {
    throw const FormatException('currency_code is invalid.');
  }
  return normalized;
}

String _money(Object? value, String field) {
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
    throw FormatException('$field must be a non-negative integer.');
  }
  return parsed;
}

String _requiredText(Object? value, String field) {
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('$field must be a non-empty string.');
  }
  return value.trim();
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException('finance optional text must be string or null.');
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

String _date(Object? value, String field) {
  final text = _requiredText(value, field);
  if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(text)) {
    throw FormatException('$field must be YYYY-MM-DD.');
  }
  return text;
}

String? _optionalDate(Object? value, String field) {
  final text = _optionalText(value);
  if (text == null) return null;
  if (!RegExp(r'^\d{4}-\d{2}-\d{2}$').hasMatch(text)) {
    throw FormatException('$field must be YYYY-MM-DD.');
  }
  return text;
}
