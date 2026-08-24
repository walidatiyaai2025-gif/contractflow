import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../../core/widgets/compact_pagination.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_models.dart';
import '../suppliers/suppliers.dart';
import '../reports/report_printing.dart';
import '../ui/safecontracts_design.dart';
import 'contract_media.dart';
import 'contracts.dart';

final class ContractsScreen extends StatefulWidget {
  const ContractsScreen({
    required this.controller,
    required this.customers,
    this.currency = const MobileCurrencyConfig.defaults(),
    required this.onOpenContract,
    super.key,
  });

  final ContractsController controller;
  final List<CustomerOption> customers;
  final MobileCurrencyConfig currency;
  final ValueChanged<int> onOpenContract;

  @override
  State<ContractsScreen> createState() => _ContractsScreenState();
}

final class _ContractsScreenState extends State<ContractsScreen> {
  late final TextEditingController _search;
  final Map<int, Future<ContractMedia?>> _media = {};
  Timer? _searchDebounce;
  String _searchText = '';

  @override
  void initState() {
    super.initState();
    _searchText = widget.controller.searchQuery;
    _search = TextEditingController(text: _searchText);
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  void didUpdateWidget(ContractsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      _searchDebounce?.cancel();
      _media.clear();
      _searchText = widget.controller.searchQuery;
      _search.value = TextEditingValue(
        text: _searchText,
        selection: TextSelection.collapsed(offset: _searchText.length),
      );
      unawaited(widget.controller.ensureLoaded());
    }
  }

  @override
  void dispose() {
    _searchDebounce?.cancel();
    _search.dispose();
    super.dispose();
  }

  Future<ContractMedia?> _loadMedia(int id) async {
    try {
      return await ContractMediaRepository(
        widget.controller.repository.client,
      ).load(id);
    } on Object {
      return null;
    }
  }

  Future<ContractMedia?> _mediaFor(int id) =>
      _media.putIfAbsent(id, () => _loadMedia(id));

  void _onSearchChanged(String value) {
    setState(() => _searchText = value);
    _searchDebounce?.cancel();
    _searchDebounce = Timer(const Duration(milliseconds: 350), () {
      if (!mounted || widget.controller.pageRequestInFlight) return;
      unawaited(widget.controller.selectSearch(value));
    });
  }

  void _clearSearch() {
    _searchDebounce?.cancel();
    _search.clear();
    setState(() => _searchText = '');
    if (!widget.controller.pageRequestInFlight) {
      unawaited(widget.controller.selectSearch(''));
    }
  }

