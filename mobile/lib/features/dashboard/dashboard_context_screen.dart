import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../ui/safecontracts_design.dart';
import 'dashboard_controller.dart';
import 'dashboard_models.dart';
import 'dashboard_screen.dart';

final class DashboardContextScreen extends StatelessWidget {
  const DashboardContextScreen({
    required this.controller,
    required this.currency,
    this.onOpenPayments,
    super.key,
  });

  final DashboardController controller;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final entity = _selectedEntity(controller);
        final contract = _selectedContract(controller);
        final overview = controller.overview;
        return Column(
          children: [
            if (overview != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: _PremiumDashboardOverview(
                  kpis: overview.kpis,
                  currency: currency,
                ),
              ),
            if (entity != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                child: _EntityContextBanner(
                  entityName: entity,
                  contractNumber: contract,
                ),
              ),
            Expanded(
              child: DashboardScreen(
                controller: controller,
                currency: currency,
                onOpenPayments: onOpenPayments,
              ),
            ),
          ],
        );
      },
    );
  }

  String? _selectedEntity(DashboardController controller) {
    final customerId = controller.filters.customerId;
    if (customerId == null) return null;

    final remembered = controller.selectedCustomerName?.trim();
    if (remembered != null && remembered.isNotEmpty) return remembered;

    final customers =
        controller.overview?.customers ?? const <CustomerOption>[];
    for (final customer in customers) {
      if (customer.id == customerId) return customer.name;
    }

    final lists = controller.lists;
    if (lists != null) {
      final records = <DashboardRecord>[
        ...lists.contracts,
        ...lists.payments,
        ...lists.collections,
        ...lists.followUps,
      ];
      for (final record in records) {
        final name = record.customerName?.trim();
        if (name != null && name.isNotEmpty) return name;
      }
    }
    return '#$customerId';
  }

  String? _selectedContract(DashboardController controller) {
    final contractId = controller.filters.contractId;
    if (contractId == null) return null;
    final remembered = controller.selectedContractNumber?.trim();
    if (remembered != null && remembered.isNotEmpty) return remembered;
    for (final contract in controller.availableContracts) {
      if (contract.id == contractId) return contract.contractNumber;
    }
    return '#$contractId';
  }
}

final class _PremiumDashboardOverview extends StatelessWidget {
  const _PremiumDashboardOverview({
    required this.kpis,
    required this.currency,
  });

  final DashboardKpis kpis;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final scheduled = double.tryParse(kpis.scheduledTotal) ?? 0;
    final collected = double.tryParse(kpis.collectedTotal) ?? 0;
    final completion = scheduled <= 0 ? 0.0 : (collected / scheduled).clamp(0, 1);
    final completionPercent = (completion * 100).round();

    return Column(
      children: [
        SafeContractsPremiumHeader(
          title: l10n.isArabic
              ? 'نظرة عامة على الأداء المالي'
              : 'Financial performance overview',
          subtitle: l10n.isArabic
              ? 'ملخص تنفيذي سريع للعقود والدفعات والتحصيلات الحالية.'
              : 'A concise executive view of current contracts, payments and collections.',
          leading: const Icon(
            Icons.insights_rounded,
            color: SafeContractsVisual.roseGoldSoft,
            size: 30,
          ),
          trailing: _ProgressBadge(
            percent: completionPercent,
            label: l10n.isArabic ? 'التحصيل' : 'collected',
          ),
        ),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final width = constraints.maxWidth;
            final columns = width >= 720 ? 4 : 2;
            final gap = 10.0;
            final cardWidth = (width - (gap * (columns - 1))) / columns;
            return Wrap(
              spacing: gap,
              runSpacing: gap,
              children: [
                SizedBox(
                  width: cardWidth,
                  child: SafeContractsMetricCard(
                    label: l10n.isArabic ? 'إجمالي العقود' : 'Total contracts',
                    value: '${kpis.contractCount}',
                    icon: Icons.folder_copy_outlined,
                    accent: SafeContractsVisual.champagne,
                  ),
                ),
                SizedBox(
                  width: cardWidth,
                  child: SafeContractsMetricCard(
                    label: l10n.isArabic ? 'المجدول' : 'Scheduled',
                    value: l10n.money(kpis.scheduledTotal, currency),
                    icon: Icons.calendar_month_outlined,
                    accent: SafeContractsVisual.navy,
                  ),
                ),
                SizedBox(
                  width: cardWidth,
                  child: SafeContractsMetricCard(
                    label: l10n.isArabic ? 'المحصل' : 'Collected',
                    value: l10n.money(kpis.collectedTotal, currency),
                    icon: Icons.south_west_rounded,
                    accent: SafeContractsVisual.green,
                  ),
                ),
                SizedBox(
                  width: cardWidth,
                  child: SafeContractsMetricCard(
                    label: l10n.isArabic ? 'المتبقي' : 'Remaining',
                    value: l10n.money(kpis.remainingTotal, currency),
                    icon: Icons.schedule_rounded,
                    accent: SafeContractsVisual.roseGold,
                  ),
                ),
              ],
            );
          },
        ),
      ],
    );
  }
}

final class _ProgressBadge extends StatelessWidget {
  const _ProgressBadge({required this.percent, required this.label});

  final int percent;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 66,
      height: 66,
      alignment: Alignment.center,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: SafeContractsVisual.roseGold, width: 5),
        color: Colors.white.withValues(alpha: 0.08),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Text(
            '$percent%',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.w900,
            ),
          ),
          Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
              color: Colors.white.withValues(alpha: 0.72),
              fontSize: 9,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}

final class _EntityContextBanner extends StatelessWidget {
  const _EntityContextBanner({
    required this.entityName,
    required this.contractNumber,
  });

  final String entityName;
  final String? contractNumber;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final isArabic = l10n.isArabic;
    return SafeContractsSurface(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      accent: SafeContractsVisual.roseGold,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: SafeContractsVisual.roseGoldSoft,
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(
              Icons.business_rounded,
              color: SafeContractsVisual.navy,
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isArabic ? 'بيانات الجهة' : 'Dashboard entity',
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: SafeContractsVisual.muted,
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 2),
                Text(
                  entityName,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: SafeContractsVisual.navy,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 3),
                Text(
                  contractNumber == null
                      ? (isArabic
                          ? 'كل الأرقام والمؤشرات أدناه مفلترة لهذه الجهة.'
                          : 'All figures and indicators below are filtered for this entity.')
                      : (isArabic
                          ? 'العقد: $contractNumber · كل البيانات أدناه ضمن هذا النطاق.'
                          : 'Contract: $contractNumber · all data below uses this scope.'),
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
