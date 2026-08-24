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
import '../ui/safecontracts_components.dart';
import '../ui/safecontracts_design.dart';
import '../ui/safecontracts_tokens.dart';
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

  final GlobalKey<ScaffoldState> _scaffoldKey = GlobalKey<ScaffoldState>();
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
      // Silent refresh intentionally keeps the last known-good UI.
    } finally {
      _liveRefreshInFlight = false;
    }
  }

  void _selectDestination(MobileDestination destination) {
    if (!widget.policy.destinations.contains(destination)) return;
    if (_selected != destination) setState(() => _selected = destination);
    unawaited(_refreshActiveSurface());
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (!widget.policy.destinations.contains(_selected)) {
      _selected = widget.policy.destinations.first;
    }

    final bottomItems = _bottomNavigationItems(l10n);
    final quickAdds = availableMobileQuickAdds(widget.session);
    return Scaffold(
      key: _scaffoldKey,
      backgroundColor: SafeContractsVisual.background,
      appBar: _buildAppBar(l10n),
      drawer: SafeContractsDrawer<MobileDestination>(
        workspaceLabel: l10n.isArabic
            ? 'مساحة العمل التنفيذية'
            : 'Executive workspace',
        items: widget.policy.destinations
            .map(
              (destination) => SafeContractsDrawerItem<MobileDestination>(
                value: destination,
                label: _label(l10n, destination),
                icon: _icon(destination),
              ),
            )
            .toList(growable: false),
        selected: _selected,
        onSelected: (destination) {
          _selectDestination(destination);
          Navigator.of(context).pop();
        },
        onLogout: () => unawaited(_requestLogout()),
        logoutLabel: l10n.isArabic ? 'تسجيل الخروج' : 'Sign out',
      ),
      body: SafeContractsBackdrop(
        child: Column(
          children: [
            if (widget.usingConfigDefaults)
              MaterialBanner(
                backgroundColor: SafeContractsVisual.amberSoft,
                leading: const Icon(
                  Icons.info_outline_rounded,
                  color: SafeContractsVisual.amber,
                ),
                content: Text(
                  l10n.t(
                    'Remote mobile configuration is unavailable. Safe defaults are active.',
                  ),
                ),
                actions: const <Widget>[SizedBox.shrink()],
              ),
            Expanded(
              child: AnimatedSwitcher(
                duration: SafeContractsMotion.standard,
                reverseDuration: SafeContractsMotion.fast,
                switchInCurve: Curves.easeOutCubic,
                switchOutCurve: Curves.easeInCubic,
                transitionBuilder: (child, animation) {
                  final slide = Tween<Offset>(
                    begin: const Offset(0.025, 0),
                    end: Offset.zero,
                  ).animate(animation);
                  return FadeTransition(
                    opacity: animation,
                    child: SlideTransition(position: slide, child: child),
                  );
                },
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
      bottomNavigationBar: bottomItems.isEmpty
          ? null
          : SafeContractsBottomNavigation<MobileDestination>(
              items: bottomItems,
              selected: _selected,
              onSelected: _selectDestination,
              moreLabel: l10n.isArabic ? 'المزيد' : 'More',
              onMore: () => _scaffoldKey.currentState?.openDrawer(),
            ),
    );
  }

  PreferredSizeWidget _buildAppBar(SafeContractsLocalizations l10n) {
    return AppBar(
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
              color: Colors.white.withValues(alpha: 0.10),
              borderRadius: BorderRadius.circular(SafeContractsRadii.xs),
            ),
            child: const SafeContractsBrandMark(size: 32, borderRadius: 9),
          ),
          const SizedBox(width: SafeContractsSpacing.xs),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  SafeContractsBrand.name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w900,
                    fontSize: 16,
                  ),
                ),
                Text(
                  _label(l10n, _selected),
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: TextStyle(
                    color: Colors.white.withValues(alpha: 0.68),
                    fontSize: 11,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  List<SafeContractsNavigationItem<MobileDestination>> _bottomNavigationItems(
    SafeContractsLocalizations l10n,
  ) {
    const preferred = <MobileDestination>[
      MobileDestination.dashboard,
      MobileDestination.contracts,
      MobileDestination.payments,
      MobileDestination.customers,
    ];
    return preferred
        .where(widget.policy.destinations.contains)
        .map(
          (destination) => SafeContractsNavigationItem<MobileDestination>(
            value: destination,
            label: _label(l10n, destination),
            icon: _icon(destination),
            selectedIcon: _selectedIcon(destination),
          ),
        )
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
      _ => _PlannedDestination(destination: _selected),
    };
  }

  Future<void> _showQuickAdd(List<MobileQuickAddType> actions) async {
    final selected = await showModalBottomSheet<MobileQuickAddType>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (sheetContext) => _QuickAddSheet(actions: actions),
    );
    if (!mounted || selected == null) return;

    final created = await Navigator.of(context).push<bool>(
      PageRouteBuilder<bool>(
        pageBuilder: (context, animation, secondaryAnimation) {
          return MobileQuickAddScreen(
            client: widget.contractsController.repository.client,
            session: widget.session,
            type: selected,
          );
        },
        transitionDuration: SafeContractsMotion.emphasized,
        reverseTransitionDuration: SafeContractsMotion.standard,
        transitionsBuilder: (context, animation, secondaryAnimation, child) {
          final curved = CurvedAnimation(
            parent: animation,
            curve: Curves.easeOutCubic,
            reverseCurve: Curves.easeInCubic,
          );
          return FadeTransition(
            opacity: curved,
            child: ScaleTransition(
              scale: Tween<double>(begin: 0.985, end: 1).animate(curved),
              child: child,
            ),
          );
        },
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

  Future<void> _requestLogout() async {
    if (_scaffoldKey.currentState?.isDrawerOpen ?? false) {
      Navigator.of(context).pop();
      await Future<void>.delayed(Duration.zero);
    }
    if (!mounted) return;
    final arabic = context.scL10n.isArabic;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: const Icon(
          Icons.logout_rounded,
          color: SafeContractsVisual.roseGold,
        ),
        title: Text(arabic ? 'تسجيل الخروج؟' : 'Sign out?'),
        content: Text(
          arabic
              ? 'سيتم إنهاء جلسة Alkenzy ADV على هذا الجهاز.'
              : 'Your Alkenzy ADV session on this device will be ended.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(arabic ? 'إلغاء' : 'Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: SafeContractsVisual.red,
            ),
            child: Text(arabic ? 'تسجيل الخروج' : 'Sign out'),
          ),
        ],
      ),
    );
    if (confirmed == true && mounted) widget.onClearSession();
  }

  void _openContract(int contractId) {
    final canOpenContractEditor = widget.contractsController.canEditContract ||
        widget.session.can('safecontracts_assign_contracts');
    Navigator.of(context).push(
      MaterialPageRoute<void>(
        builder: (context) => ContractDetailsScreen(
          controller: widget.contractsController,
          contractId: contractId,
          currency: widget.config.currency,
          onEditContract: canOpenContractEditor ? _openContractEdit : null,
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

final class _QuickAddFab extends StatefulWidget {
  const _QuickAddFab({required this.tooltip, required this.onPressed});

  final String tooltip;
  final VoidCallback onPressed;

  @override
  State<_QuickAddFab> createState() => _QuickAddFabState();
}

final class _QuickAddFabState extends State<_QuickAddFab>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 430),
    );
    _scale = CurvedAnimation(parent: _controller, curve: Curves.easeOutBack);
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ScaleTransition(
      scale: _scale,
      child: Container(
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          border: Border.all(color: SafeContractsVisual.roseGoldSoft, width: 3),
          boxShadow: [
            BoxShadow(
              color: SafeContractsVisual.roseGold.withValues(alpha: 0.30),
              blurRadius: 24,
              offset: const Offset(0, 10),
            ),
          ],
        ),
        child: FloatingActionButton(
          tooltip: widget.tooltip,
          onPressed: widget.onPressed,
          child: const Icon(Icons.add_rounded, size: 32),
        ),
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
    return SafeContractsBottomSheetShell(
      title: arabic ? 'إضافة جديدة' : 'Quick add',
      subtitle: arabic
          ? 'تظهر فقط الإجراءات المسموح بها حسب صلاحيات حسابك.'
          : 'Only actions allowed by your account permissions are shown.',
      child: ConstrainedBox(
        constraints: BoxConstraints(
          maxHeight: MediaQuery.sizeOf(context).height * 0.62,
        ),
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: actions.indexed.map((entry) {
              final index = entry.$1;
              final action = entry.$2;
              return TweenAnimationBuilder<double>(
                tween: Tween<double>(begin: 0, end: 1),
                duration: Duration(milliseconds: 220 + (index * 60)),
                curve: Curves.easeOutCubic,
                builder: (context, value, child) => Opacity(
                  opacity: value,
                  child: Transform.translate(
                    offset: Offset(0, 10 * (1 - value)),
                    child: child,
                  ),
                ),
                child: Padding(
                  padding: const EdgeInsets.only(
                    bottom: SafeContractsSpacing.sm,
                  ),
                  child: Material(
                    color: SafeContractsVisual.backgroundRaised,
                    borderRadius: BorderRadius.circular(SafeContractsRadii.md),
                    child: InkWell(
                      borderRadius:
                          BorderRadius.circular(SafeContractsRadii.md),
                      onTap: () => Navigator.of(context).pop(action),
                      child: Padding(
                        padding: const EdgeInsets.all(SafeContractsSpacing.sm),
                        child: Row(
                          children: [
                            Container(
                              width: 46,
                              height: 46,
                              decoration: BoxDecoration(
                                color: SafeContractsVisual.roseGoldSoft,
                                borderRadius: BorderRadius.circular(
                                  SafeContractsRadii.sm,
                                ),
                              ),
                              child: Icon(
                                mobileQuickAddIcon(action),
                                color: SafeContractsVisual.navy,
                              ),
                            ),
                            const SizedBox(width: SafeContractsSpacing.sm),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    mobileQuickAddLabel(context, action),
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium
                                        ?.copyWith(
                                          color: SafeContractsVisual.navyDeep,
                                          fontWeight: FontWeight.w900,
                                        ),
                                  ),
                                  const SizedBox(
                                    height: SafeContractsSpacing.xxs,
                                  ),
                                  Text(
                                    mobileQuickAddDescription(context, action),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                    style: Theme.of(context)
                                        .textTheme
                                        .bodySmall
                                        ?.copyWith(
                                          color: SafeContractsVisual.muted,
                                        ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: SafeContractsSpacing.xs),
                            Icon(
                              Directionality.of(context) == TextDirection.rtl
                                  ? Icons.chevron_left_rounded
                                  : Icons.chevron_right_rounded,
                              color: SafeContractsVisual.roseGoldDark,
                            ),
                          ],
                        ),
                      ),
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
        padding: const EdgeInsets.all(SafeContractsSpacing.xl),
        child: SafeContractsEmptyState(
          icon: _icon(destination),
          title: _label(l10n, destination),
          message: l10n.isArabic
              ? 'التنقل مسموح لهذا الحساب. شاشة الموبايل المخصصة تُنفذ ضمن مهمة النطاق المقابلة.'
              : 'Navigation is authorized. The dedicated screen is implemented in its corresponding scope.',
        ),
      ),
    );
  }
}

String _label(SafeContractsLocalizations l10n, MobileDestination destination) {
  return l10n.t(switch (destination) {
    MobileDestination.dashboard => 'Dashboard',
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
    MobileDestination.dashboard => Icons.home_outlined,
    MobileDestination.customers => Icons.people_alt_outlined,
    MobileDestination.suppliers => Icons.local_shipping_outlined,
    MobileDestination.contracts => Icons.description_outlined,
    MobileDestination.payments => Icons.receipt_long_outlined,
    MobileDestination.finance => Icons.account_balance_wallet_outlined,
    MobileDestination.collections => Icons.payments_outlined,
    MobileDestination.followUps => Icons.timeline_outlined,
    MobileDestination.notifications => Icons.notifications_outlined,
    MobileDestination.export => Icons.file_download_outlined,
    MobileDestination.profile => Icons.person_outline_rounded,
  };
}

IconData _selectedIcon(MobileDestination destination) {
  return switch (destination) {
    MobileDestination.dashboard => Icons.home_rounded,
    MobileDestination.customers => Icons.people_alt_rounded,
    MobileDestination.suppliers => Icons.local_shipping_rounded,
    MobileDestination.contracts => Icons.description_rounded,
    MobileDestination.payments => Icons.receipt_long_rounded,
    MobileDestination.finance => Icons.account_balance_wallet_rounded,
    MobileDestination.collections => Icons.payments_rounded,
    MobileDestination.followUps => Icons.timeline_rounded,
    MobileDestination.notifications => Icons.notifications_rounded,
    MobileDestination.export => Icons.file_download_rounded,
    MobileDestination.profile => Icons.person_rounded,
  };
}
