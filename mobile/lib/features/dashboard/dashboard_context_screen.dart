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
                padding: const EdgeInsets.fromLTRB(10, 8, 10, 0),
                child: _CompactEntityContext(
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

final class _CompactEntityContext extends StatelessWidget {
  const _CompactEntityContext({
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
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      elevated: false,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 30,
            height: 30,
            decoration: BoxDecoration(
              color: SafeContractsVisual.roseGoldSoft,
              borderRadius: BorderRadius.circular(10),
            ),
            child: const Icon(
              Icons.business_rounded,
              size: 17,
              color: SafeContractsVisual.navy,
            ),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              mainAxisSize: MainAxisSize.min,
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isArabic ? 'بيانات الجهة' : 'Dashboard entity',
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: SafeContractsVisual.muted,
                        fontSize: 9,
                        fontWeight: FontWeight.w700,
                      ),
                ),
                Text(
                  entityName,
                  maxLines: 2,
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: SafeContractsVisual.navy,
                        fontSize: 11,
                        height: 1.15,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                if (contractNumber != null)
                  Text(
                    isArabic
                        ? 'العقد: $contractNumber'
                        : 'Contract: $contractNumber',
                    maxLines: 2,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: SafeContractsVisual.muted,
                          fontSize: 8.5,
                          height: 1.1,
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
