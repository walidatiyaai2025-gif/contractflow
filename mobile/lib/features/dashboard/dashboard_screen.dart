import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import 'dashboard_controller.dart';
import 'dashboard_models.dart';

final class DashboardScreen extends StatelessWidget {
  const DashboardScreen({
    required this.controller,
    this.currency = const MobileCurrencyConfig.defaults(),
    super.key,
  });

  final DashboardController controller;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final overview = controller.overview;
        if (controller.state == DashboardLoadState.loading &&
            overview == null) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.state == DashboardLoadState.error && overview == null) {
          return _DashboardError(
            message: l10n.rawMessage(
              controller.errorMessage ?? 'Unable to load dashboard.',
            ),
            onRetry: () => unawaited(controller.refresh()),
          );
        }
        if (overview == null) {
          return Center(child: Text(l10n.t('Dashboard is not loaded yet.')));
        }

        return RefreshIndicator(
          onRefresh: controller.refresh,
          child: ListView(
            padding: const EdgeInsets.all(16),
            children: [
              if (controller.state == DashboardLoadState.loading)
                const LinearProgressIndicator(),
              const SizedBox(height: 8),
              _DashboardFilters(controller: controller, overview: overview),
              const SizedBox(height: 16),
              _KpiGrid(kpis: overview.kpis, currency: currency),
              const SizedBox(height: 16),
              if (controller.state == DashboardLoadState.error)
                _InlineError(
                  message: l10n.rawMessage(
                    controller.errorMessage ?? 'Dashboard refresh failed.',
                  ),
                ),
              _DashboardListsView(lists: controller.lists, currency: currency),
            ],
          ),
        );
      },
    );
  }
}

final class _DashboardFilters extends StatelessWidget {
  const _DashboardFilters({
    required this.controller,
    required this.overview,
  });

  final DashboardController controller;
  final DashboardOverview overview;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final customerValue = controller.filters.customerId ?? 0;
    final contractValue = controller.filters.contractId ?? 0;
    final busy = controller.state == DashboardLoadState.loading;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              l10n.t('Dashboard filters'),
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 12),
            Wrap(
              spacing: 16,
              runSpacing: 12,
              children: [
                _FilterField(
                  label: l10n.t('Customer'),
                  child: DropdownButton<int>(
                    value: customerValue,
                    isExpanded: true,
                    onChanged: busy
                        ? null
                        : (value) {
                            unawaited(
                              controller.selectCustomer(
                                value == 0 ? null : value,
                              ),
                            );
                          },
                    items: <DropdownMenuItem<int>>[
                      DropdownMenuItem<int>(
                        value: 0,
                        child: Text(l10n.t('All customers')),
                      ),
                      ...overview.customers.map(
                        (option) => DropdownMenuItem<int>(
                          value: option.id,
                          child: Text(option.name),
                        ),
                      ),
                    ],
                  ),
                ),
                _FilterField(
                  label: l10n.t('Contract'),
                  child: DropdownButton<int>(
                    value: contractValue,
                    isExpanded: true,
                    onChanged: busy
                        ? null
                        : (value) {
                            unawaited(
                              controller.selectContract(
                                value == 0 ? null : value,
                              ),
                            );
                          },
                    items: <DropdownMenuItem<int>>[
                      DropdownMenuItem<int>(
                        value: 0,
                        child: Text(l10n.t('All contracts')),
                      ),
                      ...controller.availableContracts.map(
                        (option) => DropdownMenuItem<int>(
                          value: option.id,
                          child: Text(option.contractNumber),
                        ),
                      ),
                    ],
                  ),
                ),
                _FilterField(
                  label: l10n.t('Status'),
                  child: DropdownButton<String>(
                    value: controller.filters.status ?? '',
                    isExpanded: true,
                    onChanged: busy
                        ? null
                        : (value) {
                            unawaited(
                              controller.selectStatus(
                                value == null || value.isEmpty ? null : value,
                              ),
                            );
                          },
                    items: <DropdownMenuItem<String>>[
                      DropdownMenuItem<String>(
                        value: '',
                        child: Text(l10n.t('All statuses')),
                      ),
                      for (final status in const <String>[
                        'active',
                        'due',
                        'overdue',
                        'partially_paid',
                        'paid',
                      ])
                        DropdownMenuItem<String>(
                          value: status,
                          child: Text(l10n.status(status)),
                        ),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

final class _FilterField extends StatelessWidget {
  const _FilterField({required this.label, required this.child});

  final String label;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 220,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.labelMedium),
          const SizedBox(height: 4),
          child,
        ],
      ),
    );
  }
}

