import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';

void main() {
  group('AppEnvironment', () {
    test('accepts local HTTP API and normalizes trailing slash', () {
      final environment = AppEnvironment.fromValues(
        name: 'local',
        apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1',
      );

      expect(environment.name, AppEnvironmentName.local);
      expect(
        environment.apiBaseUri.toString(),
        'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
      );
      expect(
        environment.endpoint('health').toString(),
        'http://127.0.0.1:8080/wp-json/safecontracts/v1/health',
      );
    });

    test('requires HTTPS in production', () {
      expect(
        () => AppEnvironment.fromValues(
          name: 'production',
          apiBaseUrl: 'http://contracts.example.test/wp-json/safecontracts/v1/',
        ),
        throwsFormatException,
      );
    });

    test('rejects unknown environment names', () {
      expect(
        () => AppEnvironment.fromValues(
          name: 'demo',
          apiBaseUrl: 'https://contracts.example.test/wp-json/safecontracts/v1/',
        ),
        throwsFormatException,
      );
    });
  });
}
