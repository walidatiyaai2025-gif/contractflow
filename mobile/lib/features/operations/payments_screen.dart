import 'dart:async';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import '../session/session_controller.dart';
import 'operations_models.dart';
import 'operations_repository.dart';

final class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({
    required this.repository,
    required this.filters,
    required this.session,
    required this.pageSize,
    super.key,
  });

  final MobileOperationsRepository repository;
  final DashboardFilters filters;
  final SafeContractsSession session;
  final int pageSize;

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

final class _PaymentsScreenState extends State<PaymentsScreen> {
  late Future<List<PaymentRecord>> _rows;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _rows = widget.repository.payments(widget.filters, pageSize: widget.pageSize);
  }

  Future<void> _refresh() async {
    setState(_reload);
    await _rows;
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<PaymentRecord>>(
      future: _rows,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return Center(
            child: FilledButton(
              onPressed: () => unawaited(_refresh()),
              child: Text('Retry: ${snapshot.error}'),
            ),
          );
        }
        final rows = snapshot.data ?? const <PaymentRecord>[];
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: rows.isEmpty ? 1 : rows.length,
            itemBuilder: (context, index) {
              if (rows.isEmpty) {
                return const Padding(
                  padding: EdgeInsets.all(32),
                  child: Center(child: Text('No payments match the current dashboard filters.')),
                );
              }
              final payment = rows[index];
              return ListTile(
                leading: const Icon(Icons.event_note_outlined),
                title: Text(payment.reference ?? 'Payment #${payment.id}'),
                subtitle: Text(
                  '${payment.dueDate} • ${payment.status} • Remaining ${payment.remainingAmount}',
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => unawaited(_showPayment(context, payment.id)),
              );
            },
          ),
        );
      },
    );
  }

  Future<void> _showPayment(BuildContext context, int id) async {
    try {
      var payment = await widget.repository.payment(id);
      if (!context.mounted) return;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Text(payment.reference ?? 'Payment #${payment.id}'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Contract: ${payment.contractNumber ?? payment.contractId}'),
                  Text('Due date: ${payment.dueDate}'),
                  Text('Expected date: ${payment.expectedPaymentDate ?? 'Not set'}'),
                  Text('Original: ${payment.originalAmount}'),
                  Text('Paid: ${payment.paidAmount}'),
                  Text('Remaining: ${payment.remainingAmount}'),
                  Text('Status: ${payment.status}'),
                ],
              ),
            ),
            actions: [
              if (widget.session.can('safecontracts_manage_payments') &&
                  !payment.contractIsArchived)
                TextButton(
                  onPressed: () async {
                    final changed = await _editExpectedDate(dialogContext, payment);
                    if (!changed || !dialogContext.mounted) return;
                    payment = await widget.repository.payment(id);
                    setDialogState(() {});
                    if (mounted) setState(_reload);
                  },
                  child: const Text('Edit expected date'),
                ),
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('Close'),
              ),
            ],
          ),
        ),
      );
    } on Object catch (error) {
      if (context.mounted) _showError(context, error);
    }
  }

  Future<bool> _editExpectedDate(
    BuildContext context,
    PaymentRecord payment,
  ) async {
    final controller = TextEditingController(text: payment.expectedPaymentDate ?? '');
    try {
      return await showDialog<bool>(
            context: context,
            builder: (dialogContext) => AlertDialog(
              title: const Text('Expected payment date'),
              content: TextField(
                controller: controller,
                decoration: const InputDecoration(
                  labelText: 'YYYY-MM-DD',
                  helperText: 'Leave empty to clear the operational expected date.',
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(dialogContext).pop(false),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: () async {
                    final value = controller.text.trim();
                    if (value.isNotEmpty && !_isDate(value)) {
                      _showError(dialogContext, const FormatException('Date must use YYYY-MM-DD.'));
                      return;
                    }
                    try {
                      await widget.repository.updateExpectedPaymentDate(
                        payment.id,
                        value.isEmpty ? null : value,
                      );
                      if (dialogContext.mounted) Navigator.of(dialogContext).pop(true);
                    } on Object catch (error) {
                      if (dialogContext.mounted) _showError(dialogContext, error);
                    }
                  },
                  child: const Text('Save'),
                ),
              ],
            ),
          ) ??
          false;
    } finally {
      controller.dispose();
    }
  }
}

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
}

bool _isDate(String value) {
  final text = value.trim();
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(text);
  final parsed = DateTime.tryParse(text);
  return match != null &&
      parsed != null &&
      parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}
