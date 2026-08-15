import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/auth/mobile_token_store.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';

import 'fake_api_transport.dart';

void main() {
  testWidgets('first launch shows login then opens authenticated app', (
    tester,
  ) async {
    final environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    );
    final tokenStore = MemoryMobileTokenStore();
    final token = 'scm_${List<String>.filled(43, 'A').join()}';
    late FakeApiTransport transport;
    transport = FakeApiTransport((uri) {
      final request = transport.requests.last;
      if (uri.path.endsWith('/auth/login')) {
        return _ok(
          <String, Object?>{
            'token': token,
            'token_type': 'Bearer',
            'expires_at': '2026-09-15T00:00:00Z',
            'user_id': 42,
          },
          statusCode: 201,
        );
      }
      if (uri.path.endsWith('/session')) {
        if (request.headers['Authorization'] != 'Bearer $token') {
          return _error(
            401,
            'safecontracts_unauthenticated',
            'Authentication is required to access SafeContracts.',
          );
        }
        return _ok(<String, Object?>{
          'authenticated': true,
          'user_id': 42,
          'scope': 'assigned',
          'capabilities': <String, Object?>{
            'safecontracts_access': true,
            'safecontracts_view_assigned': true,
            'safecontracts_view_reports': true,
            'safecontracts_export_reports': true,
          },
        });
      }
      if (uri.path.endsWith('/mobile-config')) {
        return _ok(<String, Object?>{
          'support_text': '',
          'default_page_size': 25,
          'features': <String, Object?>{
            'excel_export': true,
            'push_notifications': false,
            'collection_entry': false,
          },
        });
      }
      if (uri.path.endsWith('/dashboard')) {
        return _ok(<String, Object?>{
          'filters': <String, Object?>{},
          'kpis': <String, Object?>{
            'contract_count': '0',
            'scheduled_total': '0.0000',
            'remaining_total': '0.0000',
            'overdue_exposure': '0.0000',
            'collected_total': '0.0000',
          },
          'customers': <Object?>[],
          'contracts': <Object?>[],
        });
      }
      return _ok(<Object?>[]);
    });
    final client = SafeContractsApiClient(
      environment: environment,
      transport: transport,
      headersProvider: () async {
        final stored = await tokenStore.read();
        return stored == null
            ? <String, String>{}
            : <String, String>{'Authorization': 'Bearer $stored'};
      },
    );

    await tester.pumpWidget(
      SafeContractsApp(
        environment: environment,
        client: client,
        tokenStore: tokenStore,
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Username'), findsOneWidget);
    expect(find.text('Password'), findsOneWidget);
    expect(find.text('Sign in'), findsOneWidget);
    expect(find.text('You do not have access to SafeContracts.'), findsNothing);

    await tester.enterText(find.byType(EditableText).first, 'admin');
    await tester.enterText(find.byType(EditableText).last, 'secret');
    await tester.tap(find.text('Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Dashboard'), findsOneWidget);
    expect(await tokenStore.read(), token);
    expect(
      transport.requests.any(
        (request) =>
            request.uri.path.endsWith('/session') &&
            request.headers['Authorization'] == 'Bearer $token',
      ),
      isTrue,
    );
  });
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
      'data': <String, Object?>{
        'status': statusCode,
        'api_version': 'v1',
      },
    }),
  );
}
