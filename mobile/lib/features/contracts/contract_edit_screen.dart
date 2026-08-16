import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import 'contracts.dart';

enum ContractEditState {
  idle,
  loadingAccountants,
  saving,
  assigning,
  validationError,
  forbidden,
  conflict,
  error
}

final class AccountantOption {
  const AccountantOption({
    required this.id,
    required this.name,
    required this.email,
  });

  final int id;
  final String name;
  final String? email;

  String get label => email == null ? name : '$name <$email>';

  factory AccountantOption.fromData(Object? value) {
    final data = apiObjectMap(value, 'reference-data.accountant');
    return AccountantOption(
      id: _positiveInt(data['id'], 'reference-data.accountant.id'),
      name: _requiredText(data['name'], 'reference-data.accountant.name'),
      email: _optionalText(data['email']),
    );
  }
}

final class ContractEditController extends ChangeNotifier {
  ContractEditController({required this.client, required this.canEdit});

  final SafeContractsApiClient client;
  final bool canEdit;
  ContractEditState state = ContractEditState.idle;
  String? message;
  bool canAssignAccountant = false;
  List<AccountantOption> accountants = const <AccountantOption>[];

  Future<void> loadAccountants() async {
    try {
      final sessionEnvelope = await client.get('session');
      final session = apiObjectMap(sessionEnvelope.data, 'session.data');
      final capabilities = apiObjectMap(
        session['capabilities'],
        'session.capabilities',
      );
      canAssignAccountant =
          capabilities['safecontracts_assign_contracts'] == true;
      if (!canAssignAccountant) {
        accountants = const <AccountantOption>[];
        notifyListeners();
        return;
      }

      state = ContractEditState.loadingAccountants;
      message = null;
      notifyListeners();
      final envelope = await client.get('reference-data');
      final data = apiObjectMap(envelope.data, 'reference-data.data');
      final rows = apiObjectList(
        data['accountants'],
        'reference-data.accountants',
      );
      if (rows.length > 250) {
        throw const FormatException(
          'reference-data.accountants contains too many entries.',
        );
      }
      final ids = <int>{};
      final parsed = <AccountantOption>[];
      for (final row in rows) {
        final accountant = AccountantOption.fromData(row);
        if (!ids.add(accountant.id)) {
          throw const FormatException(
            'reference-data.accountants contains duplicate IDs.',
          );
        }
        parsed.add(accountant);
      }
      accountants = List<AccountantOption>.unmodifiable(parsed);
      state = ContractEditState.idle;
    } on SafeContractsApiException catch (error) {
      accountants = const <AccountantOption>[];
      message = error.message;
      state = error.statusCode == 403
          ? ContractEditState.forbidden
          : ContractEditState.error;
    } on Object catch (error) {
      accountants = const <AccountantOption>[];
      message = error.toString();
      state = ContractEditState.error;
    }
    notifyListeners();
  }

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
    if (updateDates &&
        (!_validNullableDate(startDate) || !_validNullableDate(endDate))) {
      state = ContractEditState.validationError;
      message = 'Contract dates must use YYYY-MM-DD or be blank.';
      notifyListeners();
      return false;
    }
    final normalizedStart = updateDates ? _nullableDate(startDate) : null;
    final normalizedEnd = updateDates ? _nullableDate(endDate) : null;
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
      await client.patch(
        'contracts/$contractId/light',
        body: <String, Object?>{
          'contract_number': number,
          if (updateDates) 'start_date': normalizedStart,
          if (updateDates) 'end_date': normalizedEnd,
        },
      );
      state = ContractEditState.idle;
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

