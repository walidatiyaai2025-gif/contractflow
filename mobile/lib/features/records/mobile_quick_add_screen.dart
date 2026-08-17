import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../contracts/contracts.dart';
import '../customers/customers.dart';
import '../dashboard/dashboard_models.dart';
import '../session/session_controller.dart';
import '../ui/safecontracts_design.dart';

enum MobileQuickAddType { customer, contract, payment }

List<MobileQuickAddType> availableMobileQuickAdds(
  SafeContractsSession session,
) {
  return <MobileQuickAddType>[
    if (session.can('safecontracts_create_customers'))
      MobileQuickAddType.customer,
    if (session.can('safecontracts_create_contracts'))
      MobileQuickAddType.contract,
    if (session.can('safecontracts_create_payments'))
      MobileQuickAddType.payment,
  ];
}

String mobileQuickAddLabel(
  BuildContext context,
  MobileQuickAddType type,
) {
  final arabic = context.scL10n.isArabic;
  return switch (type) {
    MobileQuickAddType.customer => arabic ? 'إضافة عميل' : 'Add customer',
    MobileQuickAddType.contract => arabic ? 'إضافة عقد' : 'Add contract',
    MobileQuickAddType.payment => arabic ? 'إضافة دفعة' : 'Add payment',
  };
}

String mobileQuickAddDescription(
  BuildContext context,
  MobileQuickAddType type,
) {
  final arabic = context.scL10n.isArabic;
  return switch (type) {
    MobileQuickAddType.customer => arabic
        ? 'أنشئ سجل عميل جديد ضمن نطاق صلاحيتك.'
        : 'Create a new customer inside your authorized scope.',
    MobileQuickAddType.contract => arabic
        ? 'أنشئ عقدًا جديدًا واربطه بالعميل المسموح لك.'
        : 'Create a new contract and link it to an authorized customer.',
    MobileQuickAddType.payment => arabic
        ? 'أضف دفعة جديدة إلى عقد متاح في نطاقك.'
        : 'Add a new payment to a contract in your scope.',
  };
}

IconData mobileQuickAddIcon(MobileQuickAddType type) {
  return switch (type) {
    MobileQuickAddType.customer => Icons.person_add_alt_1_rounded,
    MobileQuickAddType.contract => Icons.note_add_rounded,
    MobileQuickAddType.payment => Icons.add_card_rounded,
  };
}

final class MobileQuickAddScreen extends StatefulWidget {
  const MobileQuickAddScreen({
    required this.client,
    required this.session,
    required this.type,
    super.key,
  });

  final SafeContractsApiClient client;
  final SafeContractsSession session;
  final MobileQuickAddType type;

  @override
  State<MobileQuickAddScreen> createState() => _MobileQuickAddScreenState();
}

final class _MobileQuickAddScreenState extends State<MobileQuickAddScreen> {
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
  List<SafeContractsCustomer> _customers = const <SafeContractsCustomer>[];
  List<SafeContractsContract> _contracts = const <SafeContractsContract>[];
  List<_AccountantOption> _accountants = const <_AccountantOption>[];
  int? _contractCustomerId;
  int? _contractAccountantId;
  int? _paymentContractId;
  bool _customerActive = true;

  bool get _canAssignContracts =>
      widget.session.can('safecontracts_assign_contracts');
  bool get _canEditContracts =>
      widget.session.can('safecontracts_edit_contracts');

