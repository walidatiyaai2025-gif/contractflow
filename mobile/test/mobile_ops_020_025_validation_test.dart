import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';
import 'package:safecontracts_mobile/features/navigation/navigation_policy.dart';
import 'package:safecontracts_mobile/features/notifications/deep_link.dart';
import 'package:safecontracts_mobile/features/notifications/notifications.dart';
import 'package:safecontracts_mobile/features/profile/profile.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';
import 'package:safecontracts_mobile/features/ui/mobile_layout.dart';
import 'package:safecontracts_mobile/features/ui/mobile_states.dart';

import 'support/safecontracts_test_harness.dart';

void main() {
  group('SC-P9-020 notifications inbox', () {
    test(
      'loads current-user page and persists read state through REST',
      () async {
        final harness = SafeContractsTestHarness((uri) {
          if (uri.path.endsWith('/notifications/91/read')) {
            return SafeContractsTestHarness.ok(<String, Object?>{
              'id': 91,
              'is_read': true,
            });
          }
          return SafeContractsTestHarness.ok(
            <Object?>[_notificationData()],
            meta: <String, Object?>{
              'api_version': 'v1',
              'scope': 'current_user',
              'page': 1,
              'per_page': 25,
              'returned': 1,
              'has_more': false,
            },
          );
        });
        final controller = NotificationsController(
          repository: NotificationsRepository(harness.client),
          pageSize: 25,
          canAccess: true,
        );

        await controller.ensureLoaded();
        expect(controller.state, NotificationsLoadState.ready);
        final notification = controller.currentPage!.notifications.single;
        expect(notification.id, 91);
        expect(notification.paymentId, 21);
        expect(controller.isRead(91), isFalse);
        expect(
          harness.transport.requests.single.uri.path,
          endsWith('/notifications'),
        );

        final link = await controller.openNotification(notification);
        expect(controller.isRead(91), isTrue);
        expect(link?.destination, SafeContractsDeepLinkDestination.payments);
        expect(link?.resourceId, 21);
        expect(harness.transport.requests, hasLength(2));
        expect(
          harness.transport.requests.last.uri.path,
          endsWith('/notifications/91/read'),
        );
        expect(harness.transport.requests.last.method, 'POST');
        controller.dispose();
      },
    );

    test('unauthorized inbox fails before transport access', () async {
      final harness = SafeContractsTestHarness(
        (uri) => SafeContractsTestHarness.ok(<Object?>[]),
      );
      final controller = NotificationsController(
        repository: NotificationsRepository(harness.client),
        pageSize: 25,
        canAccess: false,
      );
      await controller.ensureLoaded();
      expect(controller.state, NotificationsLoadState.error);
      expect(controller.currentPage, isNull);
      expect(harness.transport.requests, isEmpty);
      controller.dispose();
    });
  });

  group('SC-P9-021 / SC-P9-046 push deep links', () {
    test('resolves only known in-app destination plus positive ID', () {
      final link = SafeContractsDeepLink.fromData(<String, Object?>{
        'destination': 'contracts',
        'resource_id': '70',
      });
      expect(link.destination, SafeContractsDeepLinkDestination.contracts);
      expect(link.resourceId, 70);
    });

    test('rejects external, unknown, malformed and extra metadata', () {
      for (final payload in <Object?>[
        <String, Object?>{
          'destination': 'https://evil.example.test',
          'resource_id': 7,
        },
        <String, Object?>{'destination': 'admin', 'resource_id': 7},
        <String, Object?>{'destination': 'payments', 'resource_id': 0},
        <String, Object?>{
          'destination': 'payments',
          'resource_id': 7,
          'url': 'https://evil.example.test',
        },
      ]) {
        expect(SafeContractsDeepLink.tryResolve(payload), isNull);
      }
    });
  });

  group('SC-P9-022 profile/session/device', () {
    test('device model accepts only current-user safe projection', () {
      final snapshot = DevicesSnapshot.fromEnvelope(
        ApiEnvelope(
          data: <Object?>[
            <String, Object?>{
              'id': 7,
              'platform': 'android',
              'is_active': 1,
              'last_seen_at': '2026-08-15 12:00:00',
              'created_at': '2026-08-01 09:00:00',
              'updated_at': '2026-08-15 12:00:00',
              'token': 'ignored-server-field',
              'token_hash': 'ignored-server-field',
            },
          ],
          meta: const <String, Object?>{'scope': 'current_user'},
        ),
      );
      expect(snapshot.devices.single.id, 7);
      expect(snapshot.devices.single.platform, 'android');
      expect(snapshot.devices.single.isActive, isTrue);
    });

    test('device scope and platform fail closed', () {
      expect(
        () => DevicesSnapshot.fromEnvelope(
          const ApiEnvelope(
            data: <Object?>[],
            meta: <String, Object?>{'scope': 'all'},
          ),
        ),
        throwsFormatException,
      );
      expect(
        () => SafeContractsDevice.fromData(<String, Object?>{
          'id': 7,
          'platform': 'server',
          'is_active': true,
        }),
        throwsFormatException,
      );
    });
  });

  group('SC-P9-023 RTL/responsive mobile UX', () {
    test('breakpoints remain deterministic', () {
      expect(safeContractsBreakpoint(320), SafeContractsBreakpoint.narrow);
      expect(safeContractsBreakpoint(600), SafeContractsBreakpoint.medium);
      expect(safeContractsBreakpoint(1024), SafeContractsBreakpoint.wide);
    });

    testWidgets('Arabic is RTL and English is LTR', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: SafeContractsDirectionScope(
            languageCode: 'ar',
            child: Text('مرحبا'),
          ),
        ),
      );
      expect(
        Directionality.of(tester.element(find.text('مرحبا'))),
        TextDirection.rtl,
      );
      await tester.pumpWidget(
        const MaterialApp(
          home: SafeContractsDirectionScope(
            languageCode: 'en',
            child: Text('Hello'),
          ),
        ),
      );
      expect(
        Directionality.of(tester.element(find.text('Hello'))),
        TextDirection.ltr,
      );
    });
  });

  group('SC-P9-024 offline/error/loading states', () {
    test('failure classifier distinguishes auth, validation and network', () {
      expect(
        classifyMobileFailure(
          const SafeContractsApiException(
            code: 'unauthorized',
            message: 'Login required',
            statusCode: 401,
          ),
        ),
        MobileFailureKind.unauthorized,
      );
      expect(
        classifyMobileFailure(
          const SafeContractsApiException(
            code: 'forbidden',
            message: 'Forbidden',
            statusCode: 403,
          ),
        ),
        MobileFailureKind.forbidden,
      );
      expect(
        classifyMobileFailure(
          const SafeContractsApiException(
            code: 'invalid',
            message: 'Invalid',
            statusCode: 422,
          ),
        ),
        MobileFailureKind.validation,
      );
      expect(
        classifyMobileFailure(const SafeContractsTransportException('offline')),
        MobileFailureKind.network,
      );
      expect(
        classifyMobileFailure(const FormatException('bad payload')),
        MobileFailureKind.invalidPayload,
      );
    });

    testWidgets('reusable offline state exposes retry action', (tester) async {
      var retries = 0;
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: SafeContractsStateView(
              kind: MobileStateKind.offline,
              message: 'Offline',
              onRetry: () => retries++,
            ),
          ),
        ),
      );
      await tester.tap(find.text('Retry'));
      expect(retries, 1);
    });
  });

  group('SC-P9-025 deterministic mobile test automation', () {
    test(
      'harness exercises API session config navigation without network',
      () async {
        final harness = SafeContractsTestHarness((uri) {
          if (uri.path.endsWith('/session')) {
            return SafeContractsTestHarness.ok(<String, Object?>{
              'authenticated': true,
              'user_id': 42,
              'scope': 'assigned',
              'capabilities': <String, Object?>{
                'safecontracts_access': true,
                'safecontracts_view_reports': false,
                'safecontracts_export_reports': false,
              },
            });
          }
          if (uri.path.endsWith('/mobile-config')) {
            return SafeContractsTestHarness.ok(<String, Object?>{
              'support_text': 'Support',
              'default_page_size': 25,
              'features': <String, Object?>{
                'excel_export': false,
                'push_notifications': true,
                'collection_entry': false,
              },
            });
          }
          return SafeContractsTestHarness.error(404, 'not_found', 'Not found');
        });
        final sessionController = SessionController(harness.client);
        final configController = MobileConfigController(harness.client);
        await sessionController.bootstrap();
        await configController.load();
        final policy = MobileNavigationPolicy.resolve(
          sessionController.session!,
          configController.config,
        );
        expect(sessionController.state, SessionState.authenticated);
        expect(configController.state, MobileConfigState.ready);
        expect(policy.destinations, contains(MobileDestination.notifications));
        expect(harness.transport.requests, hasLength(2));
        sessionController.dispose();
        configController.dispose();
      },
    );
  });

  group('SC-P9-045 notifications inbox validation', () {
    test(
      'duplicate IDs, invalid scope and mismatched deep links fail closed',
      () {
        expect(
          () => NotificationPage.fromEnvelope(
            ApiEnvelope(
              data: <Object?>[_notificationData(), _notificationData()],
              meta: const <String, Object?>{
                'scope': 'current_user',
                'page': 1,
                'per_page': 25,
                'has_more': false,
              },
            ),
          ),
          throwsFormatException,
        );
        expect(
          () => NotificationPage.fromEnvelope(
            ApiEnvelope(
              data: <Object?>[_notificationData()],
              meta: const <String, Object?>{
                'scope': 'all',
                'page': 1,
                'per_page': 25,
                'has_more': false,
              },
            ),
          ),
          throwsFormatException,
        );
        expect(
          () => SafeContractsNotification.fromData(
            _notificationData(
              deepLink: <String, Object?>{
                'destination': 'payments',
                'resource_id': 999,
              },
            ),
          ),
          throwsFormatException,
        );
      },
    );
  });
}

Map<String, Object?> _notificationData({Object? deepLink}) {
  return <String, Object?>{
    'id': 91,
    'payment_id': 21,
    'template_code': 'payment_due',
    'scheduled_for': '2026-08-15 12:00:00',
    'created_at': '2026-08-15 12:00:01',
    'is_read': false,
    'deep_link':
        deepLink ??
        <String, Object?>{'destination': 'payments', 'resource_id': 21},
    'transport_secret': 'must-not-be-modeled',
  };
}