  Future<bool> assignAccountant({
    required int contractId,
    required int accountantUserId,
  }) async {
    if (!canAssignAccountant) {
      state = ContractEditState.forbidden;
      message = 'Contract assignment is not authorized for this session.';
      notifyListeners();
      return false;
    }
    if (contractId <= 0 || accountantUserId <= 0) {
      state = ContractEditState.validationError;
      message = 'Select a valid responsible accountant.';
      notifyListeners();
      return false;
    }

    state = ContractEditState.assigning;
    message = null;
    notifyListeners();
    try {
      await client.patch(
        'contracts/$contractId/accountant',
        body: <String, Object?>{'accountant_user_id': accountantUserId},
      );
      state = ContractEditState.idle;
      message = 'Responsible accountant updated.';
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
  int? _accountantId;

  @override
  void initState() {
    super.initState();
    _editController = ContractEditController(
      client: widget.contractsController.repository.client,
      canEdit: widget.contractsController.canEditContract,
    )..addListener(_changed);
    unawaited(_load());
  }

  Future<void> _load() async {
    if (widget.contractsController.selectedContractId != widget.contractId ||
        widget.contractsController.selectedContract == null) {
      await widget.contractsController.openContract(widget.contractId);
    }
    if (!mounted) return;
    var contract = widget.contractsController.selectedContract;
    if (!_initialized && contract != null && contract.id == widget.contractId) {
      _number.text = contract.contractNumber;
      _start.text = contract.startDate ?? '';
      _end.text = contract.endDate ?? '';
      _initialized = true;
    }

    await _editController.loadAccountants();
    if (!mounted) return;
    contract = widget.contractsController.selectedContract;
    if (_editController.canAssignAccountant &&
        contract != null &&
        contract.id == widget.contractId) {
      final currentId = contract.accountantUserId;
      _accountantId = currentId != null &&
              _editController.accountants.any((item) => item.id == currentId)
          ? currentId
          : null;
    }
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
    if (mounted) Navigator.of(context).pop();
  }

  Future<void> _assignAccountant() async {
    final accountantId = _accountantId;
    if (accountantId == null) return;
    final assigned = await _editController.assignAccountant(
      contractId: widget.contractId,
      accountantUserId: accountantId,
    );
    if (!assigned || !mounted) return;
    await widget.contractsController.openContract(widget.contractId);
    await widget.contractsController.refresh();
    if (mounted) setState(() {});
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
    final l10n = context.scL10n;
    final contract = widget.contractsController.selectedContract;
    final ready = contract != null && contract.id == widget.contractId;
    final saving = _editController.state == ContractEditState.saving;
    final assigning = _editController.state == ContractEditState.assigning;
    final loadingAccountants =
        _editController.state == ContractEditState.loadingAccountants;
    final busy = saving || assigning;
    return Scaffold(
      appBar: AppBar(title: Text(l10n.t('Edit contract'))),
      body: !ready
          ? Center(
              child: widget.contractsController.detailState ==
                      ContractDetailLoadState.loading
                  ? const CircularProgressIndicator()
                  : Text(
                      l10n.rawMessage(
                        widget.contractsController.detailErrorMessage ??
                            'Contract details are unavailable.',
                      ),
                    ),
            )
          : SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  TextField(
                    controller: _number,
                    enabled: !busy,
                    maxLength: 100,
                    decoration: InputDecoration(
                      labelText: l10n.t('Contract number'),
                      border: const OutlineInputBorder(),
                    ),
                  ),
                  CheckboxListTile(
                    contentPadding: EdgeInsets.zero,
                    value: _updateDates,
                    onChanged: busy
                        ? null
                        : (value) =>
                            setState(() => _updateDates = value ?? false),
                    title: Text(l10n.t('Update start/end dates')),
                  ),
                  if (_updateDates) ...[
                    TextField(
                      controller: _start,
                      enabled: !busy,
                      decoration: InputDecoration(
                        labelText: l10n.t('Start date YYYY-MM-DD'),
                        border: const OutlineInputBorder(),
                      ),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: _end,
                      enabled: !busy,
                      decoration: InputDecoration(
                        labelText: l10n.t('End date YYYY-MM-DD'),
                        border: const OutlineInputBorder(),
                      ),
                    ),
                  ],
                  if (_editController.canAssignAccountant) ...[
                    const SizedBox(height: 24),
                    Text(
                      l10n.t('Responsible accountant'),
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 12),
                    if (loadingAccountants)
                      const Center(child: CircularProgressIndicator())
                    else if (_editController.accountants.isEmpty)
                      Text(
                        l10n.t(
                          'No eligible SafeContracts Accountant users are available.',
                        ),
                      )
                    else ...[
                      DropdownButtonFormField<int>(
                        key: ValueKey<int?>(_accountantId),
                        initialValue: _accountantId,
                        decoration: InputDecoration(
                          labelText: l10n.t('Responsible accountant'),
                          border: const OutlineInputBorder(),
                        ),
                        items: _editController.accountants
                            .map(
                              (accountant) => DropdownMenuItem<int>(
                                value: accountant.id,
                                child: Text(
                                  accountant.label,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            )
                            .toList(growable: false),
                        onChanged: busy
                            ? null
                            : (value) => setState(() => _accountantId = value),
                      ),
                      const SizedBox(height: 12),
                      OutlinedButton.icon(
                        onPressed: busy || _accountantId == null
                            ? null
                            : () => unawaited(_assignAccountant()),
                        icon: assigning
                            ? const SizedBox.square(
                                dimension: 18,
                                child:
                                    CircularProgressIndicator(strokeWidth: 2),
                              )
                            : const Icon(Icons.assignment_ind_outlined),
                        label: Text(l10n.t('Assign responsible accountant')),
                      ),
                    ],
                  ],
                  if (_editController.message != null) ...[
                    const SizedBox(height: 16),
                    Text(l10n.rawMessage(_editController.message!)),
                  ],
                  const SizedBox(height: 24),
                  FilledButton.icon(
                    onPressed: busy ? null : () => unawaited(_save()),
                    icon: saving
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.save_outlined),
                    label: Text(l10n.t('Save supported fields')),
                  ),
                  const SizedBox(height: 12),
                  Text(
                    l10n.t(
                      _editController.canAssignAccountant
                          ? 'Status and financial values are not editable here. Responsible accountant assignment is server-authorized and audited.'
                          : 'Status, assignment and financial values are not editable here. Server scope, validation and audit remain authoritative.',
                    ),
                  ),
                ],
              ),
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

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) {
    throw FormatException('$field must be a positive integer.');
  }
  return parsed;
}

String _requiredText(Object? value, String field) {
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('$field must be a non-empty string.');
  }
  final normalized = value.trim();
  if (normalized.length > 256) {
    throw FormatException('$field is too long.');
  }
  return normalized;
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException(
        'Accountant text field must be string or null.');
  }
  final normalized = value.trim();
  if (normalized.length > 254) {
    throw const FormatException('Accountant text field is too long.');
  }
  return normalized.isEmpty ? null : normalized;
}
