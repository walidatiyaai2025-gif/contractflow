import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import 'contracts.dart';

enum ContractEditState {
  idle,
  saving,
  validationError,
  forbidden,
  conflict,
  error,
  saved,
}

final class ContractEditController extends ChangeNotifier {
  ContractEditController({
    required this.client,
    required this.canEdit,
  });

  final SafeContractsApiClient client;
  final bool canEdit;

  ContractEditState state = ContractEditState.idle;
  String? message;

  Future<bool> save({
    required int contractId,
    required String contractNumber,
    required bool updateDates,
    String? startDate,
    String? endDate,
  }) async {
    if (!canEdit) {
      state = ContractEditState.forbidden;
      message = 'Contract editing is not authorized for this session.';
      notifyListeners();
      return false;
    }
    final number = contractNumber.trim();
    if (contractId <= 0 || number.isEmpty || number.length > 100) {
      state = ContractEditState.validationError;
      message = 'Contract number must contain 1 to 100 characters.';
      notifyListeners();
      return false;
    }
    final normalizedStart = updateDates ? _nullableDate(startDate) : null;
    final normalizedEnd = updateDates ? _nullableDate(endDate) : null;
    if (updateDates &&
        !_validNullableDate(startDate) ||
        updateDates && !_validNullableDate(endDate)) {
      state = ContractEditState.validationError;
      message = 'Contract dates must use YYYY-MM-DD or be blank.';
      notifyListeners();
      return false;
    }
    if (normalizedStart != null &&
        normalizedEnd != null &&
        normalizedEnd.compareTo(normalizedStart) < 0) {
      state = ContractEditState.validationError;
      message = 'Contract end date cannot precede start date.';
      notifyListeners();
      return false;
    }

    state = ContractEditState.saving;
    message = null;
    notifyListeners();
    try {
      await client.patchJson(
        'contracts/$contractId/light',
        body: <String, Object?>{
          'contract_number': number,
          if (updateDates) 'start_date': normalizedStart,
          if (updateDates) 'end_date': normalizedEnd,
        },
      );
      state = ContractEditState.saved;
      return true;
    } on SafeContractsApiException catch (error) {
      message = error.message;
      state = switch (error.statusCode) {
        422 => ContractEditState.validationError,
        403 => ContractEditState.forbidden,
        409 => ContractEditState.conflict,
        _ => ContractEditState.error,
      };
      return false;
    } on Object catch (error) {
      state = ContractEditState.error;
      message = error.toString();
      return false;
    } finally {
      notifyListeners();
    }
  }
}

final class ContractEditScreen extends StatefulWidget {
  const ContractEditScreen({
    required this.contractsController,
    required this.contractId,
    super.key,
  });

  final ContractsController contractsController;
  final int contractId;

  @override
  State<ContractEditScreen> createState() => _ContractEditScreenState();
}

final class _ContractEditScreenState extends State<ContractEditScreen> {
  late final ContractEditController _editController;
  final _number = TextEditingController();
  final _start = TextEditingController();
  final _end = TextEditingController();
  bool _updateDates = false;
  bool _initialized = false;

  @override
  void initState() {
    super.initState();
    _editController = ContractEditController(
      client: widget.contractsController.repository.client,
      canEdit: widget.contractsController.canEditContract,
    );
    _editController.addListener(_changed);
    unawaited(_load());
  }

  Future<void> _load() async {
    if (widget.contractsController.selectedContractId != widget.contractId ||
        widget.contractsController.selectedContract == null) {
      await widget.contractsController.openContract(widget.contractId);
    }
    if (!mounted) return;
    _initializeForm();
  }

  void _initializeForm() {
    final contract = widget.contractsController.selectedContract;
    if (_initialized || contract == null || contract.id != widget.contractId) {
      setState(() {});
      return;
    }
    _number.text = contract.contractNumber;
    _start.text = contract.startDate ?? '';
    _end.text = contract.endDate ?? '';
    _initialized = true;
    setState(() {});
  }

  void _changed() {
    if (mounted) setState(() {});
  }

  Future<void> _save() async {
    final saved = await _editController.save(
      contractId: widget.contractId,
      contractNumber: _number.text,
      updateDates: _updateDates,
      startDate: _start.text,
      endDate: _end.text,
    );
    if (!saved || !mounted) return;
    await widget.contractsController.openContract(widget.contractId);
    await widget.contractsController.refresh();
    if (!mounted) return;
    Navigator.of(context).pop();
  }

  @override
  void dispose() {
    _editController.removeListener(_changed);
    _editController.dispose();
    _number.dispose();
    _start.dispose();
    _end.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final contract = widget.contractsController.selectedContract;
    final detailReady = contract != null && contract.id == widget.contractId;
    final saving = _editController.state == ContractEditState.saving;

    return Scaffold(
      appBar: AppBar(title: const Text('Edit contract')),
      body: !detailReady
          ? Center(
              child: widget.contractsController.detailState ==
                      ContractDetailLoadState.loading
                  ? const CircularProgressIndicator()
                  : Text(widget.contractsController.detailErrorMessage ??
                      'Contract details are unavailable.'),
            )
          : SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Text(
                    'Contract #${contract.id}',
                    style: Theme.of(context).textTheme.headlineSmall,
                  ),
                  const SizedBox(height: 20),
                  TextField(
                    controller: _number,
                    enabled: !saving,
                    maxLength: 100,
                    decoration: const InputDecoration(
                      labelText: 'Contract number',
                      border: OutlineInputBorder(),
                    ),
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _updateDates,
                    onChanged: saving
                        ? null
                        : (value) =>
                            setState(() => _updateDates = value ?? false),
                    title: const Text('Update start/end dates'),
                  ),
                  if (_updateDates) ...[
                    TextField(
                      controller: _start,
                      enabled: !saving,
                      decoration: const InputDecoration(
                        labelText: 'Start date YYYY-MM-DD',
                        border: OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _end,
                      enabled: !saving,
                      decoration: const InputDecoration(
                        labelText: 'End date YYYY-MM-DD',
                        border: OutlineInputBorder(),
                      ),
                    ),
                  ],
                  if (_editController.message != null) ...[
                    const SizedBox(height: 16),
                    _EditMessage(
                      state: _editController.state,
                      message: _editController.message!,
                    ),
                  ],
                  const SizedBox(height: 24),
                  FilledButton.icon(
                    onPressed: saving ? null : () => unawaited(_save()),
                    icon: saving
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save_outlined),
                    label: const Text('Save supported fields'),
                  ),
                  const SizedBox(height: 12),
                  const Text(
                    'Status, customer assignment and financial values are not editable from mobile. Server scope, validation and audit remain authoritative.',
                  ),
                ],
              ),
            ),
    );
  }
}

final class _EditMessage extends StatelessWidget {
  const _EditMessage({required this.state, required this.message});

  final ContractEditState state;
  final String message;

  @override
  Widget build(BuildContext context) {
    final label = switch (state) {
      ContractEditState.validationError => 'Validation',
      ContractEditState.forbidden => 'Forbidden',
      ContractEditState.conflict => 'Conflict',
      _ => 'Error',
    };
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Text('$label: $message'),
      ),
    );
  }
}

String? _nullableDate(String? value) {
  final normalized = value?.trim() ?? '';
  return normalized.isEmpty ? null : normalized;
}

bool _validNullableDate(String? value) {
  final normalized = value?.trim() ?? '';
  if (normalized.isEmpty) return true;
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(normalized);
  if (match == null) return false;
  final parsed = DateTime.tryParse(normalized);
  return parsed != null &&
      parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}
