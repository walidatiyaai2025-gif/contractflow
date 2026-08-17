import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_design.dart';
import 'suppliers.dart';

final class SuppliersScreen extends StatefulWidget {
  const SuppliersScreen({required this.controller, super.key});

  final SuppliersController controller;

  @override
  State<SuppliersScreen> createState() => _SuppliersScreenState();
}

final class _SuppliersScreenState extends State<SuppliersScreen> {
  final _searchController = TextEditingController();

  @override
  void initState() {
    super.initState();
    _searchController.text = widget.controller.searchQuery;
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  void didUpdateWidget(SuppliersScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      _searchController.text = widget.controller.searchQuery;
      unawaited(widget.controller.ensureLoaded());
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) => LayoutBuilder(
        builder: (context, constraints) {
          final wide = constraints.maxWidth >= 900;
          return Column(
            children: [
              _SupplierToolbar(
                controller: widget.controller,
                searchController: _searchController,
                onCreate: widget.controller.canCreate
                    ? () => unawaited(_openEditor())
                    : null,
              ),
              const Divider(height: 1),
              Expanded(
                child: _SupplierContent(
                  controller: widget.controller,
                  wide: wide,
                  onEdit: (supplier) => unawaited(_openEditor(supplier)),
                  onArchive: (supplier) => unawaited(_archive(supplier)),
                ),
              ),
            ],
          );
        },
      ),
    );
  }

  Future<void> _openEditor([SafeContractsSupplier? supplier]) async {
    final draft = await showModalBottomSheet<SupplierDraft>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      showDragHandle: true,
      backgroundColor: SafeContractsVisual.surface,
      builder: (context) => _SupplierEditor(supplier: supplier),
    );
    if (!mounted || draft == null) return;
    try {
      await widget.controller.save(id: supplier?.id, draft: draft);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.scL10n.isArabic
                ? 'تم حفظ المورد بنجاح.'
                : 'Supplier saved successfully.',
          ),
        ),
      );
    } on Object catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.scL10n.rawMessage(error.toString()))),
      );
    }
  }

  Future<void> _archive(SafeContractsSupplier supplier) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(
          context.scL10n.isArabic ? 'أرشفة المورد' : 'Archive supplier',
        ),
        content: Text(
          context.scL10n.isArabic
              ? 'سيتم إيقاف استخدام المورد في العمليات الجديدة مع الاحتفاظ بالعقود والسجل المالي السابق.'
              : 'The supplier will be removed from new operations while existing contracts and finance history remain preserved.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: Text(context.scL10n.t('Cancel')),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: Text(context.scL10n.isArabic ? 'أرشفة' : 'Archive'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;
    try {
      await widget.controller.archiveSelected();
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.scL10n.isArabic
                ? 'تمت أرشفة المورد مع الاحتفاظ بالسجل التاريخي.'
                : 'Supplier archived; historical records were preserved.',
          ),
        ),
      );
    } on Object catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(context.scL10n.rawMessage(error.toString()))),
      );
    }
  }
}

final class _SupplierToolbar extends StatelessWidget {
  const _SupplierToolbar({
    required this.controller,
    required this.searchController,
    required this.onCreate,
  });

  final SuppliersController controller;
  final TextEditingController searchController;
  final VoidCallback? onCreate;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final busy =
        controller.state == SuppliersLoadState.loading ||
        controller.mutationInFlight;
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 10,
        runSpacing: 10,
        children: [
          Text(
            l10n.isArabic ? 'الموردون' : 'Suppliers',
            style: Theme.of(context).textTheme.titleLarge,
          ),
          SizedBox(
            width: 280,
            child: SearchBar(
              controller: searchController,
              enabled: !busy,
              hintText: l10n.isArabic
                  ? 'الاسم، الكود، التسجيل أو الرقم الضريبي'
                  : 'Name, code, registration or tax number',
              leading: const Icon(Icons.search_rounded),
              trailing: [
                if (searchController.text.isNotEmpty)
                  IconButton(
                    tooltip: l10n.t('Clear'),
                    onPressed: busy
                        ? null
                        : () {
                            searchController.clear();
                            unawaited(controller.setSearch(''));
                          },
                    icon: const Icon(Icons.close_rounded),
                  ),
              ],
              onSubmitted: busy
                  ? null
                  : (value) => unawaited(controller.setSearch(value)),
            ),
          ),
          if (controller.canArchive)
            FilterChip(
              label: Text(l10n.isArabic ? 'إظهار المؤرشف' : 'Show archived'),
              selected: controller.includeArchived,
              onSelected: busy
                  ? null
                  : (value) => unawaited(controller.setIncludeArchived(value)),
            ),
          IconButton(
            tooltip: l10n.t('Refresh'),
            onPressed: busy ? null : () => unawaited(controller.refresh()),
            icon: const Icon(Icons.refresh_rounded),
          ),
          if (onCreate != null)
            FilledButton.icon(
              onPressed: busy ? null : onCreate,
              icon: const Icon(Icons.add_business_rounded),
              label: Text(l10n.isArabic ? 'مورد جديد' : 'New supplier'),
            ),
        ],
      ),
    );
  }
}

