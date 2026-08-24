import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_models.dart';
import '../finance/finance.dart';
import '../payments/payments.dart';
import '../ui/safecontracts_design.dart';
import 'contract_media.dart';
import 'contracts.dart';

final class PremiumContractDetailsScreen extends StatefulWidget {
  const PremiumContractDetailsScreen({
    required this.repository,
    required this.contractId,
    required this.currency,
    this.onEditContract,
    this.onOpenLegacy,
    super.key,
  });

  final ContractsRepository repository;
  final int contractId;
  final MobileCurrencyConfig currency;
  final VoidCallback? onEditContract;
  final VoidCallback? onOpenLegacy;

  @override
  State<PremiumContractDetailsScreen> createState() =>
      _PremiumContractDetailsScreenState();
}

final class _PremiumContractDetailsScreenState
    extends State<PremiumContractDetailsScreen> {
  late Future<_PremiumContractBundle> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<_PremiumContractBundle> _load() async {
    final client = widget.repository.client;
    final contract = await widget.repository.loadContract(widget.contractId);

    ContractMedia? media;
    try {
      media = await ContractMediaRepository(client).load(widget.contractId);
    } on Object {
      // Contract details remain usable with a neutral image placeholder.
    }

    final paymentPage = await PaymentsRepository(client).loadPage(
      page: 1,
      perPage: 100,
      filters: DashboardFilters(contractId: widget.contractId),
    );

    var financeAuthorized = true;
    var finance = const <FinanceSummaryRow>[];
    try {
      final envelope = await client.get(
        'finance/summary',
        query: <String, String>{
          'contract_id': '${widget.contractId}',
          'financial_direction': contract.financialDirection,
          'page': '1',
          'per_page': '100',
          'sort': 'financial_direction',
          'order': 'asc',
        },
      );
      finance = List<FinanceSummaryRow>.unmodifiable(
        apiObjectList(
          envelope.data,
          'contract_finance.data',
        ).map(FinanceSummaryRow.fromData),
      );
    } on SafeContractsApiException catch (error) {
      if (error.statusCode != 403) rethrow;
      financeAuthorized = false;
    }

    return _PremiumContractBundle(
      contract: contract,
      media: media,
      payments: paymentPage.payments,
      finance: finance,
      financeAuthorized: financeAuthorized,
    );
  }

  Future<void> _refresh() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        backgroundColor: SafeContractsVisual.navy,
        foregroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        title: Text(ar ? 'تفاصيل العقد' : 'Contract details'),
        actions: [
          if (widget.onOpenLegacy != null)
            IconButton(
              tooltip: ar ? 'عرض التفاصيل القديمة' : 'Open legacy details',
              onPressed: widget.onOpenLegacy,
              icon: const Icon(Icons.layers_outlined),
            ),
          if (widget.onEditContract != null)
            IconButton(
              tooltip: ar ? 'تعديل العقد' : 'Edit contract',
              onPressed: widget.onEditContract,
              icon: const Icon(Icons.edit_outlined),
            ),
        ],
        flexibleSpace: const DecoratedBox(
          decoration: BoxDecoration(
            gradient: SafeContractsVisual.premiumHeaderGradient,
          ),
        ),
      ),
      body: FutureBuilder<_PremiumContractBundle>(
        future: _future,
        builder: (context, snapshot) {
          if (snapshot.connectionState != ConnectionState.done) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || snapshot.data == null) {
            return _LoadFailure(onRetry: _refresh);
          }
          return _PremiumContractBody(
            bundle: snapshot.data!,
            onRefresh: _refresh,
          );
        },
      ),
    );
  }
}

final class _PremiumContractBundle {
  const _PremiumContractBundle({
    required this.contract,
    required this.media,
    required this.payments,
    required this.finance,
    required this.financeAuthorized,
  });

  final SafeContractsContract contract;
  final ContractMedia? media;
  final List<SafeContractsPayment> payments;
  final List<FinanceSummaryRow> finance;
  final bool financeAuthorized;
}

