import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_components.dart';

import 'fake_api_transport.dart';

void main() {
  const phoneWidths = <double>[320, 360, 375, 390, 412, 430];

  testWidgets('premium bottom navigation does not overflow phone widths', (
    tester,
  ) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));

    for (final width in phoneWidths) {
      await tester.binding.setSurfaceSize(Size(width, 760));
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            bottomNavigationBar: SafeContractsBottomNavigation<int>(
              items: const <SafeContractsNavigationItem<int>>[
                SafeContractsNavigationItem<int>(
                  value: 0,
                  label: 'الرئيسية',
                  icon: Icons.home_outlined,
                ),
                SafeContractsNavigationItem<int>(
                  value: 1,
                  label: 'العقود',
                  icon: Icons.description_outlined,
                ),
                SafeContractsNavigationItem<int>(
                  value: 2,
                  label: 'الدفعات',
                  icon: Icons.receipt_long_outlined,
                ),
                SafeContractsNavigationItem<int>(
                  value: 3,
                  label: 'العملاء',
                  icon: Icons.people_alt_outlined,
                ),
              ],
              selected: 0,
              onSelected: (_) {},
              moreLabel: 'المزيد',
              onMore: () {},
            ),
          ),
        ),
      );
      await tester.pump();

      expect(tester.takeException(), isNull, reason: 'width=$width');
      expect(find.text('المزيد'), findsOneWidget);
    }
  });

  testWidgets('Arabic direction is preserved in shared navigation', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Directionality(
          textDirection: TextDirection.rtl,
          child: Scaffold(
            bottomNavigationBar: SafeContractsBottomNavigation<int>(
              items: const <SafeContractsNavigationItem<int>>[
                SafeContractsNavigationItem<int>(
                  value: 0,
                  label: 'الرئيسية',
                  icon: Icons.home_outlined,
                ),
              ],
              selected: 0,
              onSelected: (_) {},
              moreLabel: 'المزيد',
              onMore: () {},
            ),
          ),
        ),
      ),
    );

    final direction = Directionality.of(
      tester.element(find.text('الرئيسية')),
    );
    expect(direction, TextDirection.rtl);
    expect(tester.takeException(), isNull);
  });

  testWidgets('welcome and login remain usable at narrow and wide phones', (
    tester,
  ) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    const widths = <double>[320, 430];

    for (final languageCode in const <String>['ar', 'en']) {
      for (final width in widths) {
        await tester.binding.setSurfaceSize(Size(width, 820));
        final client = SafeContractsApiClient(
          environment: AppEnvironment.fromValues(
            name: 'local',
            apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
          ),
          transport: FakeApiTransport(_unauthenticatedHandler),
        );

        await tester.pumpWidget(
          SafeContractsApp(
            key: ValueKey<String>('welcome-$languageCode-$width'),
            environment: client.environment,
            client: client,
            languageCode: languageCode,
          ),
        );
        await tester.pumpAndSettle();

        final signIn = find.byKey(const Key('companyWelcomeSignIn'));
        expect(signIn, findsOneWidget);
        await tester.ensureVisible(signIn);
        await tester.tap(signIn);
        await tester.pumpAndSettle();

        expect(find.byKey(const Key('loginSubmit')), findsOneWidget);
        expect(
          Directionality.of(
            tester.element(find.byKey(const Key('loginSubmit'))),
          ),
          languageCode == 'ar' ? TextDirection.rtl : TextDirection.ltr,
        );
        expect(
          tester.takeException(),
          isNull,
          reason: 'language=$languageCode width=$width',
        );
      }
    }
  });
}

ApiTransportResponse _unauthenticatedHandler(Uri uri) {
  if (uri.path.endsWith('/session')) {
    return ApiTransportResponse(
      statusCode: 401,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'code': 'safecontracts_authentication_required',
        'message': 'Authentication required.',
        'data': <String, Object?>{
          'status': 401,
          'api_version': 'v1',
        },
      }),
    );
  }
  if (uri.path.endsWith('/mobile-landing')) {
    return ApiTransportResponse(
      statusCode: 503,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'code': 'mobile_landing_unavailable',
        'message': 'Unavailable.',
        'data': <String, Object?>{
          'status': 503,
          'api_version': 'v1',
        },
      }),
    );
  }
  return ApiTransportResponse(
    statusCode: 404,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'code': 'not_found',
      'message': 'Not found.',
    }),
  );
}