final class _SupplierContent extends StatelessWidget {
  const _SupplierContent({
    required this.controller,
    required this.wide,
    required this.onEdit,
    required this.onArchive,
  });

  final SuppliersController controller;
  final bool wide;
  final ValueChanged<SafeContractsSupplier> onEdit;
  final ValueChanged<SafeContractsSupplier> onArchive;

  @override
  Widget build(BuildContext context) {
    if (controller.state == SuppliersLoadState.loading &&
        controller.suppliers.isEmpty) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.state == SuppliersLoadState.error &&
        controller.suppliers.isEmpty) {
      return _SupplierError(
        message: context.scL10n.rawMessage(
          controller.errorMessage ?? 'Unable to load suppliers.',
        ),
        onRetry: () => unawaited(controller.refresh()),
      );
    }

    final list = _SupplierList(controller: controller);
    final detail = _SupplierDetail(
      controller: controller,
      showBack: !wide,
      onEdit: onEdit,
      onArchive: onArchive,
    );
    if (wide) {
      return Row(
        children: [
          Expanded(flex: 3, child: list),
          const VerticalDivider(width: 1),
          Expanded(flex: 2, child: detail),
        ],
      );
    }
    return controller.selectedSupplierId == null ? list : detail;
  }
}

final class _SupplierList extends StatelessWidget {
  const _SupplierList({required this.controller});

