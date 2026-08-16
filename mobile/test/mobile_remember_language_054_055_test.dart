import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/auth/mobile_token_store.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/core/localization/mobile_locale_controller.dart';
import 'package:safecontracts_mobile/features/auth/mobile_auth.dart';

import 'fake_api_transport.dart';

void main() {
  test('Remember me controls bearer-token persistence without storing password',
      () async {
    final environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    );
    final tokenStore = MemoryMobileTokenStore();
    final token = 'scm_${List<String>.filled(43, 'R').join()}';
    final transport = FakeApiTransport((uri) {
      expect(uri.path, endsWith('/auth/login'));
      return _ok(<String, Object?>{
        'token': token,
        'token_type': 'Bearer',
        'expires_at': '2026-09-15T00:00:00Z',
        'user_id': 42,
      });
    });
    final client = SafeContractsApiClient(
      environment: environment,
      transport: transport,
    );
    final repository = MobileAuthRepository(
      client: client,
      tokenStore: tokenStore,
    );

    await repository.login(
      username: 'admin',
      password: 'do-not-store-this-password',
      rememberMe: false,
    );
    expect(await tokenStore.read(), token);
    expect(tokenStore.lastPersistentWrite, isFalse);
    expect(
      transport.requests.single.body,
      contains('do-not-store-this-password'),
      reason: 'The password is sent only to the authenticated login request.',
    );
    expect(
      tokenStore.toString(),
      isNot(contains('do-not-store-this-password')),
      reason:
          'The token store never receives or retains the WordPress password.',
    );

    await repository.login(
      username: 'admin',
      password: 'second-password',
      rememberMe: true,
    );
    expect(tokenStore.lastPersistentWrite, isTrue);
  });

  test('Arabic and English language choice is normalized and persisted',
      () async {
    final store = _MemoryLocaleStore();
    final controller = MobileLocaleController(store: store);

    await controller.setLanguageCode('ar');
    expect(controller.languageCode, 'ar');
    expect(store.value, 'ar');

    await controller.setLanguageCode('EN');
    expect(controller.languageCode, 'en');
    expect(store.value, 'en');

    store.value = 'ar';
    final restored = MobileLocaleController(store: store);
    await restored.load();
    expect(restored.languageCode, 'ar');

    controller.dispose();
    restored.dispose();
  });
}

final class _MemoryLocaleStore implements MobileLocaleStore {
  String? value;

  @override
  Future<String?> readLanguageCode() async => value;

  @override
  Future<void> writeLanguageCode(String languageCode) async {
    value = languageCode;
  }
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
