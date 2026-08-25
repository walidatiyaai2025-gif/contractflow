import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_models.dart';
import '../ui/safecontracts_design.dart';
import '../ui/safecontracts_tokens.dart';
import 'collection_entry_dialog.dart';
import 'payments.dart';

typedef PaymentAction = Future<void> Function(SafeContractsPayment payment);

final class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({
    required this.repository,
    required this.pageSize,
    required this.filters,
    required this.currency,
    this.canManagePayments = false,
    this.canEnterCollection = false,
    this.onEditExpectedDate,
    this.onRecordCollection,
    this.refreshRevision = 0,
    super.key,
  });

  final PaymentsRepository repository;
  final int pageSize;
  final DashboardFilters filters;
  final MobileCurrencyConfig currency;
  final bool canManagePayments;
  final bool canEnterCollection;
  final PaymentAction? onEditExpectedDate;
  final PaymentAction? onRecordCollection;
  final int refreshRevision;

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

final class _PaymentsScreenState extends State<PaymentsScreen> {
  bool _loading = true;
  String? _error;
  PaymentPage? _page;
  int _pageNumber = 1;
  bool _requestInFlight = false;
  final ScrollController _scrollController = ScrollController();

  @override
  void initState() {
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

  @override
  void didUpdateWidget(covariant PaymentsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.filters != widget.filters ||
        oldWidget.pageSize != widget.pageSize) {
      unawaited(_load(1));
    } else if (oldWidget.refreshRevision != widget.refreshRevision) {
      unawaited(_load(_pageNumber, background: true));
    }
  }

  Future<void> _load(int page, {bool background = false}) async {
    if (_requestInFlight) return;
    _requestInFlight = true;
    final keepVisible = background && _page != null;
    if (!keepVisible) {
      setState(() {
        _loading = true;
        _error = null;
        _pageNumber = page;
      });
    }
    try {
      final result = await widget.repository.loadPage(
        page: page,
        perPage: widget.pageSize,
        filters: widget.filters,
      );
      if (!mounted) return;
      setState(() {
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
    } on Object catch (error) {
      if (!mounted) return;
      if (keepVisible) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    } finally {
      _requestInFlight = false;
    }
  }

  Future<void> _open(SafeContractsPayment payment) async {
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => PaymentDetailScreen(
          repository: widget.repository,
          paymentId: payment.id,
          currency: widget.currency,
          onEditExpectedDate: widget.onEditExpectedDate ??
              (widget.canManagePayments ? _editExpectedDate : null),
          onRecordCollection: widget.onRecordCollection ??
              (widget.canEnterCollection ? _recordCollection : null),
        ),
      ),
    );
    if (mounted) unawaited(_load(_pageNumber));
  }

