import 'dart:async';
import 'dart:convert';
import 'dart:io';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/localization/safecontracts_localizations.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
import 'package:safecontracts_mobile/features/export/mobile_excel_export.dart';
import 'package:safecontracts_mobile/features/export/mobile_excel_export_screen.dart';
import 'package:safecontracts_mobile/features/finance/finance.dart';
import 'package:safecontracts_mobile/features/finance/finance_screen.dart';
import 'package:safecontracts_mobile/features/followups/followups.dart';
import 'package:safecontracts_mobile/features/followups/followups_screen.dart';
import 'package:safecontracts_mobile/features/help/mobile_user_guide_screen.dart';
import 'package:safecontracts_mobile/features/navigation/navigation_policy.dart';
import 'package:safecontracts_mobile/features/notifications/notifications.dart';
import 'package:safecontracts_mobile/features/notifications/notifications_screen.dart';
import 'package:safecontracts_mobile/features/notifications/push_registration.dart';
import 'package:safecontracts_mobile/features/payments/collection_entry_dialog.dart';
import 'package:safecontracts_mobile/features/payments/payments.dart';
import 'package:safecontracts_mobile/features/payments/payments_screen.dart';
import 'package:safecontracts_mobile/features/profile/profile.dart';
import 'package:safecontracts_mobile/features/profile/profile_screen.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';
import 'package:safecontracts_mobile/features/ui/mobile_layout.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_design.dart';

import 'support/safecontracts_test_harness.dart';

const _captureKey = Key('worker3ReleaseCapture');
const _currency = MobileCurrencyConfig(code: 'KWD', symbol: 'د.ك');
const _screenNames = <String>[
  'payments',
  'payment_detail',
  'collection_entry',
  'finance',
  'followups',
  'followup_history',
  'notifications',
  'export',
  'profile',
  'help',
];

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Worker #3 integrated final release QA', () {
    testWidgets('captures Arabic screenshots from the real owned widgets', (
      tester,
    ) async {
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.binding.setSurfaceSize(const Size(390, 844));

      for (final name in _screenNames) {
        await tester.pumpWidget(_qaApp('ar', _buildScreen(name, 'ar')));
        await _pumpBounded(tester, cycles: 18);
        expect(tester.takeException(), isNull, reason: name);
        expect(
          Directionality.of(tester.element(find.byKey(_captureKey))),
          TextDirection.rtl,
          reason: '$name must be RTL in Arabic',
        );
        await _capture(tester, 'REF_W3_${name}_ar_390');
      }
    });

    testWidgets('renders every owned screen in English LTR', (tester) async {
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.binding.setSurfaceSize(const Size(390, 844));

      for (final name in _screenNames) {
        await tester.pumpWidget(_qaApp('en', _buildScreen(name, 'en')));
        await _pumpBounded(tester, cycles: 18);
        expect(tester.takeException(), isNull, reason: name);
        expect(
          Directionality.of(tester.element(find.byKey(_captureKey))),
          TextDirection.ltr,
          reason: '$name must be LTR in English',
        );
      }
    });

    testWidgets('stays overflow-free at narrow and wide phone widths', (
      tester,
    ) async {
      addTearDown(() => tester.binding.setSurfaceSize(null));
      const topLevel = <String>[
        'payments',
        'finance',
        'followups',
        'notifications',
        'export',
        'profile',
        'help',
      ];

      for (final width in <double>[320, 360, 390, 430]) {
        await tester.binding.setSurfaceSize(Size(width, 844));
        for (final name in topLevel) {
          await tester.pumpWidget(_qaApp('ar', _buildScreen(name, 'ar')));
          await _pumpBounded(tester, cycles: 16);
          expect(
            tester.takeException(),
            isNull,
            reason: '$name must render without overflow at ${width.toInt()}px',
          );
        }
      }
    });

    testWidgets('renders empty and API-error states for async operations', (
      tester,
    ) async {
      addTearDown(() => tester.binding.setSurfaceSize(null));
      await tester.binding.setSurfaceSize(const Size(390, 844));

      for (final state in <String>['empty', 'error']) {
        for (final name in <String>[
          'payments',
          'finance',
          'followups',
          'notifications',
          'profile',
        ]) {
          await tester.pumpWidget(
            _qaApp('ar', _buildStateScreen(name, state)),
          );
          await _pumpBounded(tester, cycles: 18);
          expect(
            tester.takeException(),
            isNull,
            reason: '$name $state state must remain renderable',
          );
        }
      }

      final harness = _successHarness();
      final exportController = MobileExcelExportController(
        repository: MobileExcelExportRepository(harness.client),
        filtersProvider: () => const DashboardFilters(),
        canExport: false,
      );
      await exportController.downloadCurrentFilters();
      await tester.pumpWidget(
        _qaApp(
          'ar',
          MobileExcelExportScreen(controller: exportController),
        ),
      );
      await _pumpBounded(tester);
      expect(tester.takeException(), isNull);
      expect(exportController.state, ExcelExportState.error);
    });
  });
}

