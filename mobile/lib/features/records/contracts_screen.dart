import 'dart:async';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import '../session/session_controller.dart';
import 'mobile_records.dart';
import 'mobile_records_repository.dart';

final class ContractsScreen extends StatefulWidget {
  const ContractsScreen({
    required this.repository,
    required this.pageSize,
    required this.session,
    required this.filters,
    super.key,
  });

  static const editCapability = 'safecontracts_edit_contracts';

  final MobileRecordsRepository repository;
  final int pageSize;
  final SafeContractsSession session;
  final DashboardFilters filters;

  @override
  State<ContractsScreen> createState() => _ContractsScreenState();
}

final class _ContractsScreenState extends State<ContractsScreen> {
  bool _loading = true;
  String? _error;
  List<ContractRecord> _contracts = const <ContractRecord>[];

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  @override
  void didUpdateWidget(covariant ContractsScreen oldWidget) {
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
      final rows = await widget.repository.contracts(
        widget.filters,
        pageSize: widget.pageSize,
      );
      if (!mounted) return;
      setState(() {
        _contracts = rows;
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
    if (_error != null) {
      return _RecordsError(message: _error!, onRetry: _load);
    }
    if (_contracts.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const <Widget>[
            SizedBox(height: 180),
            Center(child: Text('No contracts match the authorized filters.')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: _contracts.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final contract = _contracts[index];
          return Card(
            child: ListTile(
              title: Text(contract.contractNumber),
              subtitle: Text(
                '${contract.customerName ?? 'Customer #${contract.customerId}'} · '
                '${contract.status} · ${contract.baseValue}',
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () async {
                await Navigator.of(context).push<void>(
                  MaterialPageRoute<void>(
                    builder: (_) => ContractDetailScreen(
                      repository: widget.repository,
                      contractId: contract.id,
                      canEdit:
                          widget.session.can(ContractsScreen.editCapability),
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

final class ContractDetailScreen extends StatefulWidget {
  const ContractDetailScreen({
    required this.repository,
    required this.contractId,
    required this.canEdit,
    super.key,
  });

  final MobileRecordsRepository repository;
  final int contractId;
  final bool canEdit;

  @override
  State<ContractDetailScreen> createState() => _ContractDetailScreenState();
}

final class _ContractDetailScreenState extends State<ContractDetailScreen> {
  bool _loading = true;
  String? _error;
  ContractRecord? _contract;

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
      final contract = await widget.repository.contract(widget.contractId);
      if (!mounted) return;
      setState(() {
        _contract = contract;
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

  Future<void> _edit(ContractRecord contract) async {
    final result = await showDialog<_ContractEditValue>(
      context: context,
      builder: (_) => _ContractEditDialog(contract: contract),
    );
    if (result == null) return;
    try {
      await widget.repository.editContractLight(
        contract.id,
        contractNumber: result.contractNumber,
        updateDates: result.updateDates,
        startDate: result.startDate,
        endDate: result.endDate,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
            content: Text('Contract updated by SafeContracts server.')),
      );
      await _load();
    } on Object catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final contract = _contract;
    return Scaffold(
      appBar: AppBar(
        title: const Text('Contract details'),
        actions: [
          if (contract != null && widget.canEdit && !contract.isArchived)
            IconButton(
              tooltip: 'Light edit',
              onPressed: () => unawaited(_edit(contract)),
              icon: const Icon(Icons.edit_outlined),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _RecordsError(message: _error!, onRetry: _load)
              : contract == null
                  ? const Center(child: Text('Contract not found.'))
                  : ListView(
                      padding: const EdgeInsets.all(24),
                      children: [
                        Text(contract.contractNumber,
                            style: Theme.of(context).textTheme.headlineSmall),
                        const SizedBox(height: 16),
                        _DetailRow('Status', contract.status),
                        _DetailRow('Customer',
                            contract.customerName ?? '#${contract.customerId}'),
                        _DetailRow('Base value', contract.baseValue),
                        _DetailRow('Start date', contract.startDate ?? '—'),
                        _DetailRow('End date', contract.endDate ?? '—'),
                        _DetailRow(
                            'Archived', contract.isArchived ? 'Yes' : 'No'),
                      ],
                    ),
    );
  }
}

final class _ContractEditValue {
  const _ContractEditValue({
    required this.contractNumber,
    required this.updateDates,
    this.startDate,
    this.endDate,
  });

  final String contractNumber;
  final bool updateDates;
  final String? startDate;
  final String? endDate;
}

final class _ContractEditDialog extends StatefulWidget {
  const _ContractEditDialog({required this.contract});
  final ContractRecord contract;

  @override
  State<_ContractEditDialog> createState() => _ContractEditDialogState();
}

final class _ContractEditDialogState extends State<_ContractEditDialog> {
  late final TextEditingController _number;
  late final TextEditingController _start;
  late final TextEditingController _end;
  bool _updateDates = false;

  @override
  void initState() {
    super.initState();
    _number = TextEditingController(text: widget.contract.contractNumber);
    _start = TextEditingController(text: widget.contract.startDate ?? '');
    _end = TextEditingController(text: widget.contract.endDate ?? '');
  }

  @override
  void dispose() {
    _number.dispose();
    _start.dispose();
    _end.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Light contract edit'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            TextField(
              controller: _number,
              maxLength: 100,
              decoration: const InputDecoration(labelText: 'Contract number'),
            ),
            CheckboxListTile(
              contentPadding: EdgeInsets.zero,
              value: _updateDates,
              onChanged: (value) =>
                  setState(() => _updateDates = value ?? false),
              title: const Text('Update start/end dates'),
            ),
            if (_updateDates) ...[
              TextField(
                controller: _start,
                decoration:
                    const InputDecoration(labelText: 'Start YYYY-MM-DD'),
              ),
              TextField(
                controller: _end,
                decoration: const InputDecoration(labelText: 'End YYYY-MM-DD'),
              ),
            ],
          ],
        ),
      ),
      actions: [
        TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Cancel')),
        FilledButton(
          onPressed: () {
            final number = _number.text.trim();
            if (number.isEmpty) return;
            Navigator.pop(
              context,
              _ContractEditValue(
                contractNumber: number,
                updateDates: _updateDates,
                startDate: _updateDates ? _nullable(_start.text) : null,
                endDate: _updateDates ? _nullable(_end.text) : null,
              ),
            );
          },
          child: const Text('Save'),
        ),
      ],
    );
  }
}

String? _nullable(String value) {
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

final class _DetailRow extends StatelessWidget {
  const _DetailRow(this.label, this.value);
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

final class _RecordsError extends StatelessWidget {
  const _RecordsError({required this.message, required this.onRetry});
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
