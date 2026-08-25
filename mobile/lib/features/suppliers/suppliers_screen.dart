import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../contracts/counterparty_business_snapshot.dart';
import '../contracts/contracts.dart';
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
  String _status = '';
  int _visibleLimit = 30;

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
      _status = '';
      _visibleLimit = 30;
      unawaited(widget.controller.ensureLoaded());
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<SafeContractsSupplier> get _filteredSuppliers {
    if (_status.isEmpty) return widget.controller.suppliers;
    return widget.controller.suppliers
        .where((supplier) => supplier.status == _status)
        .toList(growable: false);
  }

  List<SafeContractsSupplier> get _visibleSuppliers =>
      _filteredSuppliers.take(_visibleLimit).toList(growable: false);

  Future<void> _openEditor([SafeContractsSupplier? supplier]) async {
    final draft = await showModalBottomSheet<SupplierDraft>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
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
    if (widget.controller.selectedSupplierId != supplier.id) {
      await widget.controller.openSupplier(supplier.id);
    }
    if (!mounted) return;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title:
            Text(context.scL10n.isArabic ? 'أرشفة المورد' : 'Archive supplier'),
        content: Text(
          context.scL10n.isArabic
              ? 'سيتم منع استخدام المورد في العمليات الجديدة مع بقاء العقود والسجل المالي محفوظين.'
              : 'The supplier will be unavailable for new operations while contracts and finance history stay preserved.',
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
                ? 'تمت أرشفة المورد.'
                : 'Supplier archived.',
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

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final filtered = _filteredSuppliers;
        final visible = filtered.take(_visibleLimit).toList(growable: false);
        return LayoutBuilder(
          builder: (context, constraints) {
            final split = constraints.maxWidth >= 900;
            final mobileDetail =
                !split && widget.controller.selectedSupplierId != null;
            return SafeContractsBackdrop(
              child: Column(
                children: [
                  if (!mobileDetail)
                    _SupplierHeader(
                      controller: widget.controller,
                      searchController: _searchController,
                      status: _status,
                      visibleCount: visible.length,
                      onStatusChanged: (value) => setState(() {
                        _status = value;
                        _visibleLimit = 30;
                      }),
                      onCreate: widget.controller.canCreate
                          ? () => unawaited(_openEditor())
                          : null,
                    ),
                  Expanded(
                    child: NotificationListener<ScrollNotification>(
                      onNotification: (notification) {
                        if (!mobileDetail &&
                            notification.metrics.extentAfter <= 360 &&
                            _visibleLimit < filtered.length) {
                          setState(() => _visibleLimit =
                              (_visibleLimit + 30).clamp(0, filtered.length));
                        }
                        return false;
                      },
                      child: _SupplierBody(
                        controller: widget.controller,
                        suppliers: visible,
                        split: split,
                        onEdit: (supplier) => unawaited(_openEditor(supplier)),
                        onArchive: (supplier) => unawaited(_archive(supplier)),
                      ),
                    ),
                  ),
                ],
              ),
            );
          },
        );
      },
    );
  }
}

final class _SupplierHeader extends StatelessWidget {
  const _SupplierHeader({
    required this.controller,
    required this.searchController,
    required this.status,
    required this.visibleCount,
    required this.onStatusChanged,
    required this.onCreate,
  });

