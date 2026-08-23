import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../contracts/contract_edit_screen.dart';
import '../contracts/contracts.dart';
import '../contracts/contracts_screen.dart';
import '../contracts/premium_contract_details_screen.dart';
import '../customers/customers.dart';
import '../customers/customers_screen.dart';
import '../dashboard/dashboard_context_screen.dart';
import '../dashboard/dashboard_controller.dart';
import '../dashboard/dashboard_models.dart';
import '../dashboard/dashboard_two_screen.dart';
import '../export/mobile_excel_export.dart';
import '../export/mobile_excel_export_screen.dart';
import '../finance/finance.dart';
import '../finance/finance_screen.dart';
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
import '../records/mobile_quick_add_screen.dart';
import '../refresh/silent_refresh.dart';
import '../session/session_controller.dart';
import '../suppliers/suppliers.dart';
import '../suppliers/suppliers_screen.dart';
import '../ui/safecontracts_design.dart';
import 'navigation_policy.dart';

final class SafeContractsShell extends StatefulWidget {
  const SafeContractsShell({
    required this.session,
    required this.config,
    required this.policy,
    required this.dashboardController,
    required this.customersController,
    required this.suppliersController,
    required this.contractsController,
    required this.financeController,
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
  final SuppliersController suppliersController;
  final ContractsController contractsController;
  final FinanceController financeController;
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
    if (_foreground) unawaited(_refreshActiveSurface());
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
    var shellSnapshotChanged = false;
    try {
      switch (_selected) {
        case MobileDestination.dashboard:
        case MobileDestination.dashboardTwo:
          await widget.dashboardController.refreshSilently();
          shellSnapshotChanged = true;
          break;
        case MobileDestination.customers:
          await widget.customersController.refreshSilently();
          shellSnapshotChanged = true;
          break;
        case MobileDestination.suppliers:
          await widget.suppliersController.refreshSilently();
          shellSnapshotChanged = true;
          break;
        case MobileDestination.contracts:
          await widget.contractsController.refreshSilently();
          shellSnapshotChanged = true;
          break;
        case MobileDestination.finance:
          await widget.financeController.refreshSilently();
          shellSnapshotChanged = true;
          break;
        case MobileDestination.notifications:
          await widget.notificationsController.refreshSilently();
          shellSnapshotChanged = true;
          break;
        case MobileDestination.profile:
          await widget.profileController.refreshSilently();
          shellSnapshotChanged = true;
          break;
        case MobileDestination.payments:
        case MobileDestination.followUps:
          if (mounted) setState(() => _liveRefreshRevision++);
          break;
        case MobileDestination.export:
          await widget.dashboardController.refreshSilently();
          shellSnapshotChanged = true;
          break;
        case MobileDestination.collections:
          break;
      }
      if (shellSnapshotChanged && mounted) setState(() {});
    } on Object {
      // Background refresh is deliberately non-disruptive.
    } finally {
      _liveRefreshInFlight = false;
    }
  }

  void _selectDestination(MobileDestination destination) {
    if (_selected != destination) setState(() => _selected = destination);
    unawaited(_refreshActiveSurface());
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (!widget.policy.destinations.contains(_selected)) {
      _selected = widget.policy.destinations.first;
    }
    final bottomDestinations = _bottomDestinations();
    final quickAdds = availableMobileQuickAdds(widget.session);

    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        backgroundColor: SafeContractsVisual.navy,
        foregroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        titleSpacing: 8,
        flexibleSpace: const DecoratedBox(
          decoration: BoxDecoration(
            gradient: SafeContractsVisual.premiumHeaderGradient,
          ),
        ),
        title: Row(
          children: [
            Container(
              padding: const EdgeInsets.all(2),
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: .12),
                borderRadius: BorderRadius.circular(11),
              ),
              child: const SafeContractsBrandMark(size: 32, borderRadius: 9),
            ),
            const SizedBox(width: 10),
            Expanded(
              child: Text(
                _label(l10n, _selected),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
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
          Container(
            margin: const EdgeInsets.fromLTRB(12, 14, 12, 10),
            padding: const EdgeInsets.fromLTRB(16, 18, 16, 18),
            decoration: BoxDecoration(
              gradient: SafeContractsVisual.premiumHeaderGradient,
              borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x2B092944),
                  blurRadius: 20,
                  offset: Offset(0, 8),
                ),
              ],
            ),
            child: Row(
              children: [
                const SafeContractsBrandMark(size: 48, borderRadius: 13),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        SafeContractsBrand.name,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              fontWeight: FontWeight.w900,
                              color: Colors.white,
                            ),
                      ),
                      const SizedBox(height: 2),
                      Text(
                        l10n.isArabic
                            ? 'مساحة العمل التنفيذية'
                            : 'Executive workspace',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                              color: Colors.white.withValues(alpha: .70),
                            ),
                      ),
                    ],
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
            Expanded(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 250),
                child: KeyedSubtree(
                  key: ValueKey<MobileDestination>(_selected),
                  child: _body(),
                ),
              ),
            ),
          ],
        ),
      ),
      floatingActionButton: quickAdds.isEmpty
          ? null
          : _QuickAddFab(
              tooltip: l10n.isArabic ? 'إضافة جديدة' : 'Quick add',
              onPressed: () => unawaited(_showQuickAdd(quickAdds)),
            ),
      floatingActionButtonLocation: FloatingActionButtonLocation.endFloat,
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
      MobileDestination.dashboardTwo => DashboardTwoScreen(
          controller: widget.dashboardController,
          currency: widget.config.currency,
        ),
      MobileDestination.customers => CustomersScreen(
          controller: widget.customersController,
        ),
      MobileDestination.suppliers => SuppliersScreen(
          controller: widget.suppliersController,
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
      MobileDestination.finance => FinanceScreen(
          controller: widget.financeController,
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
      MobileDestination.collections =>
        const _PlannedDestination(destination: MobileDestination.collections),
    };
  }

  Future<void> _showQuickAdd(List<MobileQuickAddType> actions) async {
    final selected = await showModalBottomSheet<MobileQuickAddType>(
      context: context,
      backgroundColor: SafeContractsVisual.surface,
      showDragHandle: true,
      useSafeArea: true,
      builder: (sheetContext) => _QuickAddSheet(actions: actions),
    );
    if (!mounted || selected == null) return;

    final created = await Navigator.of(context).push<bool>(
      MaterialPageRoute<bool>(
        builder: (context) => MobileQuickAddScreen(
          client: widget.contractsController.repository.client,
          session: widget.session,
          type: selected,
        ),
      ),
    );
    if (!mounted || created != true) return;

    switch (selected) {
      case MobileQuickAddType.customer:
        unawaited(widget.customersController.refreshSilently());
        break;
      case MobileQuickAddType.supplier:
        unawaited(widget.suppliersController.refreshSilently());
        break;
      case MobileQuickAddType.contract:
        unawaited(widget.contractsController.refreshSilently());
        break;
      case MobileQuickAddType.payment:
        setState(() => _liveRefreshRevision++);
        break;
    }
    unawaited(widget.dashboardController.refreshSilently());
    if (widget.financeController.canAccess) {
      unawaited(widget.financeController.refreshSilently());
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          context.scL10n.isArabic
              ? 'تمت الإضافة بنجاح.'
              : 'Added successfully.',
        ),
      ),
    );
  }

  void _openContract(int contractId) {
    final canEdit = widget.contractsController.canEditContract ||
        widget.session.can('safecontracts_assign_contracts');
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => PremiumContractDetailsScreen(
          repository: widget.contractsController.repository,
          contractId: contractId,
          currency: widget.config.currency,
          onEditContract: canEdit ? _openContractEdit : null,
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
    if (!widget.policy.destinations.contains(destination)) return;
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

final class _QuickAddFab extends StatelessWidget {
  const _QuickAddFab({required this.tooltip, required this.onPressed});
  final String tooltip;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        border: Border.all(color: SafeContractsVisual.roseGoldSoft, width: 3),
        boxShadow: [
          BoxShadow(
            color: SafeContractsVisual.roseGold.withValues(alpha: .30),
            blurRadius: 24,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: FloatingActionButton(
        tooltip: tooltip,
        onPressed: onPressed,
        backgroundColor: SafeContractsVisual.roseGold,
        foregroundColor: Colors.white,
        elevation: 0,
        child: const Icon(Icons.add_rounded, size: 34),
      ),
    );
  }
}

final class _QuickAddSheet extends StatelessWidget {
  const _QuickAddSheet({required this.actions});
  final List<MobileQuickAddType> actions;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return Padding(
      padding: const EdgeInsets.fromLTRB(18, 0, 18, 22),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            arabic ? 'إضافة جديدة' : 'Quick add',
            style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  color: SafeContractsVisual.navy,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 12),
          ...actions.map(
            (action) => Padding(
              padding: const EdgeInsets.only(bottom: 9),
              child: Material(
                color: SafeContractsVisual.backgroundRaised,
                borderRadius: BorderRadius.circular(16),
                child: ListTile(
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                  leading: Container(
                    width: 42,
                    height: 42,
                    decoration: BoxDecoration(
                      color: SafeContractsVisual.roseGoldSoft,
                      borderRadius: BorderRadius.circular(13),
                    ),
                    child: Icon(
                      mobileQuickAddIcon(action),
                      color: SafeContractsVisual.navy,
                    ),
                  ),
                  title: Text(
                    mobileQuickAddLabel(context, action),
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
                  subtitle: Text(
                    mobileQuickAddDescription(context, action),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  trailing: Icon(
                    Directionality.of(context) == TextDirection.rtl
                        ? Icons.chevron_left_rounded
                        : Icons.chevron_right_rounded,
                  ),
                  onTap: () => Navigator.of(context).pop(action),
                ),
              ),
            ),
          ),
        ],
      ),
    );
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
          border: Border(top: BorderSide(color: SafeContractsVisual.outline)),
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
              final selectedNow = destination == selected;
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
                          color: selectedNow
                              ? SafeContractsVisual.navy
                              : SafeContractsVisual.muted,
                        ),
                        const SizedBox(height: 3),
                        Text(
                          labelFor(destination),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                color: selectedNow
                                    ? SafeContractsVisual.navy
                                    : SafeContractsVisual.muted,
                                fontWeight: selectedNow
                                    ? FontWeight.w900
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
              ? 'تم السماح بالتنقل إلى ${_label(l10n, destination)}. هذه الشاشة ستظل كما كانت ضمن خارطة الطريق الحالية.'
              : '${_label(l10n, destination)} remains on its existing roadmap surface.',
          textAlign: TextAlign.center,
        ),
      ),
    );
  }
}

