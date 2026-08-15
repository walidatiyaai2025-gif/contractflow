import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import 'customers.dart';

final class CustomersScreen extends StatefulWidget {
  const CustomersScreen({required this.controller, super.key});

  final CustomersController controller;

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

final class _CustomersScreenState extends State<CustomersScreen> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  void didUpdateWidget(CustomersScreen oldWidget) {
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
        return LayoutBuilder(
          builder: (context, constraints) {
            final wide = constraints.maxWidth >= 840;
            return Column(
              children: [
                _CustomersToolbar(controller: widget.controller),
                const Divider(height: 1),
                Expanded(
                  child: _CustomersContent(
                    controller: widget.controller,
                    wide: wide,
                  ),
                ),
              ],
            );
          },
        );
      },
    );
  }
}

final class _CustomersToolbar extends StatelessWidget {
  const _CustomersToolbar({required this.controller});

  final CustomersController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final busy = controller.state == CustomersLoadState.loading;
    final page = controller.currentPage;

    return Padding(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 12),
      child: Wrap(
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 12,
        runSpacing: 8,
        children: [
          Text(l10n.t('Customers'), style: Theme.of(context).textTheme.titleLarge),
          ChoiceChip(
            label: const Text('A–Z'),
            selected: controller.order == 'asc',
            onSelected: busy
                ? null
                : (selected) {
                    if (selected) unawaited(controller.setOrder('asc'));
                  },
          ),
          ChoiceChip(
            label: const Text('Z–A'),
            selected: controller.order == 'desc',
            onSelected: busy
                ? null
                : (selected) {
                    if (selected) unawaited(controller.setOrder('desc'));
                  },
          ),
          IconButton(
            tooltip: l10n.t('Refresh customers'),
            onPressed: busy ? null : () => unawaited(controller.refresh()),
            icon: const Icon(Icons.refresh),
          ),
          if (page != null)
            Text(
              l10n.pageShown(page.page, page.customers.length),
              style: Theme.of(context).textTheme.bodySmall,
            ),
        ],
      ),
    );
  }
}

final class _CustomersContent extends StatelessWidget {
  const _CustomersContent({required this.controller, required this.wide});

  final CustomersController controller;
  final bool wide;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final page = controller.currentPage;

    if (controller.state == CustomersLoadState.loading && page == null) {
      return const Center(child: CircularProgressIndicator());
    }
    if (controller.state == CustomersLoadState.error && page == null) {
      return _CustomersError(
        message: l10n.rawMessage(
          controller.errorMessage ?? 'Unable to load customers.',
        ),
        onRetry: () => unawaited(controller.loadPage(1)),
      );
    }
    if (page == null) {
      return Center(child: Text(l10n.t('Customers are not loaded yet.')));
    }

    final list = _CustomerList(controller: controller, page: page);
    final detail = _CustomerDetailPane(controller: controller, showBack: !wide);

    if (wide) {
      return Row(
        children: [
          Expanded(flex: 3, child: list),
          const VerticalDivider(width: 1),
          Expanded(flex: 2, child: detail),
        ],
      );
    }

    if (controller.selectedCustomerId != null) return detail;
    return list;
  }
}

final class _CustomerList extends StatelessWidget {
  const _CustomerList({required this.controller, required this.page});

  final CustomersController controller;
  final CustomerPage page;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (page.customers.isEmpty) {
      return RefreshIndicator(
        onRefresh: controller.refresh,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: <Widget>[
            const SizedBox(height: 120),
            const Icon(Icons.business_outlined, size: 48),
            const SizedBox(height: 12),
            Center(child: Text(l10n.t('No customers are available in your scope.'))),
          ],
        ),
      );
    }

    return Column(
      children: [
        if (controller.state == CustomersLoadState.loading)
          const LinearProgressIndicator(),
        if (controller.state == CustomersLoadState.error)
          _InlineCustomerError(
            message: l10n.rawMessage(
              controller.errorMessage ?? 'Customer refresh failed.',
            ),
          ),
        Expanded(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(vertical: 8),
              itemCount: page.customers.length,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) {
                final customer = page.customers[index];
                return ListTile(
                  selected: controller.selectedCustomerId == customer.id,
                  leading: CircleAvatar(
                    child: Text(customer.name.characters.first.toUpperCase()),
                  ),
                  title: Text(customer.name),
                  subtitle: Text(
                    <String>[
                      if (customer.internalCode != null) customer.internalCode!,
                      if (customer.contactName != null) customer.contactName!,
                    ].join(' • '),
                  ),
                  trailing: Chip(
                    label: Text(l10n.status(customer.isActive ? 'active' : 'inactive')),
                  ),
                  onTap: () => unawaited(controller.openCustomer(customer.id)),
                );
              },
            ),
          ),
        ),
        _CustomerPagination(controller: controller, page: page),
      ],
    );
  }
}

