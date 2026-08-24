import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../contracts/counterparty_business_snapshot.dart';
import '../contracts/contracts.dart';
import '../ui/safecontracts_design.dart';
import 'customers.dart';

final class CustomersScreen extends StatefulWidget {
  const CustomersScreen({required this.controller, super.key});

  final CustomersController controller;

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

final class _CustomersScreenState extends State<CustomersScreen> {
  final _searchController = TextEditingController();
  String _query = '';

  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  void didUpdateWidget(CustomersScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      _searchController.clear();
      _query = '';
      unawaited(widget.controller.ensureLoaded());
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  List<SafeContractsCustomer> _visibleCustomers() {
    final customers = widget.controller.currentPage?.customers ?? const [];
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return customers;
    return customers.where((customer) {
      final values = <String?>[
        customer.name,
        customer.internalCode,
        customer.contactName,
        customer.email,
        customer.phone,
      ].whereType<String>().join(' ').toLowerCase();
      return values.contains(query);
    }).toList(growable: false);
  }

  Future<void> _openEditor([SafeContractsCustomer? customer]) async {
    if (customer == null && !widget.controller.canCreate) return;
    if (customer != null && !widget.controller.canEdit) return;
    final draft = await showModalBottomSheet<CustomerDraft>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      backgroundColor: SafeContractsVisual.surface,
      builder: (context) => _CustomerEditor(customer: customer),
    );
    if (!mounted || draft == null) return;
    try {
      await widget.controller.save(id: customer?.id, draft: draft);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.scL10n.isArabic
                ? 'تم حفظ العميل بنجاح.'
                : 'Customer saved successfully.',
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
        final visible = _visibleCustomers();
        return LayoutBuilder(
          builder: (context, constraints) {
            final split = constraints.maxWidth >= 840;
            final mobileDetail =
                !split && widget.controller.selectedCustomerId != null;
            return SafeContractsBackdrop(
              child: Column(
                children: [
                  if (!mobileDetail)
                    _CustomerHeader(
                      controller: widget.controller,
                      searchController: _searchController,
                      visibleCount: visible.length,
                      query: _query,
                      onQueryChanged: (value) =>
                          setState(() => _query = value.trim()),
                      onClear: () {
                        _searchController.clear();
                        setState(() => _query = '');
                      },
                      onCreate: widget.controller.canCreate
                          ? () => unawaited(_openEditor())
                          : null,
                    ),
                  Expanded(
                    child: _CustomerBody(
                      controller: widget.controller,
                      customers: visible,
                      split: split,
                      hasQuery: _query.isNotEmpty,
                      onEdit: (customer) => unawaited(_openEditor(customer)),
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

final class _CustomerHeader extends StatelessWidget {
  const _CustomerHeader({
    required this.controller,
    required this.searchController,
    required this.visibleCount,
    required this.query,
    required this.onQueryChanged,
    required this.onClear,
    required this.onCreate,
  });

  final CustomersController controller;
  final TextEditingController searchController;
  final int visibleCount;
  final String query;
  final ValueChanged<String> onQueryChanged;
  final VoidCallback onClear;
  final VoidCallback? onCreate;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final busy = controller.state == CustomersLoadState.loading ||
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
                Icons.groups_2_outlined,
                color: SafeContractsVisual.navyDeep,
              ),
            ),
            title: ar ? 'العملاء' : 'Customers',
            subtitle: ar
                ? 'دليل أعمال مضغوط للعقود والمستحقات والتحصيلات'
                : 'Compact business directory for contracts and receivables',
            trailing: _CountBadge(value: '$visibleCount'),
          ),
          const SizedBox(height: 10),
          SafeContractsSurface(
            elevated: false,
            padding: const EdgeInsets.all(11),
            child: Column(
              children: [
                SearchBar(
                  controller: searchController,
                  leading: const Icon(Icons.search_rounded),
                  hintText: ar
                      ? 'بحث بالاسم أو الكود أو جهة الاتصال أو الهاتف'
                      : 'Search name, code, contact, email or phone',
                  onChanged: onQueryChanged,
                  trailing: [
                    if (query.isNotEmpty)
                      IconButton(
                        onPressed: onClear,
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
                    ChoiceChip(
                      label: const Text('A–Z'),
                      selected: controller.order == 'asc',
                      showCheckmark: false,
                      onSelected: busy
                          ? null
                          : (_) => unawaited(controller.setOrder('asc')),
                    ),
                    ChoiceChip(
                      label: const Text('Z–A'),
                      selected: controller.order == 'desc',
                      showCheckmark: false,
                      onSelected: busy
                          ? null
                          : (_) => unawaited(controller.setOrder('desc')),
                    ),
                    IconButton.filledTonal(
                      tooltip: context.scL10n.t('Refresh customers'),
                      onPressed:
                          busy ? null : () => unawaited(controller.refresh()),
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                    if (onCreate != null)
                      FilledButton.icon(
                        onPressed: busy ? null : onCreate,
                        icon: const Icon(Icons.person_add_alt_1_rounded),
                        label: Text(ar ? 'عميل جديد' : 'New customer'),
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

final class _CustomerBody extends StatelessWidget {
  const _CustomerBody({
    required this.controller,
    required this.customers,
    required this.split,
    required this.hasQuery,
    required this.onEdit,
  });

  final CustomersController controller;
  final List<SafeContractsCustomer> customers;
  final bool split;
  final bool hasQuery;
  final ValueChanged<SafeContractsCustomer> onEdit;

  @override
  Widget build(BuildContext context) {
    final page = controller.currentPage;
    if (controller.state == CustomersLoadState.loading && page == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.state == CustomersLoadState.error && page == null) {
      return _StateMessage(
        icon: Icons.cloud_off_rounded,
        message: context.scL10n.rawMessage(
          controller.errorMessage ?? 'Unable to load customers.',
        ),
        action: () => controller.loadPage(1),
      );
    }
    if (page == null) {
      return _StateMessage(
        icon: Icons.people_outline_rounded,
        message: context.scL10n.t('Customers are not loaded yet.'),
        action: () => controller.loadPage(1),
      );
    }

    final list = _CustomerList(
      controller: controller,
      customers: customers,
      hasQuery: hasQuery,
    );
    final detail = _CustomerDetail(controller: controller, onEdit: onEdit);
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
    if (controller.selectedCustomerId != null) return detail;
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
      child: list,
    );
  }
}

final class _CustomerList extends StatelessWidget {
  const _CustomerList({
    required this.controller,
    required this.customers,
    required this.hasQuery,
  });

  final CustomersController controller;
  final List<SafeContractsCustomer> customers;
  final bool hasQuery;

  @override
  Widget build(BuildContext context) {
    final page = controller.currentPage!;
    if (customers.isEmpty) {
      return _StateMessage(
        icon: Icons.manage_search_rounded,
        message: hasQuery
            ? (context.scL10n.isArabic
                ? 'لا توجد نتائج مطابقة في الصفحة الحالية.'
                : 'No matching customers on this page.')
            : context.scL10n.t('No customers are available in your scope.'),
        action: controller.refresh,
      );
    }
    return Column(
      children: [
        if (controller.state == CustomersLoadState.loading ||
            controller.mutationInFlight)
          const LinearProgressIndicator(minHeight: 2),
        Expanded(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(top: 2, bottom: 8),
              itemCount: customers.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final customer = customers[index];
                return _CustomerCard(
                  customer: customer,
                  selected: controller.selectedCustomerId == customer.id,
                  onTap: () => unawaited(controller.openCustomer(customer.id)),
                );
              },
            ),
          ),
        ),
        _Pagination(
          page: page,
          busy: controller.state == CustomersLoadState.loading,
          onPrevious: controller.previousPage,
          onNext: controller.nextPage,
        ),
      ],
    );
  }
}

final class _CustomerCard extends StatelessWidget {
  const _CustomerCard({
    required this.customer,
    required this.selected,
    required this.onTap,
  });

  final SafeContractsCustomer customer;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final statusColor = customer.isActive
        ? SafeContractsVisual.green
        : SafeContractsVisual.muted;
    final detail = <String>[
      if (customer.contactName != null) customer.contactName!,
      if (customer.phone != null) customer.phone!,
      if (customer.internalCode != null) customer.internalCode!,
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
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: SafeContractsVisual.navySoft,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Text(
                  customer.name.characters.first.toUpperCase(),
                  style: const TextStyle(
                    color: SafeContractsVisual.navyDeep,
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      customer.name,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    if (detail.isNotEmpty) ...[
                      const SizedBox(height: 3),
                      Text(
                        detail,
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
              const SizedBox(width: 8),
              _StatusBadge(
                label: context.scL10n.status(
                  customer.isActive ? 'active' : 'inactive',
                ),
                color: statusColor,
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

final class _CustomerDetail extends StatefulWidget {
  const _CustomerDetail({required this.controller, required this.onEdit});

  final CustomersController controller;
  final ValueChanged<SafeContractsCustomer> onEdit;

  @override
  State<_CustomerDetail> createState() => _CustomerDetailState();
}

final class _CustomerDetailState extends State<_CustomerDetail> {
  int? _loadedId;
  Future<CounterpartyBusinessSnapshot>? _snapshot;

  void _ensureSnapshot(SafeContractsCustomer customer) {
    if (_loadedId == customer.id && _snapshot != null) return;
    _loadedId = customer.id;
    _snapshot = CounterpartyBusinessSnapshotRepository(
      widget.controller.repository.client,
    ).load(counterpartyType: 'customer', counterpartyId: customer.id);
  }

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    final id = controller.selectedCustomerId;
    if (id == null) {
      return _StateMessage(
        icon: Icons.touch_app_outlined,
        message: context.scL10n.t(
          'Select a customer to view authorized details.',
        ),
      );
    }
    if (controller.detailState == CustomerDetailLoadState.loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.detailState == CustomerDetailLoadState.error) {
      return _StateMessage(
        icon: Icons.error_outline_rounded,
        message: context.scL10n.rawMessage(
          controller.detailErrorMessage ?? 'Unable to load customer.',
        ),
        action: () => controller.openCustomer(id),
      );
    }
    final customer = controller.selectedCustomer;
    if (customer == null) return const SizedBox.shrink();
    _ensureSnapshot(customer);
    return SafeContractsSurface(
      margin: const EdgeInsets.fromLTRB(14, 0, 14, 12),
      padding: EdgeInsets.zero,
      child: ListView(
        padding: const EdgeInsets.all(15),
        children: [
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              onPressed: controller.closeCustomer,
              icon: const Icon(
                Icons.arrow_back_rounded,
              ),
              label: Text(context.scL10n.isArabic ? 'العملاء' : 'Customers'),
            ),
          ),
          _CustomerHero(customer: customer),
          const SizedBox(height: 12),
          _ContactPanel(customer: customer),
          if (controller.canEdit) ...[
            const SizedBox(height: 10),
            FilledButton.icon(
              onPressed: controller.mutationInFlight
                  ? null
                  : () => widget.onEdit(customer),
              icon: const Icon(Icons.edit_outlined),
              label: Text(context.scL10n.t('Edit')),
            ),
          ],
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
                      ? 'تعذر تحميل بيانات الأعمال المرتبطة بهذا العميل.'
                      : 'Unable to load this customer’s linked business data.',
                );
              }
              return _BusinessSnapshot(
                value: snapshot.data!,
                supplier: false,
              );
            },
          ),
        ],
      ),
    );
  }
}

final class _CustomerHero extends StatelessWidget {
  const _CustomerHero({required this.customer});

  final SafeContractsCustomer customer;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
      ),
      child: Row(
        children: [
          Container(
            width: 54,
            height: 54,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              gradient: SafeContractsVisual.roseGradient,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Text(
              customer.name.characters.first.toUpperCase(),
              style: const TextStyle(
                color: SafeContractsVisual.navyDeep,
                fontSize: 22,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  customer.name,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                if (customer.internalCode != null)
                  Text(
                    customer.internalCode!,
                    style: TextStyle(
                      color: Colors.white.withValues(alpha: 0.72),
                    ),
                  ),
              ],
            ),
          ),
          _StatusBadge(
            label: context.scL10n.status(
              customer.isActive ? 'active' : 'inactive',
            ),
            color: customer.isActive
                ? SafeContractsVisual.green
                : SafeContractsVisual.muted,
            dark: true,
          ),
        ],
      ),
    );
  }
}

final class _ContactPanel extends StatelessWidget {
  const _ContactPanel({required this.customer});
  final SafeContractsCustomer customer;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final entries = <({IconData icon, String label, String? value})>[
      (
        icon: Icons.person_outline,
        label: ar ? 'جهة الاتصال' : 'Contact',
        value: customer.contactName
      ),
      (
        icon: Icons.phone_outlined,
        label: ar ? 'الهاتف' : 'Phone',
        value: customer.phone
      ),
      (
        icon: Icons.email_outlined,
        label: ar ? 'البريد' : 'Email',
        value: customer.email
      ),
    ];
    return SafeContractsSurface(
      elevated: false,
      padding: const EdgeInsets.all(12),
      child: Column(
        children: entries
            .map(
              (entry) => _ContactRow(
                icon: entry.icon,
                label: entry.label,
                value: entry.value,
              ),
            )
            .toList(growable: false),
      ),
    );
  }
}

final class _ContactRow extends StatelessWidget {
  const _ContactRow({
    required this.icon,
    required this.label,
    required this.value,
  });
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
                Text(
                  label,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                ),
                Text(
                  actual == null || actual.isEmpty ? '—' : actual,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
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
                    content:
                        Text(context.scL10n.isArabic ? 'تم النسخ.' : 'Copied.'),
                  ),
                );
              },
              icon: const Icon(Icons.copy_rounded, size: 18),
            ),
        ],
      ),
    );
  }
}

final class _BusinessSnapshot extends StatelessWidget {
  const _BusinessSnapshot({required this.value, required this.supplier});
  final CounterpartyBusinessSnapshot value;
  final bool supplier;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final pending = value.payments
        .where((payment) => payment.status != 'paid')
        .take(5)
        .toList(growable: false);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _SectionLabel(
          title: supplier
              ? (ar ? 'ملخص المستحقات علينا' : 'Payable summary')
              : (ar ? 'الملخص المالي' : 'Financial summary'),
        ),
        if (!value.financeAuthorized)
          _InlineNotice(
            text: ar
                ? 'الملخص المالي غير متاح ضمن صلاحيات هذه الجلسة.'
                : 'Financial summary is outside this session’s permissions.',
          )
        else if (value.finance.isEmpty)
          _InlineNotice(
              text: ar ? 'لا توجد أرصدة حالية.' : 'No current balances.')
        else
          ...value.finance.map(
            (row) => _FinanceCard(
              currency: row.currencyCode,
              outstanding: row.outstandingTotal,
              overdue: row.overdueTotal,
              count: row.obligationCount,
              supplier: supplier,
            ),
          ),
        const SizedBox(height: 14),
        _SectionLabel(
          title: ar
              ? 'العقود (${value.contracts.length})'
              : 'Contracts (${value.contracts.length})',
        ),
        if (value.contracts.isEmpty)
          _InlineNotice(text: ar ? 'لا توجد عقود.' : 'No contracts.')
        else
          ...value.contracts
              .take(5)
              .map((contract) => _MiniContract(contract: contract)),
        const SizedBox(height: 14),
        _SectionLabel(
          title: supplier
              ? (ar ? 'دفعات قادمة' : 'Upcoming payments')
              : (ar ? 'المستحقات' : 'Receivables'),
        ),
        if (pending.isEmpty)
          _InlineNotice(
              text: ar ? 'لا توجد دفعات معلقة.' : 'No pending payments.')
        else
          ...pending.map(
            (payment) => _PaymentLine(
              sequence: payment.sequenceNo,
              dueDate: payment.dueDate,
              amount: payment.remainingAmount,
              status: payment.status,
            ),
          ),
        const SizedBox(height: 14),
        _SectionLabel(title: ar ? 'النشاط الأخير' : 'Recent activity'),
        if (value.activity.isEmpty)
          _InlineNotice(
              text: ar ? 'لا توجد تحصيلات مسجلة.' : 'No settlement activity.')
        else
          ...value.activity.take(5).map(
                (activity) => _ActivityLine(activity: activity),
              ),
      ],
    );
  }
}