  final SuppliersController controller;
  final TextEditingController searchController;
  final String status;
  final int visibleCount;
  final ValueChanged<String> onStatusChanged;
  final VoidCallback? onCreate;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final busy = controller.state == SuppliersLoadState.loading ||
        controller.mutationInFlight;
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 8),
      child: Column(
        children: [
          SafeContractsPremiumHeader(
            compact: true,
            leading: Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                gradient: SafeContractsVisual.roseGradient,
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(
                Icons.local_shipping_outlined,
                color: SafeContractsVisual.navyDeep,
              ),
            ),
            title: ar ? 'الموردون' : 'Suppliers',
            subtitle: ar
                ? 'العقود والمستحقات علينا منفصلة بوضوح عن تحصيلات العملاء'
                : 'Payables and supplier contracts remain financially distinct',
            trailing: _CountBadge(value: '$visibleCount'),
          ),
          const SizedBox(height: 10),
          SafeContractsSurface(
            elevated: false,
            padding: const EdgeInsets.all(11),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                SearchBar(
                  controller: searchController,
                  enabled: !controller.mutationInFlight,
                  leading: const Icon(Icons.search_rounded),
                  hintText: ar
                      ? 'بحث بالاسم أو الكود أو التسجيل أو الرقم الضريبي'
                      : 'Search name, code, registration or tax number',
                  onSubmitted: busy
                      ? null
                      : (value) => unawaited(controller.setSearch(value)),
                  trailing: [
                    if (controller.searchQuery.isNotEmpty)
                      IconButton(
                        onPressed: busy
                            ? null
                            : () {
                                searchController.clear();
                                unawaited(controller.setSearch(''));
                              },
                        icon: const Icon(Icons.close_rounded),
                      ),
                  ],
                ),
                const SizedBox(height: 9),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    _FilterChip(
                      label: ar ? 'الكل' : 'All',
                      selected: status.isEmpty,
                      onTap: () => onStatusChanged(''),
                    ),
                    for (final item in const [
                      'active',
                      'inactive',
                      'suspended'
                    ])
                      _FilterChip(
                        label: context.scL10n.status(item),
                        selected: status == item,
                        onTap: () => onStatusChanged(item),
                      ),
                    if (controller.canArchive)
                      FilterChip(
                        label: Text(ar ? 'إظهار المؤرشف' : 'Show archived'),
                        selected: controller.includeArchived,
                        showCheckmark: false,
                        onSelected: busy
                            ? null
                            : (value) => unawaited(
                                  controller.setIncludeArchived(value),
                                ),
                      ),
                    IconButton.filledTonal(
                      tooltip: context.scL10n.t('Refresh'),
                      onPressed:
                          busy ? null : () => unawaited(controller.refresh()),
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                    if (onCreate != null)
                      FilledButton.icon(
                        onPressed: busy ? null : onCreate,
                        icon: const Icon(Icons.add_business_rounded),
                        label: Text(ar ? 'مورد جديد' : 'New supplier'),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _SupplierBody extends StatelessWidget {
  const _SupplierBody({
    required this.controller,
    required this.suppliers,
    required this.split,
    required this.onEdit,
    required this.onArchive,
  });
  final SuppliersController controller;
  final List<SafeContractsSupplier> suppliers;
  final bool split;
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
      return _StateMessage(
        icon: Icons.cloud_off_rounded,
        message: context.scL10n.rawMessage(
          controller.errorMessage ?? 'Unable to load suppliers.',
        ),
        action: controller.refresh,
      );
    }
    final list = _SupplierList(controller: controller, suppliers: suppliers);
    final detail = _SupplierDetail(
      controller: controller,
      onEdit: onEdit,
      onArchive: onArchive,
    );
    if (split) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Expanded(flex: 5, child: list),
            const SizedBox(width: 10),
            Expanded(flex: 4, child: detail),
          ],
        ),
      );
    }
    if (controller.selectedSupplierId != null) return detail;
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
      child: list,
    );
  }
}

final class _SupplierList extends StatelessWidget {
  const _SupplierList({required this.controller, required this.suppliers});
  final SuppliersController controller;
  final List<SafeContractsSupplier> suppliers;

