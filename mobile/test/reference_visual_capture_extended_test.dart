import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:google_fonts/google_fonts.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';

import 'fake_api_transport.dart';

const _captureKey = Key('referenceExtendedCaptureBoundary');

void main() {
  setUpAll(() => GoogleFonts.config.allowRuntimeFetching = false);
  tearDownAll(() => GoogleFonts.config.allowRuntimeFetching = true);

  testWidgets('captures REF03 through REF06 authenticated surfaces', (
    tester,
  ) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.binding.setSurfaceSize(const Size(390, 844));

    final environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    );
    final client = SafeContractsApiClient(
      environment: environment,
      transport: FakeApiTransport(_handler),
    );

    await tester.pumpWidget(
      RepaintBoundary(
        key: _captureKey,
        child: SafeContractsApp(
          environment: environment,
          client: client,
          languageCode: 'ar',
        ),
      ),
    );
    await _pumpBounded(tester, cycles: 24);
    expect(find.byType(AppBar), findsOneWidget);

    await _captureDestination(tester, 2, 'REF03_customers_ar_390');
    await _captureDestination(tester, 3, 'REF03_suppliers_ar_390');
    await _captureDestination(tester, 4, 'REF04_contracts_ar_390');
    await _captureDestination(tester, 5, 'REF05_payments_ar_390');
    await _captureDestination(tester, 6, 'REF05_finance_ar_390');
    await _captureDestination(tester, 8, 'REF05_followups_ar_390');
    await _captureDestination(tester, 9, 'REF06_notifications_ar_390');
    await _captureDestination(tester, 10, 'REF06_export_ar_390');
    await _captureDestination(tester, 11, 'REF06_profile_ar_390');

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump();
    expect(tester.takeException(), isNull);
  });
}

Future<void> _captureDestination(
  WidgetTester tester,
  int index,
  String name,
) async {
  final scaffold = find.byWidgetPredicate(
    (widget) => widget is Scaffold && widget.drawer != null,
  );
  expect(scaffold, findsOneWidget);
  tester.state<ScaffoldState>(scaffold).openDrawer();
  await _pumpBounded(tester, cycles: 8);
  final destinations = find.byType(NavigationDrawerDestination);
  expect(destinations, findsAtLeastNWidgets(index + 1));
  await tester.tap(destinations.at(index));
  await _pumpBounded(tester, cycles: 18);
  await _capture(tester, name);
}

