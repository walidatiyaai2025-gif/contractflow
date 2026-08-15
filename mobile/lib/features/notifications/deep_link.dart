import '../../core/api/api_client.dart';

enum SafeContractsDeepLinkDestination {
  payments,
  contracts,
  customers,
  followUps,
}

final class SafeContractsDeepLink {
  const SafeContractsDeepLink({
    required this.destination,
    required this.resourceId,
  });

  final SafeContractsDeepLinkDestination destination;
  final int resourceId;

  factory SafeContractsDeepLink.fromData(Object? value) {
    final data = apiObjectMap(value, 'deep_link');
    const allowedKeys = <String>{'destination', 'resource_id'};
    if (data.keys.any((key) => !allowedKeys.contains(key))) {
      throw const FormatException('deep_link contains unsupported metadata.');
    }

    final rawDestination = data['destination'];
    if (rawDestination is! String || rawDestination.trim().isEmpty) {
      throw const FormatException('deep_link.destination is invalid.');
    }
    final destination = switch (rawDestination.trim().toLowerCase()) {
      'payments' => SafeContractsDeepLinkDestination.payments,
      'contracts' => SafeContractsDeepLinkDestination.contracts,
      'customers' => SafeContractsDeepLinkDestination.customers,
      'followups' => SafeContractsDeepLinkDestination.followUps,
      _ => throw const FormatException(
          'deep_link.destination is not supported.',
        ),
    };

    final resourceId = _positiveInt(data['resource_id']);
    return SafeContractsDeepLink(
      destination: destination,
      resourceId: resourceId,
    );
  }

  static SafeContractsDeepLink? tryResolve(Object? value) {
    if (value == null) {
      return null;
    }
    try {
      return SafeContractsDeepLink.fromData(value);
    } on FormatException {
      return null;
    }
  }
}

int _positiveInt(Object? value) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) {
    throw const FormatException('deep_link.resource_id must be positive.');
  }
  return parsed;
}
