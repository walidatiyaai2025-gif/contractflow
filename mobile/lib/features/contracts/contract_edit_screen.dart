import 'package:flutter/material.dart';

import 'contracts.dart';

final class ContractEditScreen extends StatefulWidget {
  const ContractEditScreen({
    required this.controller,
    required this.contract,
    super.key,
  });

  final ContractsController controller;
  final SafeContractsContract contract;

  @override
  State<ContractEditScreen> createState() => _ContractEditScreenState();
}

final class _ContractEditScreenState extends State<ContractEditScreen> {
  late final TextEditingController _number;
  late final TextEditingController _startDate;
  late final TextEditingController _endDate;
  late final TextEditingController _baseValue;
  late String _status;
  String? _message;
  bool _saving = false;

  @override
  void initState() {
    super.initState();
    _number = TextEditingController(text: widget.contract.contractNumber);
    _startDate = TextEditingController(text: widget.contract.startDate ?? '');
    _endDate = TextEditingController(text: widget.contract.endDate ?? '');
    _baseValue = TextEditingController(text: widget.contract.baseValue ?? '0');
    _status = widget.contract.status;
    widget.controller.resetEditState();
  }

  @override
  void dispose() {
    _number.dispose();
    _startDate.dispose();
    _endDate.dispose();
    _baseValue.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: Text('Edit ${widget.contract.contractNumber}')),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(16),
          children: [
            Text(
              'Each Save button is an independent server transaction. '
              'SafeContracts rechecks your capability and contract scope on every request.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            if (widget.contract.isArchived) ...[
              const SizedBox(height: 12),
              const Card(
                child: ListTile(
                  leading: Icon(Icons.archive_outlined),
                  title: Text('Archived contract'),
                  subtitle: Text(
                    'The server will reject light edits to archived contracts.',
                  ),
                ),
              ),
            ],
            if (_message != null) ...[
              const SizedBox(height: 12),
              _EditMessage(
                message: _message!,
                isError: widget.controller.editState != ContractEditState.saved,
              ),
            ],
            const SizedBox(height: 12),
            _EditSection(
              title: 'Contract number',
              child: TextField(
                controller: _number,
                enabled: !_saving,
                decoration: const InputDecoration(
                  labelText: 'Contract number',
                  border: OutlineInputBorder(),
                ),
              ),
              onSave: _saving ? null : _saveNumber,
              buttonLabel: 'Save contract number',
            ),
            _EditSection(
              title: 'Contract dates',
              child: Column(
                children: [
                  TextField(
                    controller: _startDate,
                    enabled: !_saving,
                    decoration: const InputDecoration(
                      labelText: 'Start date (YYYY-MM-DD)',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: _endDate,
                    enabled: !_saving,
                    decoration: const InputDecoration(
                      labelText: 'End date (YYYY-MM-DD)',
                      border: OutlineInputBorder(),
                    ),
                  ),
                ],
              ),
              onSave: _saving ? null : _saveDates,
              buttonLabel: 'Save dates',
            ),
            _EditSection(
              title: 'Base value',
              child: TextField(
                controller: _baseValue,
                enabled: !_saving,
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(
                  labelText: 'Base value',
                  border: OutlineInputBorder(),
                ),
              ),
              onSave: _saving ? null : _saveBaseValue,
              buttonLabel: 'Save base value',
            ),
            _EditSection(
              title: 'Lifecycle status',
              child: DropdownButtonFormField<String>(
                key: ValueKey<String>(_status),
                initialValue: _status,
                isExpanded: true,
                decoration: const InputDecoration(
                  labelText: 'Status',
                  border: OutlineInputBorder(),
                ),
                items: ContractsController.supportedContractStatuses
                    .map(
                      (status) => DropdownMenuItem<String>(
                        value: status,
                        child: Text(status),
                      ),
                    )
                    .toList(growable: false),
                onChanged: _saving
                    ? null
                    : (value) {
                        if (value != null) {
                          setState(() => _status = value);
                        }
                      },
              ),
              onSave: _saving ? null : _saveStatus,
              buttonLabel: 'Save status',
            ),
            if (_saving) const LinearProgressIndicator(),
          ],
        ),
      ),
    );
  }

  Future<void> _saveNumber() async {
    await _save(
      () => widget.controller.editContractNumber(
        widget.contract.id,
        _number.text,
      ),
      'Contract number saved.',
    );
  }

  Future<void> _saveDates() async {
    await _save(
      () => widget.controller.editDates(
        widget.contract.id,
        _startDate.text,
        _endDate.text,
      ),
      'Contract dates saved.',
    );
  }

  Future<void> _saveBaseValue() async {
    await _save(
      () => widget.controller.editBaseValue(
        widget.contract.id,
        _baseValue.text,
      ),
      'Base value saved.',
    );
  }

  Future<void> _saveStatus() async {
    await _save(
      () => widget.controller.editStatus(widget.contract.id, _status),
      'Contract status saved.',
    );
  }

  Future<void> _save(Future<bool> Function() action, String success) async {
    if (_saving) {
      return;
    }
    setState(() {
      _message = null;
      _saving = true;
    });
    final ok = await action();
    if (!mounted) {
      return;
    }

    final refreshed = widget.controller.selectedContract;
    if (ok && refreshed != null && refreshed.id == widget.contract.id) {
      _number.text = refreshed.contractNumber;
      _startDate.text = refreshed.startDate ?? '';
      _endDate.text = refreshed.endDate ?? '';
      _baseValue.text = refreshed.baseValue ?? '0';
      _status = refreshed.status;
    }

    setState(() {
      _saving = false;
      _message = ok
          ? success
          : widget.controller.editErrorMessage ?? 'Contract edit failed.';
    });
  }
}

final class _EditSection extends StatelessWidget {
  const _EditSection({
    required this.title,
    required this.child,
    required this.onSave,
    required this.buttonLabel,
  });

  final String title;
  final Widget child;
  final VoidCallback? onSave;
  final String buttonLabel;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 14),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            child,
            const SizedBox(height: 12),
            FilledButton.tonal(onPressed: onSave, child: Text(buttonLabel)),
          ],
        ),
      ),
    );
  }
}

final class _EditMessage extends StatelessWidget {
  const _EditMessage({required this.message, required this.isError});

  final String message;
  final bool isError;

  @override
  Widget build(BuildContext context) {
    final color = isError
        ? Theme.of(context).colorScheme.errorContainer
        : Theme.of(context).colorScheme.secondaryContainer;
    return Card(
      color: color,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Text(message),
      ),
    );
  }
}