  final SuppliersController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (controller.suppliers.isEmpty) {
      return RefreshIndicator(
        onRefresh: controller.refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: [
            const SizedBox(height: 120),
            const Icon(Icons.inventory_2_outlined, size: 52),
            const SizedBox(height: 14),
            Center(
              child: Text(
                l10n.isArabic
                    ? 'لا يوجد موردون مطابقون للصلاحيات والبحث الحالي.'
                    : 'No suppliers match your authorized scope and search.',
              ),
            ),
          ],
        ),
      );
    }
    return Column(
      children: [
        if (controller.state == SuppliersLoadState.loading ||
            controller.mutationInFlight)
          const LinearProgressIndicator(),
        if (controller.state == SuppliersLoadState.error)
          MaterialBanner(
            content: Text(
              l10n.rawMessage(controller.errorMessage ?? 'Refresh failed.'),
            ),
            actions: const [SizedBox.shrink()],
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: controller.suppliers.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final supplier = controller.suppliers[index];
                final subtitle = <String>[
                  if (supplier.internalCode != null) supplier.internalCode!,
                  if (supplier.defaultCurrency != null)
                    supplier.defaultCurrency!,
                  if (supplier.paymentTerms != null) supplier.paymentTerms!,
                ].join(' • ');
                return ListTile(
                  selected: controller.selectedSupplierId == supplier.id,
                  leading: CircleAvatar(
                    backgroundColor: supplier.isArchived
                        ? SafeContractsVisual.redSoft
                        : SafeContractsVisual.navySoft,
                    child: Icon(
                      supplier.isArchived
                          ? Icons.inventory_2_outlined
                          : Icons.local_shipping_outlined,
                      color: supplier.isArchived
                          ? SafeContractsVisual.red
                          : SafeContractsVisual.navy,
                    ),
                  ),
                  title: Text(supplier.displayName),
                  subtitle: subtitle.isEmpty ? null : Text(subtitle),
                  trailing: _SupplierStatusChip(supplier: supplier),
                  onTap: () => unawaited(controller.openSupplier(supplier.id)),
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}

final class _SupplierDetail extends StatelessWidget {
  const _SupplierDetail({
    required this.controller,
    required this.showBack,
    required this.onEdit,
    required this.onArchive,
  });

  final SuppliersController controller;
  final bool showBack;
  final ValueChanged<SafeContractsSupplier> onEdit;
  final ValueChanged<SafeContractsSupplier> onArchive;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final id = controller.selectedSupplierId;
    if (id == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            l10n.isArabic
                ? 'اختر مورداً لعرض البيانات المصرح بها.'
                : 'Select a supplier to view authorized details.',
          ),
        ),
      );
    }
    if (controller.detailState == SupplierDetailLoadState.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.detailState == SupplierDetailLoadState.error) {
      return _SupplierError(
        message: l10n.rawMessage(
          controller.detailErrorMessage ?? 'Unable to load supplier.',
        ),
        onRetry: () => unawaited(controller.openSupplier(id)),
      );
    }
    final supplier = controller.selectedSupplier;
    if (supplier == null) return const SizedBox.shrink();

    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        if (showBack)
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              onPressed: controller.closeSupplier,
              icon: const Icon(Icons.arrow_back_rounded),
              label: Text(l10n.isArabic ? 'الموردون' : 'Suppliers'),
            ),
          ),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    supplier.legalName,
                    style: Theme.of(context).textTheme.headlineSmall
                        ?.copyWith(fontWeight: FontWeight.w800),
                  ),
                  if (supplier.tradingName != null) ...[
                    const SizedBox(height: 4),
                    Text(supplier.tradingName!),
                  ],
                ],
              ),
            ),
            _SupplierStatusChip(supplier: supplier),
          ],
        ),
        const SizedBox(height: 20),
        SafeContractsSurface(
          padding: const EdgeInsets.all(16),
          child: Column(
            children: [
              _SupplierField(label: 'ID', value: '${supplier.id}'),
              _SupplierField(
                label: l10n.isArabic ? 'الكود الداخلي' : 'Internal code',
                value: supplier.internalCode ?? '—',
              ),
              _SupplierField(
                label: l10n.isArabic ? 'جهة الاتصال' : 'Contact',
                value: supplier.contactName ?? '—',
              ),
              _SupplierField(
                label: l10n.t('Email'),
                value: supplier.email ?? '—',
              ),
              _SupplierField(
                label: l10n.t('Phone'),
                value: supplier.phone ?? '—',
              ),
              _SupplierField(
                label: l10n.isArabic ? 'الدولة' : 'Country',
                value: supplier.countryCode ?? '—',
              ),
              _SupplierField(
                label: l10n.isArabic ? 'رقم التسجيل' : 'Registration',
                value: supplier.registrationNumber ?? '—',
              ),
              _SupplierField(
                label: l10n.isArabic ? 'الرقم الضريبي' : 'Tax / VAT',
                value: supplier.taxNumber ?? '—',
              ),
              _SupplierField(
                label: l10n.isArabic ? 'العملة الافتراضية' : 'Default currency',
                value: supplier.defaultCurrency ?? '—',
              ),
              _SupplierField(
                label: l10n.isArabic ? 'شروط السداد' : 'Payment terms',
                value: supplier.paymentTerms ?? '—',
              ),
            ],
          ),
        ),
        if (supplier.address != null) ...[
          const SizedBox(height: 14),
          _SupplierLongField(
            label: l10n.isArabic ? 'العنوان' : 'Address',
            value: supplier.address!,
          ),
        ],
        if (supplier.notes != null) ...[
          const SizedBox(height: 14),
          _SupplierLongField(
            label: l10n.isArabic ? 'ملاحظات' : 'Notes',
            value: supplier.notes!,
          ),
        ],
        const SizedBox(height: 20),
        Wrap(
          spacing: 10,
          runSpacing: 10,
          children: [
            if (!supplier.isArchived && controller.canEdit)
              FilledButton.icon(
                onPressed: controller.mutationInFlight
                    ? null
                    : () => onEdit(supplier),
                icon: const Icon(Icons.edit_rounded),
                label: Text(l10n.t('Edit')),
              ),
            if (!supplier.isArchived && controller.canArchive)
              OutlinedButton.icon(
                onPressed: controller.mutationInFlight
                    ? null
                    : () => onArchive(supplier),
                icon: const Icon(Icons.archive_outlined),
                label: Text(l10n.isArabic ? 'أرشفة' : 'Archive'),
              ),
          ],
        ),
        const SizedBox(height: 14),
        Text(
          l10n.isArabic
              ? 'العميل يقرأ ويكتب فقط من خلال API المصرح بها؛ صلاحيات الخادم هي المرجع النهائي.'
              : 'The client reads and writes only through authorized APIs; server permissions remain authoritative.',
          style: Theme.of(context).textTheme.bodySmall
              ?.copyWith(color: SafeContractsVisual.muted),
        ),
      ],
    );
  }
}