  Future<void> _openCreate() async {
    final draft = await showModalBottomSheet<ContractDraft>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      backgroundColor: SafeContractsVisual.surface,
      builder: (context) => _ContractCreateSheet(
        controller: widget.controller,
        customers: widget.customers,
      ),
    );
    if (!mounted || draft == null) return;
    try {
      final contract = await widget.controller.createContract(draft);
      _media.remove(contract.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            context.scL10n.isArabic
                ? 'تم إنشاء العقد بنجاح.'
                : 'Contract created successfully.',
          ),
        ),
      );
      widget.onOpenContract(contract.id);
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
        final contracts = widget.controller.currentPage?.contracts ?? const [];
        return SafeContractsBackdrop(
          child: Column(
            children: [
              _ContractsHeader(
                controller: widget.controller,
                customers: widget.customers,
                searchController: _search,
                searchText: _searchText,
                onSearchChanged: _onSearchChanged,
                onClearSearch: _clearSearch,
                onCreate: widget.controller.canCreateContract
                    ? () => unawaited(_openCreate())
                    : null,
                report: _contractsReport(context, contracts),
              ),
              Expanded(
                child: _ContractsContent(
                  controller: widget.controller,
                  contracts: contracts,
                  hasSearch: widget.controller.searchQuery.isNotEmpty,
                  mediaFor: _mediaFor,
                  onOpenContract: widget.onOpenContract,
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _ContractsHeader extends StatelessWidget {
  const _ContractsHeader({
    required this.controller,
    required this.customers,
    required this.searchController,
    required this.searchText,
    required this.onSearchChanged,
    required this.onClearSearch,
    required this.onCreate,
    required this.report,
  });

  final ContractsController controller;
  final List<CustomerOption> customers;
  final TextEditingController searchController;
  final String searchText;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback onClearSearch;
  final VoidCallback? onCreate;
  final ReportGrid report;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final busy = controller.pageRequestInFlight || controller.mutationInFlight;
    final type = controller.filters.counterpartyType ?? '';
    final status = controller.filters.status ?? '';
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 6),
      child: Column(
        children: [
          SafeContractsPremiumHeader(
            compact: true,
            leading: Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                gradient: SafeContractsVisual.roseGradient,
                borderRadius: BorderRadius.circular(13),
              ),
              child: const Icon(
                Icons.description_outlined,
                color: SafeContractsVisual.navyDeep,
              ),
            ),
            title: ar ? 'العقود' : 'Contracts',
            subtitle: ar
                ? 'عقود العملاء والموردين مع اتجاه مالي واضح'
                : 'Customer and supplier contracts with clear financial direction',
            trailing: onCreate == null
                ? null
                : IconButton.filledTonal(
                    tooltip: ar ? 'عقد جديد' : 'New contract',
                    onPressed: busy ? null : onCreate,
                    icon: const Icon(Icons.add_rounded),
                  ),
          ),
          const SizedBox(height: 8),
          SafeContractsSurface(
            elevated: false,
            padding: const EdgeInsets.all(8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                LayoutBuilder(
                  builder: (context, constraints) {
                    final narrow = constraints.maxWidth < 360;
                    return Row(
                      children: [
                        Expanded(
                          child: SearchBar(
                            controller: searchController,
                            enabled: !busy,
                            constraints: const BoxConstraints(
                              minHeight: 40,
                              maxHeight: 40,
                            ),
                            leading: const Icon(Icons.search_rounded, size: 19),
                            hintText: ar ? 'بحث في العقود' : 'Search contracts',
                            onChanged: onSearchChanged,
                            trailing: [
                              if (searchText.isNotEmpty)
                                IconButton(
                                  tooltip: ar ? 'مسح البحث' : 'Clear search',
                                  onPressed: busy ? null : onClearSearch,
                                  icon: const Icon(
                                    Icons.close_rounded,
                                    size: 18,
                                  ),
                                ),
                            ],
                          ),
                        ),
                        const SizedBox(width: 5),
                        _CustomerFilterMenu(
                          customers: customers,
                          selectedCustomerId: controller.filters.customerId,
                          filterCount: controller.activeFilterCount,
                          enabled: !busy,
                          compact: narrow,
                          onSelected: (value) => unawaited(
                            controller.selectCustomer(
                              value == 0 ? null : value,
                            ),
                          ),
                        ),
                        const SizedBox(width: 5),
                        _SortMenu(
                          value: controller.sort,
                          enabled: !busy,
                          compact: narrow,
                          onSelected: (value) =>
                              unawaited(controller.selectSort(value)),
                        ),
                      ],
                    );
                  },
                ),
                const SizedBox(height: 6),
                Row(
                  children: [
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        key: ValueKey('contract-type-$type'),
                        initialValue: type,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: ar ? 'نوع العقد' : 'Contract type',
                          isDense: true,
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 9,
                            vertical: 9,
                          ),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: '',
                            child: Text(ar ? 'الكل' : 'All'),
                          ),
                          DropdownMenuItem(
                            value: 'customer',
                            child: Text(ar ? 'العملاء' : 'Customers'),
                          ),
                          DropdownMenuItem(
                            value: 'supplier',
                            child: Text(ar ? 'الموردون' : 'Suppliers'),
                          ),
                        ],
                        onChanged: busy
                            ? null
                            : (value) => unawaited(
                                controller.selectCounterpartyType(
                                  value == null || value.isEmpty ? null : value,
                                ),
                              ),
                      ),
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: DropdownButtonFormField<String>(
                        key: ValueKey('contract-status-$status'),
                        initialValue: status,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: ar ? 'الحالة' : 'Status',
                          isDense: true,
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 9,
                            vertical: 9,
                          ),
                        ),
                        items: [
                          DropdownMenuItem(
                            value: '',
                            child: Text(ar ? 'كل الحالات' : 'All statuses'),
                          ),
                          for (final value in const [
                            'draft',
                            'active',
                            'completed',
                            'cancelled',
                          ])
                            DropdownMenuItem(
                              value: value,
                              child: Text(context.scL10n.status(value)),
                            ),
                        ],
                        onChanged: busy
                            ? null
                            : (value) => unawaited(
                                controller.selectStatus(
                                  value == null || value.isEmpty ? null : value,
                                ),
                              ),
                      ),
                    ),
                    const SizedBox(width: 4),
                    GridPrintButton(report: report, busy: busy, compact: true),
                    const SizedBox(width: 4),
                    IconButton.filledTonal(
                      tooltip: context.scL10n.t('Refresh contracts'),
                      onPressed: busy
                          ? null
                          : () => unawaited(controller.refresh()),
                      icon: const Icon(Icons.refresh_rounded, size: 19),
                    ),
                    if (controller.activeFilterCount > 0) ...[
                      const SizedBox(width: 2),
                      IconButton(
                        tooltip: ar ? 'مسح الفلاتر' : 'Clear filters',
                        onPressed: busy
                            ? null
                            : () => unawaited(controller.clearFilters()),
                        icon: const Icon(
                          Icons.filter_alt_off_outlined,
                          size: 19,
                        ),
                      ),
                    ],
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

final class _CustomerFilterMenu extends StatelessWidget {
  const _CustomerFilterMenu({
    required this.customers,
    required this.selectedCustomerId,
    required this.filterCount,
    required this.enabled,
    required this.compact,
    required this.onSelected,
  });

  final List<CustomerOption> customers;
  final int? selectedCustomerId;
  final int filterCount;
  final bool enabled;
  final bool compact;
  final ValueChanged<int> onSelected;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final text = filterCount > 0
        ? (ar ? 'الفلاتر ($filterCount)' : 'Filters ($filterCount)')
        : (ar ? 'فلتر' : 'Filter');
    return PopupMenuButton<int>(
      enabled: enabled,
      tooltip: text,
      initialValue: selectedCustomerId ?? 0,
      onSelected: onSelected,
      itemBuilder: (context) => [
        PopupMenuItem(
          value: 0,
          child: Text(ar ? 'كل العملاء' : 'All customers'),
        ),
        for (final customer in customers)
          PopupMenuItem(
            value: customer.id,
            child: Text(customer.name, overflow: TextOverflow.ellipsis),
          ),
      ],
      child: _ToolbarAction(
        icon: Icons.filter_alt_outlined,
        label: compact ? null : text,
        active: filterCount > 0,
      ),
    );
  }
}

final class _SortMenu extends StatelessWidget {
  const _SortMenu({
    required this.value,
    required this.enabled,
    required this.compact,
    required this.onSelected,
  });

  final ContractSortOption value;
  final bool enabled;
  final bool compact;
  final ValueChanged<ContractSortOption> onSelected;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return PopupMenuButton<ContractSortOption>(
      enabled: enabled,
      tooltip: ar ? 'الترتيب' : 'Sort',
      initialValue: value,
      onSelected: onSelected,
      itemBuilder: (context) => ContractSortOption.values
          .map(
            (option) => CheckedPopupMenuItem<ContractSortOption>(
              value: option,
              checked: identical(value, option),
              child: Text(context.scL10n.t(option.label)),
            ),
          )
          .toList(growable: false),
      child: _ToolbarAction(
        icon: Icons.swap_vert_rounded,
        label: compact ? null : (ar ? 'الترتيب' : 'Sort'),
        active: !identical(value, ContractSortOption.newest),
      ),
    );
  }
}

