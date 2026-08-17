import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../contracts/contracts.dart';
import '../customers/customers.dart';
import '../session/session_controller.dart';
import '../suppliers/suppliers.dart';
import '../ui/safecontracts_design.dart';

enum MobileQuickAddType { customer, supplier, contract, payment }

List<MobileQuickAddType> availableMobileQuickAdds(
  SafeContractsSession session,
) {
  return <MobileQuickAddType>[
    if (session.can('safecontracts_create_customers'))
      MobileQuickAddType.customer,
    if (session.can('safecontracts_create_suppliers'))
      MobileQuickAddType.supplier,
    if (session.can('safecontracts_create_contracts'))
      MobileQuickAddType.contract,
    if (session.can('safecontracts_create_payments'))
      MobileQuickAddType.payment,
  ];
}

String mobileQuickAddLabel(BuildContext context, MobileQuickAddType type) {
  final ar = context.scL10n.isArabic;
  return switch (type) {
    MobileQuickAddType.customer => ar ? 'إضافة عميل' : 'Add customer',
    MobileQuickAddType.supplier => ar ? 'إضافة مورد' : 'Add supplier',
    MobileQuickAddType.contract => ar ? 'إضافة عقد' : 'Add contract',
    MobileQuickAddType.payment => ar ? 'إضافة استحقاق' : 'Add obligation',
  };
}

String mobileQuickAddDescription(
  BuildContext context,
  MobileQuickAddType type,
) {
  final ar = context.scL10n.isArabic;
  return switch (type) {
    MobileQuickAddType.customer => ar
        ? 'أنشئ عميلًا جديدًا ضمن صلاحياتك.'
        : 'Create a customer inside your authorized scope.',
    MobileQuickAddType.supplier => ar
        ? 'أنشئ موردًا جديدًا ببياناته المالية الأساسية.'
        : 'Create a supplier with its core finance profile.',
    MobileQuickAddType.contract => ar
        ? 'اربط العقد بعميل أو مورد؛ اتجاه AR/AP يحدده السيرفر.'
        : 'Link a contract to a customer or supplier; the server derives AR/AP.',
    MobileQuickAddType.payment => ar
        ? 'أضف استحقاقًا إلى عقد متاح في نطاقك.'
        : 'Add an obligation to an authorized contract.',
  };
}