Widget _buildScreen(String name, String languageCode) {
  final harness = _successHarness();
  final payments = PaymentsRepository(harness.client);

  return switch (name) {
    'payments' => PaymentsScreen(
        repository: payments,
        pageSize: 25,
        filters: const DashboardFilters(),
        currency: _currency,
        canManagePayments: true,
        canEnterCollection: true,
      ),
    'payment_detail' => PaymentDetailScreen(
        repository: payments,
        paymentId: 21,
        currency: _currency,
      ),
    'collection_entry' => CollectionEntryDialog(
        repository: payments,
        payment: SafeContractsPayment.fromData(_receivablePayment()),
        currency: _currency,
      ),
    'finance' => FinanceScreen(
        controller: FinanceController(
          repository: FinanceRepository(harness.client),
          canViewPayables: true,
          canViewReceivables: true,
        ),
      ),
    'followups' => FollowUpsScreen(
        repository: FollowUpsRepository(harness.client),
        pageSize: 25,
        filters: const DashboardFilters(),
        currency: _currency,
        canManage: true,
      ),
    'followup_history' => FollowUpHistoryScreen(
        repository: FollowUpsRepository(harness.client),
        paymentId: 21,
        title: 'ADV-2026-001',
        pageSize: 25,
        canManage: true,
      ),
    'notifications' => NotificationsScreen(
        controller: NotificationsController(
          repository: NotificationsRepository(harness.client),
          pageSize: 25,
          canAccess: true,
        ),
      ),
    'export' => MobileExcelExportScreen(
        controller: MobileExcelExportController(
          repository: MobileExcelExportRepository(harness.client),
          filtersProvider: () => const DashboardFilters(status: 'overdue'),
          canExport: true,
        ),
      ),
    'profile' => _profileScreen(harness, languageCode),
    'help' => const MobileUserGuideScreen(
        destinations: <MobileDestination>[
          MobileDestination.dashboard,
          MobileDestination.dashboardTwo,
          MobileDestination.customers,
          MobileDestination.suppliers,
          MobileDestination.contracts,
          MobileDestination.payments,
          MobileDestination.finance,
          MobileDestination.collections,
          MobileDestination.followUps,
          MobileDestination.notifications,
          MobileDestination.export,
          MobileDestination.profile,
        ],
      ),
    _ => throw StateError('Unknown Worker 3 QA screen: $name'),
  };
}

Widget _buildStateScreen(String name, String state) {
  final harness = state == 'empty' ? _emptyHarness() : _errorHarness();
  return switch (name) {
    'payments' => PaymentsScreen(
        repository: PaymentsRepository(harness.client),
        pageSize: 25,
        filters: const DashboardFilters(),
        currency: _currency,
      ),
    'finance' => FinanceScreen(
        controller: FinanceController(
          repository: FinanceRepository(harness.client),
          canViewPayables: true,
          canViewReceivables: true,
        ),
      ),
    'followups' => FollowUpsScreen(
        repository: FollowUpsRepository(harness.client),
        pageSize: 25,
        filters: const DashboardFilters(),
        currency: _currency,
        canManage: true,
      ),
    'notifications' => NotificationsScreen(
        controller: NotificationsController(
          repository: NotificationsRepository(harness.client),
          pageSize: 25,
          canAccess: true,
        ),
      ),
    'profile' => _profileScreen(harness, 'ar'),
    _ => throw StateError('Unknown Worker 3 state QA screen: $name'),
  };
}

