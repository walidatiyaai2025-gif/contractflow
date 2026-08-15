import 'dart:async';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import '../session/session_controller.dart';
import 'mobile_records.dart';
import 'mobile_records_repository.dart';

final class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({
    required this.repository,
    required this.pageSize,
    required this.session,
    required this.canEnterCollection,
    required this.filters,
    super.key,
  });

  static const managePaymentsCapability = 'safecontracts_manage_payments';

  final MobileRecordsRepository repository;
  final int pageSize;
  final SafeContractsSession session;
  final bool canEnterCollection;
  final DashboardFilters filters;

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

final class _PaymentsScreenState extends State<PaymentsScreen> {
  bool _loading = true;
  String? _error;
  List<PaymentRecord> _payments = const <PaymentRecord>[];

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  @override
  void didUpdateWidget(covariant PaymentsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.filters != widget.filters) {
      unawaited(_load());
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await widget.repository.payments(
        widget.filters,
        pageSize: widget.pageSize,
      );
      if (!mounted) return;
      setState(() {
        _payments = rows;
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

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return _PaymentError(message: _error!, onRetry: _load);
    if (_payments.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const <Widget>[
            SizedBox(height: 180),
            Center(child: Text('No payments match the authorized filters.')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: _payments.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final payment = _payments[index];
          return Card(
            child: ListTile(
              title: Text(payment.reference ?? 'Payment #${payment.id}'),
              subtitle: Text(
                '${payment.customerName ?? payment.contractNumber ?? 'Contract #${payment.contractId}'} · '
                '${payment.dueDate} · ${payment.status}\n'
                'Remaining: ${payment.remainingAmount}',
              ),
              isThreeLine: true,
              trailing: const Icon(Icons.chevron_right),
              onTap: () async {
                await Navigator.of(context).push<void>(
                  MaterialPageRoute<void>(
                    builder: (_) => PaymentDetailScreen(
                      repository: widget.repository,
                      paymentId: payment.id,
                      canManagePayments: widget.session.can(
                        PaymentsScreen.managePaymentsCapability,
                      ),
                      canEnterCollection: widget.canEnterCollection,
                    ),
                  ),
                );
                if (mounted) unawaited(_load());
              },
            ),
          );
        },
      ),
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

  final MobileRecordsRepository repository;
  final int paymentId;
  final bool canManagePayments;
  final bool canEnterCollection;

  @override
  State<PaymentDetailScreen> createState() => _PaymentDetailScreenState();
}

final class _PaymentDetailScreenState extends State<PaymentDetailScreen> {
  bool _loading = true;
  String? _error;
  PaymentRecord? _payment;

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final payment = await widget.repository.payment(widget.paymentId);
      if (!mounted) return;
      setState(() {
        _payment = payment;
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

  Future<void> _editExpected(PaymentRecord payment) async {
    final controller =
        TextEditingController(text: payment.expectedPaymentDate ?? '');
    final result = await showDialog<String?>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Expected payment date'),
        content: TextField(
          controller: controller,
          decoration: const InputDecoration(
            labelText: 'YYYY-MM-DD (blank clears)',
          ),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () {
              final text = controller.text.trim();
              if (text.isNotEmpty && !_validDate(text)) return;
              Navigator.pop(context, text);
            },
            child: const Text('Save'),
          ),
        ],
      ),
    );
    controller.dispose();
    if (result == null) return;

    try {
      await widget.repository.updateExpectedPaymentDate(
        payment.id,
        result.isEmpty ? null : result,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Expected date updated by SafeContracts server.'),
        ),
      );
      await _load();
    } on Object catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  Future<void> _recordCollection(PaymentRecord payment) async {
    final receipt = await showDialog<CollectionReceipt>(
      context: context,
      barrierDismissible: false,
      builder: (_) => _CollectionEntryDialog(
        repository: widget.repository,
        payment: payment,
      ),
    );
    if (receipt == null || !mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text('Collection #${receipt.id} recorded.')),
    );
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    final payment = _payment;
    final editable = payment != null && !payment.contractIsArchived;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Payment details'),
        actions: [
          if (editable && widget.canManagePayments)
            IconButton(
              tooltip: 'Edit expected date',
              onPressed: () => unawaited(_editExpected(payment!)),
              icon: const Icon(Icons.event_available_outlined),
            ),
        ],
      ),
      floatingActionButton: editable && widget.canEnterCollection
          ? FloatingActionButton.extended(
              onPressed: () => unawaited(_recordCollection(payment!)),
              icon: const Icon(Icons.add_card_outlined),
              label: const Text('Record collection'),
            )
          : null,
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _PaymentError(message: _error!, onRetry: _load)
              : payment == null
                  ? const Center(child: Text('Payment not found.'))
                  : ListView(
                      padding: const EdgeInsets.all(24),
                      children: [
                        Text(
                          payment.reference ?? 'Payment #${payment.id}',
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 16),
                        _PaymentRow('Status', payment.status),
                        _PaymentRow('Contractual due date', payment.dueDate),
                        _PaymentRow(
                          'Expected payment date',
                          payment.expectedPaymentDate ?? '—',
                        ),
                        _PaymentRow('Original amount', payment.originalAmount),
                        _PaymentRow('Paid amount', payment.paidAmount),
                        _PaymentRow(
                            'Remaining amount', payment.remainingAmount),
                        _PaymentRow(
                          'Contract archived',
                          payment.contractIsArchived ? 'Yes' : 'No',
                        ),
                        const SizedBox(height: 8),
                        const Text(
                          'Financial balances and payment status above are server-authoritative.',
                        ),
                      ],
                    ),
    );
  }
}

