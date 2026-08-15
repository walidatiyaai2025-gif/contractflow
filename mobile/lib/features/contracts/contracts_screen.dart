import 'dart:async';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import 'contracts.dart';

final class ContractsScreen extends StatefulWidget {
  const ContractsScreen({
    required this.controller,
    required this.customers,
    required this.onOpenContract,
    super.key,
  });

  final ContractsController controller;
  final List<CustomerOption> customers;
  final ValueChanged<int> onOpenContract;

  @override
  State<ContractsScreen> createState() => _ContractsScreenState();
}

final class _ContractsScreenState extends State<ContractsScreen> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  void didUpdateWidget(ContractsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      unawaited(widget.controller.ensureLoaded());
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        return Column(
          children: [
            _ContractsToolbar(
              controller: widget.controller,
              customers: widget.customers,
            ),
            const Divider(height: 1),
            Expanded(
              child: _ContractsContent(
                controller: widget.controller,
                onOpenContract: widget.onOpenContract,
              ),
            ),
          ],
        );
      },
    );
  }
}

final class _ContractsToolbar extends StatelessWidget {
  const _ContractsToolbar({
    required this.controller,
    required this.customers,
  });

  final ContractsController controller;
  final List<CustomerOption> customers;

  @override
  Widget build(BuildContext context) {
    final busy = controller.state == ContractsLoadState.loading;
    final selectedCustomer = controller.filters.customerId ?? 0;
    final customerExists = selectedCustomer == 0 ||
        customers.any((customer) => customer.id == selectedCustomer);
    final safeCustomer = customerExists ? selectedCustomer : 0;
    final selectedStatus = controller.filters.status ?? '';

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Wrap(
        spacing: 12,
        runSpacing: 10,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          Text('Contracts', style: Theme.of(context).textTheme.titleLarge),
          SizedBox(
            width: 210,
            child: DropdownButtonFormField<int>(
              key: ValueKey<int>(safeCustomer),
              initialValue: safeCustomer,
              decoration: const InputDecoration(
                labelText: 'Customer',
                isDense: true,
                border: OutlineInputBorder(),
              ),
              items: <DropdownMenuItem<int>>[
                const DropdownMenuItem<int>(
                  value: 0,
                  child: Text('All customers'),
                ),
                ...customers.map(
                  (customer) => DropdownMenuItem<int>(
                    value: customer.id,
                    child: Text(
                      customer.name,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                ),
              ],
              onChanged: busy
                  ? null
                  : (value) => unawaited(
                        controller.selectCustomer(
                          value == null || value == 0 ? null : value,
                        ),
                      ),
            ),
          ),
          SizedBox(
            width: 170,
            child: DropdownButtonFormField<String>(
              key: ValueKey<String>(selectedStatus),
              initialValue: selectedStatus,
              decoration: const InputDecoration(
                labelText: 'Status',
                isDense: true,
                border: OutlineInputBorder(),
              ),
              items: const <DropdownMenuItem<String>>[
                DropdownMenuItem<String>(
                  value: '',
                  child: Text('All statuses'),
                ),
                DropdownMenuItem<String>(value: 'draft', child: Text('Draft')),
                DropdownMenuItem<String>(
                  value: 'active',
                  child: Text('Active'),
                ),
                DropdownMenuItem<String>(
                  value: 'completed',
                  child: Text('Completed'),
                ),
                DropdownMenuItem<String>(
                  value: 'cancelled',
                  child: Text('Cancelled'),
                ),
              ],
              onChanged: busy
                  ? null
                  : (value) => unawaited(
                        controller.selectStatus(
                          value == null || value.isEmpty ? null : value,
                        ),
                      ),
            ),
          ),
          SizedBox(
            width: 190,
            child: DropdownButtonFormField<ContractSortOption>(
              key: ValueKey<String>(
                '${controller.sort.field}:${controller.sort.order}',
              ),
              initialValue: controller.sort,
              decoration: const InputDecoration(
                labelText: 'Sort',
                isDense: true,
                border: OutlineInputBorder(),
              ),
              items: ContractSortOption.values
                  .map(
                    (option) => DropdownMenuItem<ContractSortOption>(
                      value: option,
                      child: Text(option.label),
                    ),
                  )
                  .toList(growable: false),
              onChanged: busy
                  ? null
                  : (value) {
                      if (value != null) {
                        unawaited(controller.selectSort(value));
                      }
                    },
            ),
          ),
          IconButton(
            tooltip: 'Refresh contracts',
            onPressed: busy ? null : () => unawaited(controller.refresh()),
            icon: const Icon(Icons.refresh),
          ),
          if (controller.currentPage != null)
            Text(
              'Page ${controller.currentPage!.page} • '
              '${controller.currentPage!.contracts.length} shown',
              style: Theme.of(context).textTheme.bodySmall,
            ),
        ],
      ),
    );
  }
}