Widget _profileScreen(
  SafeContractsTestHarness harness,
  String languageCode,
) {
  const session = SafeContractsSession(
    userId: 42,
    scope: SafeContractsDataScope.all,
    capabilities: <String, bool>{
      'safecontracts_access': true,
      'safecontracts_view_reports': true,
      'safecontracts_export_reports': true,
      'safecontracts_manage_payments': true,
      'safecontracts_record_collections': true,
      'safecontracts_manage_followups': true,
      'safecontracts_view_notifications': true,
    },
  );
  const config = SafeContractsMobileConfig(
    supportText: 'support@alkenzy.com',
    defaultPageSize: 25,
    currency: _currency,
    features: MobileFeatureFlags(
      excelExport: true,
      pushNotifications: true,
      collectionEntry: true,
    ),
  );
  return ProfileScreen(
    session: session,
    config: config,
    controller: ProfileController(ProfileRepository(harness.client)),
    pushRegistration: MobilePushRegistration(
      client: harness.client,
      messaging: _NoopPushGateway(),
      retryDelay: (_) async {},
    ),
    languageCode: languageCode,
    onLanguageChanged: (_) {},
    onClearSession: () {},
  );
}

Widget _qaApp(String languageCode, Widget child) {
  return MaterialApp(
    debugShowCheckedModeBanner: false,
    locale: Locale(languageCode),
    supportedLocales: SafeContractsLocalizations.supportedLocales,
    localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
      SafeContractsLocalizations.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    theme: _theme(),
    builder: (context, appChild) => SafeContractsDirectionScope(
      languageCode: languageCode,
      child: appChild ?? const SizedBox.shrink(),
    ),
    home: RepaintBoundary(
      key: _captureKey,
      child: Material(
        color: SafeContractsVisual.background,
        child: child,
      ),
    ),
  );
}

ThemeData _theme() {
  const scheme = ColorScheme.light(
    primary: SafeContractsVisual.navy,
    onPrimary: Colors.white,
    secondary: SafeContractsVisual.roseGold,
    onSecondary: Colors.white,
    tertiary: SafeContractsVisual.green,
    error: SafeContractsVisual.red,
    surface: SafeContractsVisual.surface,
    onSurface: SafeContractsVisual.ink,
    outline: SafeContractsVisual.outline,
  );
  final border = OutlineInputBorder(
    borderRadius: BorderRadius.circular(16),
    borderSide: const BorderSide(color: SafeContractsVisual.outline),
  );
  return ThemeData(
    colorScheme: scheme,
    useMaterial3: true,
    scaffoldBackgroundColor: SafeContractsVisual.background,
    canvasColor: SafeContractsVisual.background,
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: SafeContractsVisual.surface,
      border: border,
      enabledBorder: border,
      focusedBorder: border.copyWith(
        borderSide: const BorderSide(color: SafeContractsVisual.roseGold),
      ),
    ),
    filledButtonTheme: FilledButtonThemeData(
      style: FilledButton.styleFrom(
        backgroundColor: SafeContractsVisual.navy,
        foregroundColor: Colors.white,
      ),
    ),
    outlinedButtonTheme: OutlinedButtonThemeData(
      style: OutlinedButton.styleFrom(
        foregroundColor: SafeContractsVisual.navy,
        side: const BorderSide(color: SafeContractsVisual.navy),
      ),
    ),
    chipTheme: ChipThemeData(
      backgroundColor: SafeContractsVisual.surface,
      selectedColor: SafeContractsVisual.roseGoldSoft,
      side: const BorderSide(color: SafeContractsVisual.outline),
      labelStyle: const TextStyle(fontWeight: FontWeight.w700),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
    ),
    progressIndicatorTheme: const ProgressIndicatorThemeData(
      color: SafeContractsVisual.roseGold,
      linearTrackColor: SafeContractsVisual.roseGoldSoft,
    ),
  );
}

