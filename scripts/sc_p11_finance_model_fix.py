from pathlib import Path

path = Path('mobile/lib/features/finance/finance.dart')
source = path.read_text(encoding='utf-8')

old_parser = """    if (!const <String>{'customer', 'supplier'}.contains(type)) {
      throw const FormatException('finance counterparty_type is invalid.');
    }
    return FinanceObligation(
"""
new_parser = """    if (!const <String>{'customer', 'supplier'}.contains(type)) {
      throw const FormatException('finance counterparty_type is invalid.');
    }
    final direction = _direction(data['financial_direction']);
    if ((type == 'supplier' && direction != 'payable') ||
        (type == 'customer' && direction != 'receivable')) {
      throw const FormatException(
        'finance financial_direction conflicts with counterparty type.',
      );
    }
    return FinanceObligation(
"""
if old_parser not in source:
    raise SystemExit('Finance obligation parser marker not found')
source = source.replace(old_parser, new_parser, 1)

old_direction = "      direction: _direction(data['financial_direction']),\n"
if old_direction not in source:
    raise SystemExit('Finance obligation direction marker not found')
source = source.replace(old_direction, '      direction: direction,\n', 1)

old_refresh = """  Future<void> refreshSilently() async {
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
"""
new_refresh = """  Future<void> refreshSilently() async {
    if (!canAccess) return;
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
      errorMessage = null;
    } on Object {
      // Keep the last authorized snapshot if background refresh fails.
    }
  }
"""
if old_refresh not in source:
    raise SystemExit('Finance silent refresh marker not found')
source = source.replace(old_refresh, new_refresh, 1)
path.write_text(source, encoding='utf-8')
