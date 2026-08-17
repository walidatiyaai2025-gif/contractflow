import 'dart:convert';
import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/records/mobile_quick_add_screen.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';

void main() {
  test('quick add is hidden when the session has no create capability', () {
    final session = _session(<String, bool>{
      'safecontracts_edit_customers': true,
      'safecontracts_edit_contracts': true,
      'safecontracts_edit_payments': true,
    });

    expect(availableMobileQuickAdds(session), isEmpty);
  });

  test('quick add exposes only create actions granted to the session', () {
    final session = _session(<String, bool>{
      'safecontracts_create_customers': true,
      'safecontracts_create_contracts': false,
      'safecontracts_create_payments': true,
      'safecontracts_edit_contracts': true,
    });

    expect(availableMobileQuickAdds(session), <MobileQuickAddType>[
      MobileQuickAddType.customer,
      MobileQuickAddType.payment,
    ]);
  });

  test('all create capabilities expose the complete quick add set', () {
    final session = _session(<String, bool>{
      'safecontracts_create_customers': true,
      'safecontracts_create_contracts': true,
      'safecontracts_create_payments': true,
    });

    expect(availableMobileQuickAdds(session), <MobileQuickAddType>[
      MobileQuickAddType.customer,
      MobileQuickAddType.contract,
      MobileQuickAddType.payment,
    ]);
  });

  test('profile no longer owns CRUD entry points or raw capabilities', () {
    final source = <String>[
      File('lib/features/profile/profile_screen.dart').readAsStringSync(),
      File('lib/features/profile/modern_profile_content.dart')
          .readAsStringSync(),
      File('lib/features/profile/profile_identity_sections.dart')
          .readAsStringSync(),
    ].join('\n');

    expect(source, isNot(contains('MobileRecordEditorScreen')));
    expect(source, isNot(contains('mobile_record_editor_screen.dart')));
    expect(source, isNot(contains('Granted capabilities')));
    expect(source, isNot(contains('_grantedCapabilities')));
    expect(source, isNot(contains('Data management')));
    expect(source, contains('ProfileSectionTitle'));
    expect(source, contains('My profile'));
  });

  test(
    'app shell owns the animated permission-aware quick add entry point',
    () {
      final source = File('lib/features/navigation/app_shell.dart')
          .readAsStringSync();

      expect(source, contains('availableMobileQuickAdds(widget.session)'));
      expect(source, contains('floatingActionButton:'));
      expect(source, contains('_QuickAddFab'));
      expect(source, contains('_QuickAddSheet'));
      expect(source, contains('AnimatedSwitcher'));
      expect(source, contains('AnimatedScale'));
    },
  );

  testWidgets('contract quick add can reach an authorized customer on page 2', (
    tester,
  ) async {
    final transport = _PagedReferenceTransport();
    final client = SafeContractsApiClient(
      environment: AppEnvironment.fromValues(
        name: 'local',
        apiBaseUrl: 'http://example.test/wp-json/safecontracts/v1/',
      ),
      transport: transport,
    );

    await tester.pumpWidget(
      MaterialApp(
        home: MobileQuickAddScreen(
          client: client,
          session: _session(<String, bool>{
            'safecontracts_create_contracts': true,
          }),
          type: MobileQuickAddType.contract,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('First page customer'), findsOneWidget);
    expect(find.text('Second page customer'), findsNothing);

    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();

    expect(find.text('First page customer'), findsNothing);
    expect(find.text('Second page customer'), findsOneWidget);
    expect(transport.customerPages, <int>[1, 2]);
  });
}

SafeContractsSession _session(Map<String, bool> capabilities) {
  return SafeContractsSession(
    userId: 7,
    scope: SafeContractsDataScope.assigned,
    capabilities: Map<String, bool>.unmodifiable(capabilities),
  );
}

final class _PagedReferenceTransport implements SafeContractsTransport {
  final List<int> customerPages = <int>[];

  @override
  Future<ApiTransportResponse> send({
    required Uri uri,
    required String method,
    Map<String, String> headers = const <String, String>{},
    String? body,
  }) async {
    if (method != 'GET' || !uri.path.endsWith('/customers')) {
      throw StateError('Unexpected request: $method $uri');
    }

    final page = int.parse(uri.queryParameters['page']!);
    customerPages.add(page);
    final secondPage = page == 2;
    return ApiTransportResponse(
      statusCode: 200,
      headers: const <String, String>{
        'content-type': 'application/json; charset=utf-8',
      },
      body: jsonEncode(<String, Object?>{
        'data': <Object?>[
          <String, Object?>{
            'id': secondPage ? 202 : 101,
            'name': secondPage ? 'Second page customer' : 'First page customer',
            'internal_code': secondPage ? 'P2' : 'P1',
            'contact_name': null,
            'email': null,
            'phone': null,
            'is_active': true,
          },
        ],
        'meta': <String, Object?>{
          'api_version': SafeContractsApiClient.apiVersion,
          'page': page,
          'per_page': 100,
          'sort': 'name',
          'order': 'asc',
          'has_more': !secondPage,
          'bounded_window': 500,
          'scope': 'assigned',
        },
      }),
    );
  }
}