Future<void> _pumpBounded(
  WidgetTester tester, {
  int cycles = 12,
}) async {
  await tester.pump();
  for (var index = 0; index < cycles; index++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<void> _capture(WidgetTester tester, String name) async {
  final boundary = tester.renderObject<RenderRepaintBoundary>(
    find.byKey(_captureKey),
  );
  final encoded = await tester.runAsync<List<int>>(() async {
    final image = await boundary.toImage(pixelRatio: 0.75);
    try {
      final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
      if (bytes == null) throw StateError('Unable to encode $name.');
      return bytes.buffer.asUint8List();
    } finally {
      image.dispose();
    }
  });
  if (encoded == null) throw StateError('Unable to capture $name.');
  final directory = Directory('build/reference-captures')
    ..createSync(recursive: true);
  File('${directory.path}/$name.png').writeAsBytesSync(encoded, flush: true);
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
        'safecontracts_view_suppliers': true,
        'safecontracts_view_payables': true,
        'safecontracts_view_receivables': true,
        'safecontracts_manage_collections': true,
        'safecontracts_manage_followups': true,
        'safecontracts_manage_payments': true,
        'safecontracts_create_customers': true,
        'safecontracts_create_contracts': true,
        'safecontracts_create_payments': true,
      },
    });
  }
  if (uri.path.endsWith('/mobile-config')) {
    return _ok(<String, Object?>{
      'support_text': 'support@example.com',
      'default_page_size': 25,
      'currency': <String, Object?>{
        'code': 'KWD',
        'symbol': 'د.ك',
        'decimal_places': 3,
      },
      'features': <String, Object?>{
        'excel_export': true,
        'push_notifications': true,
        'collection_entry': true,
      },
    });
  }
  if (uri.path.endsWith('/dashboard')) {
    return _ok(<String, Object?>{
      'filters': <String, Object?>{},
      'kpis': <String, Object?>{
        'contract_count': '18',
        'scheduled_total': '1250000.0000',
        'remaining_total': '430000.0000',
        'overdue_exposure': '120000.0000',
        'collected_total': '820000.0000',
      },
      'customers': <Object?>[
        <String, Object?>{'id': 7, 'name': 'مؤسسة الرواد للتجارة'},
      ],
      'contracts': <Object?>[
        <String, Object?>{
          'id': 70,
          'contract_number': 'ADV-2026-070',
          'customer_id': 7,
        },
      ],
    });
  }
  if (uri.path.endsWith('/finance/overview')) {
    return _ok(<String, Object?>{
      'directions': <Object?>['receivable', 'payable'],
      'summary': <Object?>[],
      'aging': <Object?>[],
      'cash_flow': <Object?>[],
      'action_center': <Object?>[],
      'work_queue_preview': <Object?>[],
    });
  }
  if (uri.path.endsWith('/finance/obligations')) return _ok(<Object?>[]);
  if (uri.path.endsWith('/customers')) {
    return _page(<Object?>[
      <String, Object?>{
        'id': 7,
        'name': 'مؤسسة الرواد للتجارة',
        'internal_code': 'C-007',
        'contact_name': 'أحمد علي',
        'email': 'customer@example.com',
        'phone': '+96550000007',
        'is_active': true,
      },
    ], sort: 'name', order: 'asc');
  }
  if (uri.path.endsWith('/suppliers')) {
    return _page(<Object?>[
      <String, Object?>{
        'id': 8,
        'name': 'شركة الخليج للتوريدات',
        'internal_code': 'S-008',
        'contact_name': 'سالم حسن',
        'email': 'supplier@example.com',
        'phone': '+96550000008',
        'is_active': true,
      },
    ], sort: 'name', order: 'asc');
  }
  if (uri.path.endsWith('/contracts')) {
    return _page(<Object?>[], sort: 'id', order: 'desc');
  }
  if (uri.path.endsWith('/payments')) {
    return _page(<Object?>[], sort: 'due_date', order: 'asc');
  }
  if (uri.path.endsWith('/collections')) {
    return _page(<Object?>[], sort: 'id', order: 'desc');
  }
  if (uri.path.endsWith('/followups')) {
    return _page(<Object?>[], sort: 'id', order: 'desc');
  }
  if (uri.path.endsWith('/notifications')) {
    return _page(<Object?>[], sort: 'id', order: 'desc');
  }
  if (uri.path.endsWith('/devices')) {
    return _okWithMeta(<Object?>[], <String, Object?>{
      'api_version': 'v1',
      'scope': 'current_user',
    });
  }
  return _error(404, 'not_found');
}

ApiTransportResponse _page(
  Object? data, {
  required String sort,
  required String order,
}) {
  return _okWithMeta(data, <String, Object?>{
    'api_version': 'v1',
    'scope': 'assigned',
    'page': 1,
    'per_page': 25,
    'sort': sort,
    'order': order,
    'has_more': false,
    'bounded_window': 500,
  });
}

ApiTransportResponse _ok(Object? data) => _okWithMeta(
      data,
      const <String, Object?>{'api_version': 'v1'},
    );

ApiTransportResponse _okWithMeta(
  Object? data,
  Map<String, Object?> meta,
) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{'data': data, 'meta': meta}),
  );
}

ApiTransportResponse _error(int statusCode, String code) {
  return ApiTransportResponse(
    statusCode: statusCode,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'code': code,
      'message': code,
      'data': <String, Object?>{
        'status': statusCode,
        'api_version': 'v1',
      },
    }),
  );
}