  @override
  Widget build(BuildContext context) {
    if (suppliers.isEmpty) {
      return _StateMessage(
        icon: Icons.inventory_2_outlined,
        message: context.scL10n.isArabic
            ? 'لا يوجد موردون مطابقون للبحث والفلاتر الحالية.'
            : 'No suppliers match the current search and filters.',
        action: controller.refresh,
      );
    }
    return Column(
      children: [
        if (controller.state == SuppliersLoadState.loading ||
            controller.mutationInFlight)
          const LinearProgressIndicator(minHeight: 2),
        Expanded(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(top: 2, bottom: 8),
              itemCount: suppliers.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final supplier = suppliers[index];
                return _SupplierCard(
                  supplier: supplier,
                  selected: controller.selectedSupplierId == supplier.id,
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

final class _SupplierCard extends StatelessWidget {
  const _SupplierCard({
    required this.supplier,
    required this.selected,
    required this.onTap,
  });
  final SafeContractsSupplier supplier;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = supplier.isArchived
        ? SafeContractsVisual.red
        : safeContractsStatusColor(supplier.status);
    final metadata = <String>[
      if (supplier.internalCode != null) supplier.internalCode!,
      if (supplier.defaultCurrency != null) supplier.defaultCurrency!,
      if (supplier.paymentTerms != null) supplier.paymentTerms!,
    ].join(' • ');
    return Material(
      color: SafeContractsVisual.surface,
      borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            borderRadius:
                BorderRadius.circular(SafeContractsVisual.compactRadius),
            border: Border.all(
              color: selected
                  ? SafeContractsVisual.roseGold
                  : SafeContractsVisual.outline,
              width: selected ? 1.5 : 1,
            ),
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: supplier.isArchived
                      ? SafeContractsVisual.redSoft
                      : SafeContractsVisual.navySoft,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  supplier.isArchived
                      ? Icons.inventory_2_outlined
                      : Icons.local_shipping_outlined,
                  color: supplier.isArchived
                      ? SafeContractsVisual.redDeep
                      : SafeContractsVisual.navy,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      supplier.displayName,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    if (metadata.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        metadata,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: SafeContractsVisual.muted,
                            ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: 7),
              _StatusBadge(
                label: supplier.isArchived
                    ? context.scL10n.status('archived')
                    : context.scL10n.status(supplier.status),
                color: accent,
              ),
              const SizedBox(width: 3),
              const Icon(
                Icons.chevron_right_rounded,
                color: SafeContractsVisual.muted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _SupplierDetail extends StatefulWidget {
  const _SupplierDetail({
    required this.controller,
    required this.onEdit,
    required this.onArchive,
  });
  final SuppliersController controller;
  final ValueChanged<SafeContractsSupplier> onEdit;
  final ValueChanged<SafeContractsSupplier> onArchive;

  @override
  State<_SupplierDetail> createState() => _SupplierDetailState();
}

final class _SupplierDetailState extends State<_SupplierDetail> {
  int? _loadedId;
  Future<CounterpartyBusinessSnapshot>? _snapshot;

  void _ensureSnapshot(SafeContractsSupplier supplier) {
    if (_loadedId == supplier.id && _snapshot != null) return;
    _loadedId = supplier.id;
    _snapshot = CounterpartyBusinessSnapshotRepository(
      widget.controller.repository.client,
    ).load(counterpartyType: 'supplier', counterpartyId: supplier.id);
  }

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final id = controller.selectedSupplierId;
    if (id == null) {
      return _StateMessage(
        icon: Icons.touch_app_outlined,
        message: context.scL10n.isArabic
            ? 'اختر مورداً لعرض بياناته المصرح بها.'
            : 'Select a supplier to view authorized details.',
      );
    }
    if (controller.detailState == SupplierDetailLoadState.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.detailState == SupplierDetailLoadState.error) {
      return _StateMessage(
        icon: Icons.error_outline_rounded,
        message: context.scL10n.rawMessage(
          controller.detailErrorMessage ?? 'Unable to load supplier.',
        ),
        action: () => controller.openSupplier(id),
      );
    }
    final supplier = controller.selectedSupplier;
    if (supplier == null) return const SizedBox.shrink();
    _ensureSnapshot(supplier);
    return SafeContractsSurface(
      margin: const EdgeInsets.fromLTRB(14, 0, 14, 12),
      padding: EdgeInsets.zero,
      child: ListView(
        padding: const EdgeInsets.all(15),
        children: [
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              onPressed: controller.closeSupplier,
              icon: const Icon(
                Icons.arrow_back_rounded,
              ),
              label: Text(context.scL10n.isArabic ? 'الموردون' : 'Suppliers'),
            ),
          ),
          _SupplierHero(supplier: supplier),
          const SizedBox(height: 12),
          _SupplierContactPanel(supplier: supplier),
          const SizedBox(height: 10),
          _SupplierBusinessIdentity(supplier: supplier),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              if (!supplier.isArchived && controller.canEdit)
                FilledButton.icon(
                  onPressed: controller.mutationInFlight
                      ? null
                      : () => widget.onEdit(supplier),
                  icon: const Icon(Icons.edit_outlined),
                  label: Text(context.scL10n.t('Edit')),
                ),
              if (!supplier.isArchived && controller.canArchive)
                OutlinedButton.icon(
                  onPressed: controller.mutationInFlight
                      ? null
                      : () => widget.onArchive(supplier),
                  icon: const Icon(Icons.archive_outlined),
                  label: Text(context.scL10n.isArabic ? 'أرشفة' : 'Archive'),
                ),
            ],
          ),
          const SizedBox(height: 16),
          FutureBuilder<CounterpartyBusinessSnapshot>(
            future: _snapshot,
            builder: (context, snapshot) {
              if (snapshot.connectionState != ConnectionState.done) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 28),
                  child: Center(child: CircularProgressIndicator()),
                );
              }
              if (snapshot.hasError || snapshot.data == null) {
                return _InlineNotice(
                  text: context.scL10n.isArabic
                      ? 'تعذر تحميل عقود ودفعات المورد.'
                      : 'Unable to load supplier contracts and payables.',
                );
              }
              return _SupplierBusinessSnapshot(value: snapshot.data!);
            },
          ),
        ],
      ),
    );
  }
}

final class _SupplierHero extends StatelessWidget {
  const _SupplierHero({required this.supplier});
  final SafeContractsSupplier supplier;

