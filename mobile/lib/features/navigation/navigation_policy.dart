import '../config/mobile_config.dart';
import '../session/session_controller.dart';

enum MobileDestination {
  dashboard,
  customers,
  suppliers,
  contracts,
  payments,
  finance,
  collections,
  followUps,
  notifications,
  export,
  profile,
}

final class MobileNavigationPolicy {
  const MobileNavigationPolicy({
    required this.destinations,
    required this.canEnterCollection,
    required this.canManageFollowUps,
  });

  static const accessCapability = 'safecontracts_access';
  static const collectionCapability = 'safecontracts_manage_collections';
  static const followUpCapability = 'safecontracts_manage_followups';
  static const exportCapability = 'safecontracts_export_reports';
  static const viewReportsCapability = 'safecontracts_view_reports';
  static const viewSuppliersCapability = 'safecontracts_view_suppliers';
  static const viewPayablesCapability = 'safecontracts_view_payables';
  static const viewReceivablesCapability = 'safecontracts_view_receivables';

  final List<MobileDestination> destinations;
  final bool canEnterCollection;
  final bool canManageFollowUps;

  factory MobileNavigationPolicy.resolve(
    SafeContractsSession session,
    SafeContractsMobileConfig config,
  ) {
    final hasDataScope = session.scope != SafeContractsDataScope.none;
    final hasAccess = session.can(accessCapability) && hasDataScope;
    final destinations = <MobileDestination>[];

    if (hasAccess) {
      destinations.addAll(const <MobileDestination>[
        MobileDestination.dashboard,
        MobileDestination.customers,
      ]);
      if (session.can(viewSuppliersCapability)) {
        destinations.add(MobileDestination.suppliers);
      }
      destinations.addAll(const <MobileDestination>[
        MobileDestination.contracts,
        MobileDestination.payments,
      ]);
      if (session.can(viewPayablesCapability) ||
          session.can(viewReceivablesCapability)) {
        destinations.add(MobileDestination.finance);
      }
      destinations.addAll(const <MobileDestination>[
        MobileDestination.collections,
        MobileDestination.followUps,
      ]);
      if (config.features.pushNotifications) {
        destinations.add(MobileDestination.notifications);
      }
      if (config.features.excelExport &&
          session.can(viewReportsCapability) &&
          session.can(exportCapability)) {
        destinations.add(MobileDestination.export);
      }
    }
    destinations.add(MobileDestination.profile);

    return MobileNavigationPolicy(
      destinations: List<MobileDestination>.unmodifiable(destinations),
      canEnterCollection:
          hasAccess &&
          config.features.collectionEntry &&
          session.can(collectionCapability),
      canManageFollowUps: hasAccess && session.can(followUpCapability),
    );
  }
}