Future<void> _pumpBounded(
  WidgetTester tester, {
  int cycles = 12,
}) async {
  await tester.pump();
  for (var index = 0; index < cycles; index++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<void> _capture(WidgetTester tester, String name) async {
  final boundary = tester.renderObject<RenderRepaintBoundary>(
    find.byKey(_captureKey),
  );
  final image = await boundary.toImage(pixelRatio: 1);
  final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
  if (bytes == null) throw StateError('Unable to encode $name.');
  final png = bytes.buffer.asUint8List();

  final directory = Directory('build/worker3-release-captures');
  await directory.create(recursive: true);
  await File('${directory.path}/$name.png').writeAsBytes(png, flush: true);

  final encoded = base64Encode(png);
  print('WORKER3_SCREENSHOT_BEGIN:$name');
  const chunkSize = 8000;
  for (var offset = 0; offset < encoded.length; offset += chunkSize) {
    final end = (offset + chunkSize).clamp(0, encoded.length).toInt();
    print('WORKER3_SCREENSHOT_DATA:$name:${encoded.substring(offset, end)}');
  }
  print('WORKER3_SCREENSHOT_END:$name');
}

SafeContractsTestHarness _successHarness() {
  return SafeContractsTestHarness(_successHandler);
}

SafeContractsTestHarness _emptyHarness() {
  return SafeContractsTestHarness(_emptyHandler);
}

SafeContractsTestHarness _errorHarness() {
  return SafeContractsTestHarness(
    (_) => SafeContractsTestHarness.error(503, 'unavailable', 'offline'),
  );
}

ApiTransportResponse _successHandler(Uri uri) {
  final path = uri.path;
  if (path.endsWith('/finance/overview')) {
    return SafeContractsTestHarness.ok(_financeOverview());
  }
  if (path.endsWith('/finance/obligations')) {
    return SafeContractsTestHarness.ok(<Object?>[
      _receivableObligation(),
      _payableObligation(),
    ]);
  }
  if (path.endsWith('/payments/21/followups')) {
    return SafeContractsTestHarness.ok(
      <Object?>[
        <String, Object?>{
          'id': 501,
          'payment_id': 21,
          'state': 'promise',
          'note': 'Customer confirmed expected transfer.',
          'promised_date': '2026-08-27',
          'deferred_until': null,
          'created_at': '2026-08-24 09:30:00',
        },
        <String, Object?>{
          'id': 498,
          'payment_id': 21,
          'state': 'note',
          'note': 'Invoice copy sent to accounting.',
          'promised_date': null,
          'deferred_until': null,
          'created_at': '2026-08-23 14:15:00',
        },
      ],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'sort': 'created_at',
        'order': 'desc',
      },
    );
  }
  if (path.endsWith('/payments/21')) {
    return SafeContractsTestHarness.ok(_receivablePayment());
  }
  if (path.endsWith('/payments')) {
    return SafeContractsTestHarness.ok(
      <Object?>[
        _receivablePayment(),
        _payablePayment(),
        _paidPayment(),
      ],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'page': 1,
        'per_page': 25,
        'has_more': false,
        'sort': 'due_date',
        'order': 'asc',
      },
    );
  }
  if (path.endsWith('/reference-data')) {
    return SafeContractsTestHarness.ok(<String, Object?>{
      'payment_methods': <Object?>[
        <String, Object?>{
          'id': 3,
          'code': 'bank',
          'name': 'Bank transfer',
          'display_order': 1,
        },
        <String, Object?>{
          'id': 4,
          'code': 'cash',
          'name': 'Cash',
          'display_order': 2,
        },
      ],
    });
  }
  if (path.endsWith('/followups')) {
    return SafeContractsTestHarness.ok(
      <Object?>[
        <String, Object?>{
          'payment_id': 21,
          'contract_id': 101,
          'reference': 'ADV-2026-001',
          'due_date': '2026-08-20',
          'expected_payment_date': '2026-08-27',
          'remaining_amount': '250000.0000',
          'status': 'overdue',
          'followup_state': 'promise',
        },
        <String, Object?>{
          'payment_id': 22,
          'contract_id': 102,
          'reference': 'ADV-2026-002',
          'due_date': '2026-08-24',
          'expected_payment_date': null,
          'remaining_amount': '125000.0000',
          'status': 'due',
          'followup_state': 'issue',
        },
      ],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'page': 1,
        'per_page': 25,
        'has_more': false,
        'sort': 'due_date',
        'order': 'asc',
      },
    );
  }
  if (path.endsWith('/notifications')) {
    return SafeContractsTestHarness.ok(
      <Object?>[
        _notification(91, 21, 'payment_overdue', false),
        _notification(92, 22, 'payment_due', false),
        _notification(93, 23, 'payment_reminder', true),
      ],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'scope': 'current_user',
        'page': 1,
        'per_page': 25,
        'has_more': false,
      },
    );
  }
  if (path.endsWith('/devices')) {
    return SafeContractsTestHarness.ok(
      <Object?>[
        <String, Object?>{
          'id': 7,
          'platform': 'android',
          'is_active': true,
          'last_seen_at': '2026-08-24 08:30:00',
          'created_at': '2026-08-01 09:00:00',
          'updated_at': '2026-08-24 08:30:00',
        },
      ],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'scope': 'current_user',
      },
    );
  }
  return SafeContractsTestHarness.error(404, 'not_found', 'Not found');
}

