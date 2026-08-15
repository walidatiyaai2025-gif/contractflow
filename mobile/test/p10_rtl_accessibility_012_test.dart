import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/ui/mobile_layout.dart';

import 'fake_api_transport.dart';

void main() {
  group('SC-P10-012 RTL/accessibility pass', () {
    test('Arabic locale family resolves RTL and non-Arabic stays LTR', () {
      for (final locale in <String>['ar', 'ar-KW', 'ar_EG', ' AR-sa ']) {
        expect(safeContractsTextDirection(locale), TextDirection.rtl);
      }
      for (final locale in <String>['en', 'en-US', 'fr', '']) {
        expect(safeContractsTextDirection(locale), TextDirection.ltr);
      }
    });

    test('responsive breakpoints remain deterministic', () {
      expect(safeContractsBreakpoint(320), SafeContractsBreakpoint.narrow);
      expect(safeContractsBreakpoint(599.9), SafeContractsBreakpoint.narrow);
      expect(safeContractsBreakpoint(600), SafeContractsBreakpoint.medium);
      expect(safeContractsBreakpoint(1023.9), SafeContractsBreakpoint.medium);
      expect(safeContractsBreakpoint(1024), SafeContractsBreakpoint.wide);
    });

    testWidgets('adaptive body tolerates enlarged text without exceptions', (
      tester,
    ) async {
      await tester.pumpWidget(
        const MaterialApp(
          home: MediaQuery(
            data: MediaQueryData(textScaler: TextScaler.linear(2.0)),
            child: Scaffold(
              body: SafeContractsDirectionScope(
                languageCode: 'ar-KW',
                child: SafeContractsAdaptiveBody(
                  child: Text(
                    'نص تجريبي طويل لاختبار تكبير الخط والتخطيط المتجاوب',
                  ),
                ),
              ),
            ),
          ),
        ),
      );

      expect(tester.takeException(), isNull);
      expect(
        Directionality.of(
          tester.element(find.textContaining('نص تجريبي')),
        ),
        TextDirection.rtl,
      );
    });

    testWidgets('bootstrap exposes application and loading semantics', (
      tester,
    ) async {
      final semantics = tester.ensureSemantics();
      final environment = AppEnvironment.fromValues(
        name: 'local',
        apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
      );
      final transport = FakeApiTransport(
        (_) => ApiTransportResponse(
          statusCode: 403,
          headers: const <String, String>{'content-type': 'application/json'},
          body: jsonEncode(<String, Object?>{
            'code': 'forbidden',
            'message': 'Forbidden',
          }),
        ),
      );
      final client = SafeContractsApiClient(
        environment: environment,
        transport: transport,
      );

      await tester.pumpWidget(
        SafeContractsApp(
          environment: environment,
          client: client,
          languageCode: 'ar-KW',
        ),
      );

      expect(find.bySemanticsLabel('SafeContracts application'), findsOneWidget);
      expect(
        find.bySemanticsLabel('Loading SafeContracts session'),
        findsOneWidget,
      );
      semantics.dispose();
    });
  });
}
