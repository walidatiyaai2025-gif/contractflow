import 'dart:async';

import 'package:flutter/material.dart';

import '../config/mobile_config.dart';
import '../contracts/contract_details_screen.dart';
import '../contracts/contracts.dart';
import '../contracts/contracts_screen.dart';
import '../customers/customers.dart';
import '../customers/customers_screen.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_models.dart';
import '../dashboard/dashboard_screen.dart';
import '../export/mobile_excel_export.dart';
import '../export/mobile_excel_export_screen.dart';
import '../notifications/deep_link.dart';
import '../notifications/notifications.dart';
import '../notifications/notifications_screen.dart';
import '../profile/profile.dart';
import '../profile/profile_screen.dart';
import '../session/session_controller.dart';
import 'navigation_policy.dart';

final class SafeContractsShell extends StatefulWidget {
  const SafeContractsShell({
    required this.session,
    required this.config,
    required this.policy,
    required this.dashboardController,
    required this.customersController,
    required this.contractsController,
    required this.notificationsController,
    required this.profileController,
    required this.excelExportController,
    required this.usingConfigDefaults,
    required this.onClearSession,
    super.key,
  });

  final SafeContractsSession session;
  final SafeContractsMobileConfig config;
  final MobileNavigationPolicy policy;
  final DashboardController dashboardController;
  final CustomersController customersController;
  final ContractsController contractsController;
  final NotificationsController notificationsController;
  final ProfileController profileController;
  final MobileExcelExportController excelExportController;
  final bool usingConfigDefaults;
  final VoidCallback onClearSession;

  @override
  State<SafeContractsShell> createState() => _SafeContractsShellState();
}

final class _SafeContractsShellState extends State<SafeContractsShell> {
  MobileDestination _selected = MobileDestination.dashboard;

  @override
  Widget build(BuildContext context) {
    if (!widget.policy.destinations.contains(_selected)) {
      _selected = widget.policy.destinations.first;
    }

    return Scaffold(
      appBar: AppBar(
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('SafeContracts'),
            Text(
              _label(_selected),
              style: Theme.of(context).textTheme.labelMedium,
            ),
          ],
        ),
      ),
      drawer: NavigationDrawer(
        selectedIndex: widget.policy.destinations.indexOf(_selected),
        onDestinationSelected: (index) {
          setState(() {
            _selected = widget.policy.destinations[index];
          });
          Navigator.of(context).pop();
        },
        children: [
          const Padding(
            padding: EdgeInsets.fromLTRB(28, 20, 16, 12),
            child: Text('SafeContracts'),
          ),
          ...widget.policy.destinations.map(
            (destination) => NavigationDrawerDestination(
              icon: Icon(_icon(destination)),
              label: Text(_label(destination)),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          if (widget.usingConfigDefaults)
            MaterialBanner(
              content: const Text(
                'Remote mobile configuration is unavailable. Safe defaults are active.',
              ),
              actions: const <Widget>[SizedBox.shrink()],
            ),
          Expanded(child: _body()),
        ],
      ),
    );
  }

  Widget _body() {
    return switch (_selected) {
      MobileDestination.dashboard => DashboardScreen(
          controller: widget.dashboardController,
        ),
      MobileDestination.customers => CustomersScreen(
          controller: widget.customersController,
        ),
      MobileDestination.contracts => ContractsScreen(
          controller: widget.contractsController,
          customers: widget.dashboardController.overview?.customers ??
              const <CustomerOption>[],
          onOpenContract: _openContract,
        ),
      MobileDestination.notifications => NotificationsScreen(
          controller: widget.notificationsController,
          onOpenDeepLink: _openDeepLink,
        ),
      MobileDestination.export => MobileExcelExportScreen(
          controller: widget.excelExportController,
        ),
      MobileDestination.profile => ProfileScreen(
          session: widget.session,
          config: widget.config,
          controller: widget.profileController,
          onClearSession: widget.onClearSession,
        ),
      _ => _PlannedDestination(destination: _selected),
    };
  }

  void _openContract(int contractId) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => ContractDetailsScreen(
          controller: widget.contractsController,
          contractId: contractId,
          onEditContract: widget.contractsController.canEditContract
              ? _openContractEdit
              : null,
        ),
      ),
    );
  }

  void _openContractEdit(int contractId) {
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => _ContractEditHandoff(contractId: contractId),
      ),
    );
  }

  void _openDeepLink(SafeContractsDeepLink link) {
    final destination = switch (link.destination) {
      SafeContractsDeepLinkDestination.payments => MobileDestination.payments,
      SafeContractsDeepLinkDestination.contracts => MobileDestination.contracts,
      SafeContractsDeepLinkDestination.customers => MobileDestination.customers,
      SafeContractsDeepLinkDestination.followUps => MobileDestination.followUps,
    };
    if (!widget.policy.destinations.contains(destination)) {
      return;
    }
    setState(() => _selected = destination);
    switch (link.destination) {
      case SafeContractsDeepLinkDestination.contracts:
        _openContract(link.resourceId);
      case SafeContractsDeepLinkDestination.customers:
        unawaited(widget.customersController.openCustomer(link.resourceId));
      case SafeContractsDeepLinkDestination.payments:
      case SafeContractsDeepLinkDestination.followUps:
        break;
    }
  }
}

final class _ContractEditHandoff extends StatelessWidget {
  const _ContractEditHandoff({required this.contractId});

  final int contractId;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Edit contract')),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.edit_note_outlined, size: 52),
              const SizedBox(height: 16),
              Text(
                'Contract #$contractId',
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 8),
              const Text(
                'Your session is authorized to request contract edits. '
                'The supported edit fields, validation, conflicts and audit '
                'write flow are implemented in SC-P9-013.',
                textAlign: TextAlign.center,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _PlannedDestination extends StatelessWidget {
  const _PlannedDestination({required this.destination});

  final MobileDestination destination;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          '${_label(destination)} navigation is authorized. '
          'Its dedicated mobile screen is implemented in the corresponding roadmap task.',
          textAlign: TextAlign.center,
        ),
      ),
    );
  }
}

String _label(MobileDestination destination) {
  return switch (destination) {
    MobileDestination.dashboard => 'Dashboard',
    MobileDestination.customers => 'Customers',
    MobileDestination.contracts => 'Contracts',
    MobileDestination.payments => 'Payments',
    MobileDestination.collections => 'Collections',
    MobileDestination.followUps => 'Follow-up',
    MobileDestination.notifications => 'Notifications',
    MobileDestination.export => 'Excel export',
    MobileDestination.profile => 'Profile',
  };
}

IconData _icon(MobileDestination destination) {
  return switch (destination) {
    MobileDestination.dashboard => Icons.dashboard_outlined,
    MobileDestination.customers => Icons.business_outlined,
    MobileDestination.contracts => Icons.description_outlined,
    MobileDestination.payments => Icons.event_note_outlined,
    MobileDestination.collections => Icons.payments_outlined,
    MobileDestination.followUps => Icons.follow_the_signs_outlined,
    MobileDestination.notifications => Icons.notifications_outlined,
    MobileDestination.export => Icons.file_download_outlined,
    MobileDestination.profile => Icons.person_outline,
  };
}
