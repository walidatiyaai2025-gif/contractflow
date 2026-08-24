import 'dart:convert';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/auth/mobile_token_store.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';

import 'fake_api_transport.dart';

void main() {
  testWidgets('WORKER1-VISUAL captures reference comparison frames', (
    tester,
  ) async {
    await tester.binding.setSurfaceSize(const Size(390, 844));
    addTearDown(() => tester.binding.setSurfaceSize(null));

    final environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    );
    final tokenStore = MemoryMobileTokenStore();
    final token = 'scm_${List<String>.filled(43, 'A').join()}';
    late FakeApiTransport transport;
    transport = FakeApiTransport((uri) {
      final request = transport.requests.last;
      if (uri.path.endsWith('/mobile-landing')) {
        return _ok(_landingPayload());
      }
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
            'safecontracts_create_customers': true,
            'safecontracts_create_contracts': true,
            'safecontracts_create_payments': true,
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
            'contract_count': '18',
            'scheduled_total': '1250000.0000',
            'remaining_total': '430000.0000',
            'overdue_exposure': '90000.0000',
            'collected_total': '820000.0000',
          },
          'customers': <Object?>[
            <String, Object?>{'id': 7, 'name': 'شركة الإبداع للإعلان'},
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
      if (uri.path.endsWith('/customers') ||
          uri.path.endsWith('/contracts') ||
          uri.path.endsWith('/payments') ||
          uri.path.endsWith('/collections') ||
          uri.path.endsWith('/followups') ||
          uri.path.endsWith('/notifications') ||
          uri.path.endsWith('/devices')) {
        return _ok(<Object?>[], meta: <String, Object?>{
          'api_version': 'v1',
          'scope': uri.path.endsWith('/devices') ? 'current_user' : 'assigned',
          'page': 1,
          'per_page': 25,
          'sort': 'id',
          'order': 'desc',
          'has_more': false,
          'bounded_window': 500,
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
        languageCode: 'ar',
      ),
    );
    await tester.pumpAndSettle();
    await _capture(tester, 'welcome_ar');

    final welcomeSignIn = find.byKey(const Key('companyWelcomeSignIn'));
    await tester.ensureVisible(welcomeSignIn);
    await tester.tap(welcomeSignIn);
    await tester.pumpAndSettle();
    await _capture(tester, 'login_ar');

    await tester.enterText(find.byType(EditableText).first, 'admin');
    await tester.enterText(find.byType(EditableText).last, 'secret');
    await tester.tap(find.byKey(const Key('loginSubmit')));
    await tester.pumpAndSettle();
    await _capture(tester, 'shell_ar');

    final menu = find.byIcon(Icons.menu_rounded);
    if (menu.evaluate().isEmpty) {
      await tester.tap(find.byTooltip('Open navigation menu'));
    } else {
      await tester.tap(menu.first);
    }
    await tester.pumpAndSettle();
    await _capture(tester, 'drawer_ar');
    Navigator.of(tester.element(find.byType(Drawer))).pop();
    await tester.pumpAndSettle();

    await tester.tap(find.byType(FloatingActionButton));
    await tester.pumpAndSettle();
    await _capture(tester, 'quick_add_ar');

    expect(tester.takeException(), isNull);
  });
}

Future<void> _capture(WidgetTester tester, String name) async {
  final ui.Image image = await tester.binding.renderView.toImage(pixelRatio: 1);
  final data = await image.toByteData(format: ui.ImageByteFormat.png);
  final bytes = data!.buffer.asUint8List();
  // Intentionally emitted only by this temporary visual-QA test.
  // ignore: avoid_print
  print('WORKER1_PNG_BEGIN:$name');
  // ignore: avoid_print
  print(base64Encode(bytes));
  // ignore: avoid_print
  print('WORKER1_PNG_END:$name');
}

Map<String, Object?> _landingPayload() => <String, Object?>{
      'schema_version': 1,
      'brand_name': 'Alkenzy ADV',
      'agency_name': <String, Object?>{
        'en': 'Alkenzy Advertising Agency',
        'ar': 'الكنزي للإعلان',
      },
      'headline': <String, Object?>{
        'en': 'Business management made simple',
        'ar': 'إدارة أعمالك باحتراف وتميز',
      },
      'highlight': <String, Object?>{
        'en': 'Contracts, finance and reporting in one place',
        'ar': 'العقود والدفعات والتقارير في مكان واحد',
      },
      'summary': <String, Object?>{
        'en': 'Manage contracts, payments and follow-ups from one secure workspace.',
        'ar': 'منصة متكاملة لإدارة العقود والمدفوعات والمتابعات والتقارير في مكان واحد.',
      },
      'experience_years': 10,
      'services': <Object?>[],
      'contact': <String, Object?>{
        'phones': <Object?>['01000272232'],
        'office_address': <String, Object?>{
          'en': '57 Khatam Al-Morselin, Giza',
          'ar': '57 خاتم المرسلين، الجيزة',
        },
      },
      'sign_in_label': <String, Object?>{
        'en': 'Sign in',
        'ar': 'تسجيل الدخول',
      },
      'learn_more_label': <String, Object?>{
        'en': 'Learn more',
        'ar': 'تخطي',
      },
    };

ApiTransportResponse _ok(
  Object? data, {
  int statusCode = 200,
  Map<String, Object?> meta = const <String, Object?>{'api_version': 'v1'},
}) {
  return ApiTransportResponse(
    statusCode: statusCode,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{'data': data, 'meta': meta}),
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
