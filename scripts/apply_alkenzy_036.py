#!/usr/bin/env python3
"""Apply deterministic 0.3.6 runtime source transforms before Android build.

The approved 0.3.5 release remains immutable. These transforms are intentionally
small and fail closed when an expected source contract changes.
"""
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
MOBILE = ROOT / "mobile" / "lib" / "features"


def patch(relative: str, old: str, new: str) -> None:
    path = ROOT / relative
    text = path.read_text(encoding="utf-8")
    if new in text:
        return
    if old not in text:
        raise SystemExit(f"FAIL: Alkenzy 0.3.6 patch contract missing in {relative}")
    path.write_text(text.replace(old, new, 1), encoding="utf-8")


# Automatic biometric prompt once when an enrolled credential exists.
patch(
    "mobile/lib/features/auth/login_screen.dart",
    "  bool _biometricBusy = false;\n",
    "  bool _biometricBusy = false;\n  bool _autoBiometricAttempted = false;\n",
)
patch(
    "mobile/lib/features/auth/login_screen.dart",
    """    if (mounted) {
      setState(() {
        _biometricAvailable = available;
        _biometricCredentialAvailable = remembered;
      });
    }
  }
""",
    """    if (mounted) {
      setState(() {
        _biometricAvailable = available;
        _biometricCredentialAvailable = remembered;
      });
      if (remembered && !_autoBiometricAttempted) {
        _autoBiometricAttempted = true;
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted) unawaited(_submitBiometric());
        });
      }
    }
  }
""",
)

# Remove artificial five-page caps. Server metadata remains authoritative.
for relative in (
    "mobile/lib/features/payments/payments.dart",
    "mobile/lib/features/followups/followups.dart",
):
    path = ROOT / relative
    text = path.read_text(encoding="utf-8")
    text = text.replace("_boundedInt(meta['page'], 'meta.page', 1, 5)",
                        "_boundedInt(meta['page'], 'meta.page', 1, 1000000)")
    text = text.replace("if (page < 1 || page > 5) {", "if (page < 1) {")
    text = text.replace("must be between 1 and 5", "must be positive")
    path.write_text(text, encoding="utf-8")

# Payments: append authoritative server pages on scroll instead of replacing
# the list and remove Previous/Next UI.
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    "  bool _requestInFlight = false;\n",
    """  bool _requestInFlight = false;
  final ScrollController _scrollController = ScrollController();
""",
)
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    """  void initState() {
    super.initState();
    unawaited(_load(1));
  }
""",
    """  void initState() {
    super.initState();
    _scrollController.addListener(_loadNextOnScroll);
    unawaited(_load(1));
  }

  void _loadNextOnScroll() {
    final page = _page;
    if (page == null || !page.hasMore || _requestInFlight) return;
    if (!_scrollController.hasClients ||
        _scrollController.position.extentAfter > 360) return;
    unawaited(_load(page.page + 1, background: true));
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }
""",
)
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    """      setState(() {
        _page = result;
        _pageNumber = page;
        _error = null;
        _loading = false;
      });
""",
    """      setState(() {
        if (page > 1 && _page != null) {
          final merged = <int, SafeContractsPayment>{
            for (final item in _page!.payments) item.id: item,
            for (final item in result.payments) item.id: item,
          };
          _page = PaymentPage(
            payments: List<SafeContractsPayment>.unmodifiable(merged.values),
            page: result.page,
            perPage: result.perPage,
            hasMore: result.hasMore,
            sort: result.sort,
            order: result.order,
          );
        } else {
          _page = result;
        }
        _pageNumber = page;
        _error = null;
        _loading = false;
      });
""",
)
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    """              child: ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
""",
    """              child: ListView.separated(
                controller: _scrollController,
                physics: const AlwaysScrollableScrollPhysics(),
""",
)
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    """          _PaymentPaging(
            page: page,
            loading: _loading,
            onPrevious:
                page.page > 1 ? () => unawaited(_load(page.page - 1)) : null,
            onNext: page.hasMore && page.page < 5
                ? () => unawaited(_load(page.page + 1))
                : null,
          ),
""",
    """          if (_loading && page.payments.isNotEmpty)
            const Padding(
              padding: EdgeInsets.all(12),
              child: SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
""",
)
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    """    final directionLabel = payment.isPayable
        ? (l10n.isArabic ? 'واجبة الدفع' : 'Payable')
        : (l10n.isArabic ? 'مستحقة' : 'Receivable');
""",
    """    final remaining = double.tryParse(payment.remainingAmount) ?? 0;
    final paid = double.tryParse(payment.paidAmount) ?? 0;
    final original = double.tryParse(payment.originalAmount) ?? 0;
    final isPaid = payment.status.toLowerCase() == 'paid' ||
        (remaining <= 0 && (paid > 0 || original > 0));
    final amountValue = isPaid
        ? (paid > 0 ? payment.paidAmount : payment.originalAmount)
        : payment.remainingAmount;
    final amountColor =
        isPaid ? SafeContractsVisual.greenDeep : SafeContractsVisual.redDeep;
    final directionLabel = isPaid
        ? (l10n.isArabic
            ? (payment.isPayable ? 'تم الدفع' : 'تم التحصيل')
            : (payment.isPayable ? 'Paid' : 'Collected'))
        : (payment.isPayable
            ? (l10n.isArabic ? 'واجبة الدفع' : 'Payable')
            : (l10n.isArabic ? 'مستحقة' : 'Receivable'));
""",
)
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    """                            l10n.t('Remaining'),
""",
    """                            isPaid
                                ? (l10n.isArabic ? 'المبلغ المدفوع' : 'Paid amount')
                                : l10n.t('Remaining'),
""",
)
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    """                              payment.remainingAmount,
                              currency,
""",
    """                              amountValue,
                              currency,
""",
)
patch(
    "mobile/lib/features/payments/payments_screen.dart",
    """                                  color: directionColor,
                                  fontWeight: FontWeight.w900,
""",
    """                                  color: amountColor,
                                  fontWeight: FontWeight.w900,
""",
)

