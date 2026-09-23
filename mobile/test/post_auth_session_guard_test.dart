import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  String source(String relativePath) {
    final file = File(relativePath);
    expect(file.existsSync(), isTrue, reason: 'Missing source: $relativePath');
    return file.readAsStringSync();
  }

  test('failed post-login session bootstrap keeps the login surface visible', () {
    final app = source('lib/app.dart');
    final auth = source('lib/features/auth/mobile_auth.dart');

    expect(app, contains('final authenticated ='));
    expect(
      app,
      contains('widget.controller.state == MobileBootstrapState.ready'),
    );
    expect(app, contains('SessionState.authenticated'));
    expect(app, contains('setPostAuthenticationError('));
    expect(app, contains('setState(() => _showLogin = true);'));
    expect(
      app.indexOf('if (authenticated)'),
      lessThan(app.indexOf('setState(() => _showLogin = true);')),
    );

    expect(auth, contains('void setPostAuthenticationError(String message)'));
    expect(
      auth,
      contains(
        'Sign-in succeeded, but the authenticated session could not be opened. Please retry.',
      ),
    );
  });

  test('server bearer auth checks common managed-hosting header surfaces', () {
    final bearer = source(
      '../wordpress-plugin/safecontracts/src/Auth/MobileBearerAuthentication.php',
    );

    expect(bearer, contains("'HTTP_AUTHORIZATION'"));
    expect(bearer, contains("'REDIRECT_HTTP_AUTHORIZATION'"));
    expect(bearer, contains("'getallheaders'"));
    expect(bearer, contains("'apache_request_headers'"));
    expect(bearer, contains("strcasecmp(\$name, 'Authorization')"));
  });
}