  @override
  Widget build(BuildContext context) {
    final accent = supplier.isArchived
        ? SafeContractsVisual.red
        : safeContractsStatusColor(supplier.status);
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 54,
                height: 54,
                decoration: BoxDecoration(
                  gradient: SafeContractsVisual.roseGradient,
                  borderRadius: BorderRadius.circular(16),
                ),
                child: const Icon(
                  Icons.local_shipping_rounded,
                  color: SafeContractsVisual.navyDeep,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      supplier.legalName,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    if (supplier.tradingName != null)
                      Text(
                        supplier.tradingName!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.72)),
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
              _DarkPill(
                label: supplier.isArchived
                    ? context.scL10n.status('archived')
                    : context.scL10n.status(supplier.status),
                color: accent,
              ),
              if (supplier.defaultCurrency != null)
                _DarkPill(
                    label: supplier.defaultCurrency!,
                    color: SafeContractsVisual.champagne),
              if (supplier.internalCode != null)
                _DarkPill(label: supplier.internalCode!),
            ],
          ),
        ],
      ),
    );
  }
}

final class _SupplierContactPanel extends StatelessWidget {
  const _SupplierContactPanel({required this.supplier});
  final SafeContractsSupplier supplier;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return SafeContractsSurface(
      elevated: false,
      padding: const EdgeInsets.all(12),
      child: Column(
        children: [
          _ContactRow(
              icon: Icons.person_outline,
              label: ar ? 'جهة الاتصال' : 'Contact',
              value: supplier.contactName),
          _ContactRow(
              icon: Icons.phone_outlined,
              label: ar ? 'الهاتف' : 'Phone',
              value: supplier.phone),
          _ContactRow(
              icon: Icons.email_outlined,
              label: ar ? 'البريد' : 'Email',
              value: supplier.email),
          _ContactRow(
              icon: Icons.location_on_outlined,
              label: ar ? 'العنوان' : 'Address',
              value: supplier.address),
        ],
      ),
    );
  }
}