final class _CustomerPagination extends StatelessWidget {
  const _CustomerPagination({required this.controller, required this.page});

  final CustomersController controller;
  final CustomerPage page;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final busy = controller.state == CustomersLoadState.loading;
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
              label: Text(l10n.t('Previous')),
            ),
            const SizedBox(width: 12),
            Text('${page.page} / 5'),
            const SizedBox(width: 12),
            OutlinedButton.icon(
              onPressed: busy || !page.hasMore || page.page >= 5
                  ? null
                  : () => unawaited(controller.nextPage()),
              icon: const Icon(Icons.chevron_right),
              label: Text(l10n.t('Next')),
            ),
          ],
        ),
      ),
    );
  }
}

final class _CustomerDetailPane extends StatelessWidget {
  const _CustomerDetailPane({required this.controller, required this.showBack});

  final CustomersController controller;
  final bool showBack;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final selectedId = controller.selectedCustomerId;
    if (selectedId == null) {
      return Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(l10n.t('Select a customer to view authorized details.')),
        ),
      );
    }

    final customer = controller.selectedCustomer;
    return ListView(
      padding: const EdgeInsets.all(20),
      children: [
        if (showBack)
          Align(
            alignment: AlignmentDirectional.centerStart,
            child: TextButton.icon(
              onPressed: controller.closeCustomer,
              icon: const Icon(Icons.arrow_back),
              label: Text(l10n.t('Customers')),
            ),
          ),
        if (controller.detailState == CustomerDetailLoadState.loading) ...[
          const LinearProgressIndicator(),
          const SizedBox(height: 20),
          Text(l10n.loadingCustomer(selectedId)),
        ] else if (controller.detailState == CustomerDetailLoadState.error) ...[
          _CustomersError(
            message: l10n.rawMessage(
              controller.detailErrorMessage ?? 'Unable to load customer.',
            ),
            onRetry: () => unawaited(controller.openCustomer(selectedId)),
          ),
        ] else if (customer != null) ...[
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: Text(
                  customer.name,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
              ),
              Chip(
                label: Text(l10n.status(customer.isActive ? 'active' : 'inactive')),
              ),
            ],
          ),
          const SizedBox(height: 20),
          _CustomerField(label: l10n.t('Customer ID'), value: '${customer.id}'),
          _CustomerField(
            label: l10n.t('Internal code'),
            value: customer.internalCode ?? '—',
          ),
          _CustomerField(
            label: l10n.t('Contact name'),
            value: customer.contactName ?? '—',
          ),
          _CustomerField(label: l10n.t('Email'), value: customer.email ?? '—'),
          _CustomerField(label: l10n.t('Phone'), value: customer.phone ?? '—'),
          const SizedBox(height: 12),
          Text(
            l10n.t('Only server-authorized customer fields are shown.'),
            style: Theme.of(context).textTheme.bodySmall,
          ),
        ],
      ],
    );
  }
}

final class _CustomerField extends StatelessWidget {
  const _CustomerField({required this.label, required this.value});
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(bottom: 14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(label, style: Theme.of(context).textTheme.labelMedium),
            const SizedBox(height: 4),
            SelectableText(value),
          ],
        ),
      );
}

final class _CustomersError extends StatelessWidget {
  const _CustomersError({required this.message, required this.onRetry});
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, size: 44),
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

final class _InlineCustomerError extends StatelessWidget {
  const _InlineCustomerError({required this.message});
  final String message;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 4),
        child: Text(
          message,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
      );
}
