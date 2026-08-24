import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_design.dart';
import 'contracts.dart';

enum ContractEditState {
  idle,
  loadingAccountants,
  saving,
  assigning,
  validationError,
  forbidden,
  conflict,
  error,
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

  Future<void> _pickDate(TextEditingController controller) async {
    final parsed = DateTime.tryParse(controller.text.trim());
    final picked = await showDatePicker(
      context: context,
      initialDate: parsed ?? DateTime.now(),
      firstDate: DateTime(2000),
      lastDate: DateTime(2100),
    );
    if (picked == null) return;
    controller.text = _isoDate(picked);
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
    final ar = l10n.isArabic;
    final contract = widget.contractsController.selectedContract;
    final ready = contract != null && contract.id == widget.contractId;
    final saving = _editController.state == ContractEditState.saving;
    final assigning = _editController.state == ContractEditState.assigning;
    final loadingAccountants =
        _editController.state == ContractEditState.loadingAccountants;
    final busy = saving || assigning;

    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        backgroundColor: SafeContractsVisual.navy,
        foregroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        title: Text(ar ? 'تعديل العقد' : 'Edit contract'),
        flexibleSpace: const DecoratedBox(
          decoration: BoxDecoration(
            gradient: SafeContractsVisual.premiumHeaderGradient,
          ),
        ),
      ),
      body: SafeContractsBackdrop(
        child: !ready
            ? _LoadState(
                loading: widget.contractsController.detailState ==
                    ContractDetailLoadState.loading,
                message: l10n.rawMessage(
                  widget.contractsController.detailErrorMessage ??
                      'Contract details are unavailable.',
                ),
                onRetry: () => unawaited(_load()),
              )
            : ListView(
                padding: const EdgeInsets.fromLTRB(14, 14, 14, 36),
                children: [
                  _EditHero(contract: contract),
                  const SizedBox(height: 12),
                  SafeContractsSurface(
                    padding: const EdgeInsets.all(14),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        SafeContractsSectionTitle(
                          title: ar ? 'البيانات القابلة للتعديل' : 'Editable fields',
                          subtitle: ar
                              ? 'يتم إرسال الحقول المدعومة فقط إلى الخادم.'
                              : 'Only server-supported fields are submitted.',
                        ),
                        const SizedBox(height: 14),
                        TextField(
                          controller: _number,
                          enabled: !busy && _editController.canEdit,
                          maxLength: 100,
                          textInputAction: TextInputAction.next,
                          decoration: InputDecoration(
                            labelText: ar ? 'رقم / اسم العقد *' : 'Contract number *',
                            prefixIcon: const Icon(Icons.tag_rounded),
                          ),
                        ),
                        const SizedBox(height: 2),
                        Container(
                          decoration: BoxDecoration(
                            color: SafeContractsVisual.backgroundRaised,
                            borderRadius: BorderRadius.circular(14),
                            border: Border.all(color: SafeContractsVisual.outline),
                          ),
                          child: SwitchListTile.adaptive(
                            value: _updateDates,
                            contentPadding: const EdgeInsets.symmetric(
                              horizontal: 12,
                              vertical: 2,
                            ),
                            onChanged: busy || !_editController.canEdit
                                ? null
                                : (value) =>
                                    setState(() => _updateDates = value),
                            secondary: const Icon(
                              Icons.date_range_outlined,
                              color: SafeContractsVisual.navy,
                            ),
                            title: Text(
                              ar ? 'تحديث تاريخي البداية والنهاية' : 'Update start/end dates',
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                            subtitle: Text(
                              ar
                                  ? 'اترك هذا الخيار مغلقاً للحفاظ على التواريخ الحالية.'
                                  : 'Leave this off to preserve the current dates.',
                            ),
                          ),
                        ),
                        if (_updateDates) ...[
                          const SizedBox(height: 12),
                          LayoutBuilder(
                            builder: (context, constraints) {
                              final stack = constraints.maxWidth < 520;
                              final start = _DateField(
                                controller: _start,
                                enabled: !busy && _editController.canEdit,
                                label: ar ? 'تاريخ البداية' : 'Start date',
                                onPick: () => unawaited(_pickDate(_start)),
                              );
                              final end = _DateField(
                                controller: _end,
                                enabled: !busy && _editController.canEdit,
                                label: ar ? 'تاريخ النهاية' : 'End date',
                                onPick: () => unawaited(_pickDate(_end)),
                              );
                              if (stack) {
                                return Column(
                                  children: [
                                    start,
                                    const SizedBox(height: 10),
                                    end,
                                  ],
                                );
                              }
                              return Row(
                                children: [
                                  Expanded(child: start),
                                  const SizedBox(width: 10),
                                  Expanded(child: end),
                                ],
                              );
                            },
                          ),
                        ],
                        if (_editController.canEdit) ...[
                          const SizedBox(height: 14),
                          FilledButton.icon(
                            onPressed: busy ? null : () => unawaited(_save()),
                            icon: saving
                                ? const SizedBox.square(
                                    dimension: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Icon(Icons.save_outlined),
                            label: Text(
                              ar ? 'حفظ الحقول المدعومة' : 'Save supported fields',
                            ),
                          ),
                        ],
                      ],
                    ),
                  ),
                  if (_editController.canAssignAccountant || loadingAccountants) ...[
                    const SizedBox(height: 12),
                    SafeContractsSurface(
                      padding: const EdgeInsets.all(14),
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          SafeContractsSectionTitle(
                            title: ar ? 'مسؤول العقد' : 'Responsible accountant',
                            subtitle: ar
                                ? 'التعيين مستقل ويخضع لصلاحية الخادم والتدقيق.'
                                : 'Assignment is independently server-authorized and audited.',
                          ),
                          const SizedBox(height: 13),
                          if (loadingAccountants)
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 18),
                              child: Center(child: CircularProgressIndicator()),
                            )
                          else if (_editController.accountants.isEmpty)
                            _MessageBox(
                              icon: Icons.person_search_outlined,
                              text: ar
                                  ? 'لا يوجد محاسبون مؤهلون متاحون للتعيين.'
                                  : 'No eligible SafeContracts Accountant users are available.',
                            )
                          else ...[
                            DropdownButtonFormField<int>(
                              key: ValueKey<int?>(_accountantId),
                              initialValue: _accountantId,
                              isExpanded: true,
                              decoration: InputDecoration(
                                labelText: ar ? 'المحاسب المسؤول' : 'Responsible accountant',
                                prefixIcon: const Icon(Icons.badge_outlined),
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
                                  : (value) =>
                                      setState(() => _accountantId = value),
                            ),
                            const SizedBox(height: 12),
                            OutlinedButton.icon(
                              onPressed: busy || _accountantId == null
                                  ? null
                                  : () => unawaited(_assignAccountant()),
                              icon: assigning
                                  ? const SizedBox.square(
                                      dimension: 18,
                                      child: CircularProgressIndicator(
                                        strokeWidth: 2,
                                      ),
                                    )
                                  : const Icon(Icons.assignment_ind_outlined),
                              label: Text(
                                ar ? 'تعيين المسؤول' : 'Assign responsible accountant',
                              ),
                            ),
                          ],
                        ],
                      ),
                    ),
                  ],
                  if (_editController.message != null) ...[
                    const SizedBox(height: 12),
                    _EditStateMessage(
                      state: _editController.state,
                      text: l10n.rawMessage(_editController.message!),
                    ),
                  ],
                  const SizedBox(height: 12),
                  _MessageBox(
                    icon: Icons.verified_user_outlined,
                    text: ar
                        ? (!_editController.canEdit &&
                                _editController.canAssignAccountant
                            ? 'حقول العقد للقراءة فقط في هذه الجلسة. تعيين المسؤول يظل خاضعاً لصلاحيات الخادم.'
                            : _editController.canAssignAccountant
                                ? 'الحالة والقيم المالية لا يتم تعديلها هنا. الخادم هو المرجع النهائي للقواعد المالية.'
                                : 'الحالة والتعيين والقيم المالية للقراءة فقط هنا. النطاق والتحقق والتدقيق من الخادم هي المرجع النهائي.')
                        : (!_editController.canEdit &&
                                _editController.canAssignAccountant
                            ? 'Contract fields are read-only in this session. Responsible-accountant assignment remains server-authorized.'
                            : _editController.canAssignAccountant
                                ? 'Status and financial values are not edited here. The server remains authoritative for financial rules.'
                                : 'Status, assignment and financial values are read-only here. Server scope, validation and audit remain authoritative.'),
                  ),
                ],
              ),
      ),
    );
  }
}

final class _EditHero extends StatelessWidget {
  const _EditHero({required this.contract});
  final SafeContractsContract contract;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final directionColor = contract.isSupplier
        ? SafeContractsVisual.amber
        : SafeContractsVisual.green;
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  gradient: SafeContractsVisual.roseGradient,
                  borderRadius: BorderRadius.circular(15),
                ),
                child: const Icon(
                  Icons.edit_document,
                  color: SafeContractsVisual.navyDeep,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      contract.contractNumber,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    Text(
                      contract.displayCounterparty,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.72),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _HeroPill(
                label: context.scL10n.status(contract.status),
                color: safeContractsStatusColor(contract.status),
              ),
              _HeroPill(
                label: contract.isSupplier
                    ? (ar ? 'مستحق علينا' : 'Payable')
                    : (ar ? 'مستحق لنا' : 'Receivable'),
                color: directionColor,
              ),
              if (contract.currencyCode != 'UNSET')
                _HeroPill(
                  label: contract.currencyCode,
                  color: SafeContractsVisual.champagne,
                ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _HeroPill extends StatelessWidget {
  const _HeroPill({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 180),
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(99),
        border: Border.all(color: color.withValues(alpha: 0.34)),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: color == SafeContractsVisual.champagne ? color : Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

final class _DateField extends StatelessWidget {
  const _DateField({
    required this.controller,
    required this.enabled,
    required this.label,
    required this.onPick,
  });
  final TextEditingController controller;
  final bool enabled;
  final String label;
  final VoidCallback onPick;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      enabled: enabled,
      keyboardType: TextInputType.datetime,
      decoration: InputDecoration(
        labelText: label,
        hintText: 'YYYY-MM-DD',
        prefixIcon: const Icon(Icons.event_outlined),
        suffixIcon: IconButton(
          onPressed: enabled ? onPick : null,
          icon: const Icon(Icons.calendar_month_outlined),
        ),
      ),
    );
  }
}

final class _EditStateMessage extends StatelessWidget {
  const _EditStateMessage({required this.state, required this.text});
  final ContractEditState state;
  final String text;

  @override
  Widget build(BuildContext context) {
    final error = state == ContractEditState.validationError ||
        state == ContractEditState.forbidden ||
        state == ContractEditState.conflict ||
        state == ContractEditState.error;
    final color = error ? SafeContractsVisual.red : SafeContractsVisual.green;
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: color.withValues(alpha: 0.20)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(
            error ? Icons.error_outline_rounded : Icons.check_circle_outline,
            color: color,
            size: 19,
          ),
          const SizedBox(width: 8),
          Expanded(child: Text(text)),
        ],
      ),
    );
  }
}

final class _MessageBox extends StatelessWidget {
  const _MessageBox({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: SafeContractsVisual.navySoft,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: SafeContractsVisual.navy, size: 19),
          const SizedBox(width: 8),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(color: SafeContractsVisual.navyDeep),
            ),
          ),
        ],
      ),
    );
  }
}

final class _LoadState extends StatelessWidget {
  const _LoadState({
    required this.loading,
    required this.message,
    required this.onRetry,
  });
  final bool loading;
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    if (loading) return const Center(child: CircularProgressIndicator());
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.error_outline_rounded,
              size: 44,
              color: SafeContractsVisual.red,
            ),
            const SizedBox(height: 10),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: Text(context.scL10n.t('Retry')),
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

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-'
    '${value.month.toString().padLeft(2, '0')}-'
    '${value.day.toString().padLeft(2, '0')}';

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
      'Accountant text field must be string or null.',
    );
  }
  final normalized = value.trim();
  if (normalized.length > 254) {
    throw const FormatException('Accountant text field is too long.');
  }
  return normalized.isEmpty ? null : normalized;
}
