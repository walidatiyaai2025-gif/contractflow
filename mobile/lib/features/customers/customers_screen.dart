import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_design.dart';
import 'customers.dart';

final class CustomersScreen extends StatefulWidget {
  const CustomersScreen({required this.controller, super.key});

  final CustomersController controller;

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

final class _CustomersScreenState extends State<CustomersScreen> {
  final TextEditingController _searchController = TextEditingController();
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
      builder: (context, child) {
        return LayoutBuilder(
          builder: (context, constraints) {
            final wide = constraints.maxWidth >= 840;
            final page = widget.controller.currentPage;
            final filtered = _filteredCustomers(page?.customers ?? const []);
            return SafeContractsBackdrop(
              child: Column(
                children: [
                  _PremiumCustomersHeader(
                    controller: widget.controller,
                    searchController: _searchController,
                    query: _query,
                    visibleCount: filtered.length,
                    onSearchChanged: (value) {
                      setState(() => _query = value.trim().toLowerCase());
                    },
                    onClearSearch: () {
                      _searchController.clear();
                      setState(() => _query = '');
                    },
                  ),
                  const SizedBox(height: 8),
                  Expanded(
                    child: _CustomersContent(
                      controller: widget.controller,
                      wide: wide,
                      filteredCustomers: filtered,
                      hasSearch: _query.isNotEmpty,
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

  List<SafeContractsCustomer> _filteredCustomers(
    List<SafeContractsCustomer> customers,
  ) {
    if (_query.isEmpty) return customers;
    return customers.where((customer) {
      final haystack = <String?>[
        customer.name,
        customer.internalCode,
        customer.contactName,
        customer.email,
        customer.phone,
      ].whereType<String>().join(' ').toLowerCase();
      return haystack.contains(_query);
    }).toList(growable: false);
  }
}

final class _PremiumCustomersHeader extends StatelessWidget {
  const _PremiumCustomersHeader({
    required this.controller,
    required this.searchController,
    required this.query,
    required this.visibleCount,
    required this.onSearchChanged,
    required this.onClearSearch,
  });

  final CustomersController controller;
  final TextEditingController searchController;
  final String query;
  final int visibleCount;
  final ValueChanged<String> onSearchChanged;
  final VoidCallback onClearSearch;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final busy = controller.state == CustomersLoadState.loading;
    final page = controller.currentPage;
    final ar = l10n.isArabic;

    return Padding(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 0),
      child: Column(
        children: [
          Container(
            width: double.infinity,
            padding: const EdgeInsets.fromLTRB(16, 15, 16, 15),
            decoration: BoxDecoration(
              gradient: SafeContractsVisual.premiumHeaderGradient,
              borderRadius: BorderRadius.circular(
                SafeContractsVisual.compactRadius,
              ),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x2B092944),
                  blurRadius: 20,
                  offset: Offset(0, 8),
                ),
              ],
            ),
            child: Row(
              children: [
                Container(
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
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        ar ? 'دليل العملاء' : 'Customer Directory',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: Colors.white,
                              fontWeight: FontWeight.w900,
                            ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        ar
                            ? 'بحث سريع داخل العملاء المصرح لك بعرضهم'
                            : 'Quick search across your authorized customer page',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Colors.white.withValues(alpha: 0.72),
                            ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 7,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(20),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.15),
                    ),
                  ),
                  child: Text(
                    '${page?.customers.length ?? 0}',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          SafeContractsSurface(
            padding: const EdgeInsets.all(12),
            elevated: false,
            child: Column(
              children: [
                SearchBar(
                  controller: searchController,
                  enabled: !busy,
                  leading: const Icon(Icons.search_rounded),
                  hintText: ar
                      ? 'ابحث بالاسم أو الكود أو جهة الاتصال أو الهاتف'
                      : 'Search name, code, contact, email or phone',
                  onChanged: onSearchChanged,
                  trailing: [
                    if (query.isNotEmpty)
                      IconButton(
                        tooltip: l10n.t('Clear'),
                        onPressed: busy ? null : onClearSearch,
                        icon: const Icon(Icons.close_rounded),
                      ),
                  ],
                ),
                const SizedBox(height: 10),
                Row(
                  children: [
                    Expanded(
                      child: SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: [
                            _OrderChip(
                              label: 'A–Z',
                              selected: controller.order == 'asc',
                              onSelected: busy
                                  ? null
                                  : () => unawaited(
                                        controller.setOrder('asc'),
                                      ),
                            ),
                            const SizedBox(width: 8),
                            _OrderChip(
                              label: 'Z–A',
                              selected: controller.order == 'desc',
                              onSelected: busy
                                  ? null
                                  : () => unawaited(
                                        controller.setOrder('desc'),
                                      ),
                            ),
                            if (query.isNotEmpty) ...[
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 7,
                                ),
                                decoration: BoxDecoration(
                                  color: SafeContractsVisual.roseSoft,
                                  borderRadius: BorderRadius.circular(20),
                                ),
                                child: Text(
                                  ar
                                      ? '$visibleCount نتيجة'
                                      : '$visibleCount results',
                                  style: const TextStyle(
                                    color: SafeContractsVisual.redDeep,
                                    fontWeight: FontWeight.w800,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            ],
                          ],
                        ),
                      ),
                    ),
                    IconButton.filledTonal(
                      tooltip: l10n.t('Refresh customers'),
                      onPressed:
                          busy ? null : () => unawaited(controller.refresh()),
                      icon: const Icon(Icons.refresh_rounded),
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

final class _OrderChip extends StatelessWidget {
  const _OrderChip({
    required this.label,
    required this.selected,
    required this.onSelected,
  });

  final String label;
  final bool selected;
  final VoidCallback? onSelected;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      onSelected: onSelected == null ? null : (_) => onSelected!(),
      selectedColor: SafeContractsVisual.navySoft,
      showCheckmark: false,
      side: BorderSide(
        color:
            selected ? SafeContractsVisual.navy : SafeContractsVisual.outline,
      ),
      labelStyle: TextStyle(
        color:
            selected ? SafeContractsVisual.navyDeep : SafeContractsVisual.muted,
        fontWeight: FontWeight.w800,
      ),
    );
  }
}

final class _CustomersContent extends StatelessWidget {
  const _CustomersContent({
    required this.controller,
    required this.wide,
    required this.filteredCustomers,
    required this.hasSearch,
  });

  final CustomersController controller;
  final bool wide;
  final List<SafeContractsCustomer> filteredCustomers;
  final bool hasSearch;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final page = controller.currentPage;

    if (controller.state == CustomersLoadState.loading && page == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.state == CustomersLoadState.error && page == null) {
      return _CustomersError(
        message: l10n.rawMessage(
          controller.errorMessage ?? 'Unable to load customers.',
        ),
        onRetry: () => unawaited(controller.loadPage(1)),
      );
    }
    if (page == null) {
      return Center(child: Text(l10n.t('Customers are not loaded yet.')));
    }

    final list = _CustomerList(
      controller: controller,
      page: page,
      customers: filteredCustomers,
      hasSearch: hasSearch,
    );
    final detail = _CustomerDetailPane(
      controller: controller,
      showBack: !wide,
    );

    if (wide) {
      return Padding(
        padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
        child: Row(
          children: [
            Expanded(flex: 3, child: list),
            const SizedBox(width: 10),
            Expanded(flex: 2, child: detail),
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
    required this.page,
    required this.customers,
    required this.hasSearch,
  });

  final CustomersController controller;
  final CustomerPage page;
  final List<SafeContractsCustomer> customers;
  final bool hasSearch;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (customers.isEmpty) {
      return SafeContractsSurface(
        child: RefreshIndicator(
          onRefresh: controller.refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            children: <Widget>[
              const SizedBox(height: 72),
              const Icon(
                Icons.manage_search_rounded,
                size: 52,
                color: SafeContractsVisual.muted,
              ),
              const SizedBox(height: 12),
              Center(
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 18),
                  child: Text(
                    hasSearch
                        ? (l10n.isArabic
                            ? 'لا توجد نتائج مطابقة في الصفحة الحالية.'
                            : 'No matching customers on the current page.')
                        : l10n.t(
                            'No customers are available in your scope.',
                          ),
                    textAlign: TextAlign.center,
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    return Column(
      children: [
        if (controller.state == CustomersLoadState.loading)
          const LinearProgressIndicator(minHeight: 2),
        if (controller.state == CustomersLoadState.error)
          _InlineCustomerError(
            message: l10n.rawMessage(
              controller.errorMessage ?? 'Customer refresh failed.',
            ),
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: ListView.builder(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.only(top: 2, bottom: 8),
              itemCount: customers.length,
              itemBuilder: (context, index) {
                final customer = customers[index];
                return Padding(
                  padding: const EdgeInsets.only(bottom: 9),
                  child: _CustomerCard(
                    customer: customer,
                    selected: controller.selectedCustomerId == customer.id,
                    onTap: () => unawaited(
                      controller.openCustomer(customer.id),
                    ),
                  ),
                );
              },
            ),
          ),
        ),
        _CustomerPagination(controller: controller, page: page),
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
    final l10n = context.scL10n;
    final accent = customer.isActive
        ? SafeContractsVisual.green
        : SafeContractsVisual.muted;
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
            boxShadow: const [
              BoxShadow(
                color: Color(0x125A4638),
                blurRadius: 12,
                offset: Offset(0, 4),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: SafeContractsVisual.navySoft,
                  borderRadius: BorderRadius.circular(13),
                ),
                alignment: Alignment.center,
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
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      <String>[
                        if (customer.internalCode != null)
                          customer.internalCode!,
                        if (customer.contactName != null) customer.contactName!,
                        if (customer.phone != null) customer.phone!,
                      ].join(' • '),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: SafeContractsVisual.muted,
                          ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 8,
                  vertical: 5,
                ),
                decoration: BoxDecoration(
                  color: accent.withValues(alpha: 0.11),
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Text(
                  l10n.status(customer.isActive ? 'active' : 'inactive'),
                  style: TextStyle(
                    color: accent,
                    fontWeight: FontWeight.w800,
                    fontSize: 11,
                  ),
                ),
              ),
              const SizedBox(width: 4),
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

final class _CustomerPagination extends StatelessWidget {
  const _CustomerPagination({required this.controller, required this.page});

  final CustomersController controller;
  final CustomerPage page;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final busy = controller.state == CustomersLoadState.loading;
    return SafeArea(
      top: false,
      child: SafeContractsSurface(
        elevated: false,
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
        child: Wrap(
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          spacing: 10,
          runSpacing: 8,
          children: [
            OutlinedButton.icon(
              onPressed: busy || page.page <= 1
                  ? null
                  : () => unawaited(controller.previousPage()),
              icon: const Icon(Icons.chevron_left),
              label: Text(l10n.t('Previous')),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 11, vertical: 8),
              decoration: BoxDecoration(
                color: SafeContractsVisual.navySoft,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Text(
                '${page.page} / 5',
                style: const TextStyle(
                  color: SafeContractsVisual.navyDeep,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
            OutlinedButton.icon(
              onPressed: busy || !page.hasMore || page.page >= 5
                  ? null
                  : () => unawaited(controller.nextPage()),
              icon: const Icon(Icons.chevron_right),
              label: Text(l10n.t('Next')),
            ),
          ],
        ),
      ),
    );
  }
}

final class _CustomerDetailPane extends StatelessWidget {
  const _CustomerDetailPane({
    required this.controller,
    required this.showBack,
  });

  final CustomersController controller;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final selectedId = controller.selectedCustomerId;
    if (selectedId == null) {
      return SafeContractsSurface(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.touch_app_outlined,
                  size: 40,
                  color: SafeContractsVisual.muted,
                ),
                const SizedBox(height: 10),
                Text(
                  l10n.t('Select a customer to view authorized details.'),
                  textAlign: TextAlign.center,
                ),
              ],
            ),
          ),
        ),
      );
    }

    final customer = controller.selectedCustomer;
    return SafeContractsSurface(
      margin:
          showBack ? const EdgeInsets.fromLTRB(14, 0, 14, 12) : EdgeInsets.zero,
      padding: EdgeInsets.zero,
      child: ListView(
        padding: const EdgeInsets.all(16),
        children: [
          if (showBack)
            Align(
              alignment: AlignmentDirectional.centerStart,
              child: TextButton.icon(
                onPressed: controller.closeCustomer,
                icon: const Icon(Icons.arrow_back),
                label: Text(l10n.t('Customers')),
              ),
            ),
          if (controller.detailState == CustomerDetailLoadState.loading) ...[
            const LinearProgressIndicator(),
            const SizedBox(height: 20),
            Text(l10n.loadingCustomer(selectedId)),
          ] else if (controller.detailState ==
              CustomerDetailLoadState.error) ...[
            _CustomersError(
              message: l10n.rawMessage(
                controller.detailErrorMessage ?? 'Unable to load customer.',
              ),
              onRetry: () => unawaited(controller.openCustomer(selectedId)),
            ),
          ] else if (customer != null) ...[
            Container(
              padding: const EdgeInsets.all(15),
              decoration: BoxDecoration(
                gradient: SafeContractsVisual.premiumHeaderGradient,
                borderRadius:
                    BorderRadius.circular(SafeContractsVisual.compactRadius),
              ),
              child: Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      gradient: SafeContractsVisual.roseGradient,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    alignment: Alignment.center,
                    child: Text(
                      customer.name.characters.first.toUpperCase(),
                      style: const TextStyle(
                        color: SafeContractsVisual.navyDeep,
                        fontSize: 19,
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
                          style:
                              Theme.of(context).textTheme.titleLarge?.copyWith(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                  ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          l10n.status(
                            customer.isActive ? 'active' : 'inactive',
                          ),
                          style: TextStyle(
                            color: customer.isActive
                                ? const Color(0xFF9EE2BC)
                                : Colors.white70,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 14),
            _CustomerField(
              icon: Icons.tag_rounded,
              label: l10n.t('Customer ID'),
              value: '${customer.id}',
            ),
            _CustomerField(
              icon: Icons.badge_outlined,
              label: l10n.t('Internal code'),
              value: customer.internalCode ?? '—',
            ),
            _CustomerField(
              icon: Icons.person_outline_rounded,
              label: l10n.t('Contact name'),
              value: customer.contactName ?? '—',
            ),
            _CustomerField(
              icon: Icons.email_outlined,
              label: l10n.t('Email'),
              value: customer.email ?? '—',
            ),
            _CustomerField(
              icon: Icons.phone_outlined,
              label: l10n.t('Phone'),
              value: customer.phone ?? '—',
            ),
            const SizedBox(height: 6),
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: SafeContractsVisual.navySoft,
                borderRadius: BorderRadius.circular(12),
              ),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Icon(
                    Icons.verified_user_outlined,
                    size: 18,
                    color: SafeContractsVisual.navy,
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: Text(
                      l10n.t(
                          'Only server-authorized customer fields are shown.'),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: SafeContractsVisual.navyDeep,
                          ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
}

final class _CustomerField extends StatelessWidget {
  const _CustomerField({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 10),
        child: Container(
          padding: const EdgeInsets.all(11),
          decoration: BoxDecoration(
            color: SafeContractsVisual.surface,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: SafeContractsVisual.outline),
          ),
          child: Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: SafeContractsVisual.navySoft,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  icon,
                  size: 18,
                  color: SafeContractsVisual.navy,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      label,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: SafeContractsVisual.muted,
                          ),
                    ),
                    const SizedBox(height: 2),
                    SelectableText(
                      value,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      );
}

final class _CustomersError extends StatelessWidget {
  const _CustomersError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: SafeContractsSurface(
            accent: SafeContractsVisual.red,
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Icon(
                  Icons.error_outline_rounded,
                  size: 44,
                  color: SafeContractsVisual.red,
                ),
                const SizedBox(height: 12),
                Text(message, textAlign: TextAlign.center),
                const SizedBox(height: 12),
                FilledButton(
                  onPressed: onRetry,
                  child: Text(context.scL10n.t('Retry')),
                ),
              ],
            ),
          ),
        ),
      );
}

final class _InlineCustomerError extends StatelessWidget {
  const _InlineCustomerError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(0, 6, 0, 8),
        child: Container(
          width: double.infinity,
          padding: const EdgeInsets.all(10),
          decoration: BoxDecoration(
            color: SafeContractsVisual.redSoft,
            borderRadius: BorderRadius.circular(12),
            border: Border.all(
              color: SafeContractsVisual.red.withValues(alpha: 0.30),
            ),
          ),
          child: Text(
            message,
            style: const TextStyle(color: SafeContractsVisual.redDeep),
          ),
        ),
      );
}
