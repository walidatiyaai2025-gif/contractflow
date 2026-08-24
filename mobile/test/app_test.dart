import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_design.dart';

import 'fake_api_transport.dart';

void main() {
  testWidgets('renders authenticated premium Alkenzy ADV dashboard shell', (
    tester,
  ) async {
    final environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    );
    final transport = FakeApiTransport(_handler);
    final client = SafeContractsApiClient(
      environment: environment,
      transport: transport,
    );

    await tester.pumpWidget(
      SafeContractsApp(environment: environment, client: client),
    );
    await tester.pumpAndSettle();

    expect(find.byType(AppBar), findsOneWidget);
    expect(find.byType(SafeContractsPremiumHeader), findsOneWidget);
    expect(find.byType(SafeContractsMetricCard), findsNWidgets(4));
    expect(find.text('Financial performance overview'), findsOneWidget);
    expect(find.text('Total contracts'), findsOneWidget);
    expect(find.text('Remaining'), findsWidgets);
    expect(find.textContaining('125'), findsWidgets);
    expect(find.textContaining('125.00'), findsNothing);

    final appBar = tester.widget<AppBar>(find.byType(AppBar));
    expect(appBar.backgroundColor, SafeContractsVisual.navy);
    expect(appBar.foregroundColor, Colors.white);

    await tester.tap(find.text('Payment filters'));
    await tester.pumpAndSettle();
    expect(find.text('All customers'), findsOneWidget);
    expect(find.text('All contracts'), findsOneWidget);
  });
}

ApiTransportResponse _handler(Uri uri) {
  if (uri.path.endsWith('/session')) {
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
        'contract_count': '2',
        'scheduled_total': '500.0000',
        'remaining_total': '125.0000',
        'overdue_exposure': '75.0000',
        'collected_total': '375.0000',
      },
      'customers': <Object?>[
        <String, Object?>{'id': 7, 'name': 'Alpha Customer'},
      ],
      'contracts': <Object?>[
        <String, Object?>{
          'id': 70,
          'contract_number': 'SC-70',
          'customer_id': 7,
        },
      ],
    });
  }
  if (uri.path.endsWith('/contracts') ||
      uri.path.endsWith('/payments') ||
      uri.path.endsWith('/collections') ||
      uri.path.endsWith('/followups')) {
    return _ok(<Object?>[]);
  }
  return ApiTransportResponse(
    statusCode: 404,
    headers: const <String, String>{},
    body: jsonEncode(<String, Object?>{
      'code': 'not_found',
      'message': 'Not found',
    }),
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
