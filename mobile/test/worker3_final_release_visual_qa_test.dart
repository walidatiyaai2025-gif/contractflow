import 'dart:async';
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
import 'package:safecontracts_mobile/features/ui/safecontracts_theme.dart';

import 'support/safecontracts_test_harness.dart';

const _captureKey = Key('worker3ReleaseCapture');
const _currency = MobileCurrencyConfig(code: 'KWD', symbol: 'د.ك');
const _screens = <String>[
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

  testWidgets('Worker 3 Arabic release screenshots render from real widgets', (
    tester,
  ) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.binding.setSurfaceSize(const Size(390, 844));
    for (final name in _screens) {
      await tester.pumpWidget(_app('ar', _screen(name, 'ar')));
      await _pump(tester, cycles: 18);
      expect(tester.takeException(), isNull, reason: name);
      expect(
        Directionality.of(tester.element(find.byKey(_captureKey))),
        TextDirection.rtl,
        reason: '$name must be RTL in Arabic',
      );
      await _capture(tester, 'REF_W3_${name}_ar_390');
    }
  });

  testWidgets('Worker 3 owned screens render in English LTR', (tester) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    await tester.binding.setSurfaceSize(const Size(390, 844));
    for (final name in _screens) {
      await tester.pumpWidget(_app('en', _screen(name, 'en')));
      await _pump(tester, cycles: 18);
      expect(tester.takeException(), isNull, reason: name);
      expect(
        Directionality.of(tester.element(find.byKey(_captureKey))),
        TextDirection.ltr,
        reason: '$name must be LTR in English',
      );
    }
  });

  testWidgets('Worker 3 top-level screens are responsive on release widths', (
    tester,
  ) async {
    addTearDown(() => tester.binding.setSurfaceSize(null));
    const names = <String>[
      'payments',
      'finance',
      'followups',
      'notifications',
      'export',
      'profile',
      'help',
    ];
    for (final width in <double>[320, 360, 375, 390, 412, 430]) {
      await tester.binding.setSurfaceSize(Size(width, 844));
      for (final name in names) {
        await tester.pumpWidget(_app('ar', _screen(name, 'ar')));
        await _pump(tester, cycles: 16);
        expect(
          tester.takeException(),
          isNull,
          reason: '$name overflow/error at ${width.toInt()}px',
        );
      }
    }
  });

  testWidgets('Worker 3 async screens retain empty and error states', (
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
        await tester.pumpWidget(_app('ar', _stateScreen(name, state)));
        await _pump(tester, cycles: 18);
        expect(tester.takeException(), isNull, reason: '$name $state');
      }
    }

    final harness = _successHarness();
    final export = MobileExcelExportController(
      repository: MobileExcelExportRepository(harness.client),
      filtersProvider: () => const DashboardFilters(),
      canExport: false,
    );
    await export.downloadCurrentFilters();
    await tester
        .pumpWidget(_app('ar', MobileExcelExportScreen(controller: export)));
    await _pump(tester);
    expect(tester.takeException(), isNull);
    expect(export.state, ExcelExportState.error);
  });
}

Widget _screen(String name, String language) {
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
        payment: SafeContractsPayment.fromData(_payment()),
        currency: _currency,
      ),
    'finance' => FinanceScreen(controller: _financeController(harness)),
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
    'profile' => _profile(harness, language),
    'help' =>
      const MobileUserGuideScreen(destinations: MobileDestination.values),
    _ => throw StateError('Unknown Worker 3 screen: $name'),
  };
}

Widget _stateScreen(String name, String state) {
  final harness = state == 'empty' ? _emptyHarness() : _errorHarness();
  return switch (name) {
    'payments' => PaymentsScreen(
        repository: PaymentsRepository(harness.client),
        pageSize: 25,
        filters: const DashboardFilters(),
        currency: _currency,
      ),
    'finance' => FinanceScreen(controller: _financeController(harness)),
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
    'profile' => _profile(harness, 'ar'),
    _ => throw StateError('Unknown Worker 3 state screen: $name'),
  };
}

FinanceController _financeController(SafeContractsTestHarness harness) {
  return FinanceController(
    repository: FinanceRepository(harness.client),
    canViewPayables: true,
    canViewReceivables: true,
  );
}

Widget _profile(SafeContractsTestHarness harness, String language) {
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
    languageCode: language,
    onLanguageChanged: (_) {},
    onClearSession: () {},
  );
}

