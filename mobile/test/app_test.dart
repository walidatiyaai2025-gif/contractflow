import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';

void main() {
  testWidgets('renders SafeContracts foundation shell', (tester) async {
    final environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    );

    await tester.pumpWidget(SafeContractsApp(environment: environment));

    expect(find.text('SafeContracts'), findsOneWidget);
    expect(find.text('Environment: local'), findsOneWidget);
    expect(
      find.text(
        'Mobile foundation ready. Business data remains server-authoritative.',
      ),
      findsOneWidget,
    );
  });
}
