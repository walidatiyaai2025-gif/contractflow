import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/records/mobile_quick_add_screen.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';

void main() {
  testWidgets('payment quick add reaches authorized contracts on page 2', (
    tester,
  ) async {
    final transport = _PagedContractsTransport();
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
          session: SafeContractsSession(
            userId: 7,
            scope: SafeContractsDataScope.assigned,
            capabilities: const <String, bool>{
              'safecontracts_create_payments': true,
            },
          ),
          type: MobileQuickAddType.payment,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Page 1'), findsOneWidget);
    expect(find.text('Next'), findsOneWidget);

    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();

    expect(find.text('Page 2'), findsOneWidget);
    expect(transport.pages, <int>[1, 2]);

    await tester.tap(find.byType(DropdownButtonFormField<int>));
    await tester.pumpAndSettle();

    expect(find.text('PAY-P2 — Customer Two'), findsWidgets);
  });
}

final class _PagedContractsTransport implements SafeContractsTransport {
  final List<int> pages = <int>[];

  @override
  Future<ApiTransportResponse> send({
    required Uri uri,
    required String method,
    Map<String, String> headers = const <String, String>{},
    String? body,
  }) async {
    if (method != 'GET' || !uri.path.endsWith('/contracts')) {
      throw StateError('Unexpected request: $method $uri');
    }
    final page = int.parse(uri.queryParameters['page'] ?? '1');
    pages.add(page);
    final second = page == 2;
    return ApiTransportResponse(
      statusCode: 200,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'data': <Object?>[
          <String, Object?>{
            'id': second ? 202 : 101,
            'contract_number': second ? 'PAY-P2' : 'PAY-P1',
            'customer_id': second ? 22 : 11,
            'customer_name': second ? 'Customer Two' : 'Customer One',
            'counterparty_type': 'customer',
            'counterparty_id': second ? 22 : 11,
            'counterparty_name': second ? 'Customer Two' : 'Customer One',
            'financial_direction': 'receivable',
            'currency_code': 'KWD',
            'accountant_user_id': null,
            'status': 'active',
            'start_date': null,
            'end_date': null,
            'base_value': null,
            'is_archived': false,
          },
        ],
        'meta': <String, Object?>{
          'api_version': SafeContractsApiClient.apiVersion,
          'scope': 'assigned',
          'page': page,
          'per_page': 100,
          'total': 101,
          'total_pages': 2,
          'sort': 'id',
          'order': 'desc',
          'returned': 1,
          'available_in_bounded_read': 101,
          'bounded_window': 100,
          'has_more': !second,
        },
      }),
    );
  }
}