  @override
  void initState() {
    super.initState();
    unawaited(_loadReferences());
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

  Future<void> _loadReferences() async {
    if (widget.type == MobileQuickAddType.customer) {
      if (mounted) setState(() => _loading = false);
      return;
    }

    setState(() {
      _loading = true;
      _loadError = null;
    });

    try {
      if (widget.type == MobileQuickAddType.contract) {
        final customers = await CustomersRepository(widget.client).loadPage(
          page: 1,
          perPage: 100,
          order: 'asc',
        );
        var accountants = const <_AccountantOption>[];
        if (_canAssignContracts) {
          final envelope = await widget.client.get('reference-data');
          final data = apiObjectMap(envelope.data, 'reference-data.data');
          final rows = apiObjectList(
            data['accountants'],
            'reference-data.accountants',
          );
          accountants =
              rows.map(_AccountantOption.fromData).toList(growable: false);
        }
        if (!mounted) return;
        setState(() {
          _customers = customers.customers;
          _accountants = accountants;
          _loading = false;
        });
        return;
      }

      final contracts = await ContractsRepository(widget.client).loadPage(
        page: 1,
        perPage: 100,
        filters: const ContractsFilters(),
        sort: ContractSortOption.newest,
      );
      if (!mounted) return;
      setState(() {
        _contracts = contracts.contracts;
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
    final title = mobileQuickAddLabel(context, widget.type);
    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        backgroundColor: SafeContractsVisual.background,
        surfaceTintColor: Colors.transparent,
        title: Text(title),
      ),
      body: SafeContractsBackdrop(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _loadError != null
                ? _LoadError(
                    message: _loadError!,
                    onRetry: _loadReferences,
                  )
                : TweenAnimationBuilder<double>(
                    tween: Tween<double>(begin: 0, end: 1),
                    duration: const Duration(milliseconds: 360),
                    curve: Curves.easeOutCubic,
                    builder: (context, value, child) {
                      return Opacity(
                        opacity: value,
                        child: Transform.translate(
                          offset: Offset(0, 18 * (1 - value)),
                          child: child,
                        ),
                      );
                    },
                    child: SafeArea(
                      child: ListView(
                        padding: const EdgeInsets.fromLTRB(18, 8, 18, 28),
                        children: [
                          _QuickAddHeader(type: widget.type),
                          const SizedBox(height: 16),
                          SafeContractsSurface(
                            child: switch (widget.type) {
                              MobileQuickAddType.customer => _customerForm(),
                              MobileQuickAddType.contract => _contractForm(),
                              MobileQuickAddType.payment => _paymentForm(),
                            },
                          ),
                        ],
                      ),
                    ),
                  ),
      ),
    );
  }

  Widget _customerForm() {
    final arabic = context.scL10n.isArabic;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _customerName,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic ? 'الاسم *' : 'Name *',
            prefixIcon: const Icon(Icons.badge_outlined),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _customerCode,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic ? 'الكود الداخلي' : 'Internal code',
            prefixIcon: const Icon(Icons.tag_rounded),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _customerContact,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic ? 'اسم جهة الاتصال' : 'Contact name',
            prefixIcon: const Icon(Icons.contact_page_outlined),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _customerEmail,
          keyboardType: TextInputType.emailAddress,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic ? 'البريد الإلكتروني' : 'Email',
            prefixIcon: const Icon(Icons.alternate_email_rounded),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _customerPhone,
          keyboardType: TextInputType.phone,
          decoration: InputDecoration(
            labelText: arabic ? 'الهاتف' : 'Phone',
            prefixIcon: const Icon(Icons.phone_outlined),
          ),
        ),
        const SizedBox(height: 8),
        SwitchListTile.adaptive(
          contentPadding: EdgeInsets.zero,
          title: Text(arabic ? 'عميل نشط' : 'Active customer'),
          value: _customerActive,
          onChanged: _saving
              ? null
              : (value) => setState(() => _customerActive = value),
        ),
        const SizedBox(height: 8),
        _saveButton(
          icon: Icons.person_add_alt_1_rounded,
          label: arabic ? 'إضافة العميل' : 'Add customer',
          onPressed: _saveCustomer,
        ),
      ],
    );
  }

  Widget _contractForm() {
    final arabic = context.scL10n.isArabic;
    if (_customers.isEmpty) {
      return _EmptyReference(
        icon: Icons.people_outline_rounded,
        message: arabic
            ? 'لا يوجد عملاء متاحون في نطاقك لإنشاء عقد.'
            : 'No customers are available in your scope for a new contract.',
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        TextField(
          controller: _contractNumber,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic ? 'رقم العقد *' : 'Contract number *',
            prefixIcon: const Icon(Icons.numbers_rounded),
          ),
        ),
        const SizedBox(height: 14),
        DropdownButtonFormField<int>(
          initialValue: _contractCustomerId,
          decoration: InputDecoration(
            labelText: arabic ? 'العميل *' : 'Customer *',
            prefixIcon: const Icon(Icons.business_outlined),
          ),
          items: _customers
              .map(
                (customer) => DropdownMenuItem<int>(
                  value: customer.id,
                  child: Text(customer.name),
                ),
              )
              .toList(growable: false),
          onChanged: _saving
              ? null
              : (value) => setState(() => _contractCustomerId = value),
        ),
        if (_canAssignContracts) ...[
          const SizedBox(height: 14),
          DropdownButtonFormField<int>(
            initialValue: _contractAccountantId,
            decoration: InputDecoration(
              labelText: arabic ? 'المحاسب المسؤول' : 'Responsible accountant',
              prefixIcon: const Icon(Icons.support_agent_rounded),
            ),
            items: _accountants
                .map(
                  (accountant) => DropdownMenuItem<int>(
                    value: accountant.id,
                    child: Text(accountant.label),
                  ),
                )
                .toList(growable: false),
            onChanged: _saving
                ? null
                : (value) => setState(() => _contractAccountantId = value),
          ),
        ],
        if (_canEditContracts) ...[
          const SizedBox(height: 14),
          TextField(
            controller: _contractStart,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              labelText: arabic
                  ? 'تاريخ البداية YYYY-MM-DD'
                  : 'Start date YYYY-MM-DD',
              prefixIcon: const Icon(Icons.event_available_outlined),
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _contractEnd,
            textInputAction: TextInputAction.next,
            decoration: InputDecoration(
              labelText: arabic
                  ? 'تاريخ النهاية YYYY-MM-DD'
                  : 'End date YYYY-MM-DD',
              prefixIcon: const Icon(Icons.event_busy_outlined),
            ),
          ),
          const SizedBox(height: 14),
          TextField(
            controller: _contractBase,
            keyboardType: const TextInputType.numberWithOptions(decimal: true),
            decoration: InputDecoration(
              labelText: arabic ? 'القيمة الأساسية' : 'Base value',
              helperText: arabic ? 'حتى رقمين عشريين' : 'Up to 2 decimals',
              prefixIcon: const Icon(Icons.payments_outlined),
            ),
          ),
        ],
        const SizedBox(height: 18),
        _saveButton(
          icon: Icons.note_add_rounded,
          label: arabic ? 'إضافة العقد' : 'Add contract',
          onPressed: _saveContract,
        ),
      ],
    );
  }

  Widget _paymentForm() {
    final arabic = context.scL10n.isArabic;
    if (_contracts.isEmpty) {
      return _EmptyReference(
        icon: Icons.folder_off_outlined,
        message: arabic
            ? 'لا توجد عقود متاحة في نطاقك لإضافة دفعة.'
            : 'No contracts are available in your scope for a new payment.',
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DropdownButtonFormField<int>(
          initialValue: _paymentContractId,
          decoration: InputDecoration(
            labelText: arabic ? 'العقد *' : 'Contract *',
            prefixIcon: const Icon(Icons.folder_copy_outlined),
          ),
          items: _contracts
              .map(
                (contract) => DropdownMenuItem<int>(
                  value: contract.id,
                  child: Text(contract.contractNumber),
                ),
              )
              .toList(growable: false),
          onChanged: _saving
              ? null
              : (value) => setState(() => _paymentContractId = value),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _paymentSequence,
          keyboardType: TextInputType.number,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic ? 'الترتيب *' : 'Sequence *',
            prefixIcon: const Icon(Icons.format_list_numbered_rounded),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _paymentReference,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic ? 'المرجع' : 'Reference',
            prefixIcon: const Icon(Icons.receipt_outlined),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _paymentDue,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic
                ? 'تاريخ الاستحقاق YYYY-MM-DD *'
                : 'Due date YYYY-MM-DD *',
            prefixIcon: const Icon(Icons.event_note_outlined),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _paymentExpected,
          textInputAction: TextInputAction.next,
          decoration: InputDecoration(
            labelText: arabic
                ? 'تاريخ الدفع المتوقع YYYY-MM-DD'
                : 'Expected date YYYY-MM-DD',
            prefixIcon: const Icon(Icons.schedule_outlined),
          ),
        ),
        const SizedBox(height: 14),
        TextField(
          controller: _paymentAmount,
          keyboardType: const TextInputType.numberWithOptions(decimal: true),
          decoration: InputDecoration(
            labelText: arabic ? 'المبلغ الأصلي *' : 'Original amount *',
            helperText: arabic ? 'حتى رقمين عشريين' : 'Up to 2 decimals',
            prefixIcon: const Icon(Icons.account_balance_wallet_outlined),
          ),
        ),
        const SizedBox(height: 18),
        _saveButton(
          icon: Icons.add_card_rounded,
          label: arabic ? 'إضافة الدفعة' : 'Add payment',
          onPressed: _savePayment,
        ),
      ],
    );
  }

  Widget _saveButton({
    required IconData icon,
    required String label,
    required Future<void> Function() onPressed,
  }) {
    return FilledButton.icon(
      onPressed: _saving ? null : () => unawaited(onPressed()),
      icon: _saving
          ? const SizedBox.square(
              dimension: 18,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : Icon(icon),
      label: Text(_saving ? _savingLabel() : label),
    );
  }

  String _savingLabel() {
    return context.scL10n.isArabic ? 'جارٍ الحفظ…' : 'Saving…';
  }

  Future<void> _saveCustomer() async {
    final arabic = context.scL10n.isArabic;
    final name = _customerName.text.trim();
    if (name.isEmpty || name.length > 191) {
      _message(arabic ? 'أدخل اسم عميل صحيح.' : 'Enter a valid customer name.');
      return;
    }
    final email = _customerEmail.text.trim();
    if (email.isNotEmpty &&
        !RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(email)) {
      _message(arabic ? 'أدخل بريدًا إلكترونيًا صحيحًا.' : 'Enter a valid email.');
      return;
    }

    await _runSave(() async {
      await widget.client.post(
        'mobile/customers/create',
        body: <String, Object?>{
          'name': name,
          'internal_code': _customerCode.text.trim(),
          'contact_name': _customerContact.text.trim(),
          'email': email,
          'phone': _customerPhone.text.trim(),
          'is_active': _customerActive,
        },
      );
      if (mounted) Navigator.of(context).pop(true);
    });
  }

  Future<void> _saveContract() async {
    final arabic = context.scL10n.isArabic;
    final number = _contractNumber.text.trim();
    if (number.isEmpty || number.length > 100 || _contractCustomerId == null) {
      _message(
        arabic
            ? 'رقم العقد والعميل مطلوبان.'
            : 'Contract number and customer are required.',
      );
      return;
    }

    final start = _contractStart.text.trim();
    final end = _contractEnd.text.trim();
    final base = _contractBase.text.trim();
    if (_canEditContracts &&
        (!_validNullableDate(start) || !_validNullableDate(end))) {
      _message(arabic ? 'تواريخ العقد غير صحيحة.' : 'Contract dates are invalid.');
      return;
    }
    if (start.isNotEmpty && end.isNotEmpty && start.compareTo(end) > 0) {
      _message(
        arabic
            ? 'تاريخ النهاية لا يمكن أن يسبق تاريخ البداية.'
            : 'End date cannot precede start date.',
      );
      return;
    }
    if (_canEditContracts &&
        base.isNotEmpty &&
        !_validMoney(base, allowZero: true)) {
      _message(
        arabic
            ? 'أدخل مبلغًا صحيحًا حتى رقمين عشريين.'
            : 'Enter a valid amount with up to 2 decimals.',
      );
      return;
    }

    await _runSave(() async {
      await widget.client.post(
        'mobile/contracts/create',
        body: <String, Object?>{
          'contract_number': number,
          'customer_id': _contractCustomerId,
          if (_canAssignContracts) 'accountant_user_id': _contractAccountantId,
          if (_canEditContracts) 'start_date': start.isEmpty ? null : start,
          if (_canEditContracts) 'end_date': end.isEmpty ? null : end,
          if (_canEditContracts && base.isNotEmpty) 'base_value': base,
        },
      );
      if (mounted) Navigator.of(context).pop(true);
    });
  }

  Future<void> _savePayment() async {
    final arabic = context.scL10n.isArabic;
    final sequence = int.tryParse(_paymentSequence.text.trim());
    final due = _paymentDue.text.trim();
    final expected = _paymentExpected.text.trim();
    final amount = _paymentAmount.text.trim();

    if (_paymentContractId == null || sequence == null || sequence <= 0) {
      _message(
        arabic
            ? 'العقد وترتيب موجب مطلوبان.'
            : 'Contract and positive sequence are required.',
      );
      return;
    }
    if (!_validRequiredDate(due) || !_validNullableDate(expected)) {
      _message(arabic ? 'تواريخ الدفعة غير صحيحة.' : 'Payment dates are invalid.');
      return;
    }
    if (!_validMoney(amount, allowZero: false)) {
      _message(
        arabic
            ? 'أدخل مبلغًا موجبًا حتى رقمين عشريين.'
            : 'Enter a positive amount with up to 2 decimals.',
      );
      return;
    }

    await _runSave(() async {
      await widget.client.post(
        'mobile/payments/create',
        body: <String, Object?>{
          'contract_id': _paymentContractId,
          'sequence_no': sequence,
          'reference': _paymentReference.text.trim(),
          'due_date': due,
          'expected_payment_date': expected.isEmpty ? null : expected,
          'original_amount': amount,
        },
      );
      if (mounted) Navigator.of(context).pop(true);
    });
  }

  Future<void> _runSave(Future<void> Function() operation) async {
    if (_saving) return;
    setState(() => _saving = true);
    try {
      await operation();
    } on SafeContractsApiException catch (error) {
      if (mounted) {
        _message(context.scL10n.rawMessage(error.message));
      }
    } on Object catch (error) {
      if (mounted) _message(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _message(String message) {
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message)),
    );
  }
}

