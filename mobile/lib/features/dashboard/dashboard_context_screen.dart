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
        return Column(
          children: [
            if (entity != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
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
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: SafeContractsVisual.navySoft,
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
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: SafeContractsVisual.muted),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