final class _ToolbarAction extends StatelessWidget {
  const _ToolbarAction({
    required this.icon,
    required this.label,
    required this.active,
  });

  final IconData icon;
  final String? label;
  final bool active;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 40,
      padding: EdgeInsets.symmetric(horizontal: label == null ? 10 : 11),
      decoration: BoxDecoration(
        color: active
            ? SafeContractsVisual.navySoft
            : Theme.of(context).colorScheme.surface,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: SafeContractsVisual.outline),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 19, color: SafeContractsVisual.navy),
          if (label != null) ...[
            const SizedBox(width: 5),
            Text(
              label!,
              maxLines: 1,
              style: Theme.of(
                context,
              ).textTheme.labelMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
          ],
        ],
      ),
    );
  }
}

ReportGrid _contractsReport(
  BuildContext context,
  List<SafeContractsContract> contracts,
) {
  final ar = context.scL10n.isArabic;
  return ReportGrid(
    title: ar ? 'العقود المعروضة' : 'Visible contracts',
    fileStem: 'contracts_grid',
    columns: ar
        ? const [
            'العقد',
            'الطرف',
            'النوع',
            'الاتجاه',
            'القيمة',
            'العملة',
            'الحالة',
            'البداية',
            'النهاية',
          ]
        : const [
            'Contract',
            'Counterparty',
            'Type',
            'Direction',
            'Value',
            'Currency',
            'Status',
            'Start',
            'End',
          ],
    rows: contracts
        .map(
          (item) => [
            item.contractNumber,
            item.displayCounterparty,
            item.counterpartyType,
            item.financialDirection,
            item.baseValue ?? '',
            item.currencyCode,
            context.scL10n.status(item.status),
            item.startDate ?? '',
            item.endDate ?? '',
          ],
        )
        .toList(growable: false),
  );
}