ApiTransportResponse _emptyHandler(Uri uri) {
  final path = uri.path;
  if (path.endsWith('/finance/overview')) {
    return SafeContractsTestHarness.ok(<String, Object?>{
      'directions': <String>['receivable', 'payable'],
      'summary': <Object?>[],
      'aging': <Object?>[],
      'cash_flow': <Object?>[],
      'action_center': <Object?>[],
      'work_queue_preview': <Object?>[],
    });
  }
  if (path.endsWith('/finance/obligations')) {
    return SafeContractsTestHarness.ok(<Object?>[]);
  }
  if (path.endsWith('/payments')) {
    return SafeContractsTestHarness.ok(
      <Object?>[],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'page': 1,
        'per_page': 25,
        'has_more': false,
        'sort': 'due_date',
        'order': 'asc',
      },
    );
  }
  if (path.endsWith('/followups')) {
    return SafeContractsTestHarness.ok(
      <Object?>[],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'page': 1,
        'per_page': 25,
        'has_more': false,
        'sort': 'due_date',
        'order': 'asc',
      },
    );
  }
  if (path.endsWith('/notifications')) {
    return SafeContractsTestHarness.ok(
      <Object?>[],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'scope': 'current_user',
        'page': 1,
        'per_page': 25,
        'has_more': false,
      },
    );
  }
  if (path.endsWith('/devices')) {
    return SafeContractsTestHarness.ok(
      <Object?>[],
      meta: const <String, Object?>{
        'api_version': 'v1',
        'scope': 'current_user',
      },
    );
  }
  return SafeContractsTestHarness.error(404, 'not_found', 'Not found');
}

Map<String, Object?> _receivablePayment() => <String, Object?>{
      'id': 21,
      'contract_id': 101,
      'contract_number': 'ADV-2026-001',
      'customer_id': 7,
      'customer_name': 'شركة الإبداع للإعلان',
      'counterparty_name': 'شركة الإبداع للإعلان',
      'accountant_user_id': 42,
      'sequence_no': 1,
      'reference': 'PAY-2026-021',
      'due_date': '2026-08-20',
      'expected_payment_date': '2026-08-27',
      'original_amount': '500000.0000',
      'paid_amount': '250000.0000',
      'remaining_amount': '250000.0000',
      'status': 'overdue',
      'contract_is_archived': false,
      'financial_direction': 'receivable',
    };

Map<String, Object?> _payablePayment() => <String, Object?>{
      'id': 22,
      'contract_id': 102,
      'contract_number': 'SUP-2026-014',
      'customer_id': 0,
      'customer_name': null,
      'supplier_name': 'شركة النخبة للتوريدات',
      'counterparty_name': 'شركة النخبة للتوريدات',
      'accountant_user_id': 42,
      'sequence_no': 2,
      'reference': 'PAY-2026-022',
      'due_date': '2026-08-24',
      'expected_payment_date': null,
      'original_amount': '180000.0000',
      'paid_amount': '55000.0000',
      'remaining_amount': '125000.0000',
      'status': 'due',
      'contract_is_archived': false,
      'financial_direction': 'payable',
    };

Map<String, Object?> _paidPayment() => <String, Object?>{
      'id': 23,
      'contract_id': 103,
      'contract_number': 'ADV-2026-018',
      'customer_id': 9,
      'customer_name': 'مؤسسة رؤية المستقبل',
      'counterparty_name': 'مؤسسة رؤية المستقبل',
      'accountant_user_id': 42,
      'sequence_no': 3,
      'reference': 'PAY-2026-023',
      'due_date': '2026-08-18',
      'expected_payment_date': '2026-08-18',
      'original_amount': '75000.0000',
      'paid_amount': '75000.0000',
      'remaining_amount': '0.0000',
      'status': 'paid',
      'contract_is_archived': false,
      'financial_direction': 'receivable',
    };

