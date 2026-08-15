import '../config/mobile_config.dart';
import '../session/session_controller.dart';

enum MobileDestination {
  dashboard,
  customers,
  contracts,
  payments,
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
        MobileDestination.contracts,
        MobileDestination.payments,
        MobileDestination.collections,
        MobileDestination.followUps,
        MobileDestination.notifications,
      ]);
      if (config.features.excelExport &&
          session.can(viewReportsCapability) &&
          session.can(exportCapability)) {
        destinations.add(MobileDestination.export);
      }
    }
    destinations.add(MobileDestination.profile);

    return MobileNavigationPolicy(
      destinations: List<MobileDestination>.unmodifiable(destinations),
      canEnterCollection: hasAccess &&
          config.features.collectionEntry &&
          session.can(collectionCapability),
      canManageFollowUps: hasAccess && session.can(followUpCapability),
    );
  }
}