Widget _app(String language, Widget child) {
  return MaterialApp(
    debugShowCheckedModeBanner: false,
    locale: Locale(language),
    supportedLocales: SafeContractsLocalizations.supportedLocales,
    localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
      SafeContractsLocalizations.delegate,
      GlobalMaterialLocalizations.delegate,
      GlobalWidgetsLocalizations.delegate,
      GlobalCupertinoLocalizations.delegate,
    ],
    theme: SafeContractsTheme.build(language),
    builder: (context, appChild) => SafeContractsDirectionScope(
      languageCode: language,
      child: appChild ?? const SizedBox.shrink(),
    ),
    home: RepaintBoundary(
      key: _captureKey,
      child: Material(color: SafeContractsVisual.background, child: child),
    ),
  );
}

Future<void> _pump(WidgetTester tester, {int cycles = 12}) async {
  await tester.pump();
  for (var index = 0; index < cycles; index++) {
    await tester.pump(const Duration(milliseconds: 100));
  }
}

Future<void> _capture(WidgetTester tester, String name) async {
  final boundary =
      tester.renderObject<RenderRepaintBoundary>(find.byKey(_captureKey));
  final image = await boundary.toImage(pixelRatio: 1);
  final bytes = await image.toByteData(format: ui.ImageByteFormat.png);
  if (bytes == null) throw StateError('Unable to encode $name.');
  final directory = Directory('build/worker3-release-captures');
  await directory.create(recursive: true);
  await File('${directory.path}/$name.png').writeAsBytes(
    bytes.buffer.asUint8List(),
    flush: true,
  );
}

SafeContractsTestHarness _successHarness() =>
    SafeContractsTestHarness(_success);
SafeContractsTestHarness _emptyHarness() => SafeContractsTestHarness(_empty);
SafeContractsTestHarness _errorHarness() => SafeContractsTestHarness(
      (_) => SafeContractsTestHarness.error(503, 'unavailable', 'offline'),
    );

ApiTransportResponse _success(Uri uri) {
  final path = uri.path;
  if (path.endsWith('/payments/21/followups')) {
    return SafeContractsTestHarness.ok(<Object?>[
      <String, Object?>{
        'id': 501,
        'payment_id': 21,
        'state': 'promise',
        'note': 'Expected transfer confirmed.',
        'promised_date': '2026-08-27',
        'deferred_until': null,
        'created_at': '2026-08-24 09:30:00',
      },
    ], meta: const <String, Object?>{
      'api_version': 'v1',
      'sort': 'created_at',
      'order': 'desc',
    });
  }
  if (path.endsWith('/payments/21'))
    return SafeContractsTestHarness.ok(_payment());
  if (path.endsWith('/payments')) {
    return SafeContractsTestHarness.ok(<Object?>[
      _payment(),
      _supplierPayment()
    ], meta: const <String, Object?>{
      'api_version': 'v1',
      'page': 1,
      'per_page': 25,
      'has_more': false,
      'sort': 'due_date',
      'order': 'asc',
    });
  }
  if (path.endsWith('/reference-data')) {
    return SafeContractsTestHarness.ok(<String, Object?>{
      'payment_methods': <Object?>[
        <String, Object?>{
          'id': 3,
          'code': 'bank',
          'name': 'Bank transfer',
          'display_order': 1
        },
      ],
    });
  }
  if (path.endsWith('/finance/overview'))
    return SafeContractsTestHarness.ok(_overview());
  if (path.endsWith('/finance/obligations')) {
    return SafeContractsTestHarness.ok(
        <Object?>[_obligation('receivable'), _obligation('payable')]);
  }
  if (path.endsWith('/followups')) {
    return SafeContractsTestHarness.ok(<Object?>[
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
    ], meta: const <String, Object?>{
      'api_version': 'v1',
      'page': 1,
      'per_page': 25,
      'has_more': false,
      'sort': 'due_date',
      'order': 'asc',
    });
  }
  if (path.endsWith('/notifications')) {
    return SafeContractsTestHarness.ok(<Object?>[
      _notification(91, 21, 'payment_overdue', false),
      _notification(92, 22, 'payment_due', false),
    ], meta: const <String, Object?>{
      'api_version': 'v1',
      'scope': 'current_user',
      'page': 1,
      'per_page': 25,
      'has_more': false,
    });
  }
  if (path.endsWith('/devices')) {
    return SafeContractsTestHarness.ok(<Object?>[
      <String, Object?>{
        'id': 7,
        'platform': 'android',
        'is_active': true,
        'last_seen_at': '2026-08-24 08:30:00',
        'created_at': '2026-08-01 09:00:00',
        'updated_at': '2026-08-24 08:30:00',
      },
    ], meta: const <String, Object?>{
      'api_version': 'v1',
      'scope': 'current_user'
    });
  }
  return SafeContractsTestHarness.error(404, 'not_found', 'Not found');
}

