import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../ui/safecontracts_design.dart';
import 'dashboard_controller.dart';
import 'dashboard_models.dart';

final class DashboardTwoScreen extends StatelessWidget {
  const DashboardTwoScreen({
    required this.controller,
    required this.currency,
    this.onOpenPayments,
    this.onOpenContract,
    super.key,
  });

  final DashboardController controller;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;
  final ValueChanged<int>? onOpenContract;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final overview = controller.overview;
        if (overview == null) {
          if (controller.state == DashboardLoadState.error) {
            return Center(
              child: FilledButton.icon(
                onPressed: controller.refresh,
                icon: const Icon(Icons.refresh_rounded),
                label: Text(context.scL10n.t('Retry')),
              ),
            );
          }
          return const Center(child: CircularProgressIndicator());
        }
        return RefreshIndicator(
          onRefresh: controller.refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 96),
            children: [
              _Hero(kpis: overview.kpis, currency: currency),
              const SizedBox(height: 14),
              _ContractKinds(overview: overview),
              const SizedBox(height: 14),
              _Bars(kpis: overview.kpis, currency: currency),
              const SizedBox(height: 16),
              _Activities(
                lists: controller.lists,
                currency: currency,
                onOpenPayments: onOpenPayments,
                onOpenContract: onOpenContract,
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _Hero extends StatelessWidget {
  const _Hero({required this.kpis, required this.currency});
  final DashboardKpis kpis;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final scheduled = double.tryParse(kpis.scheduledTotal) ?? 0.0;
    final collected = double.tryParse(kpis.collectedTotal) ?? 0.0;
    final progress = scheduled <= 0.0
        ? 0.0
        : (collected / scheduled).clamp(0.0, 1.0).toDouble();
    final metrics = <({String label, String value, IconData icon})>[
      (
        label: ar ? 'إجمالي العقود' : 'Total contracts',
        value: '${kpis.contractCount}',
        icon: Icons.folder_copy_outlined,
      ),
      (
        label: ar ? 'المحصل' : 'Collected',
        value: context.scL10n.money(kpis.collectedTotal, currency),
        icon: Icons.payments_outlined,
      ),
      (
        label: ar ? 'المجدول' : 'Scheduled',
        value: context.scL10n.money(kpis.scheduledTotal, currency),
        icon: Icons.event_note_outlined,
      ),
      (
        label: ar ? 'المتبقي' : 'Remaining',
        value: context.scL10n.money(kpis.remainingTotal, currency),
        icon: Icons.schedule_rounded,
      ),
    ];
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(24),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  ar ? 'نظرة عامة على التقدم' : 'Progress overview',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                ),
              ),
              SizedBox(
                width: 66,
                height: 66,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    CircularProgressIndicator(
                      value: progress,
                      strokeWidth: 7,
                      strokeCap: StrokeCap.round,
                      color: SafeContractsVisual.roseGold,
                      backgroundColor: Colors.white.withValues(alpha: 0.14),
                    ),
                    Text(
                      '${(progress * 100).round()}%',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            mainAxisSpacing: 10,
            crossAxisSpacing: 10,
            childAspectRatio: 1.8,
            children: metrics
                .map(
                  (item) => Container(
                    padding: const EdgeInsets.all(11),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.10),
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(
                          item.icon,
                          color: SafeContractsVisual.champagne,
                          size: 18,
                        ),
                        const Spacer(),
                        Text(
                          item.value,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: const TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          item.label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: 0.70),
                            fontSize: 11,
                          ),
                        ),
                      ],
                    ),
                  ),
                )
                .toList(growable: false),
          ),
        ],
      ),
    );
  }
}

final class _ContractKinds extends StatelessWidget {
  const _ContractKinds({required this.overview});
  final DashboardOverview overview;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final customerCount =
        overview.contracts.where((item) => item.isCustomer).length;
    final supplierCount =
        overview.contracts.where((item) => item.isSupplier).length;
    return SafeContractsSurface(
      padding: const EdgeInsets.all(16),
      child: Row(
        children: [
          Expanded(
            child: _Kind(
              label: ar ? 'عقود العملاء' : 'Customer contracts',
              value: customerCount,
              icon: Icons.business_outlined,
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: _Kind(
              label: ar ? 'عقود الموردين' : 'Supplier contracts',
              value: supplierCount,
              icon: Icons.local_shipping_outlined,
            ),
          ),
        ],
      ),
    );
  }
}

