import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';

import 'fake_api_transport.dart';

const _captureKey = Key('referenceCaptureBoundary');

void main() {
  testWidgets('captures REF01 welcome and login implementation', (tester) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.binding.setSurfaceSize(const Size(390, 844));

    final environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    );
    final client = SafeContractsApiClient(
      environment: environment,
      transport: FakeApiTransport(_unauthenticatedHandler),
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
    await tester.pumpAndSettle();
    await _capture(tester, 'REF01_welcome_ar_390');

    final signIn = find.byKey(const Key('companyWelcomeSignIn'));
    expect(signIn, findsOneWidget);
    await tester.ensureVisible(signIn);
    await tester.tap(signIn);
    await tester.pumpAndSettle();
    await _capture(tester, 'REF01_login_ar_390');
  });

  testWidgets('captures REF02 authenticated dashboard implementation', (
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
      transport: FakeApiTransport(_authenticatedHandler),
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
    await tester.pumpAndSettle();
    await _capture(tester, 'REF02_dashboard_ar_390');
  });
}

Future<void> _capture(WidgetTester tester, String name) async {
  final boundary = tester.renderObject<RenderRepaintBoundary>(
    find.byKey(_captureKey),
  );
  final image = await boundary.toImage(pixelRatio: 1);
  final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
  if (bytes == null) throw StateError('Unable to encode $name.');
  final directory = Directory('build/reference-captures');
  await directory.create(recursive: true);
  await File('${directory.path}/$name.png').writeAsBytes(
    bytes.buffer.asUint8List(),
    flush: true,
  );
}

ApiTransportResponse _unauthenticatedHandler(Uri uri) {
  if (uri.path.endsWith('/session')) {
    return _error(401, 'safecontracts_authentication_required');
  }
  if (uri.path.endsWith('/mobile-landing')) {
    return _ok(<String, Object?>{
      'schema_version': 1,
      'brand_name': 'Alkenzy ADV',
      'agency_name': <String, String>{
        'en': 'Alkenzy Advertising Agency',
        'ar': 'الكنزي للإعلان',
      },
      'headline': <String, String>{
        'en': 'Advertising built on experience',
        'ar': 'إدارة العقود باحتراف وتميز',
      },
      'highlight': <String, String>{
        'en': 'Planning, execution, and measurable impact',
        'ar': 'تخطيط وتنفيذ وتأثير قابل للقياس',
      },
      'summary': <String, String>{
        'en': 'Manage contracts, payments, collections and reports in one place.',
        'ar': 'منصة متكاملة لإدارة العقود والمدفوعات والتحصيلات والتقارير في مكان واحد.',
      },
      'experience_years': 10,
      'services': <Object?>[
        <String, Object?>{
          'key': 'contracts',
          'title': <String, String>{'en': 'Contracts', 'ar': 'إدارة العقود'},
          'subtitle': <String, String>{
            'en': 'Create and follow contracts.',
            'ar': 'إنشاء العقود ومتابعتها.',
          },
        },
      ],
      'contact': <String, Object?>{
        'phones': <String>['+966501234567'],
        'office_address': <String, String>{
          'en': 'Business office',
          'ar': 'مكتب الأعمال',
        },
      },
      'sign_in_label': <String, String>{
        'en': 'Sign in',
        'ar': 'تسجيل الدخول',
      },
      'learn_more_label': <String, String>{
        'en': 'Learn more',
        'ar': 'اعرف المزيد',
      },
    });
  }
  return _error(404, 'not_found');
}

ApiTransportResponse _authenticatedHandler(Uri uri) {
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
      'currency': <String, Object?>{
        'code': 'SAR',
        'symbol': 'ر.س',
        'decimal_places': 2,
      },
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
  if (uri.path.endsWith('/contracts') ||
      uri.path.endsWith('/payments') ||
      uri.path.endsWith('/collections') ||
      uri.path.endsWith('/followups')) {
    return _ok(<Object?>[]);
  }
  return _error(404, 'not_found');
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