  Future<void> _editExpectedDate(SafeContractsPayment payment) async {
    final l10n = context.scL10n;
    final input =
        TextEditingController(text: payment.expectedPaymentDate ?? '');
    final result = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        backgroundColor: SafeContractsVisual.surface,
        title: Text(l10n.t('Expected payment date')),
        content: TextField(
          controller: input,
          decoration: InputDecoration(
            labelText: l10n.t('YYYY-MM-DD (blank clears)'),
            prefixIcon: const Icon(Icons.event_outlined),
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(dialogContext),
            child: Text(l10n.t('Cancel')),
          ),
          FilledButton(
            onPressed: () {
              final value = input.text.trim();
              if (!_validNullableDate(value)) return;
              Navigator.pop(dialogContext, value);
            },
            child: Text(l10n.t('Save')),
          ),
        ],
      ),
    );
    input.dispose();
    if (result == null) return;

    try {
      await widget.repository.updateExpectedPaymentDate(
        payment.id,
        result.isEmpty ? null : result,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(context.scL10n.t('Expected payment date updated.')),
        ),
      );
    } on SafeContractsApiException catch (error) {
      if (!mounted) return;
      _showApiError(error);
    }
  }

  Future<void> _recordCollection(SafeContractsPayment payment) async {
    if (payment.isPayable) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.scL10n.isArabic
                ? 'دفعة المورد واجبة الدفع ولا يمكن تسجيلها كتحصيل عميل.'
                : 'A supplier payable cannot be recorded as a customer collection.',
          ),
        ),
      );
      return;
    }
    final receipt = await showDialog<CollectionReceipt>(
      context: context,
      barrierDismissible: false,
      builder: (_) => CollectionEntryDialog(
        repository: widget.repository,
        payment: payment,
        currency: widget.currency,
      ),
    );
    if (receipt == null || !mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(context.scL10n.collectionRecorded(receipt.id))),
    );
  }

  void _showApiError(SafeContractsApiException error) {
    final l10n = context.scL10n;
    final prefix = switch (error.statusCode) {
      422 => l10n.t('Validation'),
      403 => l10n.t('Forbidden'),
      409 => l10n.t('Conflict'),
      _ => l10n.t('Error'),
    };
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('$prefix: ${l10n.rawMessage(error.message)}')),
    );
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (_loading && _page == null) {
      return const _PaymentsLoading();
    }
    if (_error != null && _page == null) {
      return _PaymentsState(
        icon: Icons.cloud_off_outlined,
        title: l10n.t('Unable to load payments'),
        message: l10n.rawMessage(_error!),
        actionLabel: l10n.t('Retry'),
        onAction: () => unawaited(_load(_pageNumber)),
      );
    }
    final page = _page;
    if (page == null || page.payments.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _load(1),
        color: SafeContractsVisual.navy,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 56, 16, 24),
          children: [
            _PaymentsState(
              icon: Icons.receipt_long_outlined,
              title: l10n.isArabic ? 'لا توجد دفعات' : 'No payments',
              message: l10n.t(
                'No payments match the authorized filters.',
              ),
              actionLabel: l10n.t('Refresh'),
              onAction: () => unawaited(_load(1)),
              embedded: true,
            ),
          ],
        ),
      );
    }

    return SafeContractsBackdrop(
      child: Column(
        children: [
          if (_loading) const LinearProgressIndicator(minHeight: 2),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _load(_pageNumber),
              color: SafeContractsVisual.navy,
              child: ListView.separated(
                controller: _scrollController,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 18),
                itemCount: page.payments.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, index) {
                  final payment = page.payments[index];
                  final owner = payment.displayOwner ??
                      l10n.contractNumber(payment.contractId);
                  return _PremiumPaymentCard(
                    payment: payment,
                    owner: owner,
                    currency: widget.currency,
                    onTap: () => unawaited(_open(payment)),
                  );
                },
              ),
            ),
          ),
          if (_loading && page.payments.isNotEmpty)
            const Padding(
              padding: EdgeInsets.all(12),
              child: SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            ),
        ],
      ),
    );
  }
}

final class _PremiumPaymentCard extends StatelessWidget {
  const _PremiumPaymentCard({
    required this.payment,
    required this.owner,
    required this.currency,
    required this.onTap,
  });

  final SafeContractsPayment payment;
  final String owner;
  final MobileCurrencyConfig currency;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final statusColor = safeContractsStatusColor(payment.status);
    final statusSoft = safeContractsStatusSoftColor(payment.status);
    final directionColor = payment.isPayable
        ? SafeContractsVisual.roseGoldDark
        : SafeContractsVisual.greenDeep;
    final directionSoft = payment.isPayable
        ? SafeContractsVisual.roseGoldSoft
        : SafeContractsVisual.greenSoft;
    final remaining = double.tryParse(payment.remainingAmount) ?? 0;
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

