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
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final controller = widget.controller;
        final contract = controller.selectedContract;
        final readyForCurrent =
            controller.detailState == ContractDetailLoadState.ready &&
                controller.selectedContractId == widget.contractId &&
                contract != null;

        return Scaffold(
          appBar: AppBar(
            title: Text(
              readyForCurrent
                  ? contract.contractNumber
                  : l10n.t('Contract details'),
            ),
            actions: [
              if (readyForCurrent &&
                  controller.canEditContract &&
                  widget.onEditContract != null)
                IconButton(
                  tooltip: l10n.t('Edit contract'),
                  onPressed: () => widget.onEditContract!(contract.id),
                  icon: const Icon(Icons.edit_outlined),
                ),
            ],
          ),
          body: SafeArea(
            child: _ContractDetailsBody(
              controller: controller,
              contractId: widget.contractId,
              currency: widget.currency,
              onEditContract: widget.onEditContract,
            ),
          ),
        );
      },
    );
  }
}

final class _ContractDetailsBody extends StatelessWidget {
  const _ContractDetailsBody({
    required this.controller,
    required this.contractId,
    required this.currency,
    required this.onEditContract,
  });

  final ContractsController controller;
  final int contractId;
  final MobileCurrencyConfig currency;
  final ValueChanged<int>? onEditContract;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (controller.selectedContractId != contractId ||
        controller.detailState == ContractDetailLoadState.loading ||
        controller.detailState == ContractDetailLoadState.idle) {
      return const Center(child: CircularProgressIndicator());
    }

    return switch (controller.detailState) {
      ContractDetailLoadState.ready => _ReadyContractDetails(
          contract: controller.selectedContract!,
          currency: currency,
          canEdit: controller.canEditContract,
          onEditContract: onEditContract,
        ),
      ContractDetailLoadState.notFound => _ContractDetailError(
          icon: Icons.search_off_outlined,
          title: l10n.t('Contract not found'),
          message: l10n.rawMessage(
            controller.detailErrorMessage ??
                'This contract was not found in your authorized scope.',
          ),
          onRetry: () => unawaited(controller.openContract(contractId)),
        ),
      ContractDetailLoadState.forbidden => _ContractDetailError(
          icon: Icons.lock_outline,
          title: l10n.t('Contract access denied'),
          message: l10n.rawMessage(
            controller.detailErrorMessage ??
                'You do not have permission to view this contract.',
          ),
          onRetry: () => unawaited(controller.openContract(contractId)),
        ),
      ContractDetailLoadState.error => _ContractDetailError(
          icon: Icons.error_outline,
          title: l10n.t('Unable to load contract'),
          message: l10n.rawMessage(
            controller.detailErrorMessage ??
                'SafeContracts could not load this contract.',
          ),
          onRetry: () => unawaited(controller.openContract(contractId)),
        ),
      ContractDetailLoadState.idle ||
      ContractDetailLoadState.loading =>
        const Center(child: CircularProgressIndicator()),
    };
  }
}

final class _ReadyContractDetails extends StatelessWidget {
  const _ReadyContractDetails({
    required this.contract,
    required this.currency,
    required this.canEdit,
    required this.onEditContract,
  });

  final SafeContractsContract contract;
  final MobileCurrencyConfig currency;
  final bool canEdit;
  final ValueChanged<int>? onEditContract;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return SingleChildScrollView(
      padding: const EdgeInsets.all(20),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Wrap(
            spacing: 8,
            runSpacing: 8,
            crossAxisAlignment: WrapCrossAlignment.center,
            children: [
              Text(
                contract.contractNumber,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              Chip(label: Text(l10n.status(contract.status))),
              if (contract.isArchived)
                Chip(
                  avatar: const Icon(Icons.archive_outlined, size: 16),
                  label: Text(l10n.t('Archived')),
                ),
            ],
          ),
          const SizedBox(height: 20),
          _DetailsSection(
            title: l10n.t('Contract'),
            children: [
              _DetailValue(
                label: l10n.t('Contract ID'),
                value: '${contract.id}',
              ),
              _DetailValue(
                label: l10n.t('Status'),
                value: l10n.status(contract.status),
              ),
              _DetailValue(
                label: l10n.t('Start date'),
                value: contract.startDate ?? '—',
              ),
              _DetailValue(
                label: l10n.t('End date'),
                value: contract.endDate ?? '—',
              ),
            ],
          ),
          const SizedBox(height: 16),
          _DetailsSection(
            title: l10n.t('Customer & assignment'),
            children: [
              _DetailValue(
                label: l10n.t('Customer'),
                value: contract.customerName ??
                    l10n.customerNumber(contract.customerId),
              ),
              _DetailValue(
                label: l10n.t('Customer ID'),
                value: '${contract.customerId}',
              ),
              _DetailValue(
                label: l10n.t('Assigned accountant user ID'),
                value: contract.accountantUserId?.toString() ?? '—',
              ),
            ],
          ),
          const SizedBox(height: 16),
          _DetailsSection(
            title: l10n.t('Financial values'),
            children: [
              _DetailValue(
                label: l10n.t('Base value'),
                value: contract.baseValue == null
                    ? '—'
                    : l10n.money(contract.baseValue!, currency),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Text(
            l10n.t(
              'Status and financial values are displayed exactly as returned by the SafeContracts server. The mobile app does not recalculate them.',
            ),
            style: Theme.of(context).textTheme.bodySmall,
          ),
          const SizedBox(height: 24),
          if (canEdit && onEditContract != null)
            FilledButton.tonalIcon(
              onPressed: () => onEditContract!(contract.id),
              icon: const Icon(Icons.edit_outlined),
              label: Text(l10n.t('Edit contract')),
            )
          else
            Text(
              l10n.t('This contract is read-only for the current session.'),
              style: Theme.of(context).textTheme.bodySmall,
            ),
        ],
      ),
    );
  }
}

final class _DetailsSection extends StatelessWidget {
  const _DetailsSection({required this.title, required this.children});

  final String title;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...children,
          ],
        ),
      ),
    );
  }
}

final class _DetailValue extends StatelessWidget {
  const _DetailValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.labelMedium),
          const SizedBox(height: 3),
          SelectableText(value),
        ],
      ),
    );
  }
}

final class _ContractDetailError extends StatelessWidget {
  const _ContractDetailError({
    required this.icon,
    required this.title,
    required this.message,
    required this.onRetry,
  });

  final IconData icon;
  final String title;
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48),
            const SizedBox(height: 12),
            Text(title, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: 8),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton(
              onPressed: onRetry,
              child: Text(context.scL10n.t('Retry')),
            ),
          ],
        ),
      ),
    );
  }
}
