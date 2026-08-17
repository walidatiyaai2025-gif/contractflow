import 'dart:async';
import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/notifications/push_registration.dart';

import 'fake_api_transport.dart';

void main() {
  test(
    'denied notification permission does not block device registration',
    () async {
      const token = 'safecontracts-fcm-registration-token-1234567890';
      final messaging = _FakePushMessagingGateway(
        permission: MobilePushPermissionState.denied,
        tokens: <String?>[token],
      );
      late FakeApiTransport transport;
      transport = FakeApiTransport((uri) {
        expect(uri.path.endsWith('/devices/register'), isTrue);
        return _ok(<String, Object?>{
          'registered': true,
          'platform': 'android',
        }, statusCode: 201);
      });
      final registration = MobilePushRegistration(
        client: _client(transport),
        messaging: messaging,
        retryDelay: (_) async {},
      );

      await registration.start();

      expect(
        registration.status.value.permission,
        MobilePushPermissionState.denied,
      );
      expect(registration.status.value.tokenAcquired, isTrue);
      expect(registration.status.value.backendRegistered, isTrue);
      expect(registration.status.value.errorCode, isNull);
      expect(transport.requests, hasLength(1));
      final body =
          jsonDecode(transport.requests.single.body!) as Map<String, Object?>;
      expect(body['token'], token);
      expect(body['platform'], 'android');

      await registration.dispose();
      await messaging.dispose();
    },
  );

  test('transient backend registration failure is retried', () async {
    const token = 'safecontracts-fcm-registration-token-abcdef123456';
    final messaging = _FakePushMessagingGateway(
      permission: MobilePushPermissionState.authorized,
      tokens: <String?>[token, token],
    );
    var attempts = 0;
    late FakeApiTransport transport;
    transport = FakeApiTransport((uri) {
      attempts++;
      if (attempts == 1) {
        return _error(
          503,
          'safecontracts_device_register_failed',
          'Temporary registration failure.',
        );
      }
      return _ok(<String, Object?>{
        'registered': true,
        'platform': 'android',
      }, statusCode: 201);
    });
    final registration = MobilePushRegistration(
      client: _client(transport),
      messaging: messaging,
      retryDelay: (_) async {},
    );

    await registration.start();

    expect(attempts, 2);
    expect(messaging.getTokenCalls, 2);
    expect(registration.status.value.backendRegistered, isTrue);
    expect(registration.status.value.errorCode, isNull);

    await registration.dispose();
    await messaging.dispose();
  });

  test(
    'token refresh can recover after initial token is unavailable',
    () async {
      const refreshedToken = 'safecontracts-fcm-refreshed-token-1234567890123';
      final messaging = _FakePushMessagingGateway(
        permission: MobilePushPermissionState.authorized,
        tokens: <String?>[null, null, null],
      );
      late FakeApiTransport transport;
      transport = FakeApiTransport(
        (uri) => _ok(<String, Object?>{
          'registered': true,
          'platform': 'android',
        }, statusCode: 201),
      );
      final registration = MobilePushRegistration(
        client: _client(transport),
        messaging: messaging,
        retryDelay: (_) async {},
      );

      await registration.start();
      expect(registration.status.value.backendRegistered, isFalse);
      expect(registration.status.value.errorCode, 'fcm_token_unavailable');

      messaging.emitRefresh(refreshedToken);
      await Future<void>.delayed(const Duration(milliseconds: 10));

      expect(registration.status.value.tokenAcquired, isTrue);
      expect(registration.status.value.backendRegistered, isTrue);
      expect(transport.requests, hasLength(1));

      await registration.dispose();
      await messaging.dispose();
    },
  );

  test('manual recovery rotates the FCM token before re-registering', () async {
    const oldToken = 'safecontracts-fcm-old-token-123456789012345678';
    const freshToken = 'safecontracts-fcm-fresh-token-9876543210987654';
    final messaging = _FakePushMessagingGateway(
      permission: MobilePushPermissionState.authorized,
      tokens: <String?>[oldToken, freshToken],
    );
    late FakeApiTransport transport;
    transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/devices/register')) {
        return _ok(<String, Object?>{
          'registered': true,
          'platform': 'android',
        }, statusCode: 201);
      }
      if (uri.path.endsWith('/devices/revoke')) {
        return _ok(<String, Object?>{'revoked': true});
      }
      fail('Unexpected push registration request: ${uri.path}');
    });
    final registration = MobilePushRegistration(
      client: _client(transport),
      messaging: messaging,
      retryDelay: (_) async {},
    );

    await registration.start();
    expect(registration.status.value.backendRegistered, isTrue);

    await registration.refreshTokenAndRetry();

    expect(messaging.deleteTokenCalls, 1);
    expect(messaging.getTokenCalls, 2);
    expect(registration.status.value.backendRegistered, isTrue);
    expect(registration.status.value.errorCode, isNull);
    expect(transport.requests, hasLength(3));

    final firstRegistration =
        jsonDecode(transport.requests[0].body!) as Map<String, Object?>;
    final revoke =
        jsonDecode(transport.requests[1].body!) as Map<String, Object?>;
    final secondRegistration =
        jsonDecode(transport.requests[2].body!) as Map<String, Object?>;
    expect(firstRegistration['token'], oldToken);
    expect(revoke['token'], oldToken);
    expect(secondRegistration['token'], freshToken);
    expect(secondRegistration['token'], isNot(oldToken));

    await registration.dispose();
    await messaging.dispose();
  });
}

SafeContractsApiClient _client(FakeApiTransport transport) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    ),
    transport: transport,
    headersProvider: () async => <String, String>{
      'Authorization': 'Bearer scm_${List<String>.filled(43, 'A').join()}',
    },
  );
}

ApiTransportResponse _ok(Object? data, {int statusCode = 200}) {
  return ApiTransportResponse(
    statusCode: statusCode,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': <String, Object?>{'api_version': 'v1'},
    }),
  );
}

ApiTransportResponse _error(int statusCode, String code, String message) {
  return ApiTransportResponse(
    statusCode: statusCode,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'code': code,
      'message': message,
      'data': <String, Object?>{'status': statusCode, 'api_version': 'v1'},
    }),
  );
}

final class _FakePushMessagingGateway implements PushMessagingGateway {
  _FakePushMessagingGateway({
    required this.permission,
    required List<String?> tokens,
  }) : _tokens = List<String?>.from(tokens);

  final MobilePushPermissionState permission;
  final List<String?> _tokens;
  final StreamController<String> _refresh =
      StreamController<String>.broadcast();
  int getTokenCalls = 0;
  int deleteTokenCalls = 0;

  @override
  Future<MobilePushPermissionState> requestPermission() async => permission;

  @override
  Future<String?> getToken() async {
    getTokenCalls++;
    if (_tokens.isEmpty) {
      return null;
    }
    return _tokens.removeAt(0);
  }

  @override
  Stream<String> get onTokenRefresh => _refresh.stream;

  void emitRefresh(String token) => _refresh.add(token);

  @override
  Future<void> deleteToken() async {
    deleteTokenCalls++;
  }

  Future<void> dispose() => _refresh.close();
}
