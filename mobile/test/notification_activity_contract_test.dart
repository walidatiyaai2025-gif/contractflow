import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/notifications/deep_link.dart';
import 'package:safecontracts_mobile/features/notifications/notifications.dart';

void main() {
  test('contract activity accepts payment id zero and contract deep link', () {
    final notification = SafeContractsNotification.fromData(<String, Object?>{
      'id': 91,
      'payment_id': 0,
      'contract_id': 42,
      'resource_type': 'contract',
      'resource_id': 42,
      'template_code': 'contract_edited',
      'scheduled_for': '2026-08-25',
      'created_at': '2026-08-25 14:00:00',
      'is_read': false,
      'deep_link': <String, Object?>{
        'destination': 'contracts',
        'resource_id': 42,
      },
    });

    expect(notification.paymentId, 0);
    expect(
      notification.deepLink?.destination,
      SafeContractsDeepLinkDestination.contracts,
    );
    expect(notification.deepLink?.resourceId, 42);
  });

  test('manual notification can remain valid without a deep link', () {
    final notification = SafeContractsNotification.fromData(<String, Object?>{
      'id': 92,
      'payment_id': 0,
      'contract_id': 0,
      'resource_type': null,
      'resource_id': null,
      'template_code': 'manual_message',
      'scheduled_for': '2026-08-25',
      'created_at': '2026-08-25 14:01:00',
      'is_read': true,
      'deep_link': null,
    });

    expect(notification.paymentId, 0);
    expect(notification.deepLink, isNull);
  });

  test('payment deep link remains bound to notification payment id', () {
    expect(
      () => SafeContractsNotification.fromData(<String, Object?>{
        'id': 93,
        'payment_id': 7,
        'contract_id': 42,
        'resource_type': 'payment',
        'resource_id': 8,
        'template_code': 'payment_details_changed',
        'scheduled_for': '2026-08-25',
        'created_at': '2026-08-25 14:02:00',
        'is_read': false,
        'deep_link': <String, Object?>{
          'destination': 'payments',
          'resource_id': 8,
        },
      }),
      throwsFormatException,
    );
  });
}
