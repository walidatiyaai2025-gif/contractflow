import 'dart:async';

import 'package:flutter/material.dart';

import 'dashboard_controller.dart';
import 'dashboard_models.dart';

final class DashboardScreen extends StatelessWidget {
  const DashboardScreen({required this.controller, super.key});

  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final overview = controller.overview;
        if (controller.state == DashboardLoadState.loading && overview == null) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.state == DashboardLoadState.error && overview == null) {
          return _DashboardError(
            message: controller.errorMessage ?? 'Unable to load dashboard.',
            onRetry: () => unawaited(controller.refresh()),
          );
        }
        if (overview == null) {
          return const Center(child: Text('Dashboard is not loaded yet.'));
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
              _KpiGrid(kpis: overview.kpis),
              const SizedBox(height: 16),
              if (controller.state == DashboardLoadState.error)
                _InlineError(
                  message: controller.errorMessage ?? 'Dashboard refresh failed.',
                ),
              _DashboardListsView(lists: controller.lists),
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
    final customerValue = controller.filters.customerId ?? 0;
    final contractValue = controller.filters.contractId ?? 0;
    final busy = controller.state == DashboardLoadState.loading;

    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Dashboard filters', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            Wrap(
              spacing: 16,
              runSpacing: 12,
              children: [
                _FilterField(
                  label: 'Customer',
                  child: DropdownButton<int>(
                    value: customerValue,
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
                      const DropdownMenuItem<int>(
                        value: 0,
                        child: Text('All customers'),
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
                  label: 'Contract',
                  child: DropdownButton<int>(
                    value: contractValue,
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
                      const DropdownMenuItem<int>(
                        value: 0,
                        child: Text('All contracts'),
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
                  label: 'Status',
                  child: DropdownButton<String>(
                    value: controller.filters.status ?? '',
                    onChanged: busy
                        ? null
                        : (value) {
                            unawaited(
                              controller.selectStatus(
                                value == null || value.isEmpty ? null : value,
                              ),
                            );
                          },
                    items: const <DropdownMenuItem<String>>[
                      DropdownMenuItem<String>(value: '', child: Text('All statuses')),
                      DropdownMenuItem<String>(value: 'active', child: Text('Active')),
                      DropdownMenuItem<String>(value: 'due', child: Text('Due')),
                      DropdownMenuItem<String>(value: 'overdue', child: Text('Overdue')),
                      DropdownMenuItem<String>(
                        value: 'partially_paid',
                        child: Text('Partially paid'),
                      ),
                      DropdownMenuItem<String>(value: 'paid', child: Text('Paid')),
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
  const _KpiGrid({required this.kpis});

  final DashboardKpis kpis;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 12,
      runSpacing: 12,
      children: [
        _KpiCard(label: 'Contracts', value: kpis.contractCount.toString()),
        _KpiCard(label: 'Scheduled', value: kpis.scheduledTotal),
        _KpiCard(label: 'Remaining', value: kpis.remainingTotal),
        _KpiCard(label: 'Overdue', value: kpis.overdueExposure),
        _KpiCard(label: 'Collected', value: kpis.collectedTotal),
      ],
    );
  }
}

final class _KpiCard extends StatelessWidget {
  const _KpiCard({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 170,
      child: Card(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(label, style: Theme.of(context).textTheme.labelLarge),
              const SizedBox(height: 8),
              Text(value, style: Theme.of(context).textTheme.titleLarge),
            ],
          ),
        ),
      ),
    );
  }
}

final class _DashboardListsView extends StatelessWidget {
  const _DashboardListsView({required this.lists});

  final DashboardLists? lists;

  @override
  Widget build(BuildContext context) {
    final value = lists;
    if (value == null || value.isEmpty) {
      return const Padding(
        padding: EdgeInsets.symmetric(vertical: 24),
        child: Center(child: Text('No records match the current filters.')),
      );
    }

    return Column(
      children: [
        _RecordSection(title: 'Contracts', records: value.contracts),
        _RecordSection(title: 'Payments', records: value.payments),
        _RecordSection(title: 'Collections', records: value.collections),
        _RecordSection(title: 'Follow-up', records: value.followUps),
      ],
    );
  }
}

final class _RecordSection extends StatelessWidget {
  const _RecordSection({required this.title, required this.records});

  final String title;
  final List<DashboardRecord> records;

  @override
  Widget build(BuildContext context) {
    if (records.isEmpty) {
      return const SizedBox.shrink();
    }
    return Card(
      child: ExpansionTile(
        initiallyExpanded: title == 'Payments',
        title: Text('$title (${records.length})'),
        children: records
            .map(
              (record) => ListTile(
                title: Text(record.title),
                subtitle: Text(
                  <String>[
                    if (record.customerName != null) record.customerName!,
                    if (record.status != null) record.status!,
                    if (record.date != null) record.date!,
                  ].join(' • '),
                ),
                trailing: Text(
                  record.remainingAmount ?? record.amount ?? '',
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
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
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