ApiTransportResponse _empty(Uri uri) {
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
  if (path.endsWith('/finance/obligations'))
    return SafeContractsTestHarness.ok(<Object?>[]);
  if (path.endsWith('/payments'))
    return SafeContractsTestHarness.ok(<Object?>[], meta: _pageMeta());
  if (path.endsWith('/followups'))
    return SafeContractsTestHarness.ok(<Object?>[], meta: _pageMeta());
  if (path.endsWith('/notifications')) {
    return SafeContractsTestHarness.ok(<Object?>[],
        meta: const <String, Object?>{
          'api_version': 'v1',
          'scope': 'current_user',
          'page': 1,
          'per_page': 25,
          'has_more': false,
        });
  }
  if (path.endsWith('/devices')) {
    return SafeContractsTestHarness.ok(<Object?>[],
        meta: const <String, Object?>{
          'api_version': 'v1',
          'scope': 'current_user',
        });
  }
  return SafeContractsTestHarness.error(404, 'not_found', 'Not found');
}

Map<String, Object?> _pageMeta() => const <String, Object?>{
      'api_version': 'v1',
      'page': 1,
      'per_page': 25,
      'has_more': false,
      'sort': 'due_date',
      'order': 'asc',
    };

Map<String, Object?> _payment() => <String, Object?>{
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

Map<String, Object?> _supplierPayment() => <String, Object?>{
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

Map<String, Object?> _overview() => <String, Object?>{
      'directions': <String>['receivable', 'payable'],
      'summary': <Object?>[
        _summary('receivable', '1250000.0000', '350000.0000'),
        _summary('payable', '600000.0000', '120000.0000'),
      ],
      'aging': <Object?>[
        _aging('receivable', '1-30', '350000.0000'),
        _aging('payable', 'current', '480000.0000'),
      ],
      'cash_flow': <Object?>[
        _cash('2026-08-25', 'receivable', 'inflow', '230000.0000'),
        _cash('2026-08-27', 'payable', 'outflow', '120000.0000'),
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
      ],
      'work_queue_preview': <Object?>[_obligation('receivable')],
    };

Map<String, Object?> _summary(
        String direction, String outstanding, String overdue) =>
    <String, Object?>{
      'financial_direction': direction,
      'currency_code': 'KWD',
      'obligation_count': 9,
      'original_total': '1850000.0000',
      'settled_total': '600000.0000',
      'outstanding_total': outstanding,
      'overdue_total': overdue,
      'overdue_count': 3,
      'due_today_total': '230000.0000',
      'due_today_count': 2,
      'due_7_total': '500000.0000',
      'due_7_count': 4,
      'due_30_total': '900000.0000',
      'due_30_count': 7,
      'upcoming_total': '900000.0000',
    };

Map<String, Object?> _aging(String direction, String bucket, String amount) =>
    <String, Object?>{
      'financial_direction': direction,
      'currency_code': 'KWD',
      'aging_bucket': bucket,
      'obligation_count': 3,
      'outstanding_total': amount,
    };

Map<String, Object?> _cash(
        String date, String direction, String kind, String amount) =>
    <String, Object?>{
      'due_date': date,
      'financial_direction': direction,
      'currency_code': 'KWD',
      'cash_flow_kind': kind,
      'obligation_count': 2,
      'expected_amount': amount,
    };

Map<String, Object?> _obligation(String direction) => <String, Object?>{
      'id': direction == 'receivable' ? 401 : 402,
      'contract_id': direction == 'receivable' ? 101 : 102,
      'contract_number':
          direction == 'receivable' ? 'ADV-2026-001' : 'SUP-2026-014',
      'counterparty_type': direction == 'receivable' ? 'customer' : 'supplier',
      'counterparty_id': direction == 'receivable' ? 7 : 17,
      'counterparty_name': direction == 'receivable'
          ? 'شركة الإبداع للإعلان'
          : 'شركة النخبة للتوريدات',
      'financial_direction': direction,
      'currency_code': 'KWD',
      'sequence_no': 1,
      'reference': 'PAY-2026-021',
      'due_date': '2026-08-20',
      'expected_payment_date': '2026-08-27',
      'original_amount': '500000.0000',
      'settled_amount': '250000.0000',
      'remaining_amount': '250000.0000',
      'status': direction == 'receivable' ? 'overdue' : 'due',
      'aging_bucket': direction == 'receivable' ? '1-30' : 'current',
    };

Map<String, Object?> _notification(
        int id, int paymentId, String code, bool read) =>
    <String, Object?>{
      'id': id,
      'payment_id': paymentId,
      'template_code': code,
      'scheduled_for': '2026-08-24 10:30:00',
      'created_at': '2026-08-24 09:30:00',
      'is_read': read,
      'deep_link': <String, Object?>{
        'destination': 'payments',
        'resource_id': paymentId
      },
    };

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
