import 'dart:async';
import 'dart:convert';
import 'dart:ui' as ui;

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:flutter/rendering.dart';
import 'package:flutter_test/flutter_test.dart';
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

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Worker #3 final release visual QA', () {
    testWidgets('captures Arabic release screenshots from real screen widgets',
        (tester) async {
      await _setViewport(tester, const Size(390, 844));

      for (final target in _releaseTargets()) {
        final key = GlobalKey();
        await tester.pumpWidget(
          _qaApp(
            languageCode: 'ar',
            boundaryKey: key,
            child: target.build(),
          ),
        );
        await tester.pumpAndSettle(const Duration(milliseconds: 120));
        expect(tester.takeException(), isNull, reason: target.name);
        expect(
          Directionality.of(key.currentContext!),
          TextDirection.rtl,
          reason: '${target.name} must render RTL in Arabic',
        );
        await _emitScreenshot(key, 'ar_${target.name}');
      }
    });

    testWidgets('every owned screen renders in English LTR', (tester) async {
      await _setViewport(tester, const Size(390, 844));

      for (final target in _releaseTargets()) {
        final key = GlobalKey();
        await tester.pumpWidget(
          _qaApp(
            languageCode: 'en',
            boundaryKey: key,
            child: target.build(),
          ),
        );
        await tester.pumpAndSettle(const Duration(milliseconds: 120));
        expect(tester.takeException(), isNull, reason: target.name);
        expect(
          Directionality.of(key.currentContext!),
          TextDirection.ltr,
          reason: '${target.name} must render LTR in English',
        );
      }
    });

    testWidgets('owned screens stay overflow-free on release phone widths',
        (tester) async {
      for (final width in <double>[320, 360, 390, 430]) {
        await _setViewport(tester, Size(width, 844));
        for (final target in _topLevelTargets()) {
          final key = GlobalKey();
          await tester.pumpWidget(
            _qaApp(
              languageCode: 'ar',
              boundaryKey: key,
              child: target.build(),
            ),
          );
          await tester.pumpAndSettle(const Duration(milliseconds: 100));
          expect(
            tester.takeException(),
            isNull,
            reason: '${target.name} overflow/error at ${width.toInt()}px',
          );
        }
      }
    });

    testWidgets('empty/error paths remain designed and renderable',
        (tester) async {
      await _setViewport(tester, const Size(390, 844));

      final emptyHarness = SafeContractsTestHarness(_emptyHandler);
      final emptyTargets = <_QaTarget>[
        _QaTarget(
          'payments_empty',
          () => PaymentsScreen(
            repository: PaymentsRepository(emptyHarness.client),
            pageSize: 25,
            filters: const DashboardFilters(),
            currency: _currency,
          ),
        ),
        _QaTarget(
          'followups_empty',
          () => FollowUpsScreen(
            repository: FollowUpsRepository(emptyHarness.client),
            pageSize: 25,
            filters: const DashboardFilters(),
            currency: _currency,
            canManage: true,
          ),
        ),
        _QaTarget(
          'notifications_empty',
          () => NotificationsScreen(
            controller: NotificationsController(
              repository: NotificationsRepository(emptyHarness.client),
              pageSize: 25,
              canAccess: true,
            ),
          ),
        ),
      ];

      for (final target in emptyTargets) {
        final key = GlobalKey();
        await tester.pumpWidget(
          _qaApp(
            languageCode: 'ar',
            boundaryKey: key,
            child: target.build(),
          ),
        );
        await tester.pumpAndSettle(const Duration(milliseconds: 100));
        expect(tester.takeException(), isNull, reason: target.name);
      }

      final errorHarness = SafeContractsTestHarness(
        (_) => SafeContractsTestHarness.error(503, 'unavailable', 'offline'),
      );
      final errorTargets = <_QaTarget>[
        _QaTarget(
          'payments_error',
          () => PaymentsScreen(
            repository: PaymentsRepository(errorHarness.client),
            pageSize: 25,
            filters: const DashboardFilters(),
            currency: _currency,
          ),
        ),
        _QaTarget(
          'followups_error',
          () => FollowUpsScreen(
            repository: FollowUpsRepository(errorHarness.client),
            pageSize: 25,
            filters: const DashboardFilters(),
            currency: _currency,
            canManage: true,
          ),
        ),
        _QaTarget(
          'notifications_error',
          () => NotificationsScreen(
            controller: NotificationsController(
              repository: NotificationsRepository(errorHarness.client),
              pageSize: 25,
              canAccess: true,
            ),
          ),
        ),
      ];

      for (final target in errorTargets) {
        final key = GlobalKey();
        await tester.pumpWidget(
          _qaApp(
            languageCode: 'ar',
            boundaryKey: key,
            child: target.build(),
          ),
        );
        await tester.pumpAndSettle(const Duration(milliseconds: 100));
        expect(tester.takeException(), isNull, reason: target.name);
      }
    });
  });
}

