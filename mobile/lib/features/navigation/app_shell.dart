import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../contracts/contract_details_screen.dart';
import '../contracts/contract_edit_screen.dart';
import '../contracts/contracts.dart';
import '../contracts/contracts_screen.dart';
import '../customers/customers.dart';
import '../customers/customers_screen.dart';
import '../dashboard/dashboard_context_screen.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_models.dart';
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
import '../refresh/silent_refresh.dart';
import '../session/session_controller.dart';
import '../ui/safecontracts_design.dart';
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
          await widget.dashboardController.refreshSilently();
          break;
        case MobileDestination.customers:
          await widget.customersController.refreshSilently();
          break;
        case MobileDestination.contracts:
          await widget.contractsController.refreshSilently();
          break;
        case MobileDestination.notifications:
          await widget.notificationsController.refreshSilently();
          break;
        case MobileDestination.profile:
          await widget.profileController.refreshSilently();
          break;
        case MobileDestination.payments:
        case MobileDestination.followUps:
          if (mounted) {
            setState(() => _liveRefreshRevision++);
          }
          break;
        case MobileDestination.export:
          await widget.dashboardController.refreshSilently();
          break;
        case MobileDestination.collections:
          break;
      }
    } on Object {
      // Automatic refresh is deliberately non-disruptive. The last good data
      // remains visible and manual refresh still exposes actionable failures.
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

    final bottomDestinations = _bottomDestinations();
    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        backgroundColor: SafeContractsVisual.background,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        titleSpacing: 8,
        title: Row(
          children: [
            const SafeContractsBrandMark(size: 32, borderRadius: 9),
            const SizedBox(width: 9),
            Expanded(
              child: Text.rich(
                TextSpan(
                  children: [
                    const TextSpan(
                      text: SafeContractsBrand.name,
                      style: TextStyle(fontWeight: FontWeight.w800),
                    ),
                    TextSpan(
                      text: ' | ${_label(l10n, _selected)}',
                      style: const TextStyle(fontWeight: FontWeight.w500),
                    ),
                  ],
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ),
          ],
        ),
      ),
      drawer: NavigationDrawer(
        backgroundColor: SafeContractsVisual.surface,
        selectedIndex: widget.policy.destinations.indexOf(_selected),
        onDestinationSelected: (index) {
          final destination = widget.policy.destinations[index];
          _selectDestination(destination);
          Navigator.of(context).pop();
        },
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(24, 22, 16, 14),
            child: Row(
              children: [
                const SafeContractsBrandMark(size: 46, borderRadius: 13),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    SafeContractsBrand.name,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                          color: SafeContractsVisual.navy,
                        ),
                  ),
                ),
              ],
            ),
          ),
          ...widget.policy.destinations.map(
            (destination) => NavigationDrawerDestination(
              icon: Icon(_icon(destination)),
              selectedIcon: Icon(
                _icon(destination),
                color: SafeContractsVisual.navy,
              ),
              label: Text(_label(l10n, destination)),
            ),
          ),
        ],
      ),
      body: SafeContractsBackdrop(
        child: Column(
          children: [
            if (widget.usingConfigDefaults)
              MaterialBanner(
                backgroundColor: SafeContractsVisual.amberSoft,
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
      ),
      bottomNavigationBar: bottomDestinations.isEmpty
          ? null
          : _SafeContractsBottomNavigation(
              destinations: bottomDestinations,
              selected: _selected,
              labelFor: (destination) => _label(l10n, destination),
              onSelected: _selectDestination,
            ),
    );
  }

  List<MobileDestination> _bottomDestinations() {
    const preferred = <MobileDestination>[
      MobileDestination.dashboard,
      MobileDestination.customers,
      MobileDestination.contracts,
      MobileDestination.payments,
      MobileDestination.profile,
    ];
    return preferred
        .where(widget.policy.destinations.contains)
        .take(5)
        .toList(growable: false);
  }

  Widget _body() {
    final apiClient = widget.contractsController.repository.client;
    return switch (_selected) {
      MobileDestination.dashboard => DashboardContextScreen(
          controller: widget.dashboardController,
          currency: widget.config.currency,
          onOpenPayments:
              widget.policy.destinations.contains(MobileDestination.payments)
                  ? () => _selectDestination(MobileDestination.payments)
                  : null,
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

final class _SafeContractsBottomNavigation extends StatelessWidget {
  const _SafeContractsBottomNavigation({
    required this.destinations,
    required this.selected,
    required this.labelFor,
    required this.onSelected,
  });

  final List<MobileDestination> destinations;
  final MobileDestination selected;
  final String Function(MobileDestination destination) labelFor;
  final ValueChanged<MobileDestination> onSelected;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: DecoratedBox(
        decoration: const BoxDecoration(
          color: SafeContractsVisual.surface,
          boxShadow: [
            BoxShadow(
              color: Color(0x205E5142),
              blurRadius: 18,
              offset: Offset(0, -4),
            ),
          ],
        ),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(6, 8, 6, 6),
          child: Row(
            children: destinations.map((destination) {
              final isSelected = destination == selected;
              return Expanded(
                child: InkWell(
                  borderRadius: BorderRadius.circular(16),
                  onTap: () => onSelected(destination),
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 5),
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Icon(
                          _icon(destination),
                          color: isSelected
                              ? SafeContractsVisual.navy
                              : SafeContractsVisual.muted,
                        ),
                        const SizedBox(height: 3),
                        Text(
                          labelFor(destination),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style:
                              Theme.of(context).textTheme.labelSmall?.copyWith(
                                    color: isSelected
                                        ? SafeContractsVisual.navy
                                        : SafeContractsVisual.muted,
                                    fontWeight: isSelected
                                        ? FontWeight.w800
                                        : FontWeight.w500,
                                  ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            }).toList(growable: false),
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
    MobileDestination.dashboard => Icons.home_rounded,
    MobileDestination.customers => Icons.people_alt_outlined,
    MobileDestination.contracts => Icons.folder_copy_outlined,
    MobileDestination.payments => Icons.receipt_long_outlined,
    MobileDestination.collections => Icons.payments_outlined,
    MobileDestination.followUps => Icons.timeline_outlined,
    MobileDestination.notifications => Icons.notifications_outlined,
    MobileDestination.export => Icons.file_download_outlined,
    MobileDestination.profile => Icons.person_outline,
  };
}
