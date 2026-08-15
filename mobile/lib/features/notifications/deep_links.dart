import '../../core/api/api_client.dart';

enum SafeDeepLinkDestination {
  dashboard,
  customers,
  contracts,
  payments,
  collections,
  followUps,
  notifications,
  profile,
}

final class SafeDeepLink {
  const SafeDeepLink({required this.destination, this.resourceId});

  final SafeDeepLinkDestination destination;
  final int? resourceId;
}

final class SafeDeepLinkResolver {
  const SafeDeepLinkResolver._();

  static SafeDeepLink? tryResolve(Object? value) {
    if (value is! Map<Object?, Object?>) {
      return null;
    }
    final data = apiObjectMap(value, 'deep_link');
    if (data.keys.any((key) => key != 'destination' && key != 'id')) {
      return null;
    }
    final rawDestination = data['destination'];
    if (rawDestination is! String) {
      return null;
    }
    final destination = switch (rawDestination.trim().toLowerCase()) {
      'dashboard' => SafeDeepLinkDestination.dashboard,
      'customers' => SafeDeepLinkDestination.customers,
      'contracts' => SafeDeepLinkDestination.contracts,
      'payments' => SafeDeepLinkDestination.payments,
      'collections' => SafeDeepLinkDestination.collections,
      'followups' => SafeDeepLinkDestination.followUps,
      'notifications' => SafeDeepLinkDestination.notifications,
      'profile' => SafeDeepLinkDestination.profile,
      _ => null,
    };
    if (destination == null) {
      return null;
    }

    final rawId = data['id'];
    int? resourceId;
    if (rawId != null) {
      if (rawId is int && rawId > 0) {
        resourceId = rawId;
      } else if (rawId is String) {
        final parsed = int.tryParse(rawId);
        if (parsed == null || parsed < 1) {
          return null;
        }
        resourceId = parsed;
      } else {
        return null;
      }
    }

    final requiresId = switch (destination) {
      SafeDeepLinkDestination.contracts ||
      SafeDeepLinkDestination.payments ||
      SafeDeepLinkDestination.collections ||
      SafeDeepLinkDestination.followUps => true,
      _ => false,
    };
    if (requiresId && resourceId == null) {
      return null;
    }

    return SafeDeepLink(destination: destination, resourceId: resourceId);
  }
}
