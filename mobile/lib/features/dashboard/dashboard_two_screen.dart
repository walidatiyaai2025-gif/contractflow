import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../ui/safecontracts_design.dart';
import 'dashboard_controller.dart';
import 'dashboard_models.dart';

/// Additive executive dashboard inspired by the Alkenzy ADV 0.3.2 reference.
///
/// The original dashboard remains available. Every number rendered here comes
/// from the existing server-authoritative dashboard payload; the mobile client
/// does not recompute AP/AR ledgers.
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
        if (overview == null &&
            (controller.state == DashboardLoadState.idle ||
                controller.state == DashboardLoadState.loading)) {
          return const Center(child: CircularProgressIndicator());
        }
        if (overview == null) {
          return _DashboardTwoError(
            message: controller.errorMessage,
            onRetry: controller.refresh,
          );
        }

        return RefreshIndicator(
          onRefresh: controller.refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 96),
            children: [
              _ProgressOverview(kpis: overview.kpis, currency: currency),
              const SizedBox(height: 14),
              _ContractMix(overview: overview),
              const SizedBox(height: 14),
              _FinancialBars(kpis: overview.kpis, currency: currency),
              const SizedBox(height: 18),
              _LatestActivities(
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

final class _ProgressOverview extends StatelessWidget {
  const _ProgressOverview({required this.kpis, required this.currency});

  final DashboardKpis kpis;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final scheduled = double.tryParse(kpis.scheduledTotal) ?? 0;
    final collected = double.tryParse(kpis.collectedTotal) ?? 0;
    final progress = scheduled <= 0 ? 0.0 : (collected / scheduled).clamp(0, 1);

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(24),
        boxShadow: const [
          BoxShadow(
            color: Color(0x33092944),
            blurRadius: 28,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      ar ? 'نظرة عامة على التقدم' : 'Progress overview',
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                            color: Colors.white,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      ar
                          ? 'المؤشرات الفعلية من نظام العقود والدفعات'
                          : 'Live indicators from contracts and payments',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: Colors.white.withValues(alpha: 0.70),
                          ),
                    ),
                  ],
                ),
              ),
              _ProgressRing(progress: progress),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: _DarkMetric(
                  icon: Icons.folder_copy_outlined,
                  label: ar ? 'إجمالي العقود' : 'Total contracts',
                  value: '${kpis.contractCount}',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _DarkMetric(
                  icon: Icons.payments_outlined,
                  label: ar ? 'المحصل' : 'Collected',
                  value: context.scL10n.money(kpis.collectedTotal, currency),
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: [
              Expanded(
                child: _DarkMetric(
                  icon: Icons.event_note_outlined,
                  label: ar ? 'المجدول' : 'Scheduled',
                  value: context.scL10n.money(kpis.scheduledTotal, currency),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _DarkMetric(
                  icon: Icons.schedule_rounded,
                  label: ar ? 'المتبقي' : 'Remaining',
                  value: context.scL10n.money(kpis.remainingTotal, currency),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _ProgressRing extends StatelessWidget {
  const _ProgressRing({required this.progress});

  final double progress;

  @override
  Widget build(BuildContext context) {
    final percent = (progress * 100).round();
    return SizedBox(
      width: 78,
      height: 78,
      child: Stack(
        alignment: Alignment.center,
        children: [
          SizedBox(
            width: 72,
            height: 72,
            child: CircularProgressIndicator(
              value: progress,
              strokeWidth: 8,
              backgroundColor: Colors.white.withValues(alpha: 0.14),
              color: SafeContractsVisual.roseGold,
              strokeCap: StrokeCap.round,
            ),
          ),
          Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                '$percent%',
                style: const TextStyle(
                  color: Colors.white,
                  fontSize: 17,
                  fontWeight: FontWeight.w900,
                ),
              ),
              Text(
                context.scL10n.isArabic ? 'التقدم' : 'progress',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: 0.68),
                  fontSize: 9,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _DarkMetric extends StatelessWidget {
  const _DarkMetric({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 82),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.10),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: Colors.white.withValues(alpha: 0.11)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 17, color: SafeContractsVisual.champagne),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  label,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.72),
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: AlignmentDirectional.centerStart,
            child: Text(
              value,
              style: const TextStyle(
                color: Colors.white,
                fontSize: 19,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

final class _ContractMix extends StatelessWidget {
  const _ContractMix({required this.overview});

  final DashboardOverview overview;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final customerContracts =
        overview.contracts.where((contract) => contract.isCustomer).length;
    final supplierContracts =
        overview.contracts.where((contract) => contract.isSupplier).length;

    return SafeContractsSurface(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            ar ? 'كل أنواع العقود' : 'All contract types',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: SafeContractsVisual.navy,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            ar
                ? 'عقود العملاء والموردين تظهر معًا بدون إسقاط أي اتجاه مالي.'
                : 'Customer and supplier contracts are shown together without dropping either financial direction.',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
          const SizedBox(height: 13),
          Row(
            children: [
              Expanded(
                child: _TypeTile(
                  icon: Icons.business_outlined,
                  label: ar ? 'عقود العملاء' : 'Customer contracts',
                  value: customerContracts,
                  accent: SafeContractsVisual.green,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _TypeTile(
                  icon: Icons.local_shipping_outlined,
                  label: ar ? 'عقود الموردين' : 'Supplier contracts',
                  value: supplierContracts,
                  accent: SafeContractsVisual.roseGold,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _TypeTile extends StatelessWidget {
  const _TypeTile({
    required this.icon,
    required this.label,
    required this.value,
    required this.accent,
  });

  final IconData icon;
  final String label;
  final int value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: accent.withValues(alpha: 0.08),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: accent.withValues(alpha: 0.24)),
      ),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: accent, size: 20),
          ),
          const SizedBox(width: 10),
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
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
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

final class _FinancialBars extends StatelessWidget {
  const _FinancialBars({required this.kpis, required this.currency});

  final DashboardKpis kpis;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final values = <_BarValue>[
      _BarValue(
        label: ar ? 'المجدول' : 'Scheduled',
        value: double.tryParse(kpis.scheduledTotal) ?? 0,
        text: context.scL10n.money(kpis.scheduledTotal, currency),
        accent: SafeContractsVisual.navy,
      ),
      _BarValue(
        label: ar ? 'المحصل' : 'Collected',
        value: double.tryParse(kpis.collectedTotal) ?? 0,
        text: context.scL10n.money(kpis.collectedTotal, currency),
        accent: SafeContractsVisual.green,
      ),
      _BarValue(
        label: ar ? 'المتبقي' : 'Remaining',
        value: double.tryParse(kpis.remainingTotal) ?? 0,
        text: context.scL10n.money(kpis.remainingTotal, currency),
        accent: SafeContractsVisual.roseGold,
      ),
    ];
    final maxValue = values.fold<double>(
      0,
      (max, item) => math.max(max, item.value),
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
          const SizedBox(height: 4),
          Text(
            ar
                ? 'مقارنة بصرية مباشرة من أرقام لوحة التحكم.'
                : 'A direct visual comparison from dashboard figures.',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
          const SizedBox(height: 18),
          SizedBox(
            height: 170,
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: values.map((item) {
                final ratio = maxValue <= 0 ? 0.08 : (item.value / maxValue);
                final height = 32 + (92 * ratio.clamp(0.0, 1.0));
                return Expanded(
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 5),
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.end,
                      children: [
                        Text(
                          item.text,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        const SizedBox(height: 7),
                        AnimatedContainer(
                          duration: const Duration(milliseconds: 420),
                          curve: Curves.easeOutCubic,
                          height: height,
                          decoration: BoxDecoration(
                            color: item.accent.withValues(alpha: 0.82),
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(12),
                            ),
                          ),
                        ),
                        const SizedBox(height: 7),
                        Text(
                          item.label,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                color: SafeContractsVisual.muted,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
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

final class _BarValue {
  const _BarValue({
    required this.label,
    required this.value,
    required this.text,
    required this.accent,
  });

  final String label;
  final double value;
  final String text;
  final Color accent;
}

final class _LatestActivities extends StatelessWidget {
  const _LatestActivities({
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
              ? 'العقود والدفعات والتنبيهات الأقرب للحركة الحالية'
              : 'Contracts, payments and follow-ups closest to current activity',
        ),
        const SizedBox(height: 10),
        if (records.isEmpty)
          SafeContractsSurface(
            padding: const EdgeInsets.all(18),
            child: Row(
              children: [
                const Icon(
                  Icons.auto_awesome_outlined,
                  color: SafeContractsVisual.roseGold,
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(
                    ar ? 'لا توجد أنشطة مطابقة حاليًا.' : 'No matching activity yet.',
                  ),
                ),
              ],
            ),
          )
        else
          SafeContractsSurface(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
            child: Column(
              children: records.indexed.map((entry) {
                final index = entry.$1;
                final record = entry.$2;
                return Column(
                  children: [
                    _ActivityRow(
                      record: record,
                      currency: currency,
                      onTap: () {
                        switch (record.type) {
                          case DashboardRecordType.contract:
                            onOpenContract?.call(record.id);
                            break;
                          case DashboardRecordType.payment:
                          case DashboardRecordType.collection:
                          case DashboardRecordType.followUp:
                            onOpenPayments?.call();
                            break;
                        }
                      },
                    ),
                    if (index != records.length - 1)
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

final class _ActivityRow extends StatelessWidget {
  const _ActivityRow({
    required this.record,
    required this.currency,
    required this.onTap,
  });

  final DashboardRecord record;
  final MobileCurrencyConfig currency;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final icon = switch (record.type) {
      DashboardRecordType.contract => Icons.folder_copy_outlined,
      DashboardRecordType.payment => Icons.receipt_long_outlined,
      DashboardRecordType.collection => Icons.savings_outlined,
      DashboardRecordType.followUp => Icons.notifications_active_outlined,
    };
    final accent = switch (record.type) {
      DashboardRecordType.contract => SafeContractsVisual.navy,
      DashboardRecordType.payment => SafeContractsVisual.roseGold,
      DashboardRecordType.collection => SafeContractsVisual.green,
      DashboardRecordType.followUp => SafeContractsVisual.champagne,
    };
    final amount = record.remainingAmount ?? record.amount;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(14),
      child: Padding(
        padding: const EdgeInsets.symmetric(vertical: 11),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.12),
                borderRadius: BorderRadius.circular(13),
              ),
              child: Icon(icon, color: accent, size: 21),
            ),
            const SizedBox(width: 11),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    record.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    [
                      if (record.customerName != null) record.customerName!,
                      if (record.date != null) record.date!,
                      if (amount != null) context.scL10n.money(amount, currency),
                    ].join(' · '),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: 6),
            Icon(
              ar ? Icons.chevron_left_rounded : Icons.chevron_right_rounded,
              color: SafeContractsVisual.muted,
            ),
          ],
        ),
      ),
    );
  }
}

final class _DashboardTwoError extends StatelessWidget {
  const _DashboardTwoError({required this.message, required this.onRetry});

  final String? message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(22),
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
              const SizedBox(height: 10),
              Text(
                ar ? 'تعذر تحميل لوحة التحكم' : 'Unable to load dashboard',
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
              ),
              if (message != null) ...[
                const SizedBox(height: 6),
                Text(
                  context.scL10n.rawMessage(message!),
                  textAlign: TextAlign.center,
                ),
              ],
              const SizedBox(height: 14),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: Text(ar ? 'إعادة المحاولة' : 'Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
