import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_models.dart';
import '../suppliers/suppliers.dart';
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
  final _search = TextEditingController();
  final Map<int, Future<ContractMedia?>> _media = {};
  String _query = '';

  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  void didUpdateWidget(ContractsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      _media.clear();
      _search.clear();
      _query = '';
      unawaited(widget.controller.ensureLoaded());
    }
  }

  @override
  void dispose() {
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

  List<SafeContractsContract> _visibleContracts() {
    final contracts = widget.controller.currentPage?.contracts ?? const [];
    final query = _query.trim().toLowerCase();
    if (query.isEmpty) return contracts;
    return contracts.where((contract) {
      return <String>[
        contract.contractNumber,
        contract.displayCounterparty,
        contract.currencyCode,
        contract.status,
      ].join(' ').toLowerCase().contains(query);
    }).toList(growable: false);
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
        final visible = _visibleContracts();
        return SafeContractsBackdrop(
          child: Column(
            children: [
              _ContractsHeader(
                controller: widget.controller,
                customers: widget.customers,
                searchController: _search,
                query: _query,
                visibleCount: visible.length,
                onQueryChanged: (value) =>
                    setState(() => _query = value.trim()),
                onClearSearch: () {
                  _search.clear();
                  setState(() => _query = '');
                },
                onCreate: widget.controller.canCreateContract
                    ? () => unawaited(_openCreate())
                    : null,
              ),
              Expanded(
                child: _ContractsContent(
                  controller: widget.controller,
                  contracts: visible,
                  hasSearch: _query.isNotEmpty,
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
    required this.query,
    required this.visibleCount,
    required this.onQueryChanged,
    required this.onClearSearch,
    required this.onCreate,
  });

  final ContractsController controller;
  final List<CustomerOption> customers;
  final TextEditingController searchController;
  final String query;
  final int visibleCount;
  final ValueChanged<String> onQueryChanged;
  final VoidCallback onClearSearch;
  final VoidCallback? onCreate;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final busy = controller.state == ContractsLoadState.loading ||
        controller.mutationInFlight;
    final type = controller.filters.counterpartyType ?? '';
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
                Icons.description_outlined,
                color: SafeContractsVisual.navyDeep,
              ),
            ),
            title: ar ? 'العقود' : 'Contracts',
            subtitle: ar
                ? 'عقود العملاء والموردين مع اتجاه مالي واضح'
                : 'Customer and supplier contracts with clear financial direction',
            trailing: _HeaderBadge(value: '$visibleCount'),
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
                  leading: const Icon(Icons.search_rounded),
                  hintText: ar
                      ? 'بحث برقم العقد أو الطرف أو العملة'
                      : 'Search contract number, party or currency',
                  onChanged: onQueryChanged,
                  trailing: [
                    if (query.isNotEmpty)
                      IconButton(
                        onPressed: onClearSearch,
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
                    _Choice(
                      label: ar ? 'كل العقود' : 'All',
                      selected: type.isEmpty,
                      onTap: busy
                          ? null
                          : () => unawaited(
                                controller.selectCounterpartyType(null),
                              ),
                    ),
                    _Choice(
                      label: ar ? 'عقود العملاء' : 'Customers',
                      selected: type == 'customer',
                      onTap: busy
                          ? null
                          : () => unawaited(
                                controller.selectCounterpartyType('customer'),
                              ),
                    ),
                    _Choice(
                      label: ar ? 'عقود الموردين' : 'Suppliers',
                      selected: type == 'supplier',
                      onTap: busy
                          ? null
                          : () => unawaited(
                                controller.selectCounterpartyType('supplier'),
                              ),
                    ),
                    if (type == 'customer')
                      SizedBox(
                        width: 190,
                        child: DropdownButtonFormField<int>(
                          initialValue: controller.filters.customerId ?? 0,
                          isExpanded: true,
                          decoration: InputDecoration(
                            labelText: ar ? 'العميل' : 'Customer',
                            isDense: true,
                          ),
                          items: [
                            DropdownMenuItem(
                              value: 0,
                              child: Text(ar ? 'كل العملاء' : 'All customers'),
                            ),
                            ...customers.map(
                              (customer) => DropdownMenuItem(
                                value: customer.id,
                                child: Text(
                                  customer.name,
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            ),
                          ],
                          onChanged: busy
                              ? null
                              : (value) => unawaited(
                                    controller.selectCustomer(
                                      value == null || value == 0
                                          ? null
                                          : value,
                                    ),
                                  ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 8),
                Wrap(
                  spacing: 7,
                  runSpacing: 7,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    _StatusFilter(
                      status: '',
                      selected: controller.filters.status == null,
                      onTap: busy
                          ? null
                          : () => unawaited(controller.selectStatus(null)),
                    ),
                    for (final status in const [
                      'draft',
                      'active',
                      'completed',
                      'cancelled',
                    ])
                      _StatusFilter(
                        status: status,
                        selected: controller.filters.status == status,
                        onTap: busy
                            ? null
                            : () => unawaited(
                                  controller.selectStatus(status),
                                ),
                      ),
                    SizedBox(
                      width: 175,
                      child: DropdownButtonFormField<ContractSortOption>(
                        initialValue: controller.sort,
                        isExpanded: true,
                        decoration: InputDecoration(
                          labelText: ar ? 'الترتيب' : 'Sort',
                          isDense: true,
                        ),
                        items: ContractSortOption.values
                            .map(
                              (item) => DropdownMenuItem(
                                value: item,
                                child: Text(
                                  context.scL10n.t(item.label),
                                  overflow: TextOverflow.ellipsis,
                                ),
                              ),
                            )
                            .toList(growable: false),
                        onChanged: busy
                            ? null
                            : (value) {
                                if (value != null) {
                                  unawaited(controller.selectSort(value));
                                }
                              },
                      ),
                    ),
                    IconButton.filledTonal(
                      tooltip: context.scL10n.t('Refresh contracts'),
                      onPressed:
                          busy ? null : () => unawaited(controller.refresh()),
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                    if (onCreate != null)
                      FilledButton.icon(
                        onPressed: busy ? null : onCreate,
                        icon: const Icon(Icons.add_rounded),
                        label: Text(ar ? 'عقد جديد' : 'New contract'),
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
      return _StateMessage(
        icon: Icons.manage_search_rounded,
        message: hasSearch
            ? (context.scL10n.isArabic
                ? 'لا توجد نتائج بحث في الصفحة الحالية.'
                : 'No search matches on this page.')
            : context.scL10n.t('No contracts match the current filters.'),
        action: controller.refresh,
      );
    }

    return Column(
      children: [
        if (controller.state == ContractsLoadState.loading ||
            controller.mutationInFlight)
          const LinearProgressIndicator(minHeight: 2),
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
                    separatorBuilder: (_, __) => const SizedBox(height: 9),
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
                          style:
                              Theme.of(context).textTheme.labelSmall?.copyWith(
                                    color: SafeContractsVisual.muted,
                                  ),
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
          errorBuilder: (_, __, ___) => const _NeutralContractPlaceholder(),
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
            SafeContractsVisual.surfaceWarm
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
  const _ContractCreateSheet(
      {required this.controller, required this.customers});
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
                keyboardType:
                    const TextInputType.numberWithOptions(decimal: true),
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
                decoration:
                    InputDecoration(labelText: ar ? 'ملاحظات' : 'Notes'),
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
    final busy = controller.state == ContractsLoadState.loading;
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(14, 2, 14, 8),
        child: Wrap(
          spacing: 10,
          runSpacing: 8,
          alignment: WrapAlignment.center,
          crossAxisAlignment: WrapCrossAlignment.center,
          children: [
            OutlinedButton.icon(
              onPressed: busy || page.page <= 1
                  ? null
                  : () => unawaited(controller.previousPage()),
              icon: const Icon(
                Icons.chevron_left_rounded,
              ),
              label: Text(context.scL10n.t('Previous')),
            ),
            Text(context.scL10n.pageShown(page.page, page.contracts.length)),
            OutlinedButton.icon(
              onPressed: busy || !page.hasMore || page.page >= 5
                  ? null
                  : () => unawaited(controller.nextPage()),
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

final class _StatusFilter extends StatelessWidget {
  const _StatusFilter({
    required this.status,
    required this.selected,
    required this.onTap,
  });
  final String status;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final label = status.isEmpty
        ? (context.scL10n.isArabic ? 'كل الحالات' : 'All statuses')
        : context.scL10n.status(status);
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      showCheckmark: false,
      onSelected: onTap == null ? null : (_) => onTap!(),
    );
  }
}

final class _Choice extends StatelessWidget {
  const _Choice(
      {required this.label, required this.selected, required this.onTap});
  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: selected,
      showCheckmark: false,
      onSelected: onTap == null ? null : (_) => onTap!(),
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
        style:
            TextStyle(color: color, fontSize: 11, fontWeight: FontWeight.w800),
      ),
    );
  }
}

final class _InfoPill extends StatelessWidget {
  const _InfoPill(
      {required this.icon,
      required this.label,
      this.color = SafeContractsVisual.navy});
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
                  color: color, fontSize: 11, fontWeight: FontWeight.w800),
            ),
          ),
        ],
      ),
    );
  }
}

final class _HeaderBadge extends StatelessWidget {
  const _HeaderBadge({required this.value});
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