final class _CollectionEntryDialog extends StatefulWidget {
  const _CollectionEntryDialog({
    required this.repository,
    required this.payment,
  });

  final MobileRecordsRepository repository;
  final PaymentRecord payment;

  @override
  State<_CollectionEntryDialog> createState() => _CollectionEntryDialogState();
}

final class _CollectionEntryDialogState extends State<_CollectionEntryDialog> {
  final _amount = TextEditingController();
  final _date = TextEditingController();
  final _reference = TextEditingController();
  final _proof = TextEditingController();
  bool _loadingMethods = true;
  bool _submitting = false;
  String? _error;
  List<PaymentMethodOption> _methods = const <PaymentMethodOption>[];
  int? _methodId;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _date.text = '${now.year.toString().padLeft(4, '0')}-'
        '${now.month.toString().padLeft(2, '0')}-'
        '${now.day.toString().padLeft(2, '0')}';
    unawaited(_loadMethods());
  }

  Future<void> _loadMethods() async {
    try {
      final methods = await widget.repository.paymentMethods();
      if (!mounted) return;
      setState(() {
        _methods = methods;
        _methodId = methods.isEmpty ? null : methods.first.id;
        _loadingMethods = false;
        _error =
            methods.isEmpty ? 'No active payment methods are available.' : null;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _loadingMethods = false;
        _error = error.toString();
      });
    }
  }

  Future<void> _submit() async {
    final amount = _amount.text.trim();
    final date = _date.text.trim();
    final methodId = _methodId;
    final amountValue = double.tryParse(amount);
    if (amountValue == null || !amountValue.isFinite || amountValue <= 0) {
      setState(() => _error = 'Enter a positive collection amount.');
      return;
    }
    if (!_validDate(date)) {
      setState(
        () => _error = 'Collection date must be a valid YYYY-MM-DD date.',
      );
      return;
    }
    if (methodId == null) {
      setState(() => _error = 'Choose an active payment method.');
      return;
    }
    final proofText = _proof.text.trim();
    final proofId = proofText.isEmpty ? null : int.tryParse(proofText);
    if (proofText.isNotEmpty && (proofId == null || proofId <= 0)) {
      setState(() => _error = 'Proof media ID must be a positive integer.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final receipt = await widget.repository.recordCollection(
        paymentId: widget.payment.id,
        amount: amount,
        collectionDate: date,
        paymentMethodId: methodId,
        reference: _reference.text,
        proofMediaId: proofId,
      );
      if (!mounted) return;
      Navigator.pop(context, receipt);
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = error.toString();
      });
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    _date.dispose();
    _reference.dispose();
    _proof.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: Text('Record collection · Payment #${widget.payment.id}'),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: _amount,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Amount'),
              ),
              TextField(
                controller: _date,
                decoration: const InputDecoration(
                  labelText: 'Collection date YYYY-MM-DD',
                ),
              ),
              if (_loadingMethods)
                const Padding(
                  padding: EdgeInsets.all(16),
                  child: CircularProgressIndicator(),
                )
              else if (_methods.isNotEmpty)
                DropdownButtonFormField<int>(
                  initialValue: _methodId,
                  decoration:
                      const InputDecoration(labelText: 'Payment method'),
                  items: _methods
                      .map(
                        (method) => DropdownMenuItem<int>(
                          value: method.id,
                          child: Text(method.name),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: _submitting
                      ? null
                      : (value) => setState(() => _methodId = value),
                ),
              TextField(
                controller: _reference,
                maxLength: 191,
                decoration: const InputDecoration(
                  labelText: 'Reference (optional)',
                ),
              ),
              TextField(
                controller: _proof,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Proof media ID (optional)',
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(
                  _error!,
                  style: TextStyle(color: Theme.of(context).colorScheme.error),
                ),
              ],
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: _submitting ? null : () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(
          onPressed: _submitting || _loadingMethods || _methods.isEmpty
              ? null
              : () => unawaited(_submit()),
          child: _submitting
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : const Text('Record'),
        ),
      ],
    );
  }
}

bool _validDate(String value) {
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(value);
  if (match == null) return false;
  final date = DateTime.tryParse(value);
  if (date == null) return false;
  return date.year == int.parse(match.group(1)!) &&
      date.month == int.parse(match.group(2)!) &&
      date.day == int.parse(match.group(3)!);
}

final class _PaymentRow extends StatelessWidget {
  const _PaymentRow(this.label, this.value);
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(label),
      subtitle: Text(value),
    );
  }
}

final class _PaymentError extends StatelessWidget {
  const _PaymentError({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton.tonal(
              onPressed: () => unawaited(onRetry()),
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}