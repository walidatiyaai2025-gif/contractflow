import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../contracts/contracts.dart';
import '../customers/customers.dart';
import '../dashboard/dashboard_models.dart';
import '../payments/payments.dart';
import '../session/session_controller.dart';

final class MobileRecordEditorScreen extends StatefulWidget {
  const MobileRecordEditorScreen({
    required this.client,
    required this.session,
    super.key,
  });

  final SafeContractsApiClient client;
  final SafeContractsSession session;

  @override
  State<MobileRecordEditorScreen> createState() =>
      _MobileRecordEditorScreenState();
}

final class _MobileRecordEditorScreenState
    extends State<MobileRecordEditorScreen> {
  final _customerName = TextEditingController();
  final _customerCode = TextEditingController();
  final _customerContact = TextEditingController();
  final _customerEmail = TextEditingController();
  final _customerPhone = TextEditingController();

  final _contractNumber = TextEditingController();
  final _contractStart = TextEditingController();
  final _contractEnd = TextEditingController();
  final _contractBase = TextEditingController();

  final _paymentSequence = TextEditingController();
  final _paymentReference = TextEditingController();
  final _paymentDue = TextEditingController();
  final _paymentExpected = TextEditingController();
  final _paymentAmount = TextEditingController();

  bool _loading = true;
  bool _saving = false;
  String? _loadError;
  List<SafeContractsCustomer> _customers = const [];
  List<SafeContractsContract> _contracts = const [];
  List<SafeContractsPayment> _payments = const [];
  List<_AccountantOption> _accountants = const [];

  String _customerMode = 'create';
  String _contractMode = 'create';
  String _paymentMode = 'create';
  int? _customerId;
  int? _contractId;
  int? _paymentId;
  int? _contractCustomerId;
  int? _contractAccountantId;
  int? _paymentContractId;
  bool _customerActive = true;

  bool get _canCreateCustomers =>
      widget.session.can('safecontracts_create_customers');
  bool get _canEditCustomers =>
      widget.session.can('safecontracts_edit_customers');
  bool get _canCreateContracts =>
      widget.session.can('safecontracts_create_contracts');
  bool get _canEditContracts =>
      widget.session.can('safecontracts_edit_contracts');
  bool get _canAssignContracts =>
      widget.session.can('safecontracts_assign_contracts');
  bool get _canCreatePayments =>
      widget.session.can('safecontracts_create_payments');
  bool get _canEditPayments =>
      widget.session.can('safecontracts_edit_payments');

  @override
  void initState() {
    super.initState();
    _customerMode = _canCreateCustomers ? 'create' : 'edit';
    _contractMode = _canCreateContracts ? 'create' : 'edit';
    _paymentMode = _canCreatePayments ? 'create' : 'edit';
    unawaited(_load());
  }

  @override
  void dispose() {
    for (final controller in <TextEditingController>[
      _customerName,
      _customerCode,
      _customerContact,
      _customerEmail,
      _customerPhone,
      _contractNumber,
      _contractStart,
      _contractEnd,
      _contractBase,
      _paymentSequence,
      _paymentReference,
      _paymentDue,
      _paymentExpected,
      _paymentAmount,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      final customers = await CustomersRepository(widget.client).loadPage(
        page: 1,
        perPage: 100,
        order: 'asc',
      );
      final contracts = await ContractsRepository(widget.client).loadPage(
        page: 1,
        perPage: 100,
        filters: const ContractsFilters(),
        sort: ContractSortOption.newest,
      );
      final payments = await PaymentsRepository(widget.client).loadPage(
        page: 1,
        perPage: 100,
        filters: const DashboardFilters(),
      );
      var accountants = const <_AccountantOption>[];
      if (_canAssignContracts) {
        final envelope = await widget.client.get('reference-data');
        final data = apiObjectMap(envelope.data, 'reference-data.data');
        final rows = apiObjectList(
          data['accountants'],
          'reference-data.accountants',
        );
        accountants = rows
            .map(_AccountantOption.fromData)
            .toList(growable: false);
      }
      if (!mounted) return;
      setState(() {
        _customers = customers.customers;
        _contracts = contracts.contracts;
        _payments = payments.payments;
        _accountants = accountants;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _loadError = error.toString();
        _loading = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return DefaultTabController(
      length: 3,
      child: Scaffold(
        appBar: AppBar(
          title: Text(_t(context, 'Add / edit data', 'إضافة / تعديل البيانات')),
          bottom: TabBar(
            tabs: [
              Tab(text: _t(context, 'Customers', 'العملاء')),
              Tab(text: _t(context, 'Contracts', 'العقود')),
              Tab(text: _t(context, 'Payments', 'الدفعات')),
            ],
          ),
        ),
        body: _loading
            ? const Center(child: CircularProgressIndicator())
            : _loadError != null
                ? _LoadError(message: _loadError!, onRetry: _load)
                : TabBarView(
                    children: [
                      _customerTab(),
                      _contractTab(),
                      _paymentTab(),
                    ],
                  ),
      ),
    );
  }

  Widget _customerTab() {
    if (!_canCreateCustomers && !_canEditCustomers) {
      return _NoPermission(
        text: _t(
          context,
          'Customer create/edit permission is disabled for this role.',
          'صلاحية إضافة/تعديل العملاء غير مفعلة لهذا الدور.',
        ),
      );
    }
    final editing = _customerMode == 'edit';
    return _formList([
      _ModeSelector(
        canCreate: _canCreateCustomers,
        canEdit: _canEditCustomers,
        mode: _customerMode,
        onChanged: (mode) {
          setState(() {
            _customerMode = mode;
            _customerId = null;
            _clearCustomer();
          });
        },
      ),
      if (editing)
        DropdownButtonFormField<int>(
          value: _customerId,
          decoration: InputDecoration(
            labelText: _t(context, 'Customer', 'العميل'),
          ),
          items: _customers
              .map(
                (item) => DropdownMenuItem<int>(
                  value: item.id,
                  child: Text(item.name),
                ),
              )
              .toList(growable: false),
          onChanged: _saving ? null : _selectCustomer,
        ),
      TextField(
        controller: _customerName,
        enabled: !editing || _customerId != null,
        decoration: InputDecoration(
          labelText: _t(context, 'Name *', 'الاسم *'),
        ),
      ),
      TextField(
        controller: _customerCode,
        enabled: !editing || _customerId != null,
        decoration: InputDecoration(
          labelText: _t(context, 'Internal code', 'الكود الداخلي'),
        ),
      ),
      TextField(
        controller: _customerContact,
        enabled: !editing || _customerId != null,
        decoration: InputDecoration(
          labelText: _t(context, 'Contact name', 'اسم جهة الاتصال'),
        ),
      ),
      TextField(
        controller: _customerEmail,
        enabled: !editing || _customerId != null,
        keyboardType: TextInputType.emailAddress,
        decoration: InputDecoration(
          labelText: _t(context, 'Email', 'البريد الإلكتروني'),
        ),
      ),
      TextField(
        controller: _customerPhone,
        enabled: !editing || _customerId != null,
        keyboardType: TextInputType.phone,
        decoration: InputDecoration(
          labelText: _t(context, 'Phone', 'الهاتف'),
        ),
      ),
      SwitchListTile(
        contentPadding: EdgeInsets.zero,
        title: Text(_t(context, 'Active', 'نشط')),
        value: _customerActive,
        onChanged: _saving || (editing && _customerId == null)
            ? null
            : (value) => setState(() => _customerActive = value),
      ),
      FilledButton.icon(
        onPressed: _saving || (editing && _customerId == null)
            ? null
            : () => unawaited(_saveCustomer()),
        icon: Icon(editing ? Icons.save_outlined : Icons.add_business_outlined),
        label: Text(
          editing
              ? _t(context, 'Save customer', 'حفظ العميل')
              : _t(context, 'Add customer', 'إضافة عميل'),
        ),
      ),
    ]);
  }

  Widget _contractTab() {
    if (!_canCreateContracts && !_canEditContracts) {
      return _NoPermission(
        text: _t(
          context,
          'Contract create/edit permission is disabled for this role.',
          'صلاحية إضافة/تعديل العقود غير مفعلة لهذا الدور.',
        ),
      );
    }
    final editing = _contractMode == 'edit';
    final enabled = !editing || _contractId != null;
    return _formList([
      _ModeSelector(
        canCreate: _canCreateContracts,
        canEdit: _canEditContracts,
        mode: _contractMode,
        onChanged: (mode) {
          setState(() {
            _contractMode = mode;
            _contractId = null;
            _clearContract();
          });
        },
      ),
      if (editing)
        DropdownButtonFormField<int>(
          value: _contractId,
          decoration: InputDecoration(
            labelText: _t(context, 'Contract', 'العقد'),
          ),
          items: _contracts
              .map(
                (item) => DropdownMenuItem<int>(
                  value: item.id,
                  child: Text(item.contractNumber),
                ),
              )
              .toList(growable: false),
          onChanged: _saving ? null : _selectContract,
        ),
      TextField(
        controller: _contractNumber,
        enabled: enabled,
        decoration: InputDecoration(
          labelText: _t(context, 'Contract number *', 'رقم العقد *'),
        ),
      ),
      DropdownButtonFormField<int>(
        value: _contractCustomerId,
        decoration: InputDecoration(
          labelText: _t(context, 'Customer *', 'العميل *'),
        ),
        items: _customers
            .map(
              (item) => DropdownMenuItem<int>(
                value: item.id,
                child: Text(item.name),
              ),
            )
            .toList(growable: false),
        onChanged: enabled && (!editing || _canAssignContracts)
            ? (value) => setState(() => _contractCustomerId = value)
            : null,
      ),
      if (_canAssignContracts)
        DropdownButtonFormField<int>(
          value: _contractAccountantId,
          decoration: InputDecoration(
            labelText: _t(context, 'Responsible accountant', 'المحاسب المسؤول'),
          ),
          items: _accountants
              .map(
                (item) => DropdownMenuItem<int>(
                  value: item.id,
                  child: Text(item.label),
                ),
              )
              .toList(growable: false),
          onChanged: enabled
              ? (value) => setState(() => _contractAccountantId = value)
              : null,
        ),
      if (_canEditContracts) ...[
        TextField(
          controller: _contractStart,
          enabled: enabled,
          decoration: InputDecoration(
            labelText: _t(context, 'Start date YYYY-MM-DD', 'تاريخ البداية YYYY-MM-DD'),
          ),
        ),
        TextField(
          controller: _contractEnd,
          enabled: enabled,
          decoration: InputDecoration(
            labelText: _t(context, 'End date YYYY-MM-DD', 'تاريخ النهاية YYYY-MM-DD'),
          ),
        ),
        TextField(
          controller: _contractBase,
          enabled: enabled,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(
            labelText: _t(context, 'Base value', 'القيمة الأساسية'),
            helperText: _t(context, 'Up to 2 decimals', 'حتى رقمين عشريين'),
          ),
        ),
      ],
      FilledButton.icon(
        onPressed: _saving || !enabled ? null : () => unawaited(_saveContract()),
        icon: Icon(editing ? Icons.save_outlined : Icons.post_add_outlined),
        label: Text(
          editing
              ? _t(context, 'Save contract', 'حفظ العقد')
              : _t(context, 'Add contract', 'إضافة عقد'),
        ),
      ),
    ]);
  }

  Widget _paymentTab() {
    if (!_canCreatePayments && !_canEditPayments) {
      return _NoPermission(
        text: _t(
          context,
          'Payment create/edit permission is disabled for this role.',
          'صلاحية إضافة/تعديل الدفعات غير مفعلة لهذا الدور.',
        ),
      );
    }
    final editing = _paymentMode == 'edit';
    final enabled = !editing || _paymentId != null;
    return _formList([
      _ModeSelector(
        canCreate: _canCreatePayments,
        canEdit: _canEditPayments,
        mode: _paymentMode,
        onChanged: (mode) {
          setState(() {
            _paymentMode = mode;
            _paymentId = null;
            _clearPayment();
          });
        },
      ),
      if (editing)
        DropdownButtonFormField<int>(
          value: _paymentId,
          decoration: InputDecoration(
            labelText: _t(context, 'Payment', 'الدفعة'),
          ),
          items: _payments
              .map(
                (item) => DropdownMenuItem<int>(
                  value: item.id,
                  child: Text(
                    item.reference ?? '#${item.id} · ${item.dueDate}',
                  ),
                ),
              )
              .toList(growable: false),
          onChanged: _saving ? null : _selectPayment,
        ),
      DropdownButtonFormField<int>(
        value: _paymentContractId,
        decoration: InputDecoration(
          labelText: _t(context, 'Contract *', 'العقد *'),
        ),
        items: _contracts
            .map(
              (item) => DropdownMenuItem<int>(
                value: item.id,
                child: Text(item.contractNumber),
              ),
            )
            .toList(growable: false),
        onChanged: enabled && !editing
            ? (value) => setState(() => _paymentContractId = value)
            : null,
      ),
      TextField(
        controller: _paymentSequence,
        enabled: enabled,
        keyboardType: TextInputType.number,
        decoration: InputDecoration(
          labelText: _t(context, 'Sequence *', 'الترتيب *'),
        ),
      ),
      TextField(
        controller: _paymentReference,
        enabled: enabled,
        decoration: InputDecoration(
          labelText: _t(context, 'Reference', 'المرجع'),
        ),
      ),
      TextField(
        controller: _paymentDue,
        enabled: enabled,
        decoration: InputDecoration(
          labelText: _t(context, 'Due date YYYY-MM-DD *', 'تاريخ الاستحقاق YYYY-MM-DD *'),
        ),
      ),
      TextField(
        controller: _paymentExpected,
        enabled: enabled,
        decoration: InputDecoration(
          labelText: _t(context, 'Expected date YYYY-MM-DD', 'تاريخ الدفع المتوقع YYYY-MM-DD'),
        ),
      ),
      TextField(
        controller: _paymentAmount,
        enabled: enabled,
        keyboardType: const TextInputType.numberWithOptions(decimal: true),
        decoration: InputDecoration(
          labelText: _t(context, 'Original amount *', 'المبلغ الأصلي *'),
          helperText: _t(
            context,
            'Up to 2 decimals. Amount cannot change after collection.',
            'حتى رقمين عشريين. لا يمكن تغيير المبلغ بعد تسجيل تحصيل.',
          ),
        ),
      ),
      FilledButton.icon(
        onPressed: _saving || !enabled ? null : () => unawaited(_savePayment()),
        icon: Icon(editing ? Icons.save_outlined : Icons.add_card_outlined),
        label: Text(
          editing
              ? _t(context, 'Save payment', 'حفظ الدفعة')
              : _t(context, 'Add payment', 'إضافة دفعة'),
        ),
      ),
    ]);
  }

  Widget _formList(List<Widget> children) {
    return ListView.separated(
      padding: const EdgeInsets.all(20),
      itemCount: children.length,
      separatorBuilder: (_, __) => const SizedBox(height: 14),
      itemBuilder: (_, index) => children[index],
    );
  }

  void _selectCustomer(int? id) {
    final customer = _customers.where((item) => item.id == id).firstOrNull;
    setState(() {
      _customerId = id;
      if (customer == null) {
        _clearCustomer();
        return;
      }
      _customerName.text = customer.name;
      _customerCode.text = customer.internalCode ?? '';
      _customerContact.text = customer.contactName ?? '';
      _customerEmail.text = customer.email ?? '';
      _customerPhone.text = customer.phone ?? '';
      _customerActive = customer.isActive;
    });
  }

  void _selectContract(int? id) {
    final contract = _contracts.where((item) => item.id == id).firstOrNull;
    setState(() {
      _contractId = id;
      if (contract == null) {
        _clearContract();
        return;
      }
      _contractNumber.text = contract.contractNumber;
      _contractCustomerId = contract.customerId;
      _contractAccountantId = contract.accountantUserId;
      _contractStart.text = contract.startDate ?? '';
      _contractEnd.text = contract.endDate ?? '';
      _contractBase.text = contract.baseValue ?? '';
    });
  }

  void _selectPayment(int? id) {
    final payment = _payments.where((item) => item.id == id).firstOrNull;
    setState(() {
      _paymentId = id;
      if (payment == null) {
        _clearPayment();
        return;
      }
      _paymentContractId = payment.contractId;
      _paymentSequence.text = '${payment.sequenceNo}';
      _paymentReference.text = payment.reference ?? '';
      _paymentDue.text = payment.dueDate;
      _paymentExpected.text = payment.expectedPaymentDate ?? '';
      _paymentAmount.text = _twoDecimals(payment.originalAmount);
    });
  }

  Future<void> _saveCustomer() async {
    final editing = _customerMode == 'edit';
    final name = _customerName.text.trim();
    if (name.isEmpty || name.length > 191) {
      _message(_t(context, 'Enter a valid customer name.', 'أدخل اسم عميل صحيح.'));
      return;
    }
    final email = _customerEmail.text.trim();
    if (email.isNotEmpty && !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      _message(_t(context, 'Enter a valid email.', 'أدخل بريدًا إلكترونيًا صحيحًا.'));
      return;
    }
    await _runSave(() async {
      final body = <String, Object?>{
        'name': name,
        'internal_code': _customerCode.text.trim(),
        'contact_name': _customerContact.text.trim(),
        'email': email,
        'phone': _customerPhone.text.trim(),
        'is_active': _customerActive,
      };
      if (editing) {
        await widget.client.patch('mobile/customers/$_customerId/edit', body: body);
      } else {
        await widget.client.post('mobile/customers/create', body: body);
      }
      _message(
        editing
            ? _t(context, 'Customer updated.', 'تم تعديل العميل.')
            : _t(context, 'Customer added.', 'تمت إضافة العميل.'),
      );
      _clearCustomer();
      _customerId = null;
      await _load();
    });
  }

  Future<void> _saveContract() async {
    final editing = _contractMode == 'edit';
    final number = _contractNumber.text.trim();
    if (number.isEmpty || number.length > 100 || _contractCustomerId == null) {
      _message(_t(context, 'Contract number and customer are required.', 'رقم العقد والعميل مطلوبان.'));
      return;
    }
    final start = _contractStart.text.trim();
    final end = _contractEnd.text.trim();
    final base = _contractBase.text.trim();
    if (_canEditContracts && (!_validNullableDate(start) || !_validNullableDate(end))) {
      _message(_t(context, 'Contract dates are invalid.', 'تواريخ العقد غير صحيحة.'));
      return;
    }
    if (start.isNotEmpty && end.isNotEmpty && start.compareTo(end) > 0) {
      _message(_t(context, 'End date cannot precede start date.', 'تاريخ النهاية لا يمكن أن يسبق تاريخ البداية.'));
      return;
    }
    if (_canEditContracts && base.isNotEmpty && !_validMoney(base, allowZero: true)) {
      _message(_t(context, 'Enter a valid amount with up to 2 decimals.', 'أدخل مبلغًا صحيحًا حتى رقمين عشريين.'));
      return;
    }
    await _runSave(() async {
      final body = <String, Object?>{
        'contract_number': number,
        'customer_id': _contractCustomerId,
        if (_canAssignContracts) 'accountant_user_id': _contractAccountantId,
        if (_canEditContracts) 'start_date': start.isEmpty ? null : start,
        if (_canEditContracts) 'end_date': end.isEmpty ? null : end,
        if (_canEditContracts && base.isNotEmpty) 'base_value': base,
      };
      if (editing) {
        await widget.client.patch('mobile/contracts/$_contractId/edit', body: body);
      } else {
        await widget.client.post('mobile/contracts/create', body: body);
      }
      _message(
        editing
            ? _t(context, 'Contract updated.', 'تم تعديل العقد.')
            : _t(context, 'Contract added.', 'تمت إضافة العقد.'),
      );
      _clearContract();
      _contractId = null;
      await _load();
    });
  }

  Future<void> _savePayment() async {
    final editing = _paymentMode == 'edit';
    final sequence = int.tryParse(_paymentSequence.text.trim());
    final due = _paymentDue.text.trim();
    final expected = _paymentExpected.text.trim();
    final amount = _paymentAmount.text.trim();
    if ((!editing && _paymentContractId == null) || sequence == null || sequence <= 0) {
      _message(_t(context, 'Contract and positive sequence are required.', 'العقد وترتيب موجب مطلوبان.'));
      return;
    }
    if (!_validRequiredDate(due) || !_validNullableDate(expected)) {
      _message(_t(context, 'Payment dates are invalid.', 'تواريخ الدفعة غير صحيحة.'));
      return;
    }
    if (!_validMoney(amount, allowZero: false)) {
      _message(_t(context, 'Enter a positive amount with up to 2 decimals.', 'أدخل مبلغًا موجبًا حتى رقمين عشريين.'));
      return;
    }
    await _runSave(() async {
      final body = <String, Object?>{
        if (!editing) 'contract_id': _paymentContractId,
        'sequence_no': sequence,
        'reference': _paymentReference.text.trim(),
        'due_date': due,
        'expected_payment_date': expected.isEmpty ? null : expected,
        'original_amount': amount,
      };
      if (editing) {
        await widget.client.patch('mobile/payments/$_paymentId/edit', body: body);
      } else {
        await widget.client.post('mobile/payments/create', body: body);
      }
      _message(
        editing
            ? _t(context, 'Payment updated.', 'تم تعديل الدفعة.')
            : _t(context, 'Payment added.', 'تمت إضافة الدفعة.'),
      );
      _clearPayment();
      _paymentId = null;
      await _load();
    });
  }

  Future<void> _runSave(Future<void> Function() operation) async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      await operation();
    } on SafeContractsApiException catch (error) {
      if (mounted) {
        _message('${_t(context, 'Request failed', 'فشل الطلب')}: ${context.scL10n.rawMessage(error.message)}');
      }
    } on Object catch (error) {
      if (mounted) _message(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _clearCustomer() {
    _customerName.clear();
    _customerCode.clear();
    _customerContact.clear();
    _customerEmail.clear();
    _customerPhone.clear();
    _customerActive = true;
  }

  void _clearContract() {
    _contractNumber.clear();
    _contractStart.clear();
    _contractEnd.clear();
    _contractBase.clear();
    _contractCustomerId = null;
    _contractAccountantId = null;
  }

  void _clearPayment() {
    _paymentSequence.clear();
    _paymentReference.clear();
    _paymentDue.clear();
    _paymentExpected.clear();
    _paymentAmount.clear();
    _paymentContractId = null;
  }

  void _message(String text) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(text)));
  }
}

final class _ModeSelector extends StatelessWidget {
  const _ModeSelector({
    required this.canCreate,
    required this.canEdit,
    required this.mode,
    required this.onChanged,
  });

  final bool canCreate;
  final bool canEdit;
  final String mode;
  final ValueChanged<String> onChanged;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 8,
      children: [
        if (canCreate)
          ChoiceChip(
            selected: mode == 'create',
            label: Text(_t(context, 'Add', 'إضافة')),
            onSelected: (selected) {
              if (selected) onChanged('create');
            },
          ),
        if (canEdit)
          ChoiceChip(
            selected: mode == 'edit',
            label: Text(_t(context, 'Edit', 'تعديل')),
            onSelected: (selected) {
              if (selected) onChanged('edit');
            },
          ),
      ],
    );
  }
}