final class _SupplierBusinessIdentity extends StatelessWidget {
  const _SupplierBusinessIdentity({required this.supplier});
  final SafeContractsSupplier supplier;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final entries = <({String label, String? value})>[
      (
        label: ar ? 'رقم التسجيل' : 'Registration',
        value: supplier.registrationNumber
      ),
      (label: ar ? 'الرقم الضريبي' : 'Tax / VAT', value: supplier.taxNumber),
      (label: ar ? 'الدولة' : 'Country', value: supplier.countryCode),
      (
        label: ar ? 'شروط السداد' : 'Payment terms',
        value: supplier.paymentTerms
      ),
    ];
    return SafeContractsSurface(
      elevated: false,
      padding: const EdgeInsets.all(12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Wrap(
            spacing: 18,
            runSpacing: 10,
            children: entries
                .map(
                  (entry) => _Metric(
                    label: entry.label,
                    value: entry.value?.trim().isNotEmpty == true
                        ? entry.value!
                        : '—',
                  ),
                )
                .toList(growable: false),
          ),
          if (supplier.notes != null) ...[
            const SizedBox(height: 12),
            Text(ar ? 'ملاحظات' : 'Notes',
                style: const TextStyle(fontWeight: FontWeight.w800)),
            const SizedBox(height: 4),
            Text(supplier.notes!),
          ],
        ],
      ),
    );
  }
}

final class _SupplierBusinessSnapshot extends StatelessWidget {
  const _SupplierBusinessSnapshot({required this.value});
  final CounterpartyBusinessSnapshot value;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final pending = value.payments
        .where((payment) => payment.status != 'paid')
        .take(6)
        .toList(growable: false);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _SectionLabel(title: ar ? 'ملخص المستحقات علينا' : 'Payable summary'),
        if (!value.financeAuthorized)
          _InlineNotice(
            text: ar
                ? 'ملخص المالية غير متاح ضمن صلاحيات الجلسة.'
                : 'Finance summary is outside this session’s permissions.',
          )
        else if (value.finance.isEmpty)
          _InlineNotice(
              text: ar ? 'لا توجد التزامات حالية.' : 'No current payables.')
        else
          ...value.finance.map((row) => _PayableCard(row: row)),
        const SizedBox(height: 14),
        _SectionLabel(
            title: ar
                ? 'عقود المورد (${value.contracts.length})'
                : 'Supplier contracts (${value.contracts.length})'),
        if (value.contracts.isEmpty)
          _InlineNotice(text: ar ? 'لا توجد عقود.' : 'No contracts.')
        else
          ...value.contracts
              .take(5)
              .map((contract) => _MiniContract(contract: contract)),
        const SizedBox(height: 14),
        _SectionLabel(title: ar ? 'الدفعات القادمة' : 'Upcoming payments'),
        if (pending.isEmpty)
          _InlineNotice(
              text: ar ? 'لا توجد دفعات معلقة.' : 'No pending payments.')
        else
          ...pending.map((payment) => _PaymentLine(payment: payment)),
        const SizedBox(height: 14),
        _SectionLabel(
            title: ar ? 'آخر عمليات السداد' : 'Recent payment activity'),
        if (value.activity.isEmpty)
          _InlineNotice(
              text:
                  ar ? 'لا توجد عمليات سداد مسجلة.' : 'No settlement activity.')
        else
          ...value.activity
              .take(5)
              .map((activity) => _ActivityLine(activity: activity)),
      ],
    );
  }
}

final class _PayableCard extends StatelessWidget {
  const _PayableCard({required this.row});
  final dynamic row;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: SafeContractsSurface(
        elevated: false,
        accent: SafeContractsVisual.amber,
        padding: const EdgeInsets.all(12),
        child: Wrap(
          spacing: 16,
          runSpacing: 8,
          children: [
            _Metric(
                label: ar ? 'العملة' : 'Currency',
                value: row.currencyCode as String),
            _Metric(
                label: ar ? 'المطلوب دفعه' : 'Outstanding',
                value: _money(row.outstandingTotal as String,
                    row.currencyCode as String)),
            _Metric(
                label: ar ? 'المتأخر' : 'Overdue',
                value: _money(
                    row.overdueTotal as String, row.currencyCode as String)),
            _Metric(
                label: ar ? 'الدفعات' : 'Obligations',
                value: '${row.obligationCount}'),
          ],
        ),
      ),
    );
  }
}

