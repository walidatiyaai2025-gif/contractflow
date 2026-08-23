import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_models.dart';
import '../payments/payments.dart';
import '../ui/safecontracts_design.dart';
import 'contract_media.dart';
import 'contracts.dart';

final class PremiumContractDetailsScreen extends StatefulWidget {
  const PremiumContractDetailsScreen({
    required this.repository,
    required this.contractId,
    required this.currency,
    super.key,
  });

  final ContractsRepository repository;
  final int contractId;
  final MobileCurrencyConfig currency;

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
    final results = await Future.wait<Object>([
      ContractMediaRepository(client).load(widget.contractId),
      PaymentsRepository(client).loadPage(
        page: 1,
        perPage: 100,
        filters: DashboardFilters(contractId: widget.contractId),
      ),
    ]);
    return _PremiumContractBundle(
      contract: contract,
      media: results[0] as ContractMedia,
      payments: (results[1] as PaymentPage).payments,
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
                    ),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: _refresh,
                      icon: const Icon(Icons.refresh_rounded),
                      label: Text(ar ? 'إعادة المحاولة' : 'Retry'),
                    ),
                  ],
                ),
              ),
            );
          }
          return _PremiumContractBody(
            bundle: snapshot.data!,
            currency: widget.currency,
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
  });

  final SafeContractsContract contract;
  final ContractMedia media;
  final List<SafeContractsPayment> payments;
}

final class _PremiumContractBody extends StatelessWidget {
  const _PremiumContractBody({
    required this.bundle,
    required this.currency,
    required this.onRefresh,
  });

  final _PremiumContractBundle bundle;
  final MobileCurrencyConfig currency;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final contract = bundle.contract;
    return DefaultTabController(
      length: 3,
      child: NestedScrollView(
        headerSliverBuilder: (context, innerBoxIsScrolled) => [
          SliverToBoxAdapter(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(14, 14, 14, 0),
              child: _ContractHero(bundle: bundle, currency: currency),
            ),
          ),
          SliverPersistentHeader(
            pinned: true,
            delegate: _TabHeaderDelegate(
              TabBar(
                labelColor: SafeContractsVisual.navy,
                unselectedLabelColor: SafeContractsVisual.muted,
                indicatorColor: SafeContractsVisual.roseGold,
                indicatorWeight: 3,
                tabs: [
                  Tab(text: ar ? 'ملخص العقد' : 'Summary'),
                  Tab(text: ar ? 'الدفعات' : 'Payments'),
                  Tab(text: ar ? 'المرفقات' : 'Attachments'),
                ],
              ),
            ),
          ),
        ],
        body: TabBarView(
          children: [
            RefreshIndicator(
              onRefresh: onRefresh,
              child: _SummaryTab(contract: contract, currency: currency),
            ),
            RefreshIndicator(
              onRefresh: onRefresh,
              child: _PaymentsTab(
                payments: bundle.payments,
                currency: currency,
              ),
            ),
            RefreshIndicator(
              onRefresh: onRefresh,
              child: _AttachmentsTab(media: bundle.media),
            ),
          ],
        ),
      ),
    );
  }
}

final class _ContractHero extends StatelessWidget {
  const _ContractHero({required this.bundle, required this.currency});

  final _PremiumContractBundle bundle;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final contract = bundle.contract;
    return Container(
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(22),
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
            height: 170,
            width: double.infinity,
            child: Stack(
              fit: StackFit.expand,
              children: [
                Image.network(
                  bundle.media.heroUrl,
                  fit: BoxFit.cover,
                  errorBuilder: (context, error, stackTrace) => Container(
                    color: SafeContractsVisual.navySoft,
                    alignment: Alignment.center,
                    child: const Icon(
                      Icons.business_rounded,
                      color: SafeContractsVisual.navy,
                      size: 54,
                    ),
                  ),
                ),
                const DecoratedBox(
                  decoration: BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topCenter,
                      end: Alignment.bottomCenter,
                      colors: [Colors.transparent, Color(0xB3092944)],
                    ),
                  ),
                ),
                PositionedDirectional(
                  start: 14,
                  end: 14,
                  bottom: 12,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.end,
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              contract.contractNumber,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                color: Colors.white,
                                fontSize: 20,
                                fontWeight: FontWeight.w900,
                              ),
                            ),
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
                      _StatusPill(status: contract.status),
                    ],
                  ),
                ),
              ],
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Row(
              children: [
                Expanded(
                  child: _HeroMetric(
                    label: ar ? 'قيمة العقد' : 'Contract value',
                    value: context.scL10n.money(
                      contract.baseValue ?? '0',
                      currency,
                    ),
                  ),
                ),
                Container(
                  width: 1,
                  height: 42,
                  color: SafeContractsVisual.outline,
                ),
                Expanded(
                  child: _HeroMetric(
                    label: ar ? 'نوع العقد' : 'Direction',
                    value: contract.isSupplier
                        ? (ar ? 'مستحق علينا' : 'Payable')
                        : (ar ? 'مستحق لنا' : 'Receivable'),
                  ),
                ),
              ],
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
    return Column(
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: SafeContractsVisual.muted,
              ),
        ),
        const SizedBox(height: 4),
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
    );
  }
}