final class _PremiumContractBody extends StatelessWidget {
  const _PremiumContractBody({required this.bundle, required this.onRefresh});

  final _PremiumContractBundle bundle;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return DefaultTabController(
      length: 4,
      child: NestedScrollView(
        headerSliverBuilder: (context, innerBoxIsScrolled) => [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 14, 0),
              child: _ContractHero(bundle: bundle),
            ),
          ),
          SliverPersistentHeader(
            pinned: true,
            delegate: _TabHeaderDelegate(
              TabBar(
                isScrollable: true,
                tabAlignment: TabAlignment.start,
                labelColor: SafeContractsVisual.navy,
                unselectedLabelColor: SafeContractsVisual.muted,
                indicatorColor: SafeContractsVisual.roseGold,
                indicatorWeight: 3,
                dividerColor: SafeContractsVisual.outline,
                tabs: [
                  Tab(text: ar ? 'الملخص' : 'Summary'),
                  Tab(text: ar ? 'الدفعات' : 'Payments'),
                  Tab(text: ar ? 'المرفقات' : 'Attachments'),
                  Tab(text: ar ? 'التفاصيل' : 'Details'),
                ],
              ),
            ),
          ),
        ],
        body: TabBarView(
          children: [
            RefreshIndicator(
              onRefresh: onRefresh,
              child: _SummaryTab(bundle: bundle),
            ),
            RefreshIndicator(
              onRefresh: onRefresh,
              child: _PaymentsTab(
                payments: bundle.payments,
                currencyCode: bundle.contract.currencyCode,
              ),
            ),
            RefreshIndicator(
              onRefresh: onRefresh,
              child: _AttachmentsTab(media: bundle.media),
            ),
            RefreshIndicator(
              onRefresh: onRefresh,
              child: _DetailsTab(contract: bundle.contract),
            ),
          ],
        ),
      ),
    );
  }
}

final class _ContractHero extends StatelessWidget {
  const _ContractHero({required this.bundle});
  final _PremiumContractBundle bundle;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final contract = bundle.contract;
    final progress = _termProgress(contract.startDate, contract.endDate);
    final media = bundle.media;
    return Container(
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
        border: Border.all(color: SafeContractsVisual.outline),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1F092944),
            blurRadius: 24,
            offset: Offset(0, 10),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        children: [
          SizedBox(
            height: 184,
            width: double.infinity,
            child: Stack(
              fit: StackFit.expand,
              children: [
                _HeroImage(media: media),
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Color(0xD2092944)],
                    ),
                  ),
                ),
                if (media?.usesCompanyLogo == true)
                  PositionedDirectional(
                    start: 12,
                    top: 12,
                    child: _HeroSourceBadge(
                      label: ar ? 'شعار الشركة' : 'Company logo',
                    ),
                  ),
                PositionedDirectional(
                  start: 14,
                  end: 14,
                  bottom: 13,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              contract.contractNumber,
                              maxLines: 2,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
                            const SizedBox(height: 2),
                            Text(
                              contract.displayCounterparty,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: TextStyle(
                                color: Colors.white.withValues(alpha: 0.82),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 8),
                      _StatusPill(status: contract.status, inverted: true),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              children: [
                Wrap(
                  spacing: 20,
                  runSpacing: 10,
                  alignment: WrapAlignment.spaceAround,
                  children: [
                    _HeroMetric(
                      label: ar ? 'قيمة العقد' : 'Contract value',
                      value: _money(
                        contract.baseValue ?? '0',
                        contract.currencyCode,
                      ),
                    ),
                    _HeroMetric(
                      label: ar ? 'الاتجاه المالي' : 'Direction',
                      value: contract.isSupplier
                          ? (ar ? 'مستحق علينا' : 'Payable')
                          : (ar ? 'مستحق لنا' : 'Receivable'),
                    ),
                    _HeroMetric(
                      label: ar ? 'عدد الدفعات' : 'Payments',
                      value: '${bundle.payments.length}',
                    ),
                  ],
                ),
                if (progress != null) ...[
                  const SizedBox(height: 13),
                  Row(
                    children: [
                      Text(
                        ar ? 'تقدم مدة العقد' : 'Term progress',
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                      ),
                      const Spacer(),
                      Text(
                        '${(progress * 100).round()}%',
                        style: const TextStyle(
                          color: SafeContractsVisual.navy,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 5),
                  ClipRRect(
                    borderRadius: BorderRadius.circular(99),
                    child: LinearProgressIndicator(
                      minHeight: 6,
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
        ],
      ),
    );
  }
}

final class _HeroImage extends StatelessWidget {
  const _HeroImage({required this.media});
  final ContractMedia? media;

  @override
  Widget build(BuildContext context) {
    final url = media?.heroUrl;
    if (url == null || url.isEmpty) return const _NeutralHeroPlaceholder();
    return Image.network(
      url,
      fit: BoxFit.cover,
      errorBuilder: (_, _, _) => const _NeutralHeroPlaceholder(),
    );
  }
}

final class _NeutralHeroPlaceholder extends StatelessWidget {
  const _NeutralHeroPlaceholder();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            SafeContractsVisual.navySoft,
            SafeContractsVisual.surfaceWarm,
          ],
        ),
      ),
      child: Center(
        child: Icon(
          Icons.description_outlined,
          color: SafeContractsVisual.navy,
          size: 58,
        ),
      ),
    );
  }
}