final class _MiniContract extends StatelessWidget {
  const _MiniContract({required this.contract});
  final SafeContractsContract contract;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Container(
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(
          color: SafeContractsVisual.backgroundRaised,
          borderRadius: BorderRadius.circular(13),
          border: Border.all(color: SafeContractsVisual.outline),
        ),
        child: Row(
          children: [
            const Icon(Icons.description_outlined,
                color: SafeContractsVisual.navy),
            const SizedBox(width: 9),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(contract.contractNumber,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w900)),
                  Text(
                    <String>[
                      if (contract.endDate != null) contract.endDate!,
                      if (contract.baseValue != null)
                        _money(contract.baseValue!, contract.currencyCode),
                    ].join(' • '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context)
                        .textTheme
                        .bodySmall
                        ?.copyWith(color: SafeContractsVisual.muted),
                  ),
                ],
              ),
            ),
            _StatusBadge(
                label: context.scL10n.status(contract.status),
                color: safeContractsStatusColor(contract.status)),
          ],
        ),
      ),
    );
  }
}

final class _PaymentLine extends StatelessWidget {
  const _PaymentLine({required this.payment});
  final dynamic payment;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 7),
      child: Row(
        children: [
          Container(
            width: 36,
            height: 36,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: safeContractsStatusSoftColor(payment.status as String),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Text('${payment.sequenceNo}',
                style: const TextStyle(fontWeight: FontWeight.w900)),
          ),
          const SizedBox(width: 9),
          Expanded(
              child: Text(payment.dueDate as String,
                  maxLines: 1, overflow: TextOverflow.ellipsis)),
          const SizedBox(width: 8),
          Text(_compactNumber(payment.remainingAmount as String),
              style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(width: 8),
          _StatusBadge(
              label: context.scL10n.status(payment.status as String),
              color: safeContractsStatusColor(payment.status as String)),
        ],
      ),
    );
  }
}

final class _ActivityLine extends StatelessWidget {
  const _ActivityLine({required this.activity});
  final CounterpartyActivity activity;