final class _ContractsContent extends StatelessWidget {
  const _ContractsContent({
    required this.controller,
    required this.contracts,
    required this.hasSearch,
    required this.mediaFor,
    required this.onOpenContract,
  });

  final ContractsController controller;
  final List<SafeContractsContract> contracts;
  final bool hasSearch;
  final Future<ContractMedia?> Function(int id) mediaFor;
  final ValueChanged<int> onOpenContract;

  @override
  Widget build(BuildContext context) {
    final page = controller.currentPage;
    if (controller.state == ContractsLoadState.loading && page == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.state == ContractsLoadState.error && page == null) {
      return _StateMessage(
        icon: Icons.cloud_off_rounded,
        message: context.scL10n.rawMessage(
          controller.errorMessage ?? 'Unable to load contracts.',
        ),
        action: () => controller.loadPage(1),
      );
    }
    if (page == null) {
      return _StateMessage(
        icon: Icons.description_outlined,
        message: context.scL10n.t('Contracts are not loaded yet.'),
        action: () => controller.loadPage(1),
      );
    }
    if (contracts.isEmpty) {
      return Column(
        children: [
          if (controller.state == ContractsLoadState.loading)
            const LinearProgressIndicator(minHeight: 2),
          Expanded(
            child: _StateMessage(
              icon: Icons.manage_search_rounded,
              message: hasSearch
                  ? (context.scL10n.isArabic
                        ? 'لا توجد عقود تطابق البحث الحالي.'
                        : 'No contracts match the current search.')
                  : context.scL10n.t('No contracts match the current filters.'),
              action: controller.refresh,
            ),
          ),
          _Pagination(controller: controller, page: page),
        ],
      );
    }

    return Column(
      children: [
        if (controller.state == ContractsLoadState.loading ||
            controller.mutationInFlight)
          const LinearProgressIndicator(minHeight: 2),
        if (controller.state == ContractsLoadState.error &&
            controller.errorMessage != null)
          _InlineLoadError(
            message: context.scL10n.rawMessage(controller.errorMessage!),
            onRetry: controller.refresh,
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: LayoutBuilder(
              builder: (context, constraints) {
                final columns = constraints.maxWidth >= 780 ? 2 : 1;
                if (columns == 1) {
                  return ListView.separated(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding: const EdgeInsets.fromLTRB(14, 2, 14, 12),
                    itemCount: contracts.length,
                    separatorBuilder: (_, _) => const SizedBox(height: 9),
                    itemBuilder: (context, index) => _ContractCard(
                      contract: contracts[index],
                      media: mediaFor(contracts[index].id),
                      onTap: () => onOpenContract(contracts[index].id),
                    ),
                  );
                }
                return GridView.builder(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.fromLTRB(14, 2, 14, 12),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 2,
                    mainAxisExtent: 220,
                    crossAxisSpacing: 10,
                    mainAxisSpacing: 10,
                  ),
                  itemCount: contracts.length,
                  itemBuilder: (context, index) => _ContractCard(
                    contract: contracts[index],
                    media: mediaFor(contracts[index].id),
                    onTap: () => onOpenContract(contracts[index].id),
                  ),
                );
              },
            ),
          ),
        ),
        _Pagination(controller: controller, page: page),
      ],
    );
  }
}