# Follow-ups: append each next server page when the user approaches the end.
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    "  int _pageNumber = 1;\n",
    """  int _pageNumber = 1;
  bool _requestInFlight = false;
  final ScrollController _scrollController = ScrollController();
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """  void initState() {
    super.initState();
    unawaited(_load(1));
  }
""",
    """  void initState() {
    super.initState();
    _scrollController.addListener(_loadNextOnScroll);
    unawaited(_load(1));
  }

  void _loadNextOnScroll() {
    final page = _page;
    if (page == null || !page.hasMore || _requestInFlight) return;
    if (!_scrollController.hasClients ||
        _scrollController.position.extentAfter > 360) return;
    unawaited(_load(page.page + 1, background: true));
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """  Future<void> _load(int page, {bool background = false}) async {
    final keepVisible = background && _page != null;
""",
    """  Future<void> _load(int page, {bool background = false}) async {
    if (_requestInFlight) return;
    _requestInFlight = true;
    final keepVisible = background && _page != null;
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """      setState(() {
        _page = result;
        _pageNumber = page;
        _error = null;
        _loading = false;
      });
""",
    """      setState(() {
        if (page > 1 && _page != null) {
          final merged = <int, FollowUpQueueItem>{
            for (final item in _page!.items) item.paymentId: item,
            for (final item in result.items) item.paymentId: item,
          };
          _page = FollowUpQueuePage(
            items: List<FollowUpQueueItem>.unmodifiable(merged.values),
            page: result.page,
            perPage: result.perPage,
            hasMore: result.hasMore,
          );
        } else {
          _page = result;
        }
        _pageNumber = page;
        _error = null;
        _loading = false;
      });
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """    } on Object catch (error) {
      if (!mounted) return;
      if (keepVisible) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }
""",
    """    } on Object catch (error) {
      if (!mounted) return;
      if (!keepVisible) {
        setState(() {
          _error = error.toString();
          _loading = false;
        });
      }
    } finally {
      _requestInFlight = false;
    }
  }
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """              child: ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
""",
    """              child: ListView.separated(
                controller: _scrollController,
                physics: const AlwaysScrollableScrollPhysics(),
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """          _FollowUpPaging(
            page: page,
            loading: _loading,
            onPrevious:
                page.page > 1 ? () => unawaited(_load(page.page - 1)) : null,
            onNext: page.hasMore && page.page < 5
                ? () => unawaited(_load(page.page + 1))
                : null,
          ),
""",
    """          if (_loading && page.items.isNotEmpty)
            const Padding(
              padding: EdgeInsets.all(12),
              child: SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """    final urgency = _followUpUrgency(item);
""",
    """    final urgency = _followUpUrgency(item);
    final remaining = double.tryParse(item.remainingAmount) ?? 0;
    final isPaid = item.paymentStatus.toLowerCase() == 'paid' || remaining <= 0;
    final amountColor =
        isPaid ? SafeContractsVisual.greenDeep : SafeContractsVisual.redDeep;
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """                            l10n.t('Remaining'),
""",
    """                            isPaid
                                ? (l10n.isArabic ? 'تم الدفع' : 'Paid')
                                : l10n.t('Remaining'),
""",
)
patch(
    "mobile/lib/features/followups/followups_screen.dart",
    """                                  color: urgency.color,
                                  fontWeight: FontWeight.w900,
""",
    """                                  color: amountColor,
                                  fontWeight: FontWeight.w900,
""",
)

# Dashboard list records use the authoritative paid amount for settled rows so
# a paid payment never renders as a green zero merely because remaining=0.
patch(
    "mobile/lib/features/dashboard/dashboard_models.dart",
    """    final id = _positiveInt(data['id'], 'payment.id');
    return DashboardRecord(
      id: id,
      type: DashboardRecordType.payment,
      title: _optionalText(data['reference'], 'payment.reference') ??
          'Payment #$id',
      status: _optionalText(data['status'], 'payment.status'),
      date: _optionalDate(data['due_date'], 'payment.due_date'),
      customerName: _counterpartyName(data, 'payment'),
      remainingAmount: _optionalMoneyText(
        data['remaining_amount'],
        'payment.remaining_amount',
      ),
      amount: _optionalMoneyText(
        data['original_amount'],
        'payment.original_amount',
      ),
    );
""",
    """    final id = _positiveInt(data['id'], 'payment.id');
    final status = _optionalText(data['status'], 'payment.status');
    final original = _optionalMoneyText(
      data['original_amount'],
      'payment.original_amount',
    );
    final paid = _optionalMoneyText(data['paid_amount'], 'payment.paid_amount');
    final remaining = _optionalMoneyText(
      data['remaining_amount'],
      'payment.remaining_amount',
    );
    final settledAmount = status == 'paid' ? (paid ?? original) : remaining;
    return DashboardRecord(
      id: id,
      type: DashboardRecordType.payment,
      title: _optionalText(data['reference'], 'payment.reference') ??
          'Payment #$id',
      status: status,
      date: _optionalDate(data['due_date'], 'payment.due_date'),
      customerName: _counterpartyName(data, 'payment'),
      remainingAmount: settledAmount,
      amount: status == 'paid' ? (paid ?? original) : original,
    );
""",
)

print("Alkenzy ADV 0.3.6 runtime transforms applied")