final class _SupplierEditor extends StatefulWidget {
  const _SupplierEditor({this.supplier});

  final SafeContractsSupplier? supplier;

  @override
  State<_SupplierEditor> createState() => _SupplierEditorState();
}

final class _SupplierEditorState extends State<_SupplierEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _legalName;
  late final TextEditingController _tradingName;
  late final TextEditingController _internalCode;
  late final TextEditingController _contact;
  late final TextEditingController _phone;
  late final TextEditingController _email;
  late final TextEditingController _address;
  late final TextEditingController _country;
  late final TextEditingController _registration;
  late final TextEditingController _tax;
  late final TextEditingController _currency;
  late final TextEditingController _terms;
  late final TextEditingController _notes;
  late String _status;

  @override
  void initState() {
    super.initState();
    final supplier = widget.supplier;
    _legalName = TextEditingController(text: supplier?.legalName ?? '');
    _tradingName = TextEditingController(text: supplier?.tradingName ?? '');
    _internalCode = TextEditingController(text: supplier?.internalCode ?? '');
    _contact = TextEditingController(text: supplier?.contactName ?? '');
    _phone = TextEditingController(text: supplier?.phone ?? '');
    _email = TextEditingController(text: supplier?.email ?? '');
    _address = TextEditingController(text: supplier?.address ?? '');
    _country = TextEditingController(text: supplier?.countryCode ?? '');
    _registration = TextEditingController(
      text: supplier?.registrationNumber ?? '',
    );
    _tax = TextEditingController(text: supplier?.taxNumber ?? '');
    _currency = TextEditingController(text: supplier?.defaultCurrency ?? '');
    _terms = TextEditingController(text: supplier?.paymentTerms ?? '');
    _notes = TextEditingController(text: supplier?.notes ?? '');
    _status = supplier?.status == 'archived'
        ? 'inactive'
        : supplier?.status ?? 'active';
  }

  @override
  void dispose() {
    for (final controller in <TextEditingController>[
      _legalName,
      _tradingName,
      _internalCode,
      _contact,
      _phone,
      _email,
      _address,
      _country,
      _registration,
      _tax,
      _currency,
      _terms,
      _notes,
    ]) {
      controller.dispose();
    }
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return Padding(
      padding: EdgeInsets.only(
        left: 20,
        right: 20,
        bottom: MediaQuery.viewInsetsOf(context).bottom + 24,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                widget.supplier == null
                    ? (l10n.isArabic ? 'مورد جديد' : 'New supplier')
                    : (l10n.isArabic ? 'تعديل المورد' : 'Edit supplier'),
                style: Theme.of(context).textTheme.headlineSmall
                    ?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 18),
              _field(
                _legalName,
                l10n.isArabic ? 'الاسم القانوني' : 'Legal name',
                required: true,
              ),
              _field(
                _tradingName,
                l10n.isArabic ? 'الاسم التجاري' : 'Trading name',
              ),
              _field(
                _internalCode,
                l10n.isArabic ? 'الكود الداخلي' : 'Internal code',
              ),
              _field(_contact, l10n.isArabic ? 'جهة الاتصال' : 'Contact name'),
              _field(_phone, l10n.t('Phone')),
              _field(_email, l10n.t('Email'), email: true),
              _field(
                _country,
                l10n.isArabic ? 'رمز الدولة' : 'Country code',
                maxLength: 2,
              ),
              _field(
                _registration,
                l10n.isArabic ? 'رقم التسجيل' : 'Registration number',
              ),
              _field(
                _tax,
                l10n.isArabic ? 'الرقم الضريبي' : 'Tax / VAT number',
              ),
              _field(
                _currency,
                l10n.isArabic ? 'العملة الافتراضية' : 'Default currency',
                maxLength: 3,
              ),
              _field(_terms, l10n.isArabic ? 'شروط السداد' : 'Payment terms'),
              _field(_address, l10n.isArabic ? 'العنوان' : 'Address', lines: 3),
              _field(_notes, l10n.isArabic ? 'ملاحظات' : 'Notes', lines: 3),
              DropdownButtonFormField<String>(
                initialValue: _status,
                decoration: InputDecoration(
                  labelText: l10n.t('Status'),
                  border: const OutlineInputBorder(),
                ),
                items: const <DropdownMenuItem<String>>[
                  DropdownMenuItem(value: 'active', child: Text('Active')),
                  DropdownMenuItem(value: 'inactive', child: Text('Inactive')),
                  DropdownMenuItem(
                    value: 'suspended',
                    child: Text('Suspended'),
                  ),
                ],
                onChanged: (value) {
                  if (value != null) setState(() => _status = value);
                },
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: _submit,
                  icon: const Icon(Icons.save_rounded),
                  label: Text(l10n.t('Save')),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _field(
    TextEditingController controller,
    String label, {
    bool required = false,
    bool email = false,
    int lines = 1,
    int? maxLength,
  }) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: TextFormField(
        controller: controller,
        maxLines: lines,
        maxLength: maxLength,
        keyboardType: email ? TextInputType.emailAddress : TextInputType.text,
        decoration: InputDecoration(
          labelText: label,
          border: const OutlineInputBorder(),
        ),
        validator: (value) {
          final text = value?.trim() ?? '';
          if (required && text.isEmpty) return 'Required';
          if (email && text.isNotEmpty && !text.contains('@')) {
            return 'Invalid email';
          }
          return null;
        },
      ),
    );
  }

  void _submit() {
    if (_formKey.currentState?.validate() != true) return;
    Navigator.of(context).pop(
      SupplierDraft(
        legalName: _legalName.text,
        tradingName: _tradingName.text,
        internalCode: _internalCode.text,
        contactName: _contact.text,
        phone: _phone.text,
        email: _email.text,
        address: _address.text,
        countryCode: _country.text,
        registrationNumber: _registration.text,
        taxNumber: _tax.text,
        defaultCurrency: _currency.text,
        paymentTerms: _terms.text,
        status: _status,
        notes: _notes.text,
      ),
    );
  }
}