final class _HeroSourceBadge extends StatelessWidget {
  const _HeroSourceBadge({required this.label});
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      decoration: BoxDecoration(
        color: SafeContractsVisual.navyDeep.withValues(alpha: 0.80),
        borderRadius: BorderRadius.circular(12),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.business_rounded, color: Colors.white, size: 14),
          const SizedBox(width: 5),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

final class _HeroMetric extends StatelessWidget {
  const _HeroMetric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minWidth: 92, maxWidth: 180),
      child: Column(
        children: [
          Text(
            label,
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: SafeContractsVisual.muted),
          ),
          const SizedBox(height: 3),
          Text(
            value,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: SafeContractsVisual.navy,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }
}

final class _SummaryTab extends StatelessWidget {
  const _SummaryTab({required this.bundle});
  final _PremiumContractBundle bundle;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final contract = bundle.contract;
    final paid = bundle.payments.where((item) => item.status == 'paid').length;
    final overdue = bundle.payments
        .where((item) => item.status == 'overdue')
        .length;
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 80),
      children: [
        SafeContractsSectionTitle(
          title: ar ? 'ملخص الأعمال' : 'Business summary',
        ),
        const SizedBox(height: 8),
        SafeContractsSurface(
          elevated: false,
          padding: const EdgeInsets.all(13),
          child: Wrap(
            spacing: 18,
            runSpacing: 10,
            children: [
              _SummaryMetric(
                label: ar ? 'الطرف' : 'Counterparty',
                value: contract.displayCounterparty,
              ),
              _SummaryMetric(
                label: ar ? 'مدفوع بالكامل' : 'Paid payments',
                value: '$paid',
              ),
              _SummaryMetric(
                label: ar ? 'دفعات متأخرة' : 'Overdue payments',
                value: '$overdue',
              ),
              _SummaryMetric(
                label: ar ? 'العملة' : 'Currency',
                value: contract.currencyCode,
              ),
            ],
          ),
        ),
        const SizedBox(height: 14),
        SafeContractsSectionTitle(
          title: ar ? 'الملخص المالي من الخادم' : 'Server financial summary',
        ),
        const SizedBox(height: 8),
        if (!bundle.financeAuthorized)
          _Notice(
            text: ar
                ? 'الملخص المالي غير متاح ضمن صلاحيات هذه الجلسة.'
                : 'Financial summary is outside this session’s permissions.',
          )
        else if (bundle.finance.isEmpty)
          _Notice(
            text: ar
                ? 'لا توجد التزامات مالية مجدولة لهذا العقد.'
                : 'No scheduled financial obligations for this contract.',
          )
        else
          ...bundle.finance.map(
            (row) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: SafeContractsSurface(
                elevated: false,
                accent: contract.isSupplier
                    ? SafeContractsVisual.amber
                    : SafeContractsVisual.green,
                padding: const EdgeInsets.all(13),
                child: Wrap(
                  spacing: 18,
                  runSpacing: 10,
                  children: [
                    _SummaryMetric(
                      label: ar ? 'الإجمالي' : 'Original',
                      value: _money(row.originalTotal, row.currencyCode),
                    ),
                    _SummaryMetric(
                      label: ar ? 'تمت تسويته' : 'Settled',
                      value: _money(row.settledTotal, row.currencyCode),
                    ),
                    _SummaryMetric(
                      label: ar ? 'القائم' : 'Outstanding',
                      value: _money(row.outstandingTotal, row.currencyCode),
                    ),
                    _SummaryMetric(
                      label: ar ? 'المتأخر' : 'Overdue',
                      value: _money(row.overdueTotal, row.currencyCode),
                    ),
                  ],
                ),
              ),
            ),
          ),
        const SizedBox(height: 14),
        SafeContractsSectionTitle(title: ar ? 'الفترة' : 'Term'),
        const SizedBox(height: 8),
        SafeContractsSurface(
          elevated: false,
          padding: const EdgeInsets.all(13),
          child: Wrap(
            spacing: 18,
            runSpacing: 10,
            children: [
              _SummaryMetric(
                label: ar ? 'البداية' : 'Start',
                value: contract.startDate ?? '—',
              ),
              _SummaryMetric(
                label: ar ? 'النهاية' : 'End',
                value: contract.endDate ?? '—',
              ),
              _SummaryMetric(
                label: ar ? 'المدة' : 'Duration',
                value: _durationLabel(
                  context,
                  contract.startDate,
                  contract.endDate,
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

final class _PaymentsTab extends StatelessWidget {
  const _PaymentsTab({required this.payments, required this.currencyCode});
  final List<SafeContractsPayment> payments;
  final String currencyCode;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    if (payments.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          _Notice(
            text: ar
                ? 'لا توجد دفعات لهذا العقد.'
                : 'No payments for this contract.',
          ),
        ],
      );
    }
    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 80),
      itemCount: payments.length,
      separatorBuilder: (_, _) => const SizedBox(height: 9),
      itemBuilder: (context, index) {
        final payment = payments[index];
        return SafeContractsSurface(
          padding: const EdgeInsets.all(13),
          accent: safeContractsStatusColor(payment.status),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _PaymentIcon(status: payment.status),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Wrap(
                      spacing: 7,
                      runSpacing: 5,
                      crossAxisAlignment: WrapCrossAlignment.center,
                      children: [
                        Text(
                          ar
                              ? 'دفعة ${payment.sequenceNo}'
                              : 'Payment ${payment.sequenceNo}',
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w900),
                        ),
                        _StatusPill(status: payment.status),
                      ],
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '${ar ? 'الاستحقاق' : 'Due'}: ${payment.dueDate}',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                    ),
                    if (payment.expectedPaymentDate != null)
                      Text(
                        '${ar ? 'المتوقع' : 'Expected'}: ${payment.expectedPaymentDate}',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                      ),
                    if (payment.reference != null)
                      Text(
                        payment.reference!,
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 124),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.end,
                  children: [
                    Text(
                      _money(payment.originalAmount, currencyCode),
                      textAlign: TextAlign.end,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${ar ? 'متبقي' : 'Left'} ${_money(payment.remainingAmount, currencyCode)}',
                      textAlign: TextAlign.end,
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _AttachmentsTab extends StatelessWidget {
  const _AttachmentsTab({required this.media});
  final ContractMedia? media;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final attachments = media?.attachments ?? const <ContractAttachment>[];
    if (attachments.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          _Notice(
            text: media?.usesCompanyLogo == true
                ? (ar
                      ? 'لا توجد مرفقات؛ يتم استخدام شعار الشركة كصورة افتراضية للعقد.'
                      : 'No attachments; the company logo is used as the contract fallback image.')
                : (ar
                      ? 'لا توجد مرفقات متاحة لهذا العقد.'
                      : 'No attachments are available for this contract.'),
          ),
        ],
      );
    }
    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 80),
      itemCount: attachments.length,
      separatorBuilder: (_, _) => const SizedBox(height: 9),
      itemBuilder: (context, index) {
        final item = attachments[index];
        return SafeContractsSurface(
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Container(
                width: 52,
                height: 52,
                decoration: BoxDecoration(
                  color: item.isImage
                      ? SafeContractsVisual.roseGoldSoft
                      : SafeContractsVisual.navySoft,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  _fileIcon(item.mimeType),
                  color: item.isImage
                      ? SafeContractsVisual.roseGoldDark
                      : SafeContractsVisual.navy,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.label.isEmpty ? item.role : item.label,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      <String>[
                        if (item.mimeType.isNotEmpty) item.mimeType,
                        if (item.createdAt.isNotEmpty) item.createdAt,
                      ].join(' • '),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                    ),
                  ],
                ),
              ),
              IconButton.filledTonal(
                tooltip: ar ? 'نسخ رابط المرفق' : 'Copy attachment link',
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: item.url));
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text(
                        ar ? 'تم نسخ رابط المرفق.' : 'Attachment link copied.',
                      ),
                    ),
                  );
                },
                icon: const Icon(Icons.link_rounded),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _DetailsTab extends StatelessWidget {
  const _DetailsTab({required this.contract});
  final SafeContractsContract contract;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final rows = <({IconData icon, String label, String value})>[
      (
        icon: Icons.tag_rounded,
        label: ar ? 'رقم العقد' : 'Contract number',
        value: contract.contractNumber,
      ),
      (
        icon: contract.isSupplier
            ? Icons.local_shipping_outlined
            : Icons.person_outline,
        label: ar ? 'الطرف' : 'Counterparty',
        value: contract.displayCounterparty,
      ),
      (
        icon: Icons.swap_horiz_rounded,
        label: ar ? 'نوع الطرف' : 'Counterparty type',
        value: contract.isSupplier
            ? (ar ? 'مورد' : 'Supplier')
            : (ar ? 'عميل' : 'Customer'),
      ),
      (
        icon: Icons.account_balance_wallet_outlined,
        label: ar ? 'الاتجاه المالي' : 'Financial direction',
        value: contract.isSupplier
            ? (ar ? 'مستحق علينا' : 'Payable')
            : (ar ? 'مستحق لنا' : 'Receivable'),
      ),
      (
        icon: Icons.circle_outlined,
        label: ar ? 'الحالة' : 'Status',
        value: context.scL10n.status(contract.status),
      ),
      (
        icon: Icons.payments_outlined,
        label: ar ? 'قيمة العقد' : 'Contract value',
        value: _money(contract.baseValue ?? '0', contract.currencyCode),
      ),
      (
        icon: Icons.currency_exchange_rounded,
        label: ar ? 'العملة' : 'Currency',
        value: contract.currencyCode,
      ),
      (
        icon: Icons.calendar_month_outlined,
        label: ar ? 'تاريخ البداية' : 'Start date',
        value: contract.startDate ?? '—',
      ),
      (
        icon: Icons.event_available_outlined,
        label: ar ? 'تاريخ النهاية' : 'End date',
        value: contract.endDate ?? '—',
      ),
      if (contract.accountantUserId != null)
        (
          icon: Icons.badge_outlined,
          label: ar ? 'مسؤول العقد' : 'Accountant ID',
          value: '${contract.accountantUserId}',
        ),
      if (contract.isArchived)
        (
          icon: Icons.archive_outlined,
          label: ar ? 'الأرشفة' : 'Archive state',
          value: context.scL10n.status('archived'),
        ),
    ];
    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 80),
      itemCount: rows.length,
      separatorBuilder: (_, _) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final row = rows[index];
        return SafeContractsSurface(
          elevated: false,
          padding: const EdgeInsets.all(12),
          child: Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: SafeContractsVisual.roseGoldSoft,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(row.icon, color: SafeContractsVisual.navy),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      row.label,
                      style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                    ),
                    Text(
                      row.value,
                      maxLines: 3,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(fontWeight: FontWeight.w800),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _SummaryMetric extends StatelessWidget {
  const _SummaryMetric({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return ConstrainedBox(
      constraints: const BoxConstraints(minWidth: 100, maxWidth: 210),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: Theme.of(
              context,
            ).textTheme.labelSmall?.copyWith(color: SafeContractsVisual.muted),
          ),
          const SizedBox(height: 2),
          Text(
            value,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(fontWeight: FontWeight.w900),
          ),
        ],
      ),
    );
  }
}

