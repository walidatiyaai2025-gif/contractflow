import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_models.dart';
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

  @override
  void initState() {
    super.initState();
    unawaited(_load(1));
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
        _page = result;
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
    final input = TextEditingController(
      text: payment.expectedPaymentDate ?? '',
    );
    final result = await showDialog<String>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l10n.t('Expected payment date')),
        content: TextField(
          controller: input,
          decoration: InputDecoration(
            labelText: l10n.t('YYYY-MM-DD (blank clears)'),
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
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return _ErrorState(
        message: l10n.rawMessage(_error!),
        onRetry: () => _load(_pageNumber),
      );
    }
    final page = _page;
    if (page == null || page.payments.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _load(1),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: <Widget>[
            const SizedBox(height: 180),
            Center(
              child: Text(l10n.t('No payments match the authorized filters.')),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => _load(_pageNumber),
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              itemCount: page.payments.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final payment = page.payments[index];
                final owner = payment.customerName ??
                    payment.contractNumber ??
                    l10n.contractNumber(payment.contractId);
                return Card(
                  child: ListTile(
                    title: Text(
                      payment.reference ?? l10n.paymentNumber(payment.id),
                    ),
                    subtitle: Text(
                      '$owner · ${payment.dueDate} · ${l10n.status(payment.status)}\n'
                      '${l10n.t('Remaining')}: ${l10n.money(payment.remainingAmount, widget.currency)}',
                    ),
                    isThreeLine: true,
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => unawaited(_open(payment)),
                  ),
                );
              },
            ),
          ),
        ),
        SafeArea(
          top: false,
          child: Row(
            children: [
              IconButton(
                tooltip: l10n.t('Previous page'),
                onPressed: page.page > 1
                    ? () => unawaited(_load(page.page - 1))
                    : null,
                icon: const Icon(Icons.chevron_left),
              ),
              Expanded(
                child: Text(
                  '${l10n.pageNumber(page.page)} · ${page.sort} ${page.order}',
                  textAlign: TextAlign.center,
                ),
              ),
              IconButton(
                tooltip: l10n.t('Next page'),
                onPressed: page.hasMore && page.page < 5
                    ? () => unawaited(_load(page.page + 1))
                    : null,
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
        ),
      ],
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

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
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
      appBar: AppBar(title: Text(l10n.t('Payment details'))),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _errorTitle != null
              ? _ErrorState(
                  title: l10n.t(_errorTitle!),
                  message: l10n.rawMessage(
                    _errorMessage ?? 'SafeContracts request failed.',
                  ),
                  onRetry: _load,
                )
              : payment == null
                  ? Center(child: Text(l10n.t('Payment not found.')))
                  : SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(
                            payment.reference ?? l10n.paymentNumber(payment.id),
                            style: Theme.of(context).textTheme.headlineSmall,
                          ),
                          const SizedBox(height: 16),
                          _Value(l10n.t('Status'), l10n.status(payment.status)),
                          _Value(
                              l10n.t('Contractual due date'), payment.dueDate),
                          _Value(
                            l10n.t('Expected payment date'),
                            payment.expectedPaymentDate ?? '—',
                          ),
                          _Value(
                            l10n.t('Original amount'),
                            l10n.money(payment.originalAmount, widget.currency),
                          ),
                          _Value(
                            l10n.t('Paid amount'),
                            l10n.money(payment.paidAmount, widget.currency),
                          ),
                          _Value(
                            l10n.t('Remaining amount'),
                            l10n.money(
                                payment.remainingAmount, widget.currency),
                          ),
                          _Value(
                            l10n.t('Contract archived'),
                            l10n.yesNo(payment.contractIsArchived),
                          ),
                          const SizedBox(height: 12),
                          Text(
                            l10n.t(
                              'Dates, balances and status are server-authoritative. Mobile does not recalculate receivables.',
                            ),
                          ),
                          if (!payment.contractIsArchived &&
                              widget.onEditExpectedDate != null) ...[
                            const SizedBox(height: 20),
                            FilledButton.tonalIcon(
                              onPressed: () => unawaited(
                                  _runAction(widget.onEditExpectedDate!)),
                              icon: const Icon(Icons.event_available_outlined),
                              label: Text(l10n.t('Edit expected payment date')),
                            ),
                          ],
                          if (!payment.contractIsArchived &&
                              widget.onRecordCollection != null) ...[
                            const SizedBox(height: 12),
                            FilledButton.tonalIcon(
                              onPressed: () => unawaited(
                                  _runAction(widget.onRecordCollection!)),
                              icon: const Icon(Icons.add_card_outlined),
                              label: Text(l10n.t('Record collection')),
                            ),
                          ],
                        ],
                      ),
                    ),
    );
  }
}

final class _Value extends StatelessWidget {
  const _Value(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(label),
        subtitle: SelectableText(value),
      );
}

final class _ErrorState extends StatelessWidget {
  const _ErrorState({
    this.title = 'Unable to load payments',
    required this.message,
    required this.onRetry,
  });

  final String title;
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                context.scL10n.t(title),
                style: Theme.of(context).textTheme.titleLarge,
              ),
              const SizedBox(height: 8),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton.tonal(
                onPressed: () => unawaited(onRetry()),
                child: Text(context.scL10n.t('Retry')),
              ),
            ],
          ),
        ),
      );
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