final class _SupplierStatusChip extends StatelessWidget {
  const _SupplierStatusChip({required this.supplier});

  final SafeContractsSupplier supplier;

  @override
  Widget build(BuildContext context) {
    final label = supplier.isArchived
        ? (context.scL10n.isArabic ? 'مؤرشف' : 'Archived')
        : context.scL10n.status(supplier.status);
    return Chip(
      avatar: Icon(
        supplier.isArchived ? Icons.archive_outlined : Icons.circle,
        size: 14,
      ),
      label: Text(label),
    );
  }
}

final class _SupplierField extends StatelessWidget {
  const _SupplierField({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.symmetric(vertical: 6),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 130,
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: SafeContractsVisual.muted,
              fontWeight: FontWeight.w700,
            ),
          ),
        ),
        Expanded(child: Text(value)),
      ],
    ),
  );
}

final class _SupplierLongField extends StatelessWidget {
  const _SupplierLongField({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
    padding: const EdgeInsets.all(16),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.labelLarge
              ?.copyWith(color: SafeContractsVisual.muted),
        ),
        const SizedBox(height: 6),
        Text(value),
      ],
    ),
  );
}

final class _SupplierError extends StatelessWidget {
  const _SupplierError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.error_outline_rounded, size: 44),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: Text(context.scL10n.t('Retry')),
          ),
        ],
      ),
    ),
  );
}
