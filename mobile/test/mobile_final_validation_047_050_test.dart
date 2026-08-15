import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/features/profile/profile.dart';
import 'package:safecontracts_mobile/features/ui/mobile_layout.dart';
import 'package:safecontracts_mobile/features/ui/mobile_states.dart';

import 'support/safecontracts_test_harness.dart';

void main() {
  group('SC-P9-047 profile/session/device validation', () {
    test('accepts only current-user bounded unique safe device projection', () {
      final snapshot = DevicesSnapshot.fromEnvelope(
        ApiEnvelope(
          data: <Object?>[
            _deviceData(1),
            _deviceData(
              2,
              platform: 'ios',
              extra: <String, Object?>{
                'token': 'must-not-be-modeled',
                'token_hash': 'must-not-be-modeled',
              },
            ),
          ],
          meta: const <String, Object?>{'scope': 'current_user'},
        ),
      );

      expect(snapshot.devices, hasLength(2));
      expect(snapshot.devices.map((item) => item.id), <int>[1, 2]);
      expect(snapshot.devices.last.platform, 'ios');
    });

    test('rejects invalid scope, duplicate IDs and oversized device payloads',
        () {
      expect(
        () => DevicesSnapshot.fromEnvelope(
          ApiEnvelope(
            data: <Object?>[_deviceData(1)],
            meta: const <String, Object?>{'scope': 'all'},
          ),
        ),
        throwsFormatException,
      );
      expect(
        () => DevicesSnapshot.fromEnvelope(
          ApiEnvelope(
            data: <Object?>[_deviceData(1), _deviceData(1)],
            meta: const <String, Object?>{'scope': 'current_user'},
          ),
        ),
        throwsFormatException,
      );
      expect(
        () => DevicesSnapshot.fromEnvelope(
          ApiEnvelope(
            data: List<Object?>.generate(
              DevicesSnapshot.maxDevices + 1,
              (index) => _deviceData(index + 1),
            ),
            meta: const <String, Object?>{'scope': 'current_user'},
          ),
        ),
        throwsFormatException,
      );
    });

    test('profile repository stays on protected devices endpoint', () async {
      final harness = SafeContractsTestHarness(
        (uri) => SafeContractsTestHarness.ok(
          <Object?>[_deviceData(7)],
          meta: const <String, Object?>{
            'api_version': 'v1',
            'scope': 'current_user',
          },
        ),
      );
      final controller = ProfileController(ProfileRepository(harness.client));

      await controller.load();

      expect(controller.state, ProfileDeviceLoadState.ready);
      expect(controller.snapshot?.devices.single.id, 7);
      expect(harness.singleRequest.method, 'GET');
      expect(harness.singleRequest.uri.path, endsWith('/devices'));
      controller.dispose();
    });
  });

  group('SC-P9-048 RTL/responsive mobile UX validation', () {
    test('recognizes Arabic locale variants and exact responsive boundaries',
        () {
      expect(safeContractsIsRtlLanguage('ar'), isTrue);
      expect(safeContractsIsRtlLanguage('ar-KW'), isTrue);
      expect(safeContractsIsRtlLanguage('AR_eg'), isTrue);
      expect(safeContractsIsRtlLanguage('en'), isFalse);
      expect(safeContractsBreakpoint(599), SafeContractsBreakpoint.narrow);
      expect(safeContractsBreakpoint(600), SafeContractsBreakpoint.medium);
      expect(safeContractsBreakpoint(1023), SafeContractsBreakpoint.medium);
      expect(safeContractsBreakpoint(1024), SafeContractsBreakpoint.wide);
    });

    testWidgets('direction scope follows locale without changing child content',
        (tester) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: SafeContractsDirectionScope(
            languageCode: 'ar-KW',
            child: Text('SafeContracts'),
          ),
        ),
      );

      expect(
        Directionality.of(tester.element(find.text('SafeContracts'))),
        TextDirection.rtl,
      );
    });

    test('adaptive body rejects invalid non-positive maximum width', () {
      expect(
        () => SafeContractsAdaptiveBody(
          maxWidth: 0,
          child: const SizedBox.shrink(),
        ),
        throwsAssertionError,
      );
    });
  });

  group('SC-P9-049 offline/error/loading state validation', () {
    test('failure classification remains deterministic and fail-closed', () {
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
        classifyMobileFailure(
          const SafeContractsTransportException('offline'),
        ),
        MobileFailureKind.network,
      );
      expect(
        classifyMobileFailure(const FormatException('bad payload')),
        MobileFailureKind.invalidPayload,
      );
      expect(mobileStateAllowsRetry(MobileStateKind.forbidden), isFalse);
      expect(mobileStateAllowsRetry(MobileStateKind.loading), isFalse);
      expect(mobileStateAllowsRetry(MobileStateKind.offline), isTrue);
    });

    testWidgets(
        'forbidden state suppresses retry while offline state permits it',
        (tester) async {
      var retries = 0;
      await tester.pumpWidget(
        MaterialApp(
          home: SafeContractsStateView(
            kind: MobileStateKind.forbidden,
            message: 'Forbidden',
            onRetry: () => retries++,
          ),
        ),
      );
      expect(find.text('Retry'), findsNothing);
      expect(find.byType(Semantics), findsWidgets);

      await tester.pumpWidget(
        MaterialApp(
          home: SafeContractsStateView(
            kind: MobileStateKind.offline,
            message: 'Offline',
            onRetry: () => retries++,
          ),
        ),
      );
      await tester.tap(find.text('Retry'));
      expect(retries, 1);
    });
  });

  group('SC-P9-050 mobile test automation validation', () {
    test('hermetic harness uses local fake transport and records exact request',
        () async {
      final harness = SafeContractsTestHarness(
        (uri) => SafeContractsTestHarness.ok(<String, Object?>{'ok': true}),
      );

      final envelope = await harness.client.get('session');

      expect(harness.environment.name.name, 'local');
      expect(harness.environment.apiBaseUri.host, '127.0.0.1');
      expect(envelope.data, <String, Object?>{'ok': true});
      expect(harness.transport.requests, hasLength(1));
      expect(harness.singleRequest.method, 'GET');
      expect(harness.singleRequest.uri.path, endsWith('/session'));
      expect(harness.singleRequest.body, isNull);
    });
  });
}

Map<String, Object?> _deviceData(
  int id, {
  String platform = 'android',
  Map<String, Object?> extra = const <String, Object?>{},
}) {
  return <String, Object?>{
    'id': id,
    'platform': platform,
    'is_active': true,
    'last_seen_at': '2026-08-15 18:00:00',
    'created_at': '2026-08-01 09:00:00',
    'updated_at': '2026-08-15 18:00:00',
    ...extra,
  };
}