  @override
  Widget build(BuildContext context) {
    return ListTile(
      dense: true,
      contentPadding: EdgeInsets.zero,
      leading:
          const Icon(Icons.payments_outlined, color: SafeContractsVisual.amber),
      title: Text(_money(activity.amount, activity.currencyCode)),
      subtitle: Text(
        <String>[
          activity.date,
          if (activity.contractNumber != null) activity.contractNumber!,
          if (activity.reference != null) activity.reference!,
        ].join(' • '),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
    );
  }
}

final class _ContactRow extends StatelessWidget {
  const _ContactRow(
      {required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label;
  final String? value;

  @override
  Widget build(BuildContext context) {
    final actual = value?.trim();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        children: [
          Icon(icon, size: 19, color: SafeContractsVisual.navy),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(label,
                    style: Theme.of(context)
                        .textTheme
                        .labelSmall
                        ?.copyWith(color: SafeContractsVisual.muted)),
                Text(actual == null || actual.isEmpty ? '—' : actual,
                    maxLines: 2, overflow: TextOverflow.ellipsis),
              ],
            ),
          ),
          if (actual != null && actual.isNotEmpty)
            IconButton(
              tooltip: context.scL10n.isArabic ? 'نسخ' : 'Copy',
              onPressed: () async {
                await Clipboard.setData(ClipboardData(text: actual));
                if (!context.mounted) return;
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                      content: Text(
                          context.scL10n.isArabic ? 'تم النسخ.' : 'Copied.')),
                );
              },
              icon: const Icon(Icons.copy_rounded, size: 18),
            ),
        ],
      ),
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
  late final Map<String, TextEditingController> _fields;
  late String _status;

  @override
  void initState() {
    super.initState();
    final supplier = widget.supplier;
    _fields = <String, TextEditingController>{
      'legalName': TextEditingController(text: supplier?.legalName ?? ''),
      'tradingName': TextEditingController(text: supplier?.tradingName ?? ''),
      'internalCode': TextEditingController(text: supplier?.internalCode ?? ''),
      'contactName': TextEditingController(text: supplier?.contactName ?? ''),
      'phone': TextEditingController(text: supplier?.phone ?? ''),
      'email': TextEditingController(text: supplier?.email ?? ''),
      'address': TextEditingController(text: supplier?.address ?? ''),
      'countryCode': TextEditingController(text: supplier?.countryCode ?? ''),
      'registrationNumber':
          TextEditingController(text: supplier?.registrationNumber ?? ''),
      'taxNumber': TextEditingController(text: supplier?.taxNumber ?? ''),
      'defaultCurrency':
          TextEditingController(text: supplier?.defaultCurrency ?? ''),
      'paymentTerms': TextEditingController(text: supplier?.paymentTerms ?? ''),
      'notes': TextEditingController(text: supplier?.notes ?? ''),
    };
    _status = supplier?.status ?? 'active';
  }

  @override
  void dispose() {
    for (final controller in _fields.values) {
      controller.dispose();
    }
    super.dispose();
  }

  Widget _field(String key, String label,
      {TextInputType? keyboardType, int maxLines = 1}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: TextFormField(
        controller: _fields[key],
        keyboardType: keyboardType,
        maxLines: maxLines,
        decoration: InputDecoration(labelText: label),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return Padding(
      padding: EdgeInsets.fromLTRB(
          16, 8, 16, MediaQuery.viewInsetsOf(context).bottom + 18),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SafeContractsPremiumHeader(
                compact: true,
                title: widget.supplier == null
                    ? (ar ? 'إضافة مورد' : 'Add supplier')
                    : (ar ? 'تعديل المورد' : 'Edit supplier'),
                subtitle: ar
                    ? 'بيانات المورد الفعلية فقط.'
                    : 'Only supported supplier fields are submitted.',
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _fields['legalName'],
                decoration: InputDecoration(
                    labelText: ar ? 'الاسم القانوني *' : 'Legal name *'),
                validator: (value) => value == null || value.trim().isEmpty
                    ? (ar ? 'الاسم القانوني مطلوب.' : 'Legal name is required.')
                    : null,
              ),
              const SizedBox(height: 10),
              _field('tradingName', ar ? 'الاسم التجاري' : 'Trading name'),
              _field('internalCode', ar ? 'الكود الداخلي' : 'Internal code'),
              _field('contactName', ar ? 'جهة الاتصال' : 'Contact name'),
              _field('phone', ar ? 'الهاتف' : 'Phone',
                  keyboardType: TextInputType.phone),
              _field('email', ar ? 'البريد الإلكتروني' : 'Email',
                  keyboardType: TextInputType.emailAddress),
              _field('address', ar ? 'العنوان' : 'Address', maxLines: 2),
              _field('countryCode',
                  ar ? 'كود الدولة (حرفان)' : 'Country code (2 letters)'),
              _field('registrationNumber',
                  ar ? 'رقم التسجيل' : 'Registration number'),
              _field('taxNumber', ar ? 'الرقم الضريبي' : 'Tax number'),
              _field('defaultCurrency',
                  ar ? 'العملة (3 أحرف)' : 'Currency (3 letters)'),
              _field('paymentTerms', ar ? 'شروط السداد' : 'Payment terms'),
              _field('notes', ar ? 'ملاحظات' : 'Notes', maxLines: 3),
              DropdownButtonFormField<String>(
                initialValue: _status,
                decoration:
                    InputDecoration(labelText: ar ? 'الحالة' : 'Status'),
                items: const ['active', 'inactive', 'suspended']
                    .map((value) => DropdownMenuItem(
                        value: value,
                        child: Text(context.scL10n.status(value))))
                    .toList(growable: false),
                onChanged: (value) =>
                    setState(() => _status = value ?? 'active'),
              ),
              const SizedBox(height: 14),
              FilledButton.icon(
                onPressed: () {
                  if (!_formKey.currentState!.validate()) return;
                  Navigator.of(context).pop(
                    SupplierDraft(
                      legalName: _fields['legalName']!.text,
                      tradingName: _fields['tradingName']!.text,
                      internalCode: _fields['internalCode']!.text,
                      contactName: _fields['contactName']!.text,
                      phone: _fields['phone']!.text,
                      email: _fields['email']!.text,
                      address: _fields['address']!.text,
                      countryCode: _fields['countryCode']!.text,
                      registrationNumber: _fields['registrationNumber']!.text,
                      taxNumber: _fields['taxNumber']!.text,
                      defaultCurrency: _fields['defaultCurrency']!.text,
                      paymentTerms: _fields['paymentTerms']!.text,
                      status: _status,
                      notes: _fields['notes']!.text,
                    ),
                  );
                },
                icon: const Icon(Icons.save_outlined),
                label: Text(context.scL10n.t('Save')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _FilterChip extends StatelessWidget {
  const _FilterChip(
      {required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      showCheckmark: false,
      onSelected: (_) => onTap(),
    );
  }
}

final class _CountBadge extends StatelessWidget {
  const _CountBadge({required this.value});
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.11),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: Colors.white.withValues(alpha: 0.16)),
      ),
      child: Text(value,
          style: const TextStyle(
              color: Colors.white, fontWeight: FontWeight.w900)),
    );
  }
}

