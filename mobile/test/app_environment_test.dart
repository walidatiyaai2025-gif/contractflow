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

    test('derives same-origin WordPress query REST fallback', () {
      final environment = AppEnvironment.fromValues(
        name: 'production',
        apiBaseUrl: 'https://esc.50sols.com/wp-json/safecontracts/v1/',
      );

      final fallback = environment.wordpressQueryEndpoint('session');

      expect(fallback, isNotNull);
      expect(fallback!.scheme, 'https');
      expect(fallback.host, 'esc.50sols.com');
      expect(fallback.path, '/');
      expect(
        fallback.queryParameters['rest_route'],
        '/safecontracts/v1/session',
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
          apiBaseUrl:
              'https://contracts.example.test/wp-json/safecontracts/v1/',
        ),
        throwsFormatException,
      );
    });
  });
}
