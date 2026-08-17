import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
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
    if (oldWidget.controller != widget.controller || oldWidget.contractId != widget.contractId) {
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
            controller.selectedContractId == widget.contractId && contract != null;
        return Scaffold(
          appBar: AppBar(
            title: Text(ready ? contract.contractNumber : context.scL10n.t('Contract details')),
            actions: [
              if (ready && widget.onEditContract != null)
                IconButton(
                  tooltip: controller.canEditContract ? context.scL10n.t('Edit contract') : context.scL10n.t('Responsible accountant'),
                  onPressed: () => widget.onEditContract!(contract.id),
                  icon: Icon(controller.canEditContract ? Icons.edit_outlined : Icons.assignment_ind_outlined),
                ),
            ],
          ),
          body: SafeArea(child: _body(context)),
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
    if (controller.detailState == ContractDetailLoadState.ready && controller.selectedContract != null) {
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
        child: Column(mainAxisSize: MainAxisSize.min, children: [
          const Icon(Icons.error_outline, size: 48),
          const SizedBox(height: 12),
          Text(title, style: Theme.of(context).textTheme.titleLarge),
          const SizedBox(height: 8),
          Text(l10n.rawMessage(controller.detailErrorMessage ?? 'SafeContracts could not load this contract.'), textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton(onPressed: () => unawaited(controller.openContract(widget.contractId)), child: Text(l10n.t('Retry'))),
        ]),
      ),
    );
  }
}

final class _ReadyContractDetails extends StatelessWidget {
  const _ReadyContractDetails({required this.contract, required this.canEdit, required this.onEditContract});

  final SafeContractsContract contract;
  final bool canEdit;
  final ValueChanged<int>? onEditContract;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final ar = l10n.isArabic;
    final typeLabel = contract.isSupplier ? (ar ? 'مورد' : 'Supplier') : (ar ? 'عميل' : 'Customer');
    final directionLabel = contract.financialDirection == 'payable' ? (ar ? 'مطلوب الدفع (AP)' : 'Payable (AP)') : (ar ? 'مستحق القبض (AR)' : 'Receivable (AR)');
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(crossAxisAlignment: CrossAxisAlignment.stretch, children: [
        Wrap(spacing: 8, runSpacing: 8, children: [
          Text(contract.contractNumber, style: Theme.of(context).textTheme.headlineSmall),
          Chip(label: Text(l10n.status(contract.status))),
          Chip(avatar: Icon(contract.isSupplier ? Icons.local_shipping_outlined : Icons.person_outline, size: 16), label: Text(typeLabel)),
          Chip(label: Text(directionLabel)),
          Chip(label: Text(contract.currencyCode)),
          if (contract.isArchived) Chip(avatar: const Icon(Icons.archive_outlined, size: 16), label: Text(l10n.t('Archived'))),
        ]),
        const SizedBox(height: 20),
        _section(context, ar ? 'جهة التعاقد' : 'Counterparty', [
          _row(ar ? 'النوع' : 'Type', typeLabel),
          _row(ar ? 'الاسم' : 'Name', contract.displayCounterparty),
          _row(ar ? 'المعرف' : 'ID', '${contract.counterpartyId}'),
          _row(ar ? 'الاتجاه المالي' : 'Financial direction', directionLabel),
          _row(ar ? 'العملة' : 'Currency', contract.currencyCode),
        ]),
        const SizedBox(height: 16),
        _section(context, l10n.t('Contract'), [
          _row(l10n.t('Contract ID'), '${contract.id}'),
          _row(l10n.t('Status'), l10n.status(contract.status)),
          _row(l10n.t('Start date'), contract.startDate ?? '—'),
          _row(l10n.t('End date'), contract.endDate ?? '—'),
          _row(l10n.t('Assigned accountant user ID'), contract.accountantUserId?.toString() ?? '—'),
        ]),
        const SizedBox(height: 16),
        _section(context, l10n.t('Financial values'), [
          _row(l10n.t('Base value'), contract.baseValue == null ? '—' : '${contract.baseValue} ${contract.currencyCode}'),
        ]),
        const SizedBox(height: 12),
        Text(ar ? 'الاتجاه المالي والأرصدة يحددها السيرفر. التطبيق لا يعيد حساب AP أو AR محليًا.' : 'Financial direction and balances remain server-authoritative. Mobile does not recalculate AP or AR locally.', style: Theme.of(context).textTheme.bodySmall),
        const SizedBox(height: 24),
        if (onEditContract != null)
          FilledButton.tonalIcon(onPressed: () => onEditContract!(contract.id), icon: Icon(canEdit ? Icons.edit_outlined : Icons.assignment_ind_outlined), label: Text(canEdit ? l10n.t('Edit contract') : l10n.t('Responsible accountant')))
        else
          Text(l10n.t('This contract is read-only for the current session.'), style: Theme.of(context).textTheme.bodySmall),
      ]),
    );
  }

  Widget _section(BuildContext context, String title, List<Widget> children) => Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...children,
          ]),
        ),
      );

  Widget _row(String label, String value) => Padding(
        padding: const EdgeInsets.only(bottom: 12),
        child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [
          Text(label, style: const TextStyle(fontWeight: FontWeight.w600)),
          const SizedBox(height: 3),
          SelectableText(value),
        ]),
      );
}
