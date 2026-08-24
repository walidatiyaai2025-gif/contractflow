import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/core/localization/safecontracts_localizations.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_controller.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_repository.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_screen.dart';

import 'fake_api_transport.dart';

void main() {
  const phoneWidths = <double>[320, 360, 375, 390, 412, 430];

  test('dashboard tab and month/year state stay controller-owned', () async {
    final requests = <Uri>[];
    final controller = _controller(requests);
    addTearDown(controller.dispose);

    await controller.load();
    controller.selectTab(DashboardTab.payments);
    await controller.selectPeriod(year: 2026, month: 8);

    expect(controller.selectedTab, DashboardTab.payments);
    expect(controller.selectedYear, 2026);
    expect(controller.selectedMonth, 8);
    expect(controller.filters.dueFrom, '2026-08-01');
    expect(controller.filters.dueTo, '2026-08-31');

    final periodRequests = requests.where(
      (uri) => uri.queryParameters['due_from'] == '2026-08-01',
    );
    expect(
      periodRequests.any((uri) => uri.path.endsWith('/dashboard')),
      isTrue,
    );
    expect(
      periodRequests.any((uri) => uri.path.endsWith('/payments')),
      isTrue,
    );
    expect(
      periodRequests.any((uri) => uri.path.endsWith('/collections')),
      isTrue,
    );
    expect(
      periodRequests.any((uri) => uri.path.endsWith('/followups')),
      isTrue,
    );

    controller.selectTab(DashboardTab.collections);
    expect(controller.selectedYear, 2026);
    expect(controller.selectedMonth, 8);
    expect(controller.filters.dueFrom, '2026-08-01');

    await controller.selectPeriod(year: 2026);
    expect(controller.selectedTab, DashboardTab.collections);
    expect(controller.selectedYear, 2026);
    expect(controller.selectedMonth, isNull);
    expect(controller.filters.dueFrom, '2026-01-01');
    expect(controller.filters.dueTo, '2026-12-31');
  });

  testWidgets('compact dashboard has no overflow at required AR/EN widths', (
    tester,
  ) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));

    for (final languageCode in const <String>['ar', 'en']) {
      for (final width in phoneWidths) {
        final controller = _controller(<Uri>[]);
        await controller.load();
        await tester.binding.setSurfaceSize(Size(width, 760));
        await tester.pumpWidget(
          MaterialApp(
            locale: Locale(languageCode),
            supportedLocales: SafeContractsLocalizations.supportedLocales,
            localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
              SafeContractsLocalizations.delegate,
              GlobalMaterialLocalizations.delegate,
              GlobalWidgetsLocalizations.delegate,
              GlobalCupertinoLocalizations.delegate,
            ],
            home: Scaffold(
              body: DashboardScreen(
                controller: controller,
                currency: const MobileCurrencyConfig(
                  code: 'KWD',
                  symbol: 'د.ك',
                ),
              ),
            ),
          ),
        );
        await tester.pump();

        expect(
          tester.takeException(),
          isNull,
          reason: 'initial language=$languageCode width=$width',
        );
        expect(
          Directionality.of(tester.element(find.byType(DashboardScreen))),
          languageCode == 'ar' ? TextDirection.rtl : TextDirection.ltr,
        );

        controller.selectTab(DashboardTab.payments);
        await tester.pump(const Duration(milliseconds: 200));
        expect(
          tester.takeException(),
          isNull,
          reason: 'payments language=$languageCode width=$width',
        );
        expect(
          find.text(languageCode == 'ar' ? 'فلاتر' : 'Filters'),
          findsOneWidget,
        );

        controller.selectTab(DashboardTab.contracts);
        await tester.pump(const Duration(milliseconds: 200));
        expect(
          tester.takeException(),
          isNull,
          reason: 'contracts language=$languageCode width=$width',
        );

        controller.selectTab(DashboardTab.collections);
        await tester.pump(const Duration(milliseconds: 200));
        expect(
          tester.takeException(),
          isNull,
          reason: 'collections language=$languageCode width=$width',
        );

        await tester.pumpWidget(const SizedBox.shrink());
        controller.dispose();
      }
    }
  });
}

