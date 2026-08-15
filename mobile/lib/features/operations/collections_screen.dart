import 'dart:async';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import 'operations_models.dart';
import 'operations_repository.dart';

final class CollectionsScreen extends StatefulWidget {
  const CollectionsScreen({
    required this.repository,
    required this.filters,
    required this.pageSize,
    required this.canRecord,
    super.key,
  });

  final MobileOperationsRepository repository;
  final DashboardFilters filters;
  final int pageSize;
  final bool canRecord;

  @override
  State<CollectionsScreen> createState() => _CollectionsScreenState();
}

final class _CollectionsScreenState extends State<CollectionsScreen> {
  late Future<List<CollectionRecord>> _rows;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _rows = widget.repository.collections(widget.filters, pageSize: widget.pageSize);
  }

  Future<void> _refresh() async {
    setState(_reload);
    await _rows;
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        if (widget.canRecord)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: Align(
              alignment: Alignment.centerRight,
              child: FilledButton.icon(
                onPressed: () => unawaited(_record(context)),
                icon: const Icon(Icons.add),
                label: const Text('Record collection'),
              ),
            ),
          ),
        Expanded(
          child: FutureBuilder<List<CollectionRecord>>(
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
              final rows = snapshot.data ?? const <CollectionRecord>[];
              return RefreshIndicator(
                onRefresh: _refresh,
                child: ListView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  itemCount: rows.isEmpty ? 1 : rows.length,
                  itemBuilder: (context, index) {
                    if (rows.isEmpty) {
                      return const Padding(
                        padding: EdgeInsets.all(32),
                        child: Center(child: Text('No collections match the current dashboard filters.')),
                      );
                    }
                    final collection = rows[index];
                    return ListTile(
                      leading: const Icon(Icons.payments_outlined),
                      title: Text(collection.reference ?? 'Collection #${collection.id}'),
                      subtitle: Text(
                        '${collection.collectionDate} • ${collection.paymentMethodName ?? 'Method #${collection.paymentMethodId}'}',
                      ),
                      trailing: Text(collection.amount),
                    );
                  },
                ),
              );
            },
          ),
        ),
      ],
    );
  }

  Future<void> _record(BuildContext context) async {
    try {
      final methods = await widget.repository.paymentMethods();
      if (!context.mounted) return;
      if (methods.isEmpty) {
        _showError(context, const FormatException('No active payment methods are available.'));
        return;
      }

      final payment = TextEditingController();
      final amount = TextEditingController();
      final date = TextEditingController(text: _today());
      final reference = TextEditingController();
      var methodId = methods.first.id;
      try {
        final saved = await showDialog<bool>(
          context: context,
          builder: (dialogContext) => StatefulBuilder(
            builder: (context, setDialogState) => AlertDialog(
              title: const Text('Record collection'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextField(
                      controller: payment,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Payment ID'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: amount,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(labelText: 'Amount'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: date,
                      decoration: const InputDecoration(labelText: 'Collection date YYYY-MM-DD'),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: methodId,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Payment method'),
                      items: methods
                          .map(
                            (method) => DropdownMenuItem<int>(
                              value: method.id,
                              child: Text(method.name),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: (value) {
                        if (value != null) setDialogState(() => methodId = value);
                      },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: reference,
                      decoration: const InputDecoration(labelText: 'Reference (optional)'),
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(dialogContext).pop(false),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: () async {
                    final paymentId = int.tryParse(payment.text.trim()) ?? 0;
                    final amountValue = double.tryParse(amount.text.trim()) ?? 0;
                    if (paymentId <= 0 || amountValue <= 0 || !_isDate(date.text)) {
                      _showError(
                        dialogContext,
                        const FormatException('Payment, positive amount and valid date are required.'),
                      );
                      return;
                    }
                    try {
                      await widget.repository.recordCollection(
                        paymentId: paymentId,
                        amount: amount.text,
                        collectionDate: date.text,
                        paymentMethodId: methodId,
                        reference: reference.text,
                      );
                      if (dialogContext.mounted) Navigator.of(dialogContext).pop(true);
                    } on Object catch (error) {
                      if (dialogContext.mounted) _showError(dialogContext, error);
                    }
                  },
                  child: const Text('Record'),
                ),
              ],
            ),
          ),
        );
        if (saved == true && mounted) await _refresh();
      } finally {
        payment.dispose();
        amount.dispose();
        date.dispose();
        reference.dispose();
      }
    } on Object catch (error) {
      if (context.mounted) _showError(context, error);
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

String _today() {
  final now = DateTime.now();
  return '${now.year}-${now.month.toString().padLeft(2, '0')}-${now.day.toString().padLeft(2, '0')}';
}