final class _ContractsContent extends StatelessWidget {
  const _ContractsContent({
    required this.controller,
    required this.onOpenContract,
  });

  final ContractsController controller;
  final ValueChanged<int> onOpenContract;

  @override
  Widget build(BuildContext context) {
    final page = controller.currentPage;
    if (controller.state == ContractsLoadState.loading && page == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.state == ContractsLoadState.error && page == null) {
      return _ContractsError(
        message: controller.errorMessage ?? 'Unable to load contracts.',
        onRetry: () => unawaited(controller.loadPage(1)),
      );
    }
    if (page == null) {
      return const Center(child: Text('Contracts are not loaded yet.'));
    }
    if (page.contracts.isEmpty) {
      return RefreshIndicator(
        onRefresh: controller.refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const [
            SizedBox(height: 120),
            Icon(Icons.description_outlined, size: 48),
            SizedBox(height: 12),
            Center(child: Text('No contracts match the current filters.')),
          ],
        ),
      );
    }

    return Column(
      children: [
        if (controller.state == ContractsLoadState.loading)
          const LinearProgressIndicator(),
        if (controller.state == ContractsLoadState.error)
          _InlineContractsError(
            message: controller.errorMessage ?? 'Contract refresh failed.',
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: LayoutBuilder(
              builder: (context, constraints) {
                final wide = constraints.maxWidth >= 720;
                return ListView.separated(
                  physics: const AlwaysScrollableScrollPhysics(),
                  padding: const EdgeInsets.symmetric(vertical: 8),
                  itemCount: page.contracts.length,
                  separatorBuilder: (context, index) =>
                      const Divider(height: 1),
                  itemBuilder: (context, index) {
                    final contract = page.contracts[index];
                    return _ContractTile(
                      contract: contract,
                      wide: wide,
                      onTap: () => onOpenContract(contract.id),
                    );
                  },
                );
              },
            ),
          ),
        ),
        _ContractPagination(controller: controller, page: page),
      ],
    );
  }
}

final class _ContractTile extends StatelessWidget {
  const _ContractTile({
    required this.contract,
    required this.wide,
    required this.onTap,
  });

  final SafeContractsContract contract;
  final bool wide;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final dates = <String>[
      if (contract.startDate != null) 'Start ${contract.startDate}',
      if (contract.endDate != null) 'End ${contract.endDate}',
    ].join(' • ');
    final secondary = <String>[
      if (contract.customerName != null) contract.customerName!,
      if (dates.isNotEmpty) dates,
      if (contract.baseValue != null) 'Value ${contract.baseValue}',
    ].join(' • ');

    return ListTile(
      leading: const CircleAvatar(child: Icon(Icons.description_outlined)),
      title: Text(contract.contractNumber),
      subtitle: Text(secondary),
      isThreeLine: !wide && secondary.length > 45,
      trailing: Wrap(
        spacing: 6,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          if (contract.isArchived)
            const Chip(
              avatar: Icon(Icons.archive_outlined, size: 16),
              label: Text('Archived'),
            ),
          Chip(label: Text(contract.status)),
          const Icon(Icons.chevron_right),
        ],
      ),
      onTap: onTap,
    );
  }
}

final class _ContractPagination extends StatelessWidget {
  const _ContractPagination({required this.controller, required this.page});

  final ContractsController controller;
  final ContractPage page;

  @override
  Widget build(BuildContext context) {
    final busy = controller.state == ContractsLoadState.loading;
    return SafeArea(
      top: false,
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            OutlinedButton.icon(
              onPressed: busy || page.page <= 1
                  ? null
                  : () => unawaited(controller.previousPage()),
              icon: const Icon(Icons.chevron_left),
              label: const Text('Previous'),
            ),
            const SizedBox(width: 12),
            Text('${page.page} / 5'),
            const SizedBox(width: 12),
            OutlinedButton.icon(
              onPressed: busy || !page.hasMore || page.page >= 5
                  ? null
                  : () => unawaited(controller.nextPage()),
              icon: const Icon(Icons.chevron_right),
              label: const Text('Next'),
            ),
          ],
        ),
      ),
    );
  }
}

final class _ContractsError extends StatelessWidget {
  const _ContractsError({required this.message, required this.onRetry});

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
            const Icon(Icons.error_outline, size: 44),
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

final class _InlineContractsError extends StatelessWidget {
  const _InlineContractsError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
      child: Text(
        message,
        style: TextStyle(color: Theme.of(context).colorScheme.error),
      ),
    );
  }
}
