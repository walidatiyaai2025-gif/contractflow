import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/config/app_config.dart';

void main() {
  test('blank API URL leaves the client safely unconfigured', () {
    final AppConfig config = AppConfig.fromValues(
      environment: 'TEST',
      apiBaseUrl: '',
    );

    expect(config.environment, 'test');
    expect(config.isApiConfigured, isFalse);
    expect(() => config.apiUri('wp-json/safecontracts/v1/health'), throwsStateError);
  });

  test('absolute API URL is normalized and resolves WordPress paths', () {
    final AppConfig config = AppConfig.fromValues(
      environment: 'staging',
      apiBaseUrl: 'https://contracts.example.test/subsite',
    );

    expect(config.isApiConfigured, isTrue);
    expect(
      config.apiUri('wp-json/safecontracts/v1/health').toString(),
      'https://contracts.example.test/subsite/wp-json/safecontracts/v1/health',
    );
  });

  test('non-http API schemes are rejected', () {
    expect(
      () => AppConfig.fromValues(
        environment: 'production',
        apiBaseUrl: 'file:///tmp/contracts',
      ),
      throwsFormatException,
    );
  });
}