final class _KpiGrid extends StatelessWidget {
  const _KpiGrid({required this.kpis, required this.currency});

  final DashboardKpis kpis;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final cards = <_KpiData>[
      _KpiData(l10n.t('Contracts'), kpis.contractCount.toString()),
      _KpiData(l10n.t('Scheduled'), l10n.money(kpis.scheduledTotal, currency)),
      _KpiData(l10n.t('Remaining'), l10n.money(kpis.remainingTotal, currency)),
      _KpiData(l10n.t('Overdue'), l10n.money(kpis.overdueExposure, currency)),
      _KpiData(l10n.t('Collected'), l10n.money(kpis.collectedTotal, currency)),
    ];

    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 1100
            ? 5
            : constraints.maxWidth >= 700
                ? 3
                : 2;
        return GridView.builder(
          shrinkWrap: true,
          physics: const NeverScrollableScrollPhysics(),
          itemCount: cards.length,
          gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
            crossAxisCount: columns,
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: constraints.maxWidth < 420 ? 1.35 : 1.6,
          ),
          itemBuilder: (context, index) => _KpiCard(data: cards[index]),
        );
      },
    );
  }
}

final class _KpiData {
  const _KpiData(this.label, this.value);
  final String label;
  final String value;
}

final class _KpiCard extends StatelessWidget {
  const _KpiCard({required this.data});

  final _KpiData data;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(data.label, style: Theme.of(context).textTheme.labelLarge),
            const SizedBox(height: 8),
            FittedBox(
              fit: BoxFit.scaleDown,
              alignment: AlignmentDirectional.centerStart,
              child: Text(
                data.value,
                style: Theme.of(context).textTheme.titleLarge,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _DashboardListsView extends StatelessWidget {
  const _DashboardListsView({required this.lists, required this.currency});

  final DashboardLists? lists;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final value = lists;
    if (value == null || value.isEmpty) {
      return Padding(
        padding: const EdgeInsets.symmetric(vertical: 24),
        child: Center(
          child: Text(l10n.t('No records match the current filters.')),
        ),
      );
    }

    return Column(
      children: [
        _RecordSection(
          title: l10n.t('Contracts'),
          records: value.contracts,
          currency: currency,
        ),
        _RecordSection(
          title: l10n.t('Payments'),
          records: value.payments,
          currency: currency,
        ),
        _RecordSection(
          title: l10n.t('Collections'),
          records: value.collections,
          currency: currency,
        ),
        _RecordSection(
          title: l10n.t('Follow-up'),
          records: value.followUps,
          currency: currency,
        ),
      ],
    );
  }
}

final class _RecordSection extends StatelessWidget {
  const _RecordSection({
    required this.title,
    required this.records,
    required this.currency,
  });

  final String title;
  final List<DashboardRecord> records;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (records.isEmpty) {
      return const SizedBox.shrink();
    }
    return Card(
      child: ExpansionTile(
        initiallyExpanded: title == l10n.t('Payments'),
        title: Text('$title (${records.length})'),
        children: records
            .map(
              (record) => ListTile(
                title: Text(record.title),
                subtitle: Text(
                  <String>[
                    if (record.customerName != null) record.customerName!,
                    if (record.status != null) l10n.status(record.status!),
                    if (record.date != null) record.date!,
                  ].join(' • '),
                ),
                trailing: Text(
                  l10n.money(
                    record.remainingAmount ?? record.amount ?? '',
                    currency,
                  ),
                ),
              ),
            )
            .toList(growable: false),
      ),
    );
  }
}

final class _DashboardError extends StatelessWidget {
  const _DashboardError({required this.message, required this.onRetry});

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
            const Icon(Icons.error_outline, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
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

final class _InlineError extends StatelessWidget {
  const _InlineError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Text(
        message,
        style: TextStyle(color: Theme.of(context).colorScheme.error),
      ),
    );
  }
}