IconData mobileQuickAddIcon(MobileQuickAddType type) => switch (type) {
      MobileQuickAddType.customer => Icons.person_add_alt_1_rounded,
      MobileQuickAddType.supplier => Icons.local_shipping_rounded,
      MobileQuickAddType.contract => Icons.note_add_rounded,
      MobileQuickAddType.payment => Icons.add_card_rounded,
    };

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
  static const _maxReferencePage = 5;

  final _name = TextEditingController();
  final _code = TextEditingController();
  final _contact = TextEditingController();
  final _email = TextEditingController();
  final _phone = TextEditingController();
  final _tradingName = TextEditingController();
  final _currency = TextEditingController();
  final _paymentTerms = TextEditingController();
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
  CustomerPage? _customerPage;
  ContractPage? _contractPage;
  List<SafeContractsSupplier> _suppliers = const [];
  List<SafeContractsContract> _contracts = const [];
  List<_AccountantOption> _accountants = const [];
  String _counterpartyType = 'customer';
  int? _counterpartyId;
  int? _contractAccountantId;
  int? _paymentContractId;
  bool _customerActive = true;

  bool get _canAssign => widget.session.can('safecontracts_assign_contracts');
  bool get _canEditContracts =>
      widget.session.can('safecontracts_edit_contracts');
  bool get _canViewSuppliers =>
      widget.session.can('safecontracts_view_suppliers');

  @override
  void initState() {
    super.initState();
    unawaited(_loadReferences());
  }

  @override
  void dispose() {
    for (final c in <TextEditingController>[
      _name,
      _code,
      _contact,
      _email,
      _phone,
      _tradingName,
      _currency,
      _paymentTerms,
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
      c.dispose();
    }
    super.dispose();
  }

  Future<void> _loadReferences({
    int customerPage = 1,
    int contractPage = 1,
  }) async {
    if (widget.type == MobileQuickAddType.customer ||
        widget.type == MobileQuickAddType.supplier) {
      if (mounted) setState(() => _loading = false);
      return;
    }
    setState(() {
      _loading = true;
      _loadError = null;
    });
    try {
      if (widget.type == MobileQuickAddType.contract) {
        final customers = await CustomersRepository(widget.client)
            .loadPage(page: customerPage, perPage: 100, order: 'asc');
        if (customers.page == _maxReferencePage && customers.hasMore) {
          throw const FormatException(
            'Customer references exceed the supported bounded mobile window.',
          );
        }
        var suppliers = const <SafeContractsSupplier>[];
        if (_canViewSuppliers) {
          suppliers =
              await SuppliersRepository(widget.client).search(limit: 200);
        }
        var accountants = const <_AccountantOption>[];
        if (_canAssign) {
          final envelope = await widget.client.get('reference-data');
          final data = apiObjectMap(envelope.data, 'reference-data.data');
          accountants = apiObjectList(
            data['accountants'],
            'reference-data.accountants',
          ).map(_AccountantOption.fromData).toList(growable: false);
        }
        if (!mounted) return;
        setState(() {
          _customerPage = customers;
          _suppliers = suppliers;
          _accountants = accountants;
          if (_counterpartyType == 'customer') {
            final customerStillVisible = customers.customers.any(
              (customer) => customer.id == _counterpartyId,
            );
            if (!customerStillVisible && customers.customers.isNotEmpty) {
              _counterpartyId = customers.customers.first.id;
            } else if (customers.customers.isEmpty && suppliers.isNotEmpty) {
              _counterpartyType = 'supplier';
              _counterpartyId = suppliers.first.id;
              _currency.text = suppliers.first.defaultCurrency ?? '';
            }
          } else {
            final supplierStillVisible = suppliers.any(
              (supplier) => supplier.id == _counterpartyId,
            );
            if (!supplierStillVisible && suppliers.isNotEmpty) {
              _counterpartyId = suppliers.first.id;
              _currency.text = suppliers.first.defaultCurrency ?? '';
            } else if (suppliers.isEmpty && customers.customers.isNotEmpty) {
              _counterpartyType = 'customer';
              _counterpartyId = customers.customers.first.id;
            }
          }
          _loading = false;
        });
        return;
      }
      final contracts = await ContractsRepository(widget.client).loadPage(
        page: contractPage,
        perPage: 100,
        filters: const ContractsFilters(),
        sort: ContractSortOption.newest,
      );
      if (contracts.page == _maxReferencePage && contracts.hasMore) {
        throw const FormatException(
          'Contract references exceed the supported bounded mobile window.',
        );
      }
      if (!mounted) return;
      setState(() {
        _contractPage = contracts;
        _contracts = contracts.contracts;
        if (!_contracts.any((contract) => contract.id == _paymentContractId)) {
          _paymentContractId = null;
        }
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
    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(title: Text(mobileQuickAddLabel(context, widget.type))),
      body: SafeContractsBackdrop(
        child: _loading
            ? const Center(child: CircularProgressIndicator())
            : _loadError != null
                ? _LoadError(message: _loadError!, onRetry: _loadReferences)
                : SafeArea(
                    child: ListView(
                      padding: const EdgeInsets.all(18),
                      children: [
                        _QuickAddHeader(type: widget.type),
                        const SizedBox(height: 16),
                        SafeContractsSurface(child: _form()),
                      ],
                    ),
                  ),
      ),
    );
  }

  Widget _form() => switch (widget.type) {
        MobileQuickAddType.customer => _customerForm(),
        MobileQuickAddType.supplier => _supplierForm(),
        MobileQuickAddType.contract => _contractForm(),
        MobileQuickAddType.payment => _paymentForm(),
      };

  Widget _customerForm() {
    final ar = context.scL10n.isArabic;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _field(_name, ar ? 'الاسم *' : 'Name *', Icons.badge_outlined),
        _gap(),
        _field(
          _code,
          ar ? 'الكود الداخلي' : 'Internal code',
          Icons.tag_rounded,
        ),
        _gap(),
        _field(
          _contact,
          ar ? 'جهة الاتصال' : 'Contact name',
          Icons.contact_page_outlined,
        ),
        _gap(),
        _field(
          _email,
          ar ? 'البريد الإلكتروني' : 'Email',
          Icons.alternate_email_rounded,
          keyboard: TextInputType.emailAddress,
        ),
        _gap(),
        _field(
          _phone,
          ar ? 'الهاتف' : 'Phone',
          Icons.phone_outlined,
          keyboard: TextInputType.phone,
        ),
        SwitchListTile.adaptive(
          contentPadding: EdgeInsets.zero,
          title: Text(ar ? 'عميل نشط' : 'Active customer'),
          value: _customerActive,
          onChanged:
              _saving ? null : (v) => setState(() => _customerActive = v),
        ),
        _saveButton(
          Icons.person_add_alt_1_rounded,
          ar ? 'إضافة العميل' : 'Add customer',
          _saveCustomer,
        ),
      ],
    );
  }

  Widget _supplierForm() {
    final ar = context.scL10n.isArabic;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _field(
          _name,
          ar ? 'الاسم القانوني *' : 'Legal name *',
          Icons.corporate_fare_rounded,
        ),
        _gap(),
        _field(
          _tradingName,
          ar ? 'الاسم التجاري' : 'Trading name',
          Icons.storefront_outlined,
        ),
        _gap(),
        _field(
          _code,
          ar ? 'الكود الداخلي' : 'Internal code',
          Icons.tag_rounded,
        ),
        _gap(),
        _field(
          _contact,
          ar ? 'جهة الاتصال' : 'Contact name',
          Icons.contact_page_outlined,
        ),
        _gap(),
        _field(
          _email,
          ar ? 'البريد الإلكتروني' : 'Email',
          Icons.alternate_email_rounded,
          keyboard: TextInputType.emailAddress,
        ),
        _gap(),
        _field(
          _phone,
          ar ? 'الهاتف' : 'Phone',
          Icons.phone_outlined,
          keyboard: TextInputType.phone,
        ),
        _gap(),
        _field(
          _currency,
          ar ? 'العملة الافتراضية (KWD)' : 'Default currency (KWD)',
          Icons.currency_exchange_rounded,
        ),
        _gap(),
        _field(
          _paymentTerms,
          ar ? 'شروط الدفع' : 'Payment terms',
          Icons.event_repeat_rounded,
        ),
        const SizedBox(height: 18),
        _saveButton(
          Icons.local_shipping_rounded,
          ar ? 'إضافة المورد' : 'Add supplier',
          _saveSupplier,
        ),
      ],
    );
  }

  Widget _contractForm() {
    final ar = context.scL10n.isArabic;
    final customers =
        _customerPage?.customers ?? const <SafeContractsCustomer>[];
    final hasCustomers = customers.isNotEmpty;
    final hasSuppliers = _suppliers.isNotEmpty;
    if (!hasCustomers && !hasSuppliers) {
      return _EmptyReference(
        message: ar
            ? 'لا توجد جهات تعاقد متاحة في نطاقك.'
            : 'No counterparties are available in your scope.',
      );
    }
    final typeItems = <DropdownMenuItem<String>>[
      if (hasCustomers)
        DropdownMenuItem(
          value: 'customer',
          child: Text(ar ? 'عميل — Receivable' : 'Customer — Receivable'),
        ),
      if (hasSuppliers)
        DropdownMenuItem(
          value: 'supplier',
          child: Text(ar ? 'مورد — Payable' : 'Supplier — Payable'),
        ),
    ];
    final options = _counterpartyType == 'supplier'
        ? _suppliers
            .map(
              (s) => DropdownMenuItem<int>(
                value: s.id,
                child: Text(s.displayName),
              ),
            )
            .toList()
        : customers
            .map(
              (c) => DropdownMenuItem<int>(value: c.id, child: Text(c.name)),
            )
            .toList();
    final selectedExists = options.any((item) => item.value == _counterpartyId);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _field(
          _contractNumber,
          ar ? 'رقم العقد *' : 'Contract number *',
          Icons.numbers_rounded,
        ),
        _gap(),
        DropdownButtonFormField<String>(
          initialValue: _counterpartyType,
          decoration: InputDecoration(
            labelText: ar ? 'نوع جهة التعاقد *' : 'Counterparty type *',
          ),
          items: typeItems,
          onChanged: _saving
              ? null
              : (value) {
                  if (value == null) return;
                  setState(() {
                    _counterpartyType = value;
                    if (value == 'supplier') {
                      _counterpartyId =
                          _suppliers.isEmpty ? null : _suppliers.first.id;
                      _currency.text = _suppliers.isEmpty
                          ? ''
                          : (_suppliers.first.defaultCurrency ?? '');
                    } else {
                      _counterpartyId =
                          customers.isEmpty ? null : customers.first.id;
                    }
                  });
                },
        ),
        _gap(),
        DropdownButtonFormField<int>(
          key: ValueKey('$_counterpartyType:${_customerPage?.page ?? 1}'),
          initialValue: selectedExists ? _counterpartyId : null,
          decoration: InputDecoration(
            labelText: ar ? 'جهة التعاقد *' : 'Counterparty *',
          ),
          items: options,
          onChanged: _saving
              ? null
              : (value) {
                  setState(() {
                    _counterpartyId = value;
                    if (_counterpartyType == 'supplier' && value != null) {
                      final supplier =
                          _suppliers.where((s) => s.id == value).firstOrNull;
                      if (supplier?.defaultCurrency != null) {
                        _currency.text = supplier!.defaultCurrency!;
                      }
                    }
                  });
                },
        ),
        if (_counterpartyType == 'customer' && _customerPage != null) ...[
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.end,
            children: [
              OutlinedButton(
                onPressed: _saving || _customerPage!.page <= 1
                    ? null
                    : () => unawaited(
                          _loadReferences(
                              customerPage: _customerPage!.page - 1),
                        ),
                child: Text(ar ? 'السابق' : 'Previous'),
              ),
              const SizedBox(width: 8),
              OutlinedButton(
                onPressed: _saving ||
                        !_customerPage!.hasMore ||
                        _customerPage!.page >= _maxReferencePage
                    ? null
                    : () => unawaited(
                          _loadReferences(
                              customerPage: _customerPage!.page + 1),
                        ),
                child: Text(ar ? 'التالي' : 'Next'),
              ),
            ],
          ),
        ],
        _gap(),
        _field(
          _currency,
          ar ? 'عملة العقد *' : 'Contract currency *',
          Icons.currency_exchange_rounded,
        ),
        if (_canAssign) ...[
          _gap(),
          DropdownButtonFormField<int>(
            initialValue: _contractAccountantId,
            decoration: InputDecoration(
              labelText: ar ? 'المحاسب المسؤول' : 'Responsible accountant',
            ),
            items: _accountants
                .map((a) => DropdownMenuItem(value: a.id, child: Text(a.label)))
                .toList(),
            onChanged: _saving
                ? null
                : (v) => setState(() => _contractAccountantId = v),
          ),
        ],
        if (_canEditContracts) ...[
          _gap(),
          _field(
            _contractStart,
            ar ? 'تاريخ البداية YYYY-MM-DD' : 'Start date YYYY-MM-DD',
            Icons.event_available_outlined,
          ),
          _gap(),
          _field(
            _contractEnd,
            ar ? 'تاريخ النهاية YYYY-MM-DD' : 'End date YYYY-MM-DD',
            Icons.event_busy_outlined,
          ),
          _gap(),
          _field(
            _contractBase,
            ar ? 'القيمة الأساسية' : 'Base value',
            Icons.payments_outlined,
            keyboard: const TextInputType.numberWithOptions(decimal: true),
          ),
        ],
        const SizedBox(height: 18),
        _saveButton(
          Icons.note_add_rounded,
          ar ? 'إضافة العقد' : 'Add contract',
          _saveContract,
        ),
      ],
    );
  }

  Widget _paymentForm() {
    final ar = context.scL10n.isArabic;
    if (_contracts.isEmpty) {
      return _EmptyReference(
        message: ar ? 'لا توجد عقود متاحة.' : 'No contracts are available.',
      );
    }
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        DropdownButtonFormField<int>(
          initialValue: _paymentContractId,
          decoration: InputDecoration(labelText: ar ? 'العقد *' : 'Contract *'),
          items: _contracts
              .map(
                (c) => DropdownMenuItem(
                  value: c.id,
                  child: Text('${c.contractNumber} — ${c.displayCounterparty}'),
                ),
              )
              .toList(),
          onChanged:
              _saving ? null : (v) => setState(() => _paymentContractId = v),
        ),
        if (_contractPage != null) ...[
          const SizedBox(height: 8),
          Row(
            children: [
              OutlinedButton(
                onPressed: _saving || _contractPage!.page <= 1
                    ? null
                    : () => unawaited(
                          _loadReferences(
                            contractPage: _contractPage!.page - 1,
                          ),
                        ),
                child: Text(ar ? 'السابق' : 'Previous'),
              ),
              const Spacer(),
              Text(
                ar
                    ? 'الصفحة ${_contractPage!.page}'
                    : 'Page ${_contractPage!.page}',
              ),
              const Spacer(),
              OutlinedButton(
                onPressed: _saving ||
                        !_contractPage!.hasMore ||
                        _contractPage!.page >= _maxReferencePage
                    ? null
                    : () => unawaited(
                          _loadReferences(
                            contractPage: _contractPage!.page + 1,
                          ),
                        ),
                child: Text(ar ? 'التالي' : 'Next'),
              ),
            ],
          ),
        ],
        _gap(),
        _field(
          _paymentSequence,
          ar ? 'الترتيب *' : 'Sequence *',
          Icons.format_list_numbered_rounded,
          keyboard: TextInputType.number,
        ),
        _gap(),
        _field(
          _paymentReference,
          ar ? 'المرجع' : 'Reference',
          Icons.receipt_outlined,
        ),
        _gap(),
        _field(
          _paymentDue,
          ar ? 'تاريخ الاستحقاق YYYY-MM-DD *' : 'Due date YYYY-MM-DD *',
          Icons.event_note_outlined,
        ),
        _gap(),
        _field(
          _paymentExpected,
          ar ? 'التاريخ المتوقع YYYY-MM-DD' : 'Expected date YYYY-MM-DD',
          Icons.schedule_outlined,
        ),
        _gap(),
        _field(
          _paymentAmount,
          ar ? 'المبلغ الأصلي *' : 'Original amount *',
          Icons.account_balance_wallet_outlined,
          keyboard: const TextInputType.numberWithOptions(decimal: true),
        ),
        const SizedBox(height: 18),
        _saveButton(
          Icons.add_card_rounded,
          ar ? 'إضافة الاستحقاق' : 'Add obligation',
          _savePayment,
        ),
      ],
    );
  }

  Widget _field(
    TextEditingController controller,
    String label,
    IconData icon, {
    TextInputType? keyboard,
  }) =>
      TextField(
        controller: controller,
        keyboardType: keyboard,
        decoration: InputDecoration(labelText: label, prefixIcon: Icon(icon)),
      );
  Widget _gap() => const SizedBox(height: 14);
  Widget _saveButton(
    IconData icon,
    String label,
    Future<void> Function() action,
  ) =>
      FilledButton.icon(
        onPressed: _saving ? null : () => unawaited(action()),
        icon: _saving
            ? const SizedBox.square(
                dimension: 18,
                child: CircularProgressIndicator(strokeWidth: 2),
              )
            : Icon(icon),
        label: Text(
          _saving
              ? (context.scL10n.isArabic ? 'جارٍ الحفظ…' : 'Saving…')
              : label,
        ),
      );

  Future<void> _saveCustomer() async {
    final name = _name.text.trim();
    if (name.isEmpty) {
      return _message(
        context.scL10n.isArabic
            ? 'اسم العميل مطلوب.'
            : 'Customer name is required.',
      );
    }
    if (!_validEmail(_email.text)) {
      return _message(
        context.scL10n.isArabic
            ? 'البريد الإلكتروني غير صحيح.'
            : 'Email is invalid.',
      );
    }
    await _runSave(() async {
      await widget.client.post(
        'mobile/customers/create',
        body: <String, Object?>{
          'name': name,
          'internal_code': _code.text.trim(),
          'contact_name': _contact.text.trim(),
          'email': _email.text.trim(),
          'phone': _phone.text.trim(),
          'is_active': _customerActive,
        },
      );
      if (mounted) Navigator.of(context).pop(true);
    });
  }

  Future<void> _saveSupplier() async {
    final name = _name.text.trim();
    final currency = _currency.text.trim().toUpperCase();
    if (name.isEmpty) {
      return _message(
        context.scL10n.isArabic
            ? 'الاسم القانوني مطلوب.'
            : 'Legal name is required.',
      );
    }
    if (!_validEmail(_email.text)) {
      return _message(
        context.scL10n.isArabic
            ? 'البريد الإلكتروني غير صحيح.'
            : 'Email is invalid.',
      );
    }
    if (currency.isNotEmpty && !RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
      return _message(
        context.scL10n.isArabic
            ? 'العملة يجب أن تكون 3 أحرف.'
            : 'Currency must be a 3-letter code.',
      );
    }
    await _runSave(() async {
      await SuppliersRepository(widget.client).create(
        SupplierDraft(
          legalName: name,
          tradingName: _tradingName.text,
          internalCode: _code.text,
          contactName: _contact.text,
          email: _email.text,
          phone: _phone.text,
          defaultCurrency: currency,
          paymentTerms: _paymentTerms.text,
        ),
      );
      if (mounted) Navigator.of(context).pop(true);
    });
  }

  Future<void> _saveContract() async {
    final ar = context.scL10n.isArabic;
    final number = _contractNumber.text.trim();
    final currency = _currency.text.trim().toUpperCase();
    if (number.isEmpty || _counterpartyId == null) {
      return _message(
        ar
            ? 'رقم العقد وجهة التعاقد مطلوبان.'
            : 'Contract number and counterparty are required.',
      );
    }
    if (!RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
      return _message(
        ar
            ? 'عملة العقد مطلوبة بصيغة 3 أحرف.'
            : 'Contract currency must be a 3-letter code.',
      );
    }
    final start = _contractStart.text.trim();
    final end = _contractEnd.text.trim();
    final base = _contractBase.text.trim();
    if (_canEditContracts &&
        (!_validNullableDate(start) || !_validNullableDate(end))) {
      return _message(
        ar ? 'تواريخ العقد غير صحيحة.' : 'Contract dates are invalid.',
      );
    }
    if (start.isNotEmpty && end.isNotEmpty && start.compareTo(end) > 0) {
      return _message(
        ar
            ? 'تاريخ النهاية يسبق البداية.'
            : 'End date cannot precede start date.',
      );
    }
    if (_canEditContracts &&
        base.isNotEmpty &&
        !_validMoney(base, allowZero: true)) {
      return _message(
        ar ? 'قيمة العقد غير صحيحة.' : 'Contract value is invalid.',
      );
    }
    await _runSave(() async {
      await widget.client.post(
        'contracts/create',
        body: <String, Object?>{
          'contract_number': number,
          'counterparty_type': _counterpartyType,
          'counterparty_id': _counterpartyId,
          'currency_code': currency,
          if (_canAssign) 'accountant_user_id': _contractAccountantId,
          if (_canEditContracts) 'start_date': start.isEmpty ? null : start,
          if (_canEditContracts) 'end_date': end.isEmpty ? null : end,
          if (_canEditContracts && base.isNotEmpty) 'base_value': base,
        },
      );
      if (mounted) Navigator.of(context).pop(true);
    });
  }

  Future<void> _savePayment() async {
    final ar = context.scL10n.isArabic;
    final sequence = int.tryParse(_paymentSequence.text.trim());
    final due = _paymentDue.text.trim();
    final expected = _paymentExpected.text.trim();
    final amount = _paymentAmount.text.trim();
    if (_paymentContractId == null || sequence == null || sequence <= 0) {
      return _message(
        ar
            ? 'العقد والترتيب الموجب مطلوبان.'
            : 'Contract and positive sequence are required.',
      );
    }
    if (!_validRequiredDate(due) || !_validNullableDate(expected)) {
      return _message(
        ar ? 'تواريخ الاستحقاق غير صحيحة.' : 'Obligation dates are invalid.',
      );
    }
    if (!_validMoney(amount, allowZero: false)) {
      return _message(
        ar ? 'المبلغ يجب أن يكون موجبًا.' : 'Amount must be positive.',
      );
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
      if (mounted) _message(context.scL10n.rawMessage(error.message));
    } on Object catch (error) {
      if (mounted) _message(error.toString());
    } finally {
      if (mounted) setState(() => _saving = false);
    }
  }

  void _message(String message) {
    if (mounted) {
      ScaffoldMessenger.of(context)
          .showSnackBar(SnackBar(content: Text(message)));
    }
  }
}