final class _FinanceCard extends StatelessWidget {
  const _FinanceCard({
    required this.currency,
    required this.outstanding,
    required this.overdue,
    required this.count,
    required this.supplier,
  });
  final String currency;
  final String outstanding;
  final String overdue;
  final int count;
  final bool supplier;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: SafeContractsSurface(
        elevated: false,
        accent:
            supplier ? SafeContractsVisual.amber : SafeContractsVisual.green,
        padding: const EdgeInsets.all(12),
        child: Wrap(
          spacing: 16,
          runSpacing: 8,
          children: [
            _Metric(label: ar ? 'العملة' : 'Currency', value: currency),
            _Metric(
                label: ar ? 'القائم' : 'Outstanding',
                value: _money(outstanding, currency)),
            _Metric(
                label: ar ? 'المتأخر' : 'Overdue',
                value: _money(overdue, currency)),
            _Metric(label: ar ? 'عدد الدفعات' : 'Obligations', value: '$count'),
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
                      style: const TextStyle(fontWeight: FontWeight.w800)),
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
              color: safeContractsStatusColor(contract.status),
            ),
          ],
        ),
      ),
    );
  }
}

final class _PaymentLine extends StatelessWidget {
  const _PaymentLine({
    required this.sequence,
    required this.dueDate,
    required this.amount,
    required this.status,
  });
  final int sequence;
  final String dueDate;
  final String amount;
  final String status;

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
              color: safeContractsStatusSoftColor(status),
              borderRadius: BorderRadius.circular(11),
            ),
            child: Text('$sequence',
                style: const TextStyle(fontWeight: FontWeight.w900)),
          ),
          const SizedBox(width: 9),
          Expanded(
              child:
                  Text(dueDate, maxLines: 1, overflow: TextOverflow.ellipsis)),
          const SizedBox(width: 8),
          Text(_compactNumber(amount),
              style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(width: 8),
          _StatusBadge(
              label: context.scL10n.status(status),
              color: safeContractsStatusColor(status)),
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
      leading: const Icon(Icons.receipt_long_outlined,
          color: SafeContractsVisual.green),
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

final class _CustomerEditor extends StatefulWidget {
  const _CustomerEditor({this.customer});
  final SafeContractsCustomer? customer;

  @override
  State<_CustomerEditor> createState() => _CustomerEditorState();
}

final class _CustomerEditorState extends State<_CustomerEditor> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _name;
  late final TextEditingController _code;
  late final TextEditingController _contact;
  late final TextEditingController _email;
  late final TextEditingController _phone;
  late final TextEditingController _notes;
  late bool _active;

  @override
  void initState() {
    super.initState();
    final customer = widget.customer;
    _name = TextEditingController(text: customer?.name ?? '');
    _code = TextEditingController(text: customer?.internalCode ?? '');
    _contact = TextEditingController(text: customer?.contactName ?? '');
    _email = TextEditingController(text: customer?.email ?? '');
    _phone = TextEditingController(text: customer?.phone ?? '');
    _notes = TextEditingController();
    _active = customer?.isActive ?? true;
  }

  @override
  void dispose() {
    _name.dispose();
    _code.dispose();
    _contact.dispose();
    _email.dispose();
    _phone.dispose();
    _notes.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final editing = widget.customer != null;
    return Padding(
      padding: EdgeInsets.fromLTRB(
        16,
        8,
        16,
        MediaQuery.viewInsetsOf(context).bottom + 18,
      ),
      child: SingleChildScrollView(
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SafeContractsPremiumHeader(
                compact: true,
                title: editing
                    ? (ar ? 'تعديل العميل' : 'Edit customer')
                    : (ar ? 'إضافة عميل' : 'Add customer'),
                subtitle: ar
                    ? 'يتم الحفظ عبر صلاحيات الخادم والتحقق منه.'
                    : 'Saved through server-authorized validation.',
              ),
              const SizedBox(height: 14),
              TextFormField(
                controller: _name,
                maxLength: 191,
                decoration: InputDecoration(
                    labelText: ar ? 'اسم العميل *' : 'Customer name *'),
                validator: (value) => value == null || value.trim().isEmpty
                    ? (ar ? 'اسم العميل مطلوب.' : 'Customer name is required.')
                    : null,
              ),
              const SizedBox(height: 10),
              TextField(
                  controller: _code,
                  decoration: InputDecoration(
                      labelText: ar ? 'الكود الداخلي' : 'Internal code')),
              const SizedBox(height: 10),
              TextField(
                  controller: _contact,
                  decoration: InputDecoration(
                      labelText: ar ? 'جهة الاتصال' : 'Contact name')),
              const SizedBox(height: 10),
              TextFormField(
                controller: _email,
                keyboardType: TextInputType.emailAddress,
                decoration: InputDecoration(
                    labelText: ar ? 'البريد الإلكتروني' : 'Email'),
                validator: (value) {
                  final text = value?.trim() ?? '';
                  if (text.isNotEmpty && !text.contains('@')) {
                    return ar
                        ? 'البريد الإلكتروني غير صحيح.'
                        : 'Enter a valid email.';
                  }
                  return null;
                },
              ),
              const SizedBox(height: 10),
              TextField(
                  controller: _phone,
                  keyboardType: TextInputType.phone,
                  decoration:
                      InputDecoration(labelText: ar ? 'الهاتف' : 'Phone')),
              if (!editing) ...[
                const SizedBox(height: 10),
                TextField(
                  controller: _notes,
                  minLines: 2,
                  maxLines: 4,
                  maxLength: 5000,
                  decoration:
                      InputDecoration(labelText: ar ? 'ملاحظات' : 'Notes'),
                ),
              ],
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                value: _active,
                onChanged: (value) => setState(() => _active = value),
                title: Text(ar ? 'عميل نشط' : 'Active customer'),
              ),
              const SizedBox(height: 10),
              FilledButton.icon(
                onPressed: () {
                  if (!_formKey.currentState!.validate()) return;
                  Navigator.of(context).pop(
                    CustomerDraft(
                      name: _name.text,
                      internalCode: _code.text,
                      contactName: _contact.text,
                      email: _email.text,
                      phone: _phone.text,
                      notes: editing ? null : _notes.text,
                      isActive: _active,
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

final class _Pagination extends StatelessWidget {
  const _Pagination({
    required this.page,
    required this.busy,
    required this.onPrevious,
    required this.onNext,
  });
  final CustomerPage page;
  final bool busy;
  final Future<void> Function() onPrevious;
  final Future<void> Function() onNext;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Wrap(
          spacing: 10,
          runSpacing: 8,
          crossAxisAlignment: WrapCrossAlignment.center,
          alignment: WrapAlignment.center,
          children: [
            OutlinedButton.icon(
              onPressed:
                  busy || page.page <= 1 ? null : () => unawaited(onPrevious()),
              icon: const Icon(
                Icons.chevron_left_rounded,
              ),
              label: Text(context.scL10n.t('Previous')),
            ),
            Text(context.scL10n.pageShown(page.page, page.customers.length)),
            OutlinedButton.icon(
              onPressed: busy || !page.hasMore || page.page >= 5
                  ? null
                  : () => unawaited(onNext()),
              icon: const Icon(
                Icons.chevron_right_rounded,
              ),
              label: Text(context.scL10n.t('Next')),
            ),
          ],
        ),
      ),
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
  const _StatusBadge(
      {required this.label, required this.color, this.dark = false});
  final String label;
  final Color color;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: dark
            ? color.withValues(alpha: 0.28)
            : color.withValues(alpha: 0.11),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        label,
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: dark ? Colors.white : color,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
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
      constraints: const BoxConstraints(minWidth: 92, maxWidth: 170),
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
              maxLines: 1,
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
        borderRadius: BorderRadius.circular(12),
      ),
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
