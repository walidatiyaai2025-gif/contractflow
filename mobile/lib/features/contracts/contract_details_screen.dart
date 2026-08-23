import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../ui/safecontracts_design.dart';
import 'contracts.dart';

final class ContractDetailsScreen extends StatefulWidget {
  const ContractDetailsScreen({
    required this.controller,
    required this.contractId,
    this.currency = const MobileCurrencyConfig.defaults(),
    required this.onEditContract,
    super.key,
  });

  final ContractsController controller;
  final int contractId;
  final MobileCurrencyConfig currency;
  final ValueChanged<int>? onEditContract;

  @override
  State<ContractDetailsScreen> createState() => _ContractDetailsScreenState();
}

final class _ContractDetailsScreenState extends State<ContractDetailsScreen> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.openContract(widget.contractId));
  }

  @override
  void didUpdateWidget(ContractDetailsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller ||
        oldWidget.contractId != widget.contractId) {
      unawaited(widget.controller.openContract(widget.contractId));
    }
  }

  @override
  void dispose() {
    widget.controller.clearContractDetail(expectedId: widget.contractId);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final controller = widget.controller;
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final contract = controller.selectedContract;
        final ready = controller.detailState == ContractDetailLoadState.ready &&
            controller.selectedContractId == widget.contractId &&
            contract != null;
        return Scaffold(
          backgroundColor: SafeContractsVisual.background,
          appBar: AppBar(
            title: Text(
              ready
                  ? context.scL10n.t('Contract details')
                  : context.scL10n.t('Contract details'),
            ),
            actions: [
              if (ready && widget.onEditContract != null)
                IconButton(
                  tooltip: controller.canEditContract
                      ? context.scL10n.t('Edit contract')
                      : context.scL10n.t('Responsible accountant'),
                  onPressed: () => widget.onEditContract!(contract.id),
                  icon: Icon(
                    controller.canEditContract
                        ? Icons.edit_outlined
                        : Icons.assignment_ind_outlined,
                  ),
                ),
            ],
          ),
          body: SafeContractsBackdrop(child: SafeArea(child: _body(context))),
        );
      },
    );
  }

  Widget _body(BuildContext context) {
    final controller = widget.controller;
    if (controller.selectedContractId != widget.contractId ||
        controller.detailState == ContractDetailLoadState.loading ||
        controller.detailState == ContractDetailLoadState.idle) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.detailState == ContractDetailLoadState.ready &&
        controller.selectedContract != null) {
      return _ReadyContractDetails(
        contract: controller.selectedContract!,
        canEdit: controller.canEditContract,
        onEditContract: widget.onEditContract,
      );
    }

    final l10n = context.scL10n;
    final title = switch (controller.detailState) {
      ContractDetailLoadState.notFound => l10n.t('Contract not found'),
      ContractDetailLoadState.forbidden => l10n.t('Contract access denied'),
      _ => l10n.t('Unable to load contract'),
    };
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: SafeContractsSurface(
          accent: SafeContractsVisual.red,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.error_outline_rounded,
                size: 48,
                color: SafeContractsVisual.red,
              ),
              const SizedBox(height: 12),
              Text(
                title,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w900,
                    ),
              ),
              const SizedBox(height: 8),
              Text(
                l10n.rawMessage(
                  controller.detailErrorMessage ??
                      'SafeContracts could not load this contract.',
                ),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              FilledButton.icon(
                onPressed: () =>
                    unawaited(controller.openContract(widget.contractId)),
                icon: const Icon(Icons.refresh_rounded),
                label: Text(l10n.t('Retry')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _ReadyContractDetails extends StatelessWidget {
  const _ReadyContractDetails({
    required this.contract,
    required this.canEdit,
    required this.onEditContract,
  });

  final SafeContractsContract contract;
  final bool canEdit;
  final ValueChanged<int>? onEditContract;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final ar = l10n.isArabic;
    final typeLabel = contract.isSupplier
        ? (ar ? 'مورد' : 'Supplier')
        : (ar ? 'عميل' : 'Customer');
    final directionLabel = contract.financialDirection == 'payable'
        ? (ar ? 'مطلوب الدفع' : 'Payable')
        : (ar ? 'مستحق القبض' : 'Receivable');
    final directionAccent = contract.financialDirection == 'payable'
        ? SafeContractsVisual.red
        : SafeContractsVisual.green;

    return ListView(
      padding: const EdgeInsets.fromLTRB(14, 10, 14, 28),
      children: [
        _ContractHero(
          contract: contract,
          typeLabel: typeLabel,
          directionLabel: directionLabel,
          directionAccent: directionAccent,
          isArabic: ar,
        ),
        const SizedBox(height: 14),
        _ContractTabs(isArabic: ar),
        const SizedBox(height: 18),
        SafeContractsSectionTitle(
          title: ar ? 'ملخص العقد' : 'Contract Summary',
          subtitle: ar
              ? 'بيانات العقد الأساسية والجهة المتعاقدة'
              : 'Core contract and counterparty information',
        ),
        const SizedBox(height: 12),
        SafeContractsSurface(
          child: Column(
            children: [
              _PremiumRow(
                icon: contract.isSupplier
                    ? Icons.local_shipping_outlined
                    : Icons.business_outlined,
                label: ar ? 'جهة التعاقد' : 'Counterparty',
                value: contract.displayCounterparty,
              ),
              const _RowDivider(),
              _PremiumRow(
                icon: Icons.badge_outlined,
                label: ar ? 'النوع' : 'Type',
                value: typeLabel,
              ),
              const _RowDivider(),
              _PremiumRow(
                icon: Icons.swap_horiz_rounded,
                label: ar ? 'الاتجاه المالي' : 'Financial direction',
                value: directionLabel,
                valueColor: directionAccent,
              ),
              const _RowDivider(),
              _PremiumRow(
                icon: Icons.currency_exchange_rounded,
                label: ar ? 'العملة' : 'Currency',
                value: contract.currencyCode,
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        SafeContractsSectionTitle(
          title: ar ? 'البيانات المالية والزمنية' : 'Financial & Timeline',
        ),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final cards = <Widget>[
              _InfoCard(
                icon: Icons.account_balance_wallet_outlined,
                label: ar ? 'قيمة العقد' : 'Contract value',
                value: contract.baseValue == null
                    ? '—'
                    : '${_displayMoney(contract.baseValue)} ${contract.currencyCode}',
                accent: SafeContractsVisual.roseGold,
              ),
              _InfoCard(
                icon: Icons.calendar_today_outlined,
                label: ar ? 'تاريخ البداية' : 'Start date',
                value: contract.startDate ?? '—',
                accent: SafeContractsVisual.navy,
              ),
              _InfoCard(
                icon: Icons.event_available_outlined,
                label: ar ? 'تاريخ النهاية' : 'End date',
                value: contract.endDate ?? '—',
                accent: SafeContractsVisual.green,
              ),
              _InfoCard(
                icon: Icons.assignment_ind_outlined,
                label: ar ? 'المحاسب المسؤول' : 'Assigned accountant',
                value: contract.accountantUserId?.toString() ?? '—',
                accent: SafeContractsVisual.amber,
              ),
            ];
            if (constraints.maxWidth >= 620) {
              return GridView.count(
                crossAxisCount: 2,
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                crossAxisSpacing: 10,
                mainAxisSpacing: 10,
                childAspectRatio: 2.3,
                children: cards,
              );
            }
            return Column(
              children: cards
                  .map(
                    (card) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: card,
                    ),
                  )
                  .toList(growable: false),
            );
          },
        ),
        const SizedBox(height: 8),
        Container(
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            color: SafeContractsVisual.navySoft.withValues(alpha: 0.55),
            borderRadius: BorderRadius.circular(14),
            border: Border.all(color: SafeContractsVisual.outline),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Icon(
                Icons.verified_user_outlined,
                color: SafeContractsVisual.navy,
                size: 20,
              ),
              const SizedBox(width: 9),
              Expanded(
                child: Text(
                  ar
                      ? 'القيمة والاتجاه المالي والأرصدة تظل معتمدة من السيرفر. التطبيق لا يعيد حساب AP أو AR محليًا.'
                      : 'Value, direction and balances remain server-authoritative. Mobile does not recalculate AP or AR locally.',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.navyDeep,
                        height: 1.5,
                      ),
                ),
              ),
            ],
          ),
        ),
        const SizedBox(height: 18),
        if (onEditContract != null)
          FilledButton.icon(
            onPressed: () => onEditContract!(contract.id),
            icon: Icon(
              canEdit ? Icons.edit_outlined : Icons.assignment_ind_outlined,
            ),
            label: Text(
              canEdit
                  ? l10n.t('Edit contract')
                  : l10n.t('Responsible accountant'),
            ),
          )
        else
          SafeContractsSurface(
            elevated: false,
            padding: const EdgeInsets.all(13),
            child: Row(
              children: [
                const Icon(
                  Icons.lock_outline_rounded,
                  color: SafeContractsVisual.muted,
                ),
                const SizedBox(width: 9),
                Expanded(
                  child: Text(
                    l10n.t(
                        'This contract is read-only for the current session.'),
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ),
              ],
            ),
          ),
      ],
    );
  }
}

final class _ContractHero extends StatelessWidget {
  const _ContractHero({
    required this.contract,
    required this.typeLabel,
    required this.directionLabel,
    required this.directionAccent,
    required this.isArabic,
  });

  final SafeContractsContract contract;
  final String typeLabel;
  final String directionLabel;
  final Color directionAccent;
  final bool isArabic;

  @override
  Widget build(BuildContext context) {
    final statusColor = safeContractsStatusColor(contract.status);
    final amount = contract.baseValue == null
        ? '—'
        : '${_displayMoney(contract.baseValue)} ${contract.currencyCode}';
    return Container(
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
        boxShadow: const [
          BoxShadow(
            color: Color(0x33092944),
            blurRadius: 26,
            offset: Offset(0, 12),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          PositionedDirectional(
            top: -40,
            end: -34,
            child: Container(
              width: 150,
              height: 150,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: SafeContractsVisual.roseGold.withValues(alpha: 0.10),
                border: Border.all(
                  color: Colors.white.withValues(alpha: 0.10),
                ),
              ),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(18),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 62,
                      height: 62,
                      decoration: BoxDecoration(
                        gradient: SafeContractsVisual.roseGradient,
                        borderRadius: BorderRadius.circular(17),
                        border: Border.all(
                          color: Colors.white.withValues(alpha: 0.24),
                        ),
                      ),
                      child: Icon(
                        contract.isSupplier
                            ? Icons.local_shipping_rounded
                            : Icons.apartment_rounded,
                        color: SafeContractsVisual.navyDeep,
                        size: 31,
                      ),
                    ),
                    const SizedBox(width: 13),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            contract.displayCounterparty,
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context)
                                .textTheme
                                .titleLarge
                                ?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                          const SizedBox(height: 4),
                          Text(
                            contract.contractNumber,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context)
                                .textTheme
                                .bodyMedium
                                ?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.74),
                                ),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 17),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _HeroPill(
                      label: typeLabel,
                      icon: contract.isSupplier
                          ? Icons.local_shipping_outlined
                          : Icons.person_outline,
                    ),
                    _HeroPill(
                      label: directionLabel,
                      icon: Icons.swap_horiz_rounded,
                      color: directionAccent,
                    ),
                    _HeroPill(
                      label: isArabic
                          ? context.scL10n.status(contract.status)
                          : context.scL10n.status(contract.status),
                      icon: Icons.circle,
                      color: statusColor,
                    ),
                  ],
                ),
                const SizedBox(height: 18),
                Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(13),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.13),
                    ),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              isArabic ? 'قيمة العقد' : 'Contract value',
                              style: Theme.of(context)
                                  .textTheme
                                  .bodySmall
                                  ?.copyWith(
                                    color: Colors.white.withValues(alpha: 0.68),
                                  ),
                            ),
                            const SizedBox(height: 3),
                            FittedBox(
                              fit: BoxFit.scaleDown,
                              alignment: AlignmentDirectional.centerStart,
                              child: Text(
                                amount,
                                style: Theme.of(context)
                                    .textTheme
                                    .headlineSmall
                                    ?.copyWith(
                                      color: Colors.white,
                                      fontWeight: FontWeight.w900,
                                    ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (contract.isArchived)
                        const Icon(
                          Icons.archive_outlined,
                          color: SafeContractsVisual.champagne,
                        ),
                    ],
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

final class _HeroPill extends StatelessWidget {
  const _HeroPill({
    required this.label,
    required this.icon,
    this.color = Colors.white,
  });

  final String label;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: color == Colors.white
            ? Colors.white.withValues(alpha: 0.10)
            : color.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: color == Colors.white
              ? Colors.white.withValues(alpha: 0.16)
              : color.withValues(alpha: 0.42),
        ),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: 15, color: color),
          const SizedBox(width: 6),
          Text(
            label,
            style: TextStyle(
              color: color,
              fontSize: 12,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }
}

final class _ContractTabs extends StatelessWidget {
  const _ContractTabs({required this.isArabic});

  final bool isArabic;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(5),
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: SafeContractsVisual.outline),
      ),
      child: Row(
        children: [
          Expanded(
            child: Container(
              padding: const EdgeInsets.symmetric(vertical: 9),
              decoration: BoxDecoration(
                color: SafeContractsVisual.navy,
                borderRadius: BorderRadius.circular(10),
              ),
              child: Text(
                isArabic ? 'ملخص العقد' : 'Summary',
                textAlign: TextAlign.center,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
          Expanded(
            child: Text(
              isArabic ? 'الدفعات' : 'Payments',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: SafeContractsVisual.muted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Text(
              isArabic ? 'المرفقات' : 'Attachments',
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: SafeContractsVisual.muted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

final class _PremiumRow extends StatelessWidget {
  const _PremiumRow({
    required this.icon,
    required this.label,
    required this.value,
    this.valueColor,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color? valueColor;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10),
      child: Row(
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: SafeContractsVisual.navySoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, size: 19, color: SafeContractsVisual.navy),
          ),
          const SizedBox(width: 11),
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
                        color: valueColor ?? SafeContractsVisual.ink,
                        fontWeight: FontWeight.w800,
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

final class _InfoCard extends StatelessWidget {
  const _InfoCard({
    required this.icon,
    required this.label,
    required this.value,
    required this.accent,
  });

  final IconData icon;
  final String label;
  final String value;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
        border: Border.all(color: accent.withValues(alpha: 0.28)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x185A4638),
            blurRadius: 14,
            offset: Offset(0, 5),
          ),
        ],
      ),
      child: Row(
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.11),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(icon, color: accent, size: 20),
          ),
          const SizedBox(width: 11),
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
                const SizedBox(height: 3),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: AlignmentDirectional.centerStart,
                  child: Text(
                    value,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w900,
                        ),
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

final class _RowDivider extends StatelessWidget {
  const _RowDivider();

  @override
  Widget build(BuildContext context) {
    return const Divider(height: 1, color: SafeContractsVisual.outline);
  }
}

String _displayMoney(String? value) {
  if (value == null) return '—';
  final parts = value.split('.');
  if (parts.length == 1) return '${parts[0]}.00';
  final whole = parts[0];
  var fraction = parts[1];
  while (fraction.length > 2 && fraction.endsWith('0')) {
    fraction = fraction.substring(0, fraction.length - 1);
  }
  if (fraction.length == 1) fraction = '${fraction}0';
  return '$whole.$fraction';
}