const _currency = MobileCurrencyConfig(code: 'KWD', symbol: 'د.ك');

List<_QaTarget> _topLevelTargets() {
  final harness = SafeContractsTestHarness(_successHandler);
  final financeController = FinanceController(
    repository: FinanceRepository(harness.client),
    canViewPayables: true,
    canViewReceivables: true,
  );
  final notificationsController = NotificationsController(
    repository: NotificationsRepository(harness.client),
    pageSize: 25,
    canAccess: true,
  );
  final exportController = MobileExcelExportController(
    repository: MobileExcelExportRepository(harness.client),
    filtersProvider: () => const DashboardFilters(status: 'overdue'),
    canExport: true,
  );
  final profileController = ProfileController(ProfileRepository(harness.client));
  final pushRegistration = MobilePushRegistration(
    client: harness.client,
    messaging: _NoopPushGateway(),
    retryDelay: (_) async {},
  );
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

  return <_QaTarget>[
    _QaTarget(
      'payments',
      () => PaymentsScreen(
        repository: PaymentsRepository(harness.client),
        pageSize: 25,
        filters: const DashboardFilters(),
        currency: _currency,
        canManagePayments: true,
        canEnterCollection: true,
      ),
    ),
    _QaTarget('finance', () => FinanceScreen(controller: financeController)),
    _QaTarget(
      'followups',
      () => FollowUpsScreen(
        repository: FollowUpsRepository(harness.client),
        pageSize: 25,
        filters: const DashboardFilters(),
        currency: _currency,
        canManage: true,
      ),
    ),
    _QaTarget(
      'notifications',
      () => NotificationsScreen(controller: notificationsController),
    ),
    _QaTarget(
      'export',
      () => MobileExcelExportScreen(controller: exportController),
    ),
    _QaTarget(
      'profile',
      () => ProfileScreen(
        session: session,
        config: config,
        controller: profileController,
        pushRegistration: pushRegistration,
        languageCode: 'ar',
        onLanguageChanged: (_) {},
        onClearSession: () {},
      ),
    ),
    _QaTarget(
      'help',
      () => const MobileUserGuideScreen(
        destinations: <MobileDestination>[
          MobileDestination.dashboard,
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
    ),
  ];
}

List<_QaTarget> _releaseTargets() {
  final harness = SafeContractsTestHarness(_successHandler);
  final payment = SafeContractsPayment.fromData(_paymentReceivable());
  return <_QaTarget>[
    ..._topLevelTargets(),
    _QaTarget(
      'payment_detail',
      () => PaymentDetailScreen(
        repository: PaymentsRepository(harness.client),
        paymentId: 21,
        currency: _currency,
      ),
    ),
    _QaTarget(
      'collection_entry',
      () => CollectionEntryDialog(
        repository: PaymentsRepository(harness.client),
        payment: payment,
        currency: _currency,
      ),
    ),
    _QaTarget(
      'followup_history',
      () => FollowUpHistoryScreen(
        repository: FollowUpsRepository(harness.client),
        paymentId: 21,
        title: 'ADV-2026-001',
        pageSize: 25,
        canManage: true,
      ),
    ),
  ];
}

Widget _qaApp({
  required String languageCode,
  required GlobalKey boundaryKey,
  required Widget child,
}) {
  final locale = Locale(languageCode);
  return MaterialApp(
    debugShowCheckedModeBanner: false,
    locale: locale,
    supportedLocales: SafeContractsLocalizations.supportedLocales,
    localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
      SafeContractsLocalizations.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    theme: _qaTheme(),
    builder: (context, appChild) => SafeContractsDirectionScope(
      languageCode: languageCode,
      child: appChild ?? const SizedBox.shrink(),
    ),
    home: RepaintBoundary(
      key: boundaryKey,
      child: Material(
        color: SafeContractsVisual.background,
        child: child,
      ),
    ),
  );
}

ThemeData _qaTheme() {
  const scheme = ColorScheme.light(
    primary: SafeContractsVisual.navy,
    onPrimary: Colors.white,
    primaryContainer: SafeContractsVisual.navySoft,
    onPrimaryContainer: SafeContractsVisual.navyDeep,
    secondary: SafeContractsVisual.roseGold,
    onSecondary: Colors.white,
    secondaryContainer: SafeContractsVisual.roseGoldSoft,
    onSecondaryContainer: SafeContractsVisual.ink,
    tertiary: SafeContractsVisual.green,
    onTertiary: Colors.white,
    error: SafeContractsVisual.red,
    onError: Colors.white,
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

Future<void> _setViewport(WidgetTester tester, Size size) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(() {
    tester.view.resetPhysicalSize();
    tester.view.resetDevicePixelRatio();
  });
}

Future<void> _emitScreenshot(GlobalKey key, String name) async {
  final boundary = key.currentContext!.findRenderObject()! as RenderRepaintBoundary;
  final image = await boundary.toImage(pixelRatio: 1);
  final data = await image.toByteData(format: ui.ImageByteFormat.png);
  final encoded = base64Encode(data!.buffer.asUint8List());
  print('WORKER3_SCREENSHOT_BEGIN:$name');
  const chunkSize = 8000;
  for (var offset = 0; offset < encoded.length; offset += chunkSize) {
    final end = (offset + chunkSize).clamp(0, encoded.length);
    print('WORKER3_SCREENSHOT_DATA:$name:${encoded.substring(offset, end)}');
  }
  print('WORKER3_SCREENSHOT_END:$name');
}

ApiTransportResponse _successHandler(Uri uri) {
  final path = uri.path;
  if (path.endsWith('/finance/overview')) {
    return SafeContractsTestHarness.ok(_financeOverview());
  }
  if (path.endsWith('/finance/obligations')) {
    return SafeContractsTestHarness.ok(<Object?>[
      _financeReceivableObligation(),
      _financePayableObligation(),
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
          'note': 'Invoice copy sent to the accounting contact.',
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
    return SafeContractsTestHarness.ok(_paymentReceivable());
  }
  if (path.endsWith('/payments')) {
    return SafeContractsTestHarness.ok(
      <Object?>[
        _paymentReceivable(),
        _paymentPayable(),
        _paymentPaid(),
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
        <String, Object?>{
          'payment_id': 23,
          'contract_id': 103,
          'reference': 'ADV-2026-003',
          'due_date': '2026-08-29',
          'expected_payment_date': '2026-08-30',
          'remaining_amount': '75000.0000',
          'status': 'due_soon',
          'followup_state': 'note',
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
  return SafeContractsTestHarness.error(404, 'not_found', 'Not found');
}

Map<String, Object?> _paymentReceivable() => <String, Object?>{
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

Map<String, Object?> _paymentPayable() => <String, Object?>{
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

Map<String, Object?> _paymentPaid() => <String, Object?>{
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
        _financeSummary('receivable', '1850000.0000', '600000.0000',
            '1250000.0000', '350000.0000', 3),
        _financeSummary('payable', '820000.0000', '220000.0000',
            '600000.0000', '120000.0000', 2),
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
        _financeReceivableObligation(),
        _financePayableObligation(),
      ],
    };

Map<String, Object?> _financeSummary(
  String direction,
  String original,
  String settled,
  String outstanding,
  String overdue,
  int overdueCount,
) =>
    <String, Object?>{
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

Map<String, Object?> _aging(
  String direction,
  String bucket,
  String total,
  int count,
) =>
    <String, Object?>{
      'financial_direction': direction,
      'currency_code': 'KWD',
      'aging_bucket': bucket,
      'obligation_count': count,
      'outstanding_total': total,
    };

Map<String, Object?> _cashFlow(
  String date,
  String direction,
  String kind,
  String amount,
  int count,
) =>
    <String, Object?>{
      'due_date': date,
      'financial_direction': direction,
      'currency_code': 'KWD',
      'cash_flow_kind': kind,
      'obligation_count': count,
      'expected_amount': amount,
    };

Map<String, Object?> _financeReceivableObligation() => <String, Object?>{
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

Map<String, Object?> _financePayableObligation() => <String, Object?>{
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
) =>
    <String, Object?>{
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

final class _QaTarget {
  const _QaTarget(this.name, this.build);

  final String name;
  final Widget Function() build;
}

final class _NoopPushGateway implements PushMessagingGateway {
  @override
  Future<MobilePushPermissionState> requestPermission() async =>
      MobilePushPermissionState.authorized;

  @override
  Future<String?> getToken() async => 'qa-token';

  @override
  Stream<String> get onTokenRefresh => const Stream<String>.empty();

  @override
  Future<void> deleteToken() async {}
}
