import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/welcome/mobile_landing.dart';

import 'fake_api_transport.dart';

void main() {
  test('ALK-MOBILE-621 parses bounded public landing content', () async {
    final transport = FakeApiTransport((uri) {
      expect(uri.path, endsWith('/mobile-landing'));
      return _ok(_payload());
    });
    final controller = MobileLandingController(
      MobileLandingRepository(_client(transport)),
    );

    await controller.ensureLoaded();

    expect(controller.state, MobileLandingState.ready);
    expect(controller.usingFallback, isFalse);
    expect(controller.content.brandName, 'Alkenzy ADV');
    expect(controller.content.headline.resolve('ar'), 'خبرة إعلانية تصنع الفرق');
    expect(controller.content.services.single.key, 'strategy');
    expect(controller.content.phones, <String>['01000272232']);
    controller.dispose();
  });

  test('ALK-MOBILE-621 falls back without blocking sign-in surface', () async {
    final transport = FakeApiTransport((uri) {
      return const ApiTransportResponse(
        statusCode: 503,
        headers: <String, String>{'content-type': 'application/json'},
        body:
            '{"code":"safecontracts_unavailable","message":"Unavailable","data":{"status":503,"api_version":"v1"}}',
      );
    });
    final controller = MobileLandingController(
      MobileLandingRepository(_client(transport)),
    );

    await controller.ensureLoaded();

    expect(controller.state, MobileLandingState.fallback);
    expect(controller.usingFallback, isTrue);
    expect(controller.content.brandName, MobileLandingContent.fallback.brandName);
    expect(controller.errorMessage, isNotNull);
    controller.dispose();
  });

  test('ALK-MOBILE-621 rejects unsupported or unsafe public schema', () {
    final payload = _payload()
      ..['schema_version'] = 2
      ..['private_users'] = <Object?>[42];

    expect(
      () => MobileLandingContent.fromJson(payload),
      throwsA(isA<FormatException>()),
    );
  });
}

SafeContractsApiClient _client(FakeApiTransport transport) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1/wp-json/safecontracts/v1/',
    ),
    transport: transport,
  );
}

Map<String, Object?> _payload() => <String, Object?>{
      'schema_version': 1,
      'brand_name': 'Alkenzy ADV',
      'agency_name': <String, Object?>{
        'en': 'Alkenzy Advertising Agency',
        'ar': 'الكنزي للإعلان',
      },
      'headline': <String, Object?>{
        'en': 'Advertising built on experience',
        'ar': 'خبرة إعلانية تصنع الفرق',
      },
      'highlight': <String, Object?>{
        'en': 'Planning, execution, and measurable impact',
        'ar': 'تخطيط وتنفيذ وتأثير قابل للقياس',
      },
      'summary': <String, Object?>{
        'en': 'Alkenzy public marketing summary.',
        'ar': 'ملخص تسويقي عام للكنزي.',
      },
      'experience_years': 10,
      'services': <Object?>[
        <String, Object?>{
          'key': 'strategy',
          'title': <String, Object?>{
            'en': 'Marketing strategy',
            'ar': 'استراتيجيات تسويقية',
          },
          'subtitle': <String, Object?>{
            'en': 'Campaign planning',
            'ar': 'تخطيط الحملات',
          },
        },
      ],
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
        'ar': 'اعرف المزيد',
      },
    };

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