final class _StatusBadge extends StatelessWidget {
  const _StatusBadge({required this.label, required this.color});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 92),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
          color: color.withValues(alpha: 0.11),
          borderRadius: BorderRadius.circular(20)),
      child: Text(label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
              color: color, fontSize: 11, fontWeight: FontWeight.w800)),
    );
  }
}

final class _DarkPill extends StatelessWidget {
  const _DarkPill({required this.label, this.color = Colors.white});
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 180),
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.14),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: color.withValues(alpha: 0.30)),
      ),
      child: Text(label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
              color: color, fontSize: 12, fontWeight: FontWeight.w800)),
    );
  }
}

final class _SectionLabel extends StatelessWidget {
  const _SectionLabel({required this.title});
  final String title;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: SafeContractsSectionTitle(title: title),
    );
  }
}

final class _Metric extends StatelessWidget {
  const _Metric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minWidth: 92, maxWidth: 190),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label,
              style: Theme.of(context)
                  .textTheme
                  .labelSmall
                  ?.copyWith(color: SafeContractsVisual.muted)),
          const SizedBox(height: 2),
          Text(value,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: const TextStyle(fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

final class _StateMessage extends StatelessWidget {
  const _StateMessage({required this.icon, required this.message, this.action});
  final IconData icon;
  final String message;
  final Future<void> Function()? action;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: SafeContractsVisual.muted),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            if (action != null) ...[
              const SizedBox(height: 12),
              FilledButton.icon(
                onPressed: () => unawaited(action!()),
                icon: const Icon(Icons.refresh_rounded),
                label: Text(context.scL10n.t('Retry')),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

final class _InlineNotice extends StatelessWidget {
  const _InlineNotice({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
          color: SafeContractsVisual.navySoft,
          borderRadius: BorderRadius.circular(12)),
      child: Text(text,
          style: const TextStyle(color: SafeContractsVisual.navyDeep)),
    );
  }
}

String _compactNumber(String raw) {
  final value = raw.trim();
  if (!value.contains('.')) return value;
  final parts = value.split('.');
  final fraction = parts[1].replaceFirst(RegExp(r'0+$'), '');
  return fraction.isEmpty ? parts[0] : '${parts[0]}.$fraction';
}

String _money(String raw, String currency) {
  final value = _compactNumber(raw);
  return currency == 'UNSET' || currency.trim().isEmpty
      ? value
      : '$value $currency';
}