final class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status, this.inverted = false});
  final String status;
  final bool inverted;

  @override
  Widget build(BuildContext context) {
    final color = safeContractsStatusColor(status);
    return Container(
      constraints: const BoxConstraints(maxWidth: 110),
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      decoration: BoxDecoration(
        color: inverted
            ? color.withValues(alpha: 0.90)
            : safeContractsStatusSoftColor(status),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        context.scL10n.status(status),
        maxLines: 1,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(
          color: inverted ? Colors.white : color,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

final class _PaymentIcon extends StatelessWidget {
  const _PaymentIcon({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final color = safeContractsStatusColor(status);
    final normalized = status.toLowerCase();
    final icon = normalized == 'paid'
        ? Icons.check_circle_outline_rounded
        : normalized == 'overdue'
        ? Icons.warning_amber_rounded
        : Icons.schedule_rounded;
    return Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(
        color: safeContractsStatusSoftColor(status),
        borderRadius: BorderRadius.circular(13),
      ),
      child: Icon(icon, color: color),
    );
  }
}

final class _Notice extends StatelessWidget {
  const _Notice({required this.text});
  final String text;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: SafeContractsVisual.navySoft,
        borderRadius: BorderRadius.circular(13),
      ),
      child: Text(
        text,
        style: const TextStyle(color: SafeContractsVisual.navyDeep),
      ),
    );
  }
}

final class _LoadFailure extends StatelessWidget {
  const _LoadFailure({required this.onRetry});
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(
              Icons.error_outline_rounded,
              color: SafeContractsVisual.red,
              size: 42,
            ),
            const SizedBox(height: 10),
            Text(
              ar
                  ? 'تعذر تحميل تفاصيل العقد.'
                  : 'Unable to load contract details.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: onRetry,
              icon: const Icon(Icons.refresh_rounded),
              label: Text(ar ? 'إعادة المحاولة' : 'Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

final class _TabHeaderDelegate extends SliverPersistentHeaderDelegate {
  _TabHeaderDelegate(this.tabBar);
  final TabBar tabBar;

  @override
  double get minExtent => tabBar.preferredSize.height;
  @override
  double get maxExtent => tabBar.preferredSize.height;

  @override
  Widget build(
    BuildContext context,
    double shrinkOffset,
    bool overlapsContent,
  ) {
    return Material(color: SafeContractsVisual.background, child: tabBar);
  }

  @override
  bool shouldRebuild(covariant _TabHeaderDelegate oldDelegate) =>
      oldDelegate.tabBar != tabBar;
}

IconData _fileIcon(String mimeType) {
  final mime = mimeType.toLowerCase();
  if (mime.startsWith('image/')) return Icons.image_outlined;
  if (mime.contains('pdf')) return Icons.picture_as_pdf_outlined;
  if (mime.contains('sheet') || mime.contains('excel')) {
    return Icons.table_chart_outlined;
  }
  if (mime.contains('word') || mime.contains('document')) {
    return Icons.description_outlined;
  }
  return Icons.insert_drive_file_outlined;
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

String _durationLabel(
  BuildContext context,
  String? startDate,
  String? endDate,
) {
  if (startDate == null || endDate == null) return '—';
  final start = DateTime.tryParse(startDate);
  final end = DateTime.tryParse(endDate);
  if (start == null || end == null || end.isBefore(start)) return '—';
  final days = end.difference(start).inDays + 1;
  return context.scL10n.isArabic ? '$days يوم' : '$days days';
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