DashboardController _controller(List<Uri> requests) {
  final environment = AppEnvironment.fromValues(
    name: 'test',
    apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
  );
  final client = SafeContractsApiClient(
    environment: environment,
    transport: FakeApiTransport((uri) {
      requests.add(uri);
      return _handler(uri);
    }),
  );
  return DashboardController(
    repository: DashboardRepository(client),
    config: const SafeContractsMobileConfig.defaults(),
  );
}

ApiTransportResponse _handler(Uri uri) {
  if (uri.path.endsWith('/dashboard')) {
    return _ok(<String, Object?>{
      'kpis': <String, Object?>{
        'contract_count': 18,
        'scheduled_total': '1250000.0000',
        'remaining_total': '430000.0000',
        'overdue_exposure': '120000.0000',
        'collected_total': '820000.0000',
      },
      'customers': <Object?>[
        <String, Object?>{
          'id': 7,
          'name': 'عميل ذو اسم طويل لاختبار العرض Customer Long Name',
        },
      ],
      'contracts': <Object?>[
        <String, Object?>{
          'id': 70,
          'contract_number': 'ADV-2026-070-LONG-REFERENCE',
          'customer_id': 7,
          'counterparty_type': 'customer',
          'counterparty_id': 7,
          'counterparty_name': 'Customer Long Name',
        },
      ],
    });
  }
  if (uri.path.endsWith('/contracts')) {
    return _ok(<Object?>[
      <String, Object?>{
        'id': 70,
        'contract_number': 'ADV-2026-070-LONG-REFERENCE',
        'status': 'active',
        'counterparty_name': 'Customer Long Name',
        'base_value': '1250000.0000',
      },
    ]);
  }
  if (uri.path.endsWith('/payments')) {
    return _ok(<Object?>[
      <String, Object?>{
        'id': 101,
        'reference': 'PAYMENT-REFERENCE-2026-000101',
        'status': 'overdue',
        'due_date': '2026-08-24',
        'counterparty_name': 'Customer Long Name',
        'remaining_amount': '120000.0000',
        'original_amount': '200000.0000',
      },
    ]);
  }
  if (uri.path.endsWith('/collections')) {
    return _ok(<Object?>[
      <String, Object?>{
        'id': 201,
        'reference': 'COLLECTION-2026-000201',
        'payment_status': 'paid',
        'collection_date': '2026-08-20',
        'counterparty_name': 'Customer Long Name',
        'remaining_amount': '0.0000',
        'amount': '80000.0000',
      },
    ]);
  }
  if (uri.path.endsWith('/followups')) {
    return _ok(<Object?>[
      <String, Object?>{
        'payment_id': 101,
        'reference': 'FOLLOWUP-2026-000101',
        'followup_state': 'overdue',
        'due_date': '2026-08-24',
        'remaining_amount': '120000.0000',
      },
    ]);
  }
  if (uri.path.endsWith('/filters/contracts')) {
    return _ok(<Object?>[
      <String, Object?>{
        'id': 70,
        'contract_number': 'ADV-2026-070-LONG-REFERENCE',
        'customer_id': 7,
        'counterparty_type': 'customer',
        'counterparty_id': 7,
        'counterparty_name': 'Customer Long Name',
      },
    ]);
  }
  return _error(404, 'not_found');
}

ApiTransportResponse _ok(Object? data) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': const <String, Object?>{},
    }),
  );
}

ApiTransportResponse _error(int status, String code) {
  return ApiTransportResponse(
    statusCode: status,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'code': code,
      'message': code,
      'data': <String, Object?>{'status': status},
    }),
  );
}