Map<String, Object?> _financeOverview() => <String, Object?>{
      'directions': <String>['receivable', 'payable'],
      'summary': <Object?>[
        _summary(
          'receivable',
          '1850000.0000',
          '600000.0000',
          '1250000.0000',
          '350000.0000',
          3,
        ),
        _summary(
          'payable',
          '820000.0000',
          '220000.0000',
          '600000.0000',
          '120000.0000',
          2,
        ),
      ],
      'aging': <Object?>[
        _aging('receivable', 'current', '500000.0000', 4),
        _aging('receivable', '1-30', '350000.0000', 3),
        _aging('payable', 'current', '480000.0000', 5),
        _aging('payable', '1-30', '120000.0000', 2),
      ],
      'cash_flow': <Object?>[
        _cashFlow('2026-08-25', 'receivable', 'inflow', '230000.0000', 2),
        _cashFlow('2026-08-27', 'payable', 'outflow', '120000.0000', 1),
        _cashFlow('2026-08-30', 'receivable', 'inflow', '310000.0000', 3),
      ],
      'action_center': <Object?>[
        <String, Object?>{
          'kind': 'overdue',
          'direction': 'receivable',
          'currency_code': 'KWD',
          'count': 3,
          'amount': '350000.0000',
          'priority': 'high',
        },
        <String, Object?>{
          'kind': 'due_7_days',
          'direction': 'payable',
          'currency_code': 'KWD',
          'count': 4,
          'amount': '270000.0000',
          'priority': 'medium',
        },
      ],
      'work_queue_preview': <Object?>[
        _receivableObligation(),
        _payableObligation(),
      ],
    };

Map<String, Object?> _summary(
  String direction,
  String original,
  String settled,
  String outstanding,
  String overdue,
  int overdueCount,
) {
  return <String, Object?>{
    'financial_direction': direction,
    'currency_code': 'KWD',
    'obligation_count': 9,
    'original_total': original,
    'settled_total': settled,
    'outstanding_total': outstanding,
    'overdue_total': overdue,
    'overdue_count': overdueCount,
    'due_today_total': '230000.0000',
    'due_today_count': 2,
    'due_7_total': '500000.0000',
    'due_7_count': 4,
    'due_30_total': '900000.0000',
    'due_30_count': 7,
    'upcoming_total': '900000.0000',
  };
}

Map<String, Object?> _aging(
  String direction,
  String bucket,
  String total,
  int count,
) {
  return <String, Object?>{
    'financial_direction': direction,
    'currency_code': 'KWD',
    'aging_bucket': bucket,
    'obligation_count': count,
    'outstanding_total': total,
  };
}

Map<String, Object?> _cashFlow(
  String date,
  String direction,
  String kind,
  String amount,
  int count,
) {
  return <String, Object?>{
    'due_date': date,
    'financial_direction': direction,
    'currency_code': 'KWD',
    'cash_flow_kind': kind,
    'obligation_count': count,
    'expected_amount': amount,
  };
}

Map<String, Object?> _receivableObligation() => <String, Object?>{
      'id': 401,
      'contract_id': 101,
      'contract_number': 'ADV-2026-001',
      'counterparty_type': 'customer',
      'counterparty_id': 7,
      'counterparty_name': 'شركة الإبداع للإعلان',
      'financial_direction': 'receivable',
      'currency_code': 'KWD',
      'sequence_no': 1,
      'reference': 'PAY-2026-021',
      'due_date': '2026-08-20',
      'expected_payment_date': '2026-08-27',
      'original_amount': '500000.0000',
      'settled_amount': '250000.0000',
      'remaining_amount': '250000.0000',
      'status': 'overdue',
      'aging_bucket': '1-30',
    };

Map<String, Object?> _payableObligation() => <String, Object?>{
      'id': 402,
      'contract_id': 102,
      'contract_number': 'SUP-2026-014',
      'counterparty_type': 'supplier',
      'counterparty_id': 17,
      'counterparty_name': 'شركة النخبة للتوريدات',
      'financial_direction': 'payable',
      'currency_code': 'KWD',
      'sequence_no': 2,
      'reference': 'PAY-2026-022',
      'due_date': '2026-08-24',
      'expected_payment_date': null,
      'original_amount': '180000.0000',
      'settled_amount': '60000.0000',
      'remaining_amount': '120000.0000',
      'status': 'due',
      'aging_bucket': 'current',
    };

Map<String, Object?> _notification(
  int id,
  int paymentId,
  String template,
  bool read,
) {
  return <String, Object?>{
    'id': id,
    'payment_id': paymentId,
    'template_code': template,
    'scheduled_for': '2026-08-24 10:30:00',
    'created_at': '2026-08-24 09:30:00',
    'is_read': read,
    'deep_link': <String, Object?>{
      'destination': 'payments',
      'resource_id': paymentId,
    },
  };
}

final class _NoopPushGateway implements PushMessagingGateway {
  @override
  Future<MobilePushPermissionState> requestPermission() async {
    return MobilePushPermissionState.authorized;
  }

  @override
  Future<String?> getToken() async => 'qa-token';

  @override
  Stream<String> get onTokenRefresh => const Stream<String>.empty();

  @override
  Future<void> deleteToken() async {}
}
