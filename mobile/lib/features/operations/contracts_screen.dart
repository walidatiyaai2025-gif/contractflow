import 'dart:async';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import '../session/session_controller.dart';
import 'operations_models.dart';
import 'operations_repository.dart';

final class ContractsScreen extends StatefulWidget {
  const ContractsScreen({
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
  State<ContractsScreen> createState() => _ContractsScreenState();
}

final class _ContractsScreenState extends State<ContractsScreen> {
  late Future<List<ContractRecord>> _rows;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _rows = widget.repository.contracts(widget.filters, pageSize: widget.pageSize);
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<ContractRecord>>(
      future: _rows,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(message: snapshot.error.toString(), onRetry: _refresh);
        }
        final rows = snapshot.data ?? const <ContractRecord>[];
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: rows.isEmpty ? 1 : rows.length,
            itemBuilder: (context, index) {
              if (rows.isEmpty) {
                return const Padding(
                  padding: EdgeInsets.all(32),
                  child: Center(child: Text('No contracts match the current dashboard filters.')),
                );
              }
              final contract = rows[index];
              return ListTile(
                leading: const Icon(Icons.description_outlined),
                title: Text(contract.contractNumber),
                subtitle: Text(
                  <String>[
                    if (contract.customerName != null) contract.customerName!,
                    contract.status,
                    contract.baseValue,
                  ].join(' • '),
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => unawaited(_showContract(context, contract.id)),
              );
            },
          ),
        );
      },
    );
  }

  Future<void> _refresh() async {
    setState(_reload);
    await _rows;
  }

  Future<void> _showContract(BuildContext context, int id) async {
    try {
      var contract = await widget.repository.contract(id);
      if (!context.mounted) return;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Text(contract.contractNumber),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text('Customer: ${contract.customerName ?? contract.customerId}'),
                  Text('Status: ${contract.status}'),
                  if (contract.startDate != null) Text('Start: ${contract.startDate}'),
                  if (contract.endDate != null) Text('End: ${contract.endDate}'),
                  Text('Base value: ${contract.baseValue}'),
                  Text('Archived: ${contract.isArchived ? 'Yes' : 'No'}'),
                ],
              ),
            ),
            actions: [
              if (widget.session.can('safecontracts_edit_contracts') && !contract.isArchived)
                TextButton(
                  onPressed: () async {
                    final changed = await _editContract(dialogContext, contract);
                    if (!changed || !dialogContext.mounted) return;
                    contract = await widget.repository.contract(id);
                    setDialogState(() {});
                    if (mounted) setState(_reload);
                  },
                  child: const Text('Light edit'),
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

  Future<bool> _editContract(BuildContext context, ContractRecord contract) async {
    final number = TextEditingController(text: contract.contractNumber);
    final start = TextEditingController(text: contract.startDate ?? '');
    final end = TextEditingController(text: contract.endDate ?? '');
    var mode = 'number';
    try {
      return await showDialog<bool>(
            context: context,
            builder: (dialogContext) => StatefulBuilder(
              builder: (context, setDialogState) => AlertDialog(
                title: const Text('Contract light edit'),
                content: SingleChildScrollView(
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      SegmentedButton<String>(
                        segments: const [
                          ButtonSegment(value: 'number', label: Text('Number')),
                          ButtonSegment(value: 'dates', label: Text('Dates')),
                        ],
                        selected: <String>{mode},
                        onSelectionChanged: (selection) {
                          setDialogState(() => mode = selection.first);
                        },
                      ),
                      const SizedBox(height: 16),
                      if (mode == 'number')
                        TextField(
                          controller: number,
                          decoration: const InputDecoration(labelText: 'Contract number'),
                        )
                      else ...[
                        TextField(
                          controller: start,
                          decoration: const InputDecoration(labelText: 'Start date YYYY-MM-DD'),
                        ),
                        const SizedBox(height: 12),
                        TextField(
                          controller: end,
                          decoration: const InputDecoration(labelText: 'End date YYYY-MM-DD'),
                        ),
                      ],
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
                      try {
                        if (mode == 'number') {
                          if (number.text.trim().isEmpty) {
                            throw const FormatException('Contract number is required.');
                          }
                          await widget.repository.editContractNumber(contract.id, number.text);
                        } else {
                          if (!_isDate(start.text) || !_isDate(end.text)) {
                            throw const FormatException('Dates must use valid YYYY-MM-DD values.');
                          }
                          await widget.repository.editContractDates(contract.id, start.text, end.text);
                        }
                        if (dialogContext.mounted) Navigator.of(dialogContext).pop(true);
                      } on Object catch (error) {
                        if (dialogContext.mounted) _showError(dialogContext, error);
                      }
                    },
                    child: const Text('Save'),
                  ),
                ],
              ),
            ),
          ) ??
          false;
    } finally {
      number.dispose();
      start.dispose();
      end.dispose();
    }
  }
}

final class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: FilledButton(
        onPressed: () => unawaited(onRetry()),
        child: Text('Retry: $message'),
      ),
    );
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