final class _StatusPill extends StatelessWidget {
  const _StatusPill({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final normalized = status.toLowerCase();
    final color = switch (normalized) {
      'active' => SafeContractsVisual.green,
      'completed' => SafeContractsVisual.navy,
      'cancelled' => SafeContractsVisual.red,
      _ => SafeContractsVisual.roseGold,
    };
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.88),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        status,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

final class _SummaryTab extends StatelessWidget {
  const _SummaryTab({required this.contract, required this.currency});
  final SafeContractsContract contract;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final rows = <({IconData icon, String label, String value})>[
      (
        icon: Icons.account_balance_outlined,
        label: ar ? 'الطرف' : 'Counterparty',
        value: contract.displayCounterparty,
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
      (
        icon: Icons.payments_outlined,
        label: ar ? 'القيمة' : 'Value',
        value: context.scL10n.money(contract.baseValue ?? '0', currency),
      ),
      (
        icon: Icons.currency_exchange_rounded,
        label: ar ? 'العملة' : 'Currency',
        value: contract.currencyCode,
      ),
    ];
    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 80),
      children: rows
          .map(
            (row) => Padding(
              padding: const EdgeInsets.only(bottom: 10),
              child: SafeContractsSurface(
                padding: const EdgeInsets.all(14),
                child: Row(
                  children: [
                    Container(
                      width: 42,
                      height: 42,
                      decoration: BoxDecoration(
                        color: SafeContractsVisual.roseGoldSoft,
                        borderRadius: BorderRadius.circular(13),
                      ),
                      child: Icon(row.icon, color: SafeContractsVisual.navy),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            row.label,
                            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                  color: SafeContractsVisual.muted,
                                ),
                          ),
                          Text(
                            row.value,
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
            ),
          )
          .toList(growable: false),
    );
  }
}

final class _PaymentsTab extends StatelessWidget {
  const _PaymentsTab({required this.payments, required this.currency});
  final List<SafeContractsPayment> payments;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    if (payments.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [Text(ar ? 'لا توجد دفعات لهذا العقد.' : 'No payments for this contract.')],
      );
    }
    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 80),
      itemCount: payments.length,
      separatorBuilder: (_, __) => const SizedBox(height: 10),
      itemBuilder: (context, index) {
        final payment = payments[index];
        return SafeContractsSurface(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              _PaymentIcon(status: payment.status),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ar ? 'دفعة ${payment.sequenceNo}' : 'Payment ${payment.sequenceNo}',
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${payment.dueDate} · ${payment.status}',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: SafeContractsVisual.muted,
                          ),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Column(
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    context.scL10n.money(payment.originalAmount, currency),
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
                  ),
                  Text(
                    '${ar ? 'متبقي' : 'Left'} ${context.scL10n.money(payment.remainingAmount, currency)}',
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _PaymentIcon extends StatelessWidget {
  const _PaymentIcon({required this.status});
  final String status;

  @override
  Widget build(BuildContext context) {
    final normalized = status.toLowerCase();
    final color = switch (normalized) {
      'paid' => SafeContractsVisual.green,
      'overdue' => SafeContractsVisual.red,
      'partially_paid' => SafeContractsVisual.roseGold,
      'due' => SafeContractsVisual.red,
      _ => SafeContractsVisual.navy,
    };
    final icon = normalized == 'paid'
        ? Icons.check_circle_outline_rounded
        : normalized == 'overdue'
            ? Icons.warning_amber_rounded
            : Icons.schedule_rounded;
    return Container(
      width: 44,
      height: 44,
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.11),
        borderRadius: BorderRadius.circular(14),
      ),
      child: Icon(icon, color: color),
    );
  }
}

final class _AttachmentsTab extends StatelessWidget {
  const _AttachmentsTab({required this.media});
  final ContractMedia media;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    if (media.attachments.isEmpty) {
      return ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(20),
        children: [
          SafeContractsSurface(
            padding: const EdgeInsets.all(16),
            child: Row(
              children: [
                const Icon(
                  Icons.image_outlined,
                  color: SafeContractsVisual.roseGold,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    media.usesCompanyLogo
                        ? (ar
                            ? 'لا توجد مرفقات بعد؛ تم استخدام شعار الشركة كصورة للعقد.'
                            : 'No attachments yet; the company logo is used as the contract image.')
                        : (ar ? 'لا توجد مرفقات.' : 'No attachments.'),
                  ),
                ),
              ],
            ),
          ),
        ],
      );
    }
    return GridView.builder(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(14, 14, 14, 80),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: 0.9,
      ),
      itemCount: media.attachments.length,
      itemBuilder: (context, index) {
        final item = media.attachments[index];
        return SafeContractsSurface(
          padding: EdgeInsets.zero,
          child: ClipRRect(
            borderRadius: BorderRadius.circular(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(
                  child: item.isImage
                      ? Image.network(
                          item.url,
                          fit: BoxFit.cover,
                          errorBuilder: (_, __, ___) => const Center(
                            child: Icon(Icons.broken_image_outlined),
                          ),
                        )
                      : Container(
                          color: SafeContractsVisual.navySoft,
                          alignment: Alignment.center,
                          child: const Icon(
                            Icons.insert_drive_file_outlined,
                            color: SafeContractsVisual.navy,
                            size: 44,
                          ),
                        ),
                ),
                Padding(
                  padding: const EdgeInsets.all(10),
                  child: Text(
                    item.label.isEmpty ? item.role : item.label,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                ),
              ],
            ),
          ),
        );
      },
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
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) {
    return Material(
      color: SafeContractsVisual.background,
      child: tabBar,
    );
  }

  @override
  bool shouldRebuild(covariant _TabHeaderDelegate oldDelegate) =>
      oldDelegate.tabBar != tabBar;
}