    return Material(
      color: SafeContractsVisual.surface,
      borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius:
                BorderRadius.circular(SafeContractsVisual.compactRadius),
            border: Border.all(color: statusColor.withValues(alpha: 0.24)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x165A4638),
                blurRadius: 16,
                offset: Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: directionSoft,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(
                      payment.isPayable
                          ? Icons.north_east_rounded
                          : Icons.south_west_rounded,
                      color: directionColor,
                    ),
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          payment.reference ?? l10n.paymentNumber(payment.id),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style:
                              Theme.of(context).textTheme.titleMedium?.copyWith(
                                    color: SafeContractsVisual.ink,
                                    fontWeight: FontWeight.w900,
                                  ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          owner,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style:
                              Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: SafeContractsVisual.muted,
                                    fontWeight: FontWeight.w600,
                                  ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  _PaymentBadge(
                    label: l10n.status(payment.status),
                    foreground: statusColor,
                    background: statusSoft,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 7,
                children: [
                  _PaymentMeta(
                    icon: Icons.account_balance_wallet_outlined,
                    label: directionLabel,
                    color: directionColor,
                  ),
                  _PaymentMeta(
                    icon: Icons.calendar_today_outlined,
                    label: payment.dueDate,
                    color: SafeContractsVisual.navy,
                  ),
                  _PaymentMeta(
                    icon: Icons.folder_copy_outlined,
                    label: payment.contractNumber ??
                        l10n.contractNumber(payment.contractId),
                    color: SafeContractsVisual.muted,
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: SafeContractsVisual.backgroundRaised,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            isPaid
                                ? (l10n.isArabic
                                    ? 'المبلغ المدفوع'
                                    : 'Paid amount')
                                : l10n.t('Remaining'),
                            style: Theme.of(context)
                                .textTheme
                                .labelSmall
                                ?.copyWith(
                                  color: SafeContractsVisual.muted,
                                ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            _displayMoney(
                              context,
                              amountValue,
                              currency,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(
                                  color: amountColor,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                        ],
                      ),
                    ),
                    Icon(
                      Directionality.of(context) == TextDirection.rtl
                          ? Icons.chevron_left_rounded
                          : Icons.chevron_right_rounded,
                      color: SafeContractsVisual.muted,
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _PaymentBadge extends StatelessWidget {
  const _PaymentBadge({
    required this.label,
    required this.foreground,
    required this.background,
  });

  final String label;
  final Color foreground;
  final Color background;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
        decoration: BoxDecoration(
          color: background,
          borderRadius: BorderRadius.circular(99),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: foreground,
            fontSize: 10.5,
            fontWeight: FontWeight.w900,
          ),
        ),
      );
}

final class _PaymentMeta extends StatelessWidget {
  const _PaymentMeta({
    required this.icon,
    required this.label,
    required this.color,
  });

  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.08),
          borderRadius: BorderRadius.circular(99),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: color),
            const SizedBox(width: 5),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 170),
              child: Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: color,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      );
}

final class _PaymentPaging extends StatelessWidget {
  const _PaymentPaging({
    required this.page,
    required this.loading,
    required this.onPrevious,
    required this.onNext,
  });

