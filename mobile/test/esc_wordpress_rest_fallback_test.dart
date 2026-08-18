import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';

import 'fake_api_transport.dart';

void main() {
  group('ESC WordPress REST query fallback', () {
    test('derives query route on the exact same origin and namespace', () {
      final environment = _environment();

      final fallback = environment.wordpressQueryEndpoint(
        'session',
        query: const <String, String>{'page': '2'},
      );

      expect(fallback, isNotNull);
      expect(fallback!.scheme, 'https');
      expect(fallback.host, 'esc.example.test');
      expect(fallback.port, 443);
      expect(fallback.path, '/');
      expect(
        fallback.queryParameters['rest_route'],
        '/safecontracts/v1/session',
      );
      expect(fallback.queryParameters['page'], '2');
    });

    test('supports WordPress installed under a same-origin subdirectory', () {
      final environment = AppEnvironment.fromValues(
        name: 'production',
        apiBaseUrl:
            'https://esc.example.test/contracts/wp-json/safecontracts/v1/',
      );

      final fallback = environment.wordpressQueryEndpoint('health');

      expect(fallback, isNotNull);
      expect(fallback!.path, '/contracts/');
      expect(
        fallback.queryParameters['rest_route'],
        '/safecontracts/v1/health',
      );
    });

    test('does not invent fallback for a non-WordPress API base', () {
      final environment = AppEnvironment.fromValues(
        name: 'production',
        apiBaseUrl: 'https://esc.example.test/api/safecontracts/v1/',
      );

      expect(environment.wordpressQueryEndpoint('session'), isNull);
    });

    test('keeps relative-path traversal protection on fallback', () {
      expect(
        () => _environment().wordpressQueryEndpoint('../session'),
        throwsFormatException,
      );
    });

    test('valid pretty JSON response does not trigger fallback', () async {
      final transport = FakeApiTransport((uri) => _ok(<String, Object?>{
            'ok': true,
          }));
      final client = _client(transport);

      final envelope = await client.get('health');

      expect(apiObjectMap(envelope.data, 'data')['ok'], isTrue);
      expect(transport.requests, hasLength(1));
      expect(transport.requests.single.uri.path,
          '/wp-json/safecontracts/v1/health');
      expect(
          transport.requests.single.uri.queryParameters['rest_route'], isNull);
    });

    test('200 HTML pretty route retries once through WordPress rest_route',
        () async {
      final transport = FakeApiTransport((uri) {
        if (uri.queryParameters['rest_route'] == '/safecontracts/v1/session') {
          return _ok(<String, Object?>{
            'authenticated': true,
            'user_id': 42,
            'scope': 'all',
            'capabilities': <String, Object?>{
              'safecontracts_access': true,
            },
          });
        }
        return _html();
      });
      final client = _client(transport);

      final envelope = await client.get('session');

      expect(
        apiObjectMap(envelope.data, 'session')['authenticated'],
        isTrue,
      );
      expect(transport.requests, hasLength(2));
      expect(
        transport.requests.last.uri.queryParameters['rest_route'],
        '/safecontracts/v1/session',
      );
      expect(transport.requests.last.uri.host, 'esc.example.test');
    });

    test('fallback 401 JSON is handled as normal unauthenticated response',
        () async {
      final transport = FakeApiTransport((uri) {
        if (uri.queryParameters['rest_route'] == '/safecontracts/v1/session') {
          return _error(
            401,
            'safecontracts_unauthenticated',
            'Authentication is required.',
          );
        }
        return _html();
      });
      final client = _client(transport);

      await expectLater(
        client.get('session'),
        throwsA(
          isA<SafeContractsApiException>()
              .having((error) => error.statusCode, 'status', 401)
              .having(
                (error) => error.code,
                'code',
                'safecontracts_unauthenticated',
              ),
        ),
      );
      expect(transport.requests, hasLength(2));
    });

    test('primary JSON 401 never retries through fallback', () async {
      final transport = FakeApiTransport(
        (uri) => _error(
          401,
          'safecontracts_unauthenticated',
          'Authentication is required.',
        ),
      );
      final client = _client(transport);

      await expectLater(
        client.get('session'),
        throwsA(isA<SafeContractsApiException>()),
      );

      expect(transport.requests, hasLength(1));
    });

    test('malformed non-HTML success is not retried and never exposes body',
        () async {
      const secretBody = 'not-json-secret-upstream-body';
      final transport = FakeApiTransport(
        (uri) => const ApiTransportResponse(
          statusCode: 200,
          headers: <String, String>{'content-type': 'text/plain'},
          body: secretBody,
        ),
      );
      final client = _client(transport);

      await expectLater(
        client.get('session'),
        throwsA(
          isA<SafeContractsApiException>()
              .having(
                (error) => error.code,
                'code',
                'safecontracts_invalid_api_response',
              )
              .having(
                (error) => error.message.contains(secretBody),
                'sanitized message',
                isFalse,
              ),
        ),
      );
      expect(transport.requests, hasLength(1));
    });

    test('POST fallback preserves body auth and ESC tenant header', () async {
      final transport = FakeApiTransport((uri) {
        if (uri.queryParameters['rest_route'] ==
            '/safecontracts/v1/auth/login') {
          return _created(<String, Object?>{'token': 'opaque-token'});
        }
        return _html();
      });
      final client = SafeContractsApiClient(
        environment: _environment(),
        transport: transport,
        headersProvider: () async => const <String, String>{
          'Authorization': 'Bearer existing-token',
        },
        tenantIdProvider: () async => 7,
      );

      final envelope = await client.post(
        'auth/login',
        body: const <String, Object?>{
          'username': 'esc-user',
          'password': 'test-password',
        },
      );

      expect(apiObjectMap(envelope.data, 'login')['token'], 'opaque-token');
      expect(transport.requests, hasLength(2));
      final first = transport.requests.first;
      final fallback = transport.requests.last;
      expect(fallback.method, first.method);
      expect(fallback.body, first.body);
      expect(jsonDecode(fallback.body!)['username'], 'esc-user');
      expect(fallback.headers['Authorization'], 'Bearer existing-token');
      expect(
          fallback.headers[SafeContractsApiClient.enterpriseTenantHeader], '7');
      expect(
          fallback.headers['Content-Type'], 'application/json; charset=utf-8');
      expect(fallback.uri.host, first.uri.host);
      expect(fallback.uri.scheme, first.uri.scheme);
      expect(
        fallback.uri.queryParameters['rest_route'],
        '/safecontracts/v1/auth/login',
      );
    });
  });
}

AppEnvironment _environment() => AppEnvironment.fromValues(
      name: 'production',
      apiBaseUrl: 'https://esc.example.test/wp-json/safecontracts/v1/',
    );

SafeContractsApiClient _client(SafeContractsTransport transport) {
  return SafeContractsApiClient(
    environment: _environment(),
    transport: transport,
  );
}

ApiTransportResponse _html() => const ApiTransportResponse(
      statusCode: 200,
      headers: <String, String>{'content-type': 'text/html; charset=UTF-8'},
      body: '<!doctype html><html><body>front page</body></html>',
    );

ApiTransportResponse _ok(Object? data) => ApiTransportResponse(
      statusCode: 200,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'data': data,
        'meta': <String, Object?>{'api_version': 'v1'},
      }),
    );

ApiTransportResponse _created(Object? data) => ApiTransportResponse(
      statusCode: 201,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'data': data,
        'meta': <String, Object?>{'api_version': 'v1'},
      }),
    );

ApiTransportResponse _error(int status, String code, String message) =>
    ApiTransportResponse(
      statusCode: status,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'code': code,
        'message': message,
        'data': <String, Object?>{
          'status': status,
          'api_version': 'v1',
        },
      }),
    );
