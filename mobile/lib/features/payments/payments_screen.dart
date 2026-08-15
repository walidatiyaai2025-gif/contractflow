import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../dashboard/dashboard_models.dart';
import 'collection_entry_dialog.dart';
import 'payments.dart';

final class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({
    required this.repository,
    required this.pageSize,
    required this.filters,
    required this.canManagePayments,
    required this.canEnterCollection,
    super.key,
  });

  final PaymentsRepository repository;
  final int pageSize;
  final DashboardFilters filters;
  final bool canManagePayments;
  final bool canEnterCollection;

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
    if (oldWidget.filters != widget.filters || oldWidget.pageSize != widget.pageSize) {
      unawaited(_load(1));
    }
  }

  Future<void> _load(int page) async {
    setState(() {
      _loading = true;
      _error = null;
      _pageNumber = page;
    });
    try {
      final result = await widget.repository.loadPage(
        page: page,
        perPage: widget.pageSize,
        filters: widget.filters,
      );
      if (!mounted) return;
      setState(() {
        _page = result;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) return;
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
          canManagePayments: widget.canManagePayments,
          canEnterCollection: widget.canEnterCollection,
        ),
      ),
    );
    if (mounted) unawaited(_load(_pageNumber));
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return _ErrorState(message: _error!, onRetry: () => _load(_pageNumber));
    final page = _page;
    if (page == null || page.payments.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _load(1),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const <Widget>[
            SizedBox(height: 180),
            Center(child: Text('No payments match the authorized filters.')),
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
                return Card(
                  child: ListTile(
                    title: Text(payment.reference ?? 'Payment #${payment.id}'),
                    subtitle: Text(
                      '${payment.customerName ?? payment.contractNumber ?? 'Contract #${payment.contractId}'} · ${payment.dueDate} · ${payment.status}\nRemaining: ${payment.remainingAmount}',
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
                tooltip: 'Previous page',
                onPressed: page.page > 1 ? () => unawaited(_load(page.page - 1)) : null,
                icon: const Icon(Icons.chevron_left),
              ),
              Expanded(child: Text('Page ${page.page} · due_date ${page.order}', textAlign: TextAlign.center)),
              IconButton(
                tooltip: 'Next page',
                onPressed: page.hasMore && page.page < 5 ? () => unawaited(_load(page.page + 1)) : null,
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
    required this.canManagePayments,
    required this.canEnterCollection,
    super.key,
  });

  final PaymentsRepository repository;
  final int paymentId;
  final bool canManagePayments;
  final bool canEnterCollection;

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

  Future<void> _editExpectedDate(SafeContractsPayment payment) async {
    final input = TextEditingController(text: payment.expectedPaymentDate ?? '');
    final result = await showDialog<String?>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Expected payment date'),
        content: TextField(controller: input, decoration: const InputDecoration(labelText: 'YYYY-MM-DD (blank clears)')),
        actions: [
          TextButton(onPressed: () => Navigator.pop(context), child: const Text('Cancel')),
          FilledButton(
            onPressed: () {
              final value = input.text.trim();
              if (!_validNullableDate(value)) return;
              Navigator.pop(context, value);
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
    input.dispose();
    if (result == null) return;
    try {
      await widget.repository.updateExpectedPaymentDate(payment.id, result.isEmpty ? null : result);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Expected payment date updated.')));
      await _load();
    } on SafeContractsApiException catch (error) {
      if (!mounted) return;
      _showApiError(error);
    }
  }

  Future<void> _recordCollection(SafeContractsPayment payment) async {
    final receipt = await showDialog<CollectionReceipt>(
      context: context,
      barrierDismissible: false,
      builder: (_) => CollectionEntryDialog(repository: widget.repository, payment: payment),
    );
    if (receipt == null || !mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('Collection #${receipt.id} recorded.')));
    await _load();
  }

  void _showApiError(SafeContractsApiException error) {
    final prefix = switch (error.statusCode) {
      422 => 'Validation',
      403 => 'Forbidden',
      409 => 'Conflict',
      _ => 'Error',
    };
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text('$prefix: ${error.message}')));
  }

  @override
  Widget build(BuildContext context) {
    final payment = _payment;
    return Scaffold(
      appBar: AppBar(title: const Text('Payment details')),
      floatingActionButton: payment != null && !payment.contractIsArchived && widget.canEnterCollection
          ? FloatingActionButton.extended(
              onPressed: () => unawaited(_recordCollection(payment)),
              icon: const Icon(Icons.add_card_outlined),
              label: const Text('Record collection'),
            )
          : null,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _errorTitle != null
              ? _ErrorState(title: _errorTitle!, message: _errorMessage ?? 'SafeContracts request failed.', onRetry: _load)
              : payment == null
                  ? const Center(child: Text('Payment not found.'))
                  : SingleChildScrollView(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Text(payment.reference ?? 'Payment #${payment.id}', style: Theme.of(context).textTheme.headlineSmall),
                          const SizedBox(height: 16),
                          _Value('Status', payment.status),
                          _Value('Contractual due date', payment.dueDate),
                          _Value('Expected payment date', payment.expectedPaymentDate ?? '—'),
                          _Value('Original amount', payment.originalAmount),
                          _Value('Paid amount', payment.paidAmount),
                          _Value('Remaining amount', payment.remainingAmount),
                          _Value('Contract archived', payment.contractIsArchived ? 'Yes' : 'No'),
                          const SizedBox(height: 12),
                          const Text('Contractual dates, balances and status are server-authoritative. Mobile does not recalculate receivables.'),
                          if (widget.canManagePayments && !payment.contractIsArchived) ...[
                            const SizedBox(height: 20),
                            FilledButton.tonalIcon(
                              onPressed: () => unawaited(_editExpectedDate(payment)),
                              icon: const Icon(Icons.event_available_outlined),
                              label: const Text('Edit expected payment date'),
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
  Widget build(BuildContext context) => ListTile(contentPadding: EdgeInsets.zero, title: Text(label), subtitle: SelectableText(value));
}

final class _ErrorState extends StatelessWidget {
  const _ErrorState({this.title = 'Unable to load payments', required this.message, required this.onRetry});
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
              Text(title, style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 8),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton.tonal(onPressed: () => unawaited(onRetry()), child: const Text('Retry')),
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
  return parsed != null && parsed.year == int.parse(match.group(1)!) && parsed.month == int.parse(match.group(2)!) && parsed.day == int.parse(match.group(3)!);
}