  final PaymentPage page;
  final bool loading;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final rtl = Directionality.of(context) == TextDirection.rtl;
    return SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(14, 0, 14, 10),
      child: SafeContractsSurface(
        elevated: false,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
        child: Row(
          children: [
            IconButton(
              tooltip: l10n.t('Previous page'),
              onPressed: loading ? null : onPrevious,
              icon: Icon(
                rtl ? Icons.chevron_right_rounded : Icons.chevron_left_rounded,
              ),
            ),
            Expanded(
              child: Text(
                l10n.pageNumber(page.page),
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            IconButton(
              tooltip: l10n.t('Next page'),
              onPressed: loading ? null : onNext,
              icon: Icon(
                rtl ? Icons.chevron_left_rounded : Icons.chevron_right_rounded,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class PaymentDetailScreen extends StatefulWidget {
  const PaymentDetailScreen({
    required this.repository,
    required this.paymentId,
    required this.currency,
    this.onEditExpectedDate,
    this.onRecordCollection,
    super.key,
  });

  final PaymentsRepository repository;
  final int paymentId;
  final MobileCurrencyConfig currency;
  final PaymentAction? onEditExpectedDate;
  final PaymentAction? onRecordCollection;

  @override
  State<PaymentDetailScreen> createState() => _PaymentDetailScreenState();
}

final class _PaymentDetailScreenState extends State<PaymentDetailScreen> {
  bool _loading = true;
  String? _errorTitle;
  String? _errorMessage;
  SafeContractsPayment? _payment;
  bool _requestInFlight = false;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    if (_requestInFlight) return;
    _requestInFlight = true;
    setState(() {
      _loading = true;
      _errorTitle = null;
      _errorMessage = null;
    });
    try {
      final payment = await widget.repository.loadPayment(widget.paymentId);
      if (!mounted) return;
      setState(() {
        _payment = payment;
        _loading = false;
      });
    } on SafeContractsApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _payment = null;
        _loading = false;
        _errorTitle = switch (error.statusCode) {
          403 => 'Payment access denied',
          404 => 'Payment not found',
          _ => 'Unable to load payment',
        };
        _errorMessage = error.message;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _payment = null;
        _loading = false;
        _errorTitle = 'Unable to load payment';
        _errorMessage = error.toString();
      });
    } finally {
      _requestInFlight = false;
    }
  }

  Future<void> _runAction(PaymentAction action) async {
    final payment = _payment;
    if (payment == null || payment.contractIsArchived) return;
    await action(payment);
    if (mounted) await _load();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final payment = _payment;
    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        title: Text(l10n.t('Payment details')),
      ),
      body: _loading
          ? const _PaymentsLoading(detail: true)
          : _errorTitle != null
              ? _PaymentsState(
                  icon: Icons.error_outline_rounded,
                  title: l10n.t(_errorTitle!),
                  message: l10n.rawMessage(
                    _errorMessage ?? 'SafeContracts request failed.',
                  ),
                  actionLabel: l10n.t('Retry'),
                  onAction: () => unawaited(_load()),
                )
              : payment == null
                  ? _PaymentsState(
                      icon: Icons.search_off_rounded,
                      title: l10n.t('Payment not found'),
                      message: l10n.t('Payment not found.'),
                    )
                  : SafeContractsBackdrop(
                      child: RefreshIndicator(
                        onRefresh: _load,
                        color: SafeContractsVisual.navy,
                        child: ListView(
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.fromLTRB(14, 10, 14, 20),
                          children: [
                            SafeContractsPremiumHeader(
                              title: payment.reference ??
                                  l10n.paymentNumber(payment.id),
                              subtitle: payment.displayOwner ??
                                  payment.contractNumber ??
                                  l10n.contractNumber(payment.contractId),
                              leading: Container(
                                width: 42,
                                height: 42,
                                decoration: BoxDecoration(
                                  color: Colors.white.withValues(alpha: 0.12),
                                  borderRadius: BorderRadius.circular(13),
                                ),
                                child: Icon(
                                  payment.isPayable
                                      ? Icons.north_east_rounded
                                      : Icons.south_west_rounded,
                                  color: Colors.white,
                                ),
                              ),
                              trailing: _HeaderStatus(payment: payment),
                            ),
                            const SizedBox(height: 10),
                            _PaymentBalanceHero(
                              payment: payment,
                              currency: widget.currency,
                            ),
                            const SizedBox(height: 10),
                            SafeContractsSectionTitle(
                              title: l10n.isArabic
                                  ? 'بيانات الاستحقاق'
                                  : 'Due information',
                              subtitle: l10n.isArabic
                                  ? 'التواريخ والقيم من الخادم'
                                  : 'Dates and values from the server',
                            ),
                            const SizedBox(height: 6),
                            SafeContractsSurface(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 12, vertical: 4),
                              child: Column(
                                children: [
                                  _DetailValue(
                                    icon: Icons.calendar_today_outlined,
                                    label: l10n.t('Contractual due date'),
                                    value: payment.dueDate,
                                  ),
                                  _DetailValue(
                                    icon: Icons.event_available_outlined,
                                    label: l10n.t('Expected payment date'),
                                    value: payment.expectedPaymentDate ?? '—',
                                  ),
                                  _DetailValue(
                                    icon: Icons.payments_outlined,
                                    label: l10n.t('Original amount'),
                                    value: _displayMoney(
                                      context,
                                      payment.originalAmount,
                                      widget.currency,
                                    ),
                                  ),
                                  _DetailValue(
                                    icon: Icons.done_all_rounded,
                                    label: payment.isPayable
                                        ? (l10n.isArabic
                                            ? 'المبلغ المدفوع'
                                            : 'Paid amount')
                                        : (l10n.isArabic
                                            ? 'المبلغ المحصل'
                                            : 'Collected amount'),
                                    value: _displayMoney(
                                      context,
                                      payment.paidAmount,
                                      widget.currency,
                                    ),
                                  ),
                                  _DetailValue(
                                    icon: Icons.account_balance_wallet_outlined,
                                    label: l10n.t('Remaining amount'),
                                    value: _displayMoney(
                                      context,
                                      payment.remainingAmount,
                                      widget.currency,
                                    ),
                                    emphasize: true,
                                    last: true,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 10),
                            SafeContractsSectionTitle(
                              title: l10n.isArabic
                                  ? 'السياق التجاري'
                                  : 'Business context',
                            ),
                            const SizedBox(height: 6),
                            SafeContractsSurface(
                              padding: const EdgeInsets.symmetric(
                                  horizontal: 12, vertical: 4),
                              child: Column(
                                children: [
                                  _DetailValue(
                                    icon: Icons.folder_copy_outlined,
                                    label: l10n.t('Contract'),
                                    value: payment.contractNumber ??
                                        l10n.contractNumber(payment.contractId),
                                  ),
                                  if (payment.displayOwner != null)
                                    _DetailValue(
                                      icon: payment.isPayable
                                          ? Icons.local_shipping_outlined
                                          : Icons.business_outlined,
                                      label: payment.isPayable
                                          ? (l10n.isArabic
                                              ? 'المورد'
                                              : 'Supplier')
                                          : l10n.t('Customer'),
                                      value: payment.displayOwner!,
                                    ),
                                  _DetailValue(
                                    icon: Icons.archive_outlined,
                                    label: l10n.t('Contract archived'),
                                    value:
                                        l10n.yesNo(payment.contractIsArchived),
                                    last: true,
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(height: 14),
                            SafeContractsSurface(
                              elevated: false,
                              padding: const EdgeInsets.all(13),
                              accent: SafeContractsVisual.green,
                              child: Row(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  const Icon(
                                    Icons.verified_user_outlined,
                                    color: SafeContractsVisual.green,
                                  ),
                                  const SizedBox(width: 10),
                                  Expanded(
                                    child: Text(
                                      l10n.t(
                                        'Dates, balances and status are server-authoritative. Mobile does not recalculate them.',
                                      ),
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                            color: SafeContractsVisual.muted,
                                          ),
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            if (!payment.contractIsArchived &&
                                widget.onEditExpectedDate != null) ...[
                              const SizedBox(height: 16),
                              OutlinedButton.icon(
                                onPressed: () => unawaited(
                                  _runAction(widget.onEditExpectedDate!),
                                ),
                                icon:
                                    const Icon(Icons.event_available_outlined),
                                label:
                                    Text(l10n.t('Edit expected payment date')),
                              ),
                            ],
                            if (!payment.contractIsArchived &&
                                !payment.isPayable &&
                                widget.onRecordCollection != null) ...[
                              const SizedBox(height: 10),
                              FilledButton.icon(
                                onPressed: () => unawaited(
                                  _runAction(widget.onRecordCollection!),
                                ),
                                icon: const Icon(Icons.add_card_outlined),
                                label: Text(
                                  l10n.isArabic
                                      ? 'تسجيل تحصيل عميل'
                                      : 'Record customer collection',
                                ),
                              ),
                            ],
                            if (payment.isPayable) ...[
                              const SizedBox(height: 12),
                              SafeContractsSurface(
                                elevated: false,
                                padding: const EdgeInsets.all(12),
                                accent: SafeContractsVisual.roseGold,
                                child: Row(
                                  children: [
                                    const Icon(
                                      Icons.local_shipping_outlined,
                                      color: SafeContractsVisual.roseGoldDark,
                                    ),
                                    const SizedBox(width: 10),
                                    Expanded(
                                      child: Text(
                                        l10n.isArabic
                                            ? 'هذه دفعة مورد واجبة الدفع. لا يتم عرض إجراء تحصيل العملاء عليها.'
                                            : 'This is a supplier payable. Customer collection actions are not shown for it.',
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
    );
  }
}

final class _HeaderStatus extends StatelessWidget {
  const _HeaderStatus({required this.payment});

  final SafeContractsPayment payment;

  @override
  Widget build(BuildContext context) {
    final color = safeContractsStatusColor(payment.status);
    return Container(
      constraints: const BoxConstraints(maxWidth: 100),
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.12),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              context.scL10n.status(payment.status),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 10,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

final class _PaymentBalanceHero extends StatelessWidget {
  const _PaymentBalanceHero({
    required this.payment,
    required this.currency,
  });

  final SafeContractsPayment payment;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final accent = payment.isPayable
        ? SafeContractsVisual.roseGoldDark
        : SafeContractsVisual.greenDeep;
    return SafeContractsSurface(
      accent: accent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  payment.isPayable
                      ? (l10n.isArabic ? 'واجبة الدفع' : 'Payable')
                      : (l10n.isArabic ? 'مستحقة للتحصيل' : 'Receivable'),
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: accent,
                        fontWeight: FontWeight.w900,
                      ),
                ),
              ),
              Icon(
                payment.isPayable
                    ? Icons.north_east_rounded
                    : Icons.south_west_rounded,
                color: accent,
              ),
            ],
          ),
          const SizedBox(height: 10),
          _PaymentMoneyAmount(
            amount: payment.remainingAmount,
            currency: currency,
          ),
          const SizedBox(height: 2),
          Text(
            l10n.t('Remaining amount'),
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
        ],
      ),
    );
  }
}

final class _PaymentMoneyAmount extends StatelessWidget {
  const _PaymentMoneyAmount({required this.amount, required this.currency});

  final String amount;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final token = currency.displayToken;
    final formatted = _displayMoney(context, amount, currency);
    final numeric =
        token.isEmpty ? formatted : formatted.replaceFirst(token, '').trim();
    final scale =
        SafeContractsTypography.viewportScale(MediaQuery.sizeOf(context).width);
    final amountStyle = Theme.of(context).textTheme.headlineMedium?.copyWith(
          color: SafeContractsVisual.ink,
          fontSize: SafeContractsTypography.headlineMedium * scale,
          height: SafeContractsTypography.headlineHeight,
          fontWeight: FontWeight.w900,
        );
    final currencyStyle = Theme.of(context).textTheme.labelLarge?.copyWith(
          color: SafeContractsVisual.muted,
          fontSize: SafeContractsTypography.labelLarge * scale,
          height: SafeContractsTypography.labelHeight,
          fontWeight: FontWeight.w800,
        );
    if (token.isEmpty) {
      return Text(formatted,
          maxLines: 1, overflow: TextOverflow.ellipsis, style: amountStyle);
    }
    final children = l10n.isArabic
        ? <InlineSpan>[
            TextSpan(text: numeric, style: amountStyle),
            const TextSpan(text: ' '),
            TextSpan(text: token, style: currencyStyle),
          ]
        : <InlineSpan>[
            TextSpan(text: token, style: currencyStyle),
            const TextSpan(text: ' '),
            TextSpan(text: numeric, style: amountStyle),
          ];
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: AlignmentDirectional.centerStart,
      child: Text.rich(TextSpan(children: children), maxLines: 1),
    );
  }
}

final class _DetailValue extends StatelessWidget {
  const _DetailValue({
    required this.icon,
    required this.label,
    required this.value,
    this.emphasize = false,
    this.last = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool emphasize;
  final bool last;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(vertical: 7),
        decoration: BoxDecoration(
          border: last
              ? null
              : const Border(
                  bottom: BorderSide(color: SafeContractsVisual.contour),
                ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: SafeContractsVisual.navySoft,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Icon(icon, size: 16, color: SafeContractsVisual.navy),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    label,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                  ),
                  const SizedBox(height: 2),
                  SelectableText(
                    value,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          fontWeight:
                              emphasize ? FontWeight.w900 : FontWeight.w700,
                          color: emphasize
                              ? SafeContractsVisual.navy
                              : SafeContractsVisual.ink,
                        ),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
}

final class _PaymentsLoading extends StatelessWidget {
  const _PaymentsLoading({this.detail = false});

  final bool detail;

  @override
  Widget build(BuildContext context) => SafeContractsBackdrop(
        child: ListView.separated(
          padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
          itemCount: detail ? 4 : 5,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (context, index) => Container(
            height: detail ? (index == 0 ? 112 : 92) : 128,
            decoration: BoxDecoration(
              color: SafeContractsVisual.surface,
              borderRadius:
                  BorderRadius.circular(SafeContractsVisual.compactRadius),
              border: Border.all(color: SafeContractsVisual.outline),
            ),
            child: const Center(
              child: SizedBox(
                width: 28,
                child: LinearProgressIndicator(minHeight: 2),
              ),
            ),
          ),
        ),
      );
}

final class _PaymentsState extends StatelessWidget {
  const _PaymentsState({
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
    this.embedded = false,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final bool embedded;

  @override
  Widget build(BuildContext context) {
    final content = SafeContractsSurface(
      elevated: !embedded,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: SafeContractsVisual.navySoft,
              borderRadius: BorderRadius.circular(17),
            ),
            child: Icon(icon, color: SafeContractsVisual.navy, size: 28),
          ),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 14),
            FilledButton.tonal(
              onPressed: onAction,
              child: Text(actionLabel!),
            ),
          ],
        ],
      ),
    );
    if (embedded) return content;
    return SafeContractsBackdrop(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: content,
        ),
      ),
    );
  }
}

String _displayMoney(
  BuildContext context,
  String raw,
  MobileCurrencyConfig currency,
) {
  final formatted = context.scL10n.money(raw, currency);
  return formatted.replaceFirst(RegExp(r'\.00(?=\s|$)'), '');
}

bool _validNullableDate(String value) {
  final normalized = value.trim();
  if (normalized.isEmpty) return true;
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(normalized);
  if (match == null) return false;
  final parsed = DateTime.tryParse(normalized);
  return parsed != null &&
      parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}