final class _InlineLoadError extends StatelessWidget {
  const _InlineLoadError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 2, 14, 6),
      child: Material(
        color: Theme.of(context).colorScheme.errorContainer,
        borderRadius: BorderRadius.circular(10),
        child: Padding(
          padding: const EdgeInsetsDirectional.fromSTEB(10, 4, 4, 4),
          child: Row(
            children: [
              Icon(
                Icons.error_outline_rounded,
                size: 18,
                color: Theme.of(context).colorScheme.onErrorContainer,
              ),
              const SizedBox(width: 7),
              Expanded(
                child: Text(
                  message,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: Theme.of(context).colorScheme.onErrorContainer,
                  ),
                ),
              ),
              TextButton(
                onPressed: () => unawaited(onRetry()),
                child: Text(context.scL10n.t('Retry')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _ContractCard extends StatelessWidget {
  const _ContractCard({
    required this.contract,
    required this.media,
    required this.onTap,
  });

  final SafeContractsContract contract;
  final Future<ContractMedia?> media;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final directionColor = contract.isSupplier
        ? SafeContractsVisual.amber
        : SafeContractsVisual.green;
    final progress = _termProgress(contract.startDate, contract.endDate);
    return Material(
      color: SafeContractsVisual.surface,
      borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          decoration: BoxDecoration(
            border: Border.all(color: SafeContractsVisual.outline),
            borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SizedBox(
                width: 104,
                child: FutureBuilder<ContractMedia?>(
                  future: media,
                  builder: (context, snapshot) => _ContractThumbnail(
                    url: snapshot.data?.heroUrl,
                    logoFallback: snapshot.data?.usesCompanyLogo ?? false,
                  ),
                ),
              ),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Row(
                        children: [
                          Expanded(
                            child: Text(
                              contract.contractNumber,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: SafeContractsVisual.navyDeep,
                                fontWeight: FontWeight.w900,
                                fontSize: 16,
                              ),
                            ),
                          ),
                          const SizedBox(width: 6),
                          _StatusPill(status: contract.status),
                        ],
                      ),
                      const SizedBox(height: 5),
                      Text(
                        contract.displayCounterparty,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Wrap(
                        spacing: 6,
                        runSpacing: 6,
                        children: [
                          _InfoPill(
                            icon: contract.isSupplier
                                ? Icons.arrow_outward_rounded
                                : Icons.south_west_rounded,
                            label: contract.isSupplier
                                ? (ar ? 'مستحق علينا' : 'Payable')
                                : (ar ? 'مستحق لنا' : 'Receivable'),
                            color: directionColor,
                          ),
                          if (contract.baseValue != null)
                            _InfoPill(
                              icon: Icons.payments_outlined,
                              label: _money(
                                contract.baseValue!,
                                contract.currencyCode,
                              ),
                            ),
                        ],
                      ),
                      const Spacer(),
                      if (contract.startDate != null ||
                          contract.endDate != null)
                        Text(
                          <String>[
                            if (contract.startDate != null)
                              '${ar ? 'من' : 'From'} ${contract.startDate}',
                            if (contract.endDate != null)
                              '${ar ? 'إلى' : 'To'} ${contract.endDate}',
                          ].join(' • '),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelSmall
                              ?.copyWith(color: SafeContractsVisual.muted),
                        ),
                      if (progress != null) ...[
                        const SizedBox(height: 7),
                        ClipRRect(
                          borderRadius: BorderRadius.circular(99),
                          child: LinearProgressIndicator(
                            minHeight: 5,
                            value: progress,
                            backgroundColor: SafeContractsVisual.navySoft,
                            valueColor: const AlwaysStoppedAnimation(
                              SafeContractsVisual.roseGold,
                            ),
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _ContractThumbnail extends StatelessWidget {
  const _ContractThumbnail({required this.url, required this.logoFallback});
  final String? url;
  final bool logoFallback;

  @override
  Widget build(BuildContext context) {
    final url = this.url;
    if (url == null || url.isEmpty) return const _NeutralContractPlaceholder();
    return Stack(
      fit: StackFit.expand,
      children: [
        Image.network(
          url,
          fit: BoxFit.cover,
          errorBuilder: (_, _, _) => const _NeutralContractPlaceholder(),
        ),
        if (logoFallback)
          PositionedDirectional(
            start: 6,
            bottom: 6,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 3),
              decoration: BoxDecoration(
                color: SafeContractsVisual.navyDeep.withValues(alpha: 0.82),
                borderRadius: BorderRadius.circular(9),
              ),
              child: Icon(
                Icons.business_rounded,
                size: 13,
                color: Colors.white.withValues(alpha: 0.9),
              ),
            ),
          ),
      ],
    );
  }
}

final class _NeutralContractPlaceholder extends StatelessWidget {
  const _NeutralContractPlaceholder();

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            SafeContractsVisual.navySoft,
            SafeContractsVisual.surfaceWarm,
          ],
        ),
      ),
      child: const Center(
        child: Icon(
          Icons.description_outlined,
          color: SafeContractsVisual.navy,
          size: 34,
        ),
      ),
    );
  }
}

final class _ContractCreateSheet extends StatefulWidget {
  const _ContractCreateSheet({
    required this.controller,
    required this.customers,
  });
  final ContractsController controller;
  final List<CustomerOption> customers;

  @override
  State<_ContractCreateSheet> createState() => _ContractCreateSheetState();
}

final class _ContractCreateSheetState extends State<_ContractCreateSheet> {
  final _formKey = GlobalKey<FormState>();
  final _number = TextEditingController();
  final _value = TextEditingController();
  final _currency = TextEditingController();
  final _notes = TextEditingController();
  String _type = 'customer';
  int? _counterpartyId;
  List<SafeContractsSupplier> _suppliers = const [];
  bool _loadingSuppliers = true;

  @override
  void initState() {
    super.initState();
    if (widget.customers.isNotEmpty) {
      _counterpartyId = widget.customers.first.id;
    }
    unawaited(_loadSuppliers());
  }

  Future<void> _loadSuppliers() async {
    try {
      final values = await SuppliersRepository(
        widget.controller.repository.client,
      ).search(limit: 100);
      if (!mounted) return;
      setState(() {
        _suppliers = values.where((item) => !item.isArchived).toList();
        _loadingSuppliers = false;
      });
    } on SafeContractsApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _suppliers = const [];
        _loadingSuppliers = false;
      });
      if (error.statusCode != 403) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(context.scL10n.rawMessage(error.message))),
        );
      }
    } on Object {
      if (mounted) setState(() => _loadingSuppliers = false);
    }
  }

  @override
  void dispose() {
    _number.dispose();
    _value.dispose();
    _currency.dispose();
    _notes.dispose();
    super.dispose();
  }

  void _setType(String value) {
    setState(() {
      _type = value;
      _counterpartyId = value == 'customer'
          ? (widget.customers.isEmpty ? null : widget.customers.first.id)
          : (_suppliers.isEmpty ? null : _suppliers.first.id);
    });
  }

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final supplier = _type == 'supplier';
    final options = supplier
        ? _suppliers
              .map((item) => (id: item.id, name: item.displayName))
              .toList(growable: false)
        : widget.customers
              .map((item) => (id: item.id, name: item.name))
              .toList(growable: false);
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
                title: ar ? 'إنشاء عقد' : 'Create contract',
                subtitle: ar
                    ? 'نوع الطرف يحدد الاتجاه المالي من الخادم.'
                    : 'Counterparty type determines server-authoritative direction.',
              ),
              const SizedBox(height: 14),
              SegmentedButton<String>(
                segments: [
                  ButtonSegment(
                    value: 'customer',
                    icon: const Icon(Icons.person_outline),
                    label: Text(ar ? 'عميل' : 'Customer'),
                  ),
                  ButtonSegment(
                    value: 'supplier',
                    icon: const Icon(Icons.local_shipping_outlined),
                    label: Text(ar ? 'مورد' : 'Supplier'),
                    enabled: !_loadingSuppliers && _suppliers.isNotEmpty,
                  ),
                ],
                selected: {_type},
                onSelectionChanged: (value) => _setType(value.first),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<int>(
                key: ValueKey('$_type:${options.length}:$_counterpartyId'),
                initialValue: options.any((item) => item.id == _counterpartyId)
                    ? _counterpartyId
                    : null,
                isExpanded: true,
                decoration: InputDecoration(
                  labelText: supplier
                      ? (ar ? 'المورد *' : 'Supplier *')
                      : (ar ? 'العميل *' : 'Customer *'),
                ),
                items: options
                    .map(
                      (item) => DropdownMenuItem(
                        value: item.id,
                        child: Text(item.name, overflow: TextOverflow.ellipsis),
                      ),
                    )
                    .toList(growable: false),
                onChanged: (value) => setState(() => _counterpartyId = value),
                validator: (value) => value == null
                    ? (ar ? 'اختر طرف العقد.' : 'Select a counterparty.')
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _number,
                maxLength: 100,
                decoration: InputDecoration(
                  labelText: ar ? 'رقم / اسم العقد *' : 'Contract number *',
                ),
                validator: (value) => value == null || value.trim().isEmpty
                    ? (ar ? 'رقم العقد مطلوب.' : 'Contract number is required.')
                    : null,
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _value,
                keyboardType: const TextInputType.numberWithOptions(
                  decimal: true,
                ),
                decoration: InputDecoration(
                  labelText: ar ? 'قيمة العقد *' : 'Contract value *',
                ),
                validator: (value) {
                  final parsed = num.tryParse(value?.trim() ?? '');
                  return parsed == null || parsed <= 0
                      ? (ar
                            ? 'أدخل قيمة أكبر من صفر.'
                            : 'Enter a value greater than zero.')
                      : null;
                },
              ),
              const SizedBox(height: 10),
              TextFormField(
                controller: _currency,
                textCapitalization: TextCapitalization.characters,
                maxLength: 3,
                decoration: InputDecoration(
                  labelText: ar
                      ? 'العملة (اختياري؛ إعدادات الخادم عند الفراغ)'
                      : 'Currency (optional; server default when blank)',
                ),
                validator: (value) {
                  final text = value?.trim() ?? '';
                  if (text.isEmpty) return null;
                  return RegExp(r'^[A-Za-z]{3}$').hasMatch(text)
                      ? null
                      : (ar ? 'استخدم 3 أحرف.' : 'Use a 3-letter code.');
                },
              ),
              const SizedBox(height: 10),
              TextField(
                controller: _notes,
                minLines: 2,
                maxLines: 4,
                maxLength: 5000,
                decoration: InputDecoration(
                  labelText: ar ? 'ملاحظات' : 'Notes',
                ),
              ),
              const SizedBox(height: 10),
              FilledButton.icon(
                onPressed: options.isEmpty
                    ? null
                    : () {
                        if (!_formKey.currentState!.validate()) return;
                        final partyId = _counterpartyId;
                        if (partyId == null) return;
                        Navigator.of(context).pop(
                          ContractDraft(
                            contractNumber: _number.text,
                            counterpartyType: _type,
                            counterpartyId: partyId,
                            baseValue: _value.text,
                            currencyCode: _currency.text,
                            notes: _notes.text,
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
  const _Pagination({required this.controller, required this.page});
  final ContractsController controller;
  final ContractPage page;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return SafeArea(
      top: false,
      child: CompactPagination(
        page: page.page,
        totalPages: page.totalPages,
        total: page.total,
        isLoading: controller.pageRequestInFlight,
        previousLabel: context.scL10n.t('Previous'),
        nextLabel: context.scL10n.t('Next'),
        onPrevious: page.page <= 1
            ? null
            : () => unawaited(controller.previousPage()),
        onNext: page.page >= page.totalPages
            ? null
            : () => unawaited(controller.nextPage()),
        resultLabelBuilder: (total) => ar ? '$total نتيجة' : '$total results',
      ),
    );
  }
}

final class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final color = safeContractsStatusColor(status);
    return Container(
      constraints: const BoxConstraints(maxWidth: 92),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: safeContractsStatusSoftColor(status),
        borderRadius: BorderRadius.circular(20),
      ),
      child: Text(
        context.scL10n.status(status),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: color,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

final class _InfoPill extends StatelessWidget {
  const _InfoPill({
    required this.icon,
    required this.label,
    this.color = SafeContractsVisual.navy,
  });
  final IconData icon;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(maxWidth: 180),
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 14, color: color),
          const SizedBox(width: 4),
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
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

double? _termProgress(String? startDate, String? endDate) {
  if (startDate == null || endDate == null) return null;
  final start = DateTime.tryParse(startDate);
  final end = DateTime.tryParse(endDate);
  if (start == null || end == null || !end.isAfter(start)) return null;
  final now = DateTime.now();
  if (now.isBefore(start)) return 0;
  if (!now.isBefore(end)) return 1;
  final total = end.difference(start).inSeconds;
  final elapsed = now.difference(start).inSeconds;
  if (total <= 0) return null;
  return (elapsed / total).clamp(0.0, 1.0);
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