final class _QuickAddHeader extends StatelessWidget {
  const _QuickAddHeader({required this.type});

  final MobileQuickAddType type;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 52,
          height: 52,
          decoration: BoxDecoration(
            color: SafeContractsVisual.navySoft,
            borderRadius: BorderRadius.circular(18),
          ),
          child: Icon(
            mobileQuickAddIcon(type),
            color: SafeContractsVisual.navy,
          ),
        ),
        const SizedBox(width: 14),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                mobileQuickAddLabel(context, type),
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: SafeContractsVisual.ink,
                    ),
              ),
              const SizedBox(height: 3),
              Text(
                mobileQuickAddDescription(context, type),
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: SafeContractsVisual.muted,
                    ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

final class _EmptyReference extends StatelessWidget {
  const _EmptyReference({required this.icon, required this.message});

  final IconData icon;
  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 28),
      child: Column(
        children: [
          Icon(icon, size: 42, color: SafeContractsVisual.muted),
          const SizedBox(height: 12),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
        ],
      ),
    );
  }
}

final class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.cloud_off_outlined, size: 44),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.tonalIcon(
              onPressed: () => unawaited(onRetry()),
              icon: const Icon(Icons.refresh_rounded),
              label: Text(arabic ? 'إعادة المحاولة' : 'Retry'),
            ),
          ],
        ),
      ),
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