String _label(SafeContractsLocalizations l10n, MobileDestination destination) {
  if (destination == MobileDestination.dashboardTwo) {
    return l10n.isArabic ? 'لوحة تحكم اتنين' : 'Dashboard Two';
  }
  return l10n.t(switch (destination) {
    MobileDestination.dashboard => 'Dashboard',
    MobileDestination.dashboardTwo => 'Dashboard Two',
    MobileDestination.customers => 'Customers',
    MobileDestination.suppliers => 'Suppliers',
    MobileDestination.contracts => 'Contracts',
    MobileDestination.payments => 'Payments',
    MobileDestination.finance => 'Finance',
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
    MobileDestination.dashboardTwo => Icons.dashboard_customize_rounded,
    MobileDestination.customers => Icons.people_alt_outlined,
    MobileDestination.suppliers => Icons.local_shipping_outlined,
    MobileDestination.contracts => Icons.folder_copy_outlined,
    MobileDestination.payments => Icons.receipt_long_outlined,
    MobileDestination.finance => Icons.account_balance_wallet_outlined,
    MobileDestination.collections => Icons.payments_outlined,
    MobileDestination.followUps => Icons.timeline_outlined,
    MobileDestination.notifications => Icons.notifications_outlined,
    MobileDestination.export => Icons.file_download_outlined,
    MobileDestination.profile => Icons.person_outline,
  };
}