final class _AccountantOption {
  const _AccountantOption({required this.id, required this.name, this.email});

  final int id;
  final String name;
  final String? email;

  String get label => email == null || email!.isEmpty ? name : '$name <$email>';

  factory _AccountantOption.fromData(Object? value) {
    final data = apiObjectMap(value, 'accountant');
    final id = switch (data['id']) {
      final int value when value > 0 => value,
      final String value => int.tryParse(value),
      _ => null,
    };
    if (id == null || id <= 0) {
      throw const FormatException('Accountant ID is invalid.');
    }
    final name = data['name'];
    if (name is! String || name.trim().isEmpty) {
      throw const FormatException('Accountant name is invalid.');
    }
    final email = data['email'];
    if (email != null && email is! String) {
      throw const FormatException('Accountant email is invalid.');
    }
    return _AccountantOption(
      id: id,
      name: name.trim(),
      email: email is String && email.trim().isNotEmpty ? email.trim() : null,
    );
  }
}

final class _NoPermission extends StatelessWidget {
  const _NoPermission({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(text, textAlign: TextAlign.center),
        ),
      );
}

final class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton(
                onPressed: () => unawaited(onRetry()),
                child: Text(_t(context, 'Retry', 'إعادة المحاولة')),
              ),
            ],
          ),
        ),
      );
}

String _t(BuildContext context, String english, String arabic) {
  return context.scL10n.isArabic ? arabic : english;
}

bool _validNullableDate(String value) {
  final text = value.trim();
  if (text.isEmpty) return true;
  return _validRequiredDate(text);
}

bool _validRequiredDate(String value) {
  final text = value.trim();
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(text);
  if (match == null) return false;
  final parsed = DateTime.tryParse(text);
  return parsed != null &&
      parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}

bool _validMoney(String value, {required bool allowZero}) {
  final text = value.trim();
  if (!RegExp(r'^\d+(?:\.\d{1,2})?$').hasMatch(text)) return false;
  final parsed = double.tryParse(text);
  if (parsed == null) return false;
  return allowZero ? parsed >= 0 : parsed > 0;
}

String _twoDecimals(String value) {
  final parsed = double.tryParse(value);
  return parsed == null ? value : parsed.toStringAsFixed(2);
}

extension _FirstOrNullExtension<T> on Iterable<T> {
  T? get firstOrNull {
    for (final item in this) {
      return item;
    }
    return null;
  }
}