final class _Kind extends StatelessWidget {
  const _Kind({required this.label, required this.value, required this.icon});
  final String label;
  final int value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: SafeContractsVisual.navySoft,
        borderRadius: BorderRadius.circular(15),
      ),
      child: Row(
        children: [
          Icon(icon, color: SafeContractsVisual.navy),
          const SizedBox(width: 9),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  '$value',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                ),
                Text(
                  label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _Bars extends StatelessWidget {
  const _Bars({required this.kpis, required this.currency});
  final DashboardKpis kpis;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final items = <({String label, String raw, Color color})>[
      (
        label: ar ? 'المجدول' : 'Scheduled',
        raw: kpis.scheduledTotal,
        color: SafeContractsVisual.navy,
      ),
      (
        label: ar ? 'المحصل' : 'Collected',
        raw: kpis.collectedTotal,
        color: SafeContractsVisual.green,
      ),
      (
        label: ar ? 'المتبقي' : 'Remaining',
        raw: kpis.remainingTotal,
        color: SafeContractsVisual.roseGold,
      ),
    ];
    final maximum = items.fold<double>(
      0.0,
      (value, item) => math.max(value, double.tryParse(item.raw) ?? 0.0),
    );
    return SafeContractsSurface(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            ar ? 'المؤشرات المالية' : 'Financial pulse',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: SafeContractsVisual.navy,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 12),
          SizedBox(
            height: 145,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: items.map((item) {
                final value = double.tryParse(item.raw) ?? 0.0;
                final ratio = maximum <= 0.0 ? 0.08 : value / maximum;
                final height = (28.0 + 78.0 * ratio.clamp(0.0, 1.0)).toDouble();
                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 5),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Text(
                          context.scL10n.money(item.raw, currency),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelSmall,
                        ),
                        const SizedBox(height: 5),
                        Container(
                          height: height,
                          decoration: BoxDecoration(
                            color: item.color.withValues(alpha: 0.84),
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(10),
                            ),
                          ),
                        ),
                        const SizedBox(height: 5),
                        Text(item.label,
                            style: Theme.of(context).textTheme.labelSmall),
                      ],
                    ),
                  ),
                );
              }).toList(growable: false),
            ),
          ),
        ],
      ),
    );
  }
}

final class _Activities extends StatelessWidget {
  const _Activities({
    required this.lists,
    required this.currency,
    required this.onOpenPayments,
    required this.onOpenContract,
  });
  final DashboardLists? lists;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;
  final ValueChanged<int>? onOpenContract;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final records = <DashboardRecord>[
      ...?lists?.payments.take(2),
      ...?lists?.contracts.take(2),
      ...?lists?.followUps.take(1),
    ].take(5).toList(growable: false);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SafeContractsSectionTitle(
          title: ar ? 'آخر الأنشطة' : 'Latest activity',
          subtitle: ar
              ? 'العقود والدفعات والمتابعات'
              : 'Contracts, payments and follow-ups',
        ),
        const SizedBox(height: 8),
        SafeContractsSurface(
          padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
          child: records.isEmpty
              ? Padding(
                  padding: const EdgeInsets.all(12),
                  child:
                      Text(ar ? 'لا توجد أنشطة حالياً.' : 'No activity yet.'),
                )
              : Column(
                  children: records.indexed.map((entry) {
                    final record = entry.$2;
                    final amount = record.remainingAmount ?? record.amount;
                    return Column(
                      children: [
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          leading: const CircleAvatar(
                            backgroundColor: SafeContractsVisual.roseGoldSoft,
                            child: Icon(
                              Icons.auto_awesome_outlined,
                              color: SafeContractsVisual.navy,
                            ),
                          ),
                          title: Text(
                            record.title,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          subtitle: Text(
                            [
                              if (record.customerName != null)
                                record.customerName!,
                              if (record.date != null) record.date!,
                              if (amount != null)
                                context.scL10n.money(amount, currency),
                            ].join(' · '),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                          trailing: Icon(
                            ar
                                ? Icons.chevron_left_rounded
                                : Icons.chevron_right_rounded,
                          ),
                          onTap: () {
                            if (record.type == DashboardRecordType.contract) {
                              onOpenContract?.call(record.id);
                            } else {
                              onOpenPayments?.call();
                            }
                          },
                        ),
                        if (entry.$1 != records.length - 1)
                          const Divider(height: 1),
                      ],
                    );
                  }).toList(growable: false),
                ),
        ),
      ],
    );
  }
}
