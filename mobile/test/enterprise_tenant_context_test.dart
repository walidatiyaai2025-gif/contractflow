import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/core/tenancy/tenant_selection.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';

import 'fake_api_transport.dart';

void main() {
  test('ESC tenant selection adds the reserved server-validated header',
      () async {
    final selection = EnterpriseTenantSelection()..select(17);
    final transport = FakeApiTransport((uri) => _ok(<String, Object?>{}));
    final client = _client(
      transport,
      tenantIdProvider: selection.provideTenantId,
    );

    await client.get('session');

    expect(
      transport.requests.single
          .headers[SafeContractsApiClient.enterpriseTenantHeader],
      '17',
    );
    selection.clear();
    await client.get('session');
    expect(
      transport.requests.last.headers.containsKey(
        SafeContractsApiClient.enterpriseTenantHeader,
      ),
      isFalse,
    );
  });

  test('session parses only a server-returned authorized tenant identity', () {
    final session = SafeContractsSession.fromData(<String, Object?>{
      'authenticated': true,
      'user_id': 42,
      'scope': 'assigned',
      'capabilities': <String, Object?>{'safecontracts_access': true},
      'tenant': <String, Object?>{
        'id': 17,
        'uuid': 'd4498287-9e17-4a45-93ea-882455c52309',
        'slug': 'acme-export',
        'name': 'Acme Export',
        'timezone': 'Asia/Kuwait',
        'default_currency': 'KWD',
        'locale': 'ar',
        'role_code': 'owner',
        'is_owner': true,
      },
    });

    expect(session.tenant?.id, 17);
    expect(session.tenant?.name, 'Acme Export');
    expect(session.tenant?.defaultCurrency, 'KWD');
    expect(session.tenant?.isOwner, isTrue);
  });

  test('generic headers provider cannot spoof the reserved tenant header',
      () async {
    final transport = FakeApiTransport((uri) => _ok(<String, Object?>{}));
    final client = _client(
      transport,
      headersProvider: () async => <String, String>{
        SafeContractsApiClient.enterpriseTenantHeader: '999',
      },
    );

    await expectLater(client.get('session'), throwsA(isA<FormatException>()));
    expect(transport.requests, isEmpty);
  });
}

SafeContractsApiClient _client(
  SafeContractsTransport transport, {
  ApiHeadersProvider? headersProvider,
  TenantIdProvider? tenantIdProvider,
}) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    ),
    transport: transport,
    headersProvider: headersProvider,
    tenantIdProvider: tenantIdProvider,
  );
}

ApiTransportResponse _ok(Object? data) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': <String, Object?>{'api_version': 'v1'},
    }),
  );
}