final class _QuickAddHeader extends StatelessWidget {
  const _QuickAddHeader({required this.type});
  final MobileQuickAddType type;
  @override
  Widget build(BuildContext context) => Row(
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: SafeContractsVisual.navySoft,
              borderRadius: BorderRadius.circular(18),
            ),
            child:
                Icon(mobileQuickAddIcon(type), color: SafeContractsVisual.navy),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  mobileQuickAddLabel(context, type),
                  style: Theme.of(context)
                      .textTheme
                      .headlineSmall
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 3),
                Text(
                  mobileQuickAddDescription(context, type),
                  style: Theme.of(context)
                      .textTheme
                      .bodyMedium
                      ?.copyWith(color: SafeContractsVisual.muted),
                ),
              ],
            ),
          ),
        ],
      );
}

final class _EmptyReference extends StatelessWidget {
  const _EmptyReference({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 28),
        child: Column(
          children: [
            const Icon(Icons.folder_off_outlined, size: 42),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
          ],
        ),
      );
}

final class _LoadError extends StatelessWidget {
  const _LoadError({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function({int customerPage}) onRetry;
  @override
  Widget build(BuildContext context) => Center(
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
                label:
                    Text(context.scL10n.isArabic ? 'إعادة المحاولة' : 'Retry'),
              ),
            ],
          ),
        ),
      );
}

final class _AccountantOption {
  const _AccountantOption({required this.id, required this.name, this.email});
  final int id;
  final String name;
  final String? email;
  String get label => email == null || email!.isEmpty ? name : '$name <$email>';
  factory _AccountantOption.fromData(Object? value) {
    final data = apiObjectMap(value, 'accountant');
    final id =
        data['id'] is int ? data['id'] as int : int.tryParse('${data['id']}');
    if (id == null || id <= 0) {
      throw const FormatException('Accountant ID is invalid.');
    }
    final name = data['name'];
    if (name is! String || name.trim().isEmpty) {
      throw const FormatException('Accountant name is invalid.');
    }
    final email = data['email'];
    return _AccountantOption(
      id: id,
      name: name.trim(),
      email: email is String && email.trim().isNotEmpty ? email.trim() : null,
    );
  }
}

bool _validEmail(String value) =>
    value.trim().isEmpty ||
    RegExp(r'^[^@\s]+@[^@\s]+\.[^@\s]+$').hasMatch(value.trim());
bool _validNullableDate(String value) =>
    value.trim().isEmpty || _validRequiredDate(value);
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
  return parsed != null && (allowZero ? parsed >= 0 : parsed > 0);
}
