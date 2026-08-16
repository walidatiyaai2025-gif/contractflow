import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../contracts/contract_details_screen.dart';
import '../contracts/contract_edit_screen.dart';
import '../contracts/contracts.dart';
import '../contracts/contracts_screen.dart';
import '../customers/customers.dart';
import '../customers/customers_screen.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_models.dart';
import '../dashboard/dashboard_screen.dart';
import '../export/mobile_excel_export.dart';
import '../export/mobile_excel_export_screen.dart';
import '../followups/followups.dart';
import '../followups/followups_screen.dart';
import '../notifications/deep_link.dart';
import '../notifications/notifications.dart';
import '../notifications/notifications_screen.dart';
import '../notifications/push_registration.dart';
import '../payments/payments.dart';
import '../payments/payments_screen.dart';
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
    required this.pushRegistration,
    required this.languageCode,
    required this.onLanguageChanged,
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
  final MobilePushRegistration pushRegistration;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;
  final bool usingConfigDefaults;
  final VoidCallback onClearSession;

  @override
  State<SafeContractsShell> createState() => _SafeContractsShellState();
}

final class _SafeContractsShellState extends State<SafeContractsShell>
    with WidgetsBindingObserver {
  static const Duration _liveRefreshInterval = Duration(seconds: 12);

  MobileDestination _selected = MobileDestination.dashboard;
  Timer? _liveRefreshTimer;
  bool _liveRefreshInFlight = false;
  bool _foreground = true;
  int _liveRefreshRevision = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    _liveRefreshTimer = Timer.periodic(
      _liveRefreshInterval,
      (_) => unawaited(_refreshActiveSurface()),
    );
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => unawaited(_refreshActiveSurface()),
    );
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    _foreground = state == AppLifecycleState.resumed;
    if (_foreground) {
      unawaited(_refreshActiveSurface());
    }
  }

  @override
  void dispose() {
    _liveRefreshTimer?.cancel();
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  Future<void> _refreshActiveSurface() async {
    if (!mounted || !_foreground || _liveRefreshInFlight) return;
    _liveRefreshInFlight = true;
    try {
      switch (_selected) {
        case MobileDestination.dashboard:
          await widget.dashboardController.refresh();
          break;
        case MobileDestination.customers:
          await widget.customersController.refresh();
          break;
        case MobileDestination.contracts:
          await widget.contractsController.refresh();
          break;
        case MobileDestination.notifications:
          await widget.notificationsController.refresh();
          break;
        case MobileDestination.profile:
          await widget.profileController.load();
          break;
        case MobileDestination.payments:
        case MobileDestination.followUps:
          if (mounted) {
            setState(() => _liveRefreshRevision++);
          }
          break;
        case MobileDestination.export:
          await widget.dashboardController.refresh();
          break;
        case MobileDestination.collections:
          break;
      }
    } on Object {
      // Individual controllers/screens own their bounded error state. Live
      // refresh must never tear down an otherwise usable authenticated shell.
    } finally {
      _liveRefreshInFlight = false;
    }
  }

  void _selectDestination(MobileDestination destination) {
    if (_selected != destination) {
      setState(() => _selected = destination);
    }
    unawaited(_refreshActiveSurface());
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
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
              _label(l10n, _selected),
              style: Theme.of(context).textTheme.labelMedium,
            ),
          ],
        ),
      ),
      drawer: NavigationDrawer(
        selectedIndex: widget.policy.destinations.indexOf(_selected),
        onDestinationSelected: (index) {
          final destination = widget.policy.destinations[index];
          _selectDestination(destination);
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
              label: Text(_label(l10n, destination)),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          if (widget.usingConfigDefaults)
            MaterialBanner(
              content: Text(
                l10n.t(
                  'Remote mobile configuration is unavailable. Safe defaults are active.',
                ),
              ),
              actions: const <Widget>[SizedBox.shrink()],
            ),
          Expanded(child: _body()),
        ],
      ),
    );
  }

  Widget _body() {
    final apiClient = widget.contractsController.repository.client;
    return switch (_selected) {
      MobileDestination.dashboard => DashboardScreen(
          controller: widget.dashboardController,
          currency: widget.config.currency,
        ),
      MobileDestination.customers => CustomersScreen(
          controller: widget.customersController,
        ),
      MobileDestination.contracts => ContractsScreen(
          controller: widget.contractsController,
          customers: widget.dashboardController.overview?.customers ??
              const <CustomerOption>[],
          currency: widget.config.currency,
          onOpenContract: _openContract,
        ),
      MobileDestination.payments => PaymentsScreen(
          repository: PaymentsRepository(apiClient),
          pageSize: widget.config.defaultPageSize,
          filters: widget.dashboardController.filters,
          currency: widget.config.currency,
          canManagePayments:
              widget.session.can('safecontracts_manage_payments'),
          canEnterCollection: widget.policy.canEnterCollection,
          refreshRevision: _liveRefreshRevision,
        ),
      MobileDestination.followUps => FollowUpsScreen(
          repository: FollowUpsRepository(apiClient),
          pageSize: widget.config.defaultPageSize,
          filters: widget.dashboardController.filters,
          currency: widget.config.currency,
          canManage: widget.policy.canManageFollowUps,
          refreshRevision: _liveRefreshRevision,
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
          pushRegistration: widget.pushRegistration,
          languageCode: widget.languageCode,
          onLanguageChanged: widget.onLanguageChanged,
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
          currency: widget.config.currency,
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
        builder: (context) => ContractEditScreen(
          contractsController: widget.contractsController,
          contractId: contractId,
        ),
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
    _selectDestination(destination);
    switch (link.destination) {
      case SafeContractsDeepLinkDestination.contracts:
        _openContract(link.resourceId);
        break;
      case SafeContractsDeepLinkDestination.customers:
        unawaited(widget.customersController.openCustomer(link.resourceId));
        break;
      case SafeContractsDeepLinkDestination.payments:
      case SafeContractsDeepLinkDestination.followUps:
        break;
    }
  }
}

final class _PlannedDestination extends StatelessWidget {
  const _PlannedDestination({required this.destination});

  final MobileDestination destination;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(
          l10n.isArabic
              ? 'تم السماح بالتنقل إلى ${_label(l10n, destination)}. شاشة الموبايل المخصصة لها تُنفذ ضمن مهمة خارطة الطريق المقابلة.'
              : '${_label(l10n, destination)} navigation is authorized. Its dedicated mobile screen is implemented in the corresponding roadmap task.',
          textAlign: TextAlign.center,
        ),
      ),
    );
  }
}

String _label(SafeContractsLocalizations l10n, MobileDestination destination) {
  return l10n.t(switch (destination) {
    MobileDestination.dashboard => 'Dashboard',
    MobileDestination.customers => 'Customers',
    MobileDestination.contracts => 'Contracts',
    MobileDestination.payments => 'Payments',
    MobileDestination.collections => 'Collections',
    MobileDestination.followUps => 'Follow-up',
    MobileDestination.notifications => 'Notifications',
    MobileDestination.export => 'Excel export',
    MobileDestination.profile => 'Profile',
  });
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
