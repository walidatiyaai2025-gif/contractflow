import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  group('forward-only Alkenzy ADV release contracts', () {
    test('dashboard keeps the approved compact single-row KPI design', () {
      final source = File('lib/features/dashboard/dashboard_screen.dart')
          .readAsStringSync();

      expect(source, contains('Total account balance'));
      expect(source, contains('height: 70'));
      expect(
        source,
        contains('Expanded(child: _CompactKpiCard(item: items[index]))'),
      );
      expect(source, contains('_DashboardTabs(controller: controller)'));
      expect(source, contains('_TabSwipeRegion('));
      expect(
        source,
        contains('DashboardRecordType.collection => record.amount'),
      );
      expect(source, isNot(contains('final columns = compact ? 2 : 4')));
      expect(
        source,
        isNot(
          contains('final amount = record.remainingAmount ?? record.amount;'),
        ),
      );
    });

    test('only the authoritative dashboard is exposed in navigation', () {
      final policy = File('lib/features/navigation/navigation_policy.dart')
          .readAsStringSync();
      expect(
        policy,
        isNot(
          contains(
            'MobileDestination.dashboard,\n        MobileDestination.dashboardTwo,\n        MobileDestination.customers',
          ),
        ),
      );
    });

    test('contracts keep compact filters sort infinite paging and working tabs',
        () {
      final contracts = File('lib/features/contracts/contracts_screen.dart')
          .readAsStringSync();
      final details = File(
        'lib/features/contracts/premium_contract_details_screen.dart',
      ).readAsStringSync();

      expect(contracts, contains('_CustomerFilterMenu('));
      expect(contracts, contains('_SortMenu('));
      expect(contracts, contains('ScrollController'));
      expect(contracts, contains('_loadNextOnScroll'));
      expect(contracts, contains('controller.nextPage()'));
      expect(contracts, contains("ValueKey('contractCard-\${contract.id}')"));
      expect(contracts, isNot(contains('CompactPagination(')));
      expect(details, contains('DefaultTabController('));
      expect(details, contains('length: 4'));
      expect(details, contains('TabBarView('));
    });

    test('drawer/header fixes stay present without changing dashboard layout',
        () {
      final shell =
          File('lib/features/navigation/app_shell.dart').readAsStringSync();
      expect(shell, contains('indicatorColor: Colors.white'));
      expect(shell, contains('TextOverflow.clip'));
      expect(shell, contains('FloatingActionButtonLocation.endFloat'));
    });

    test('biometric, remember-off and shaped RTL reports are packaged', () {
      final login =
          File('lib/features/auth/login_screen.dart').readAsStringSync();
      final auth =
          File('lib/features/auth/mobile_auth.dart').readAsStringSync();
      final biometric =
          File('lib/features/auth/biometric_auth.dart').readAsStringSync();
      final tokenStore =
          File('lib/core/auth/mobile_token_store.dart').readAsStringSync();
      final report = File('lib/features/export/mobile_report_export.dart')
          .readAsStringSync();
      final activity =
          File('android-release/MainActivity.kt').readAsStringSync();
      final pubspec = File('pubspec.yaml').readAsStringSync();
      final profile = File('lib/features/profile/modern_profile_content.dart')
          .readAsStringSync();

      expect(login, contains("Key('biometricLogin')"));
      expect(login, contains('_offerBiometricEnrollment'));
      expect(auth, contains('bool rememberMe = false'));
      expect(auth, contains('rememberMe = false;'));
      expect(biometric, contains('TargetPlatform.android'));
      expect(tokenStore, contains('persistCurrentForBiometric'));
      expect(report, contains('MobileReportFormat.xlsx'));
      expect(report, contains('MobileReportFormat.docx'));
      expect(report, contains('MobileReportFormat.pdf'));
      expect(report, contains('ArabicReshaper.instance.reshape'));
      expect(report, contains('pw.TextDirection.rtl'));
      expect(report, contains('PdfPageFormat.a4'));
      expect(report, contains('تقرير العقود'));
      expect(report, contains('تاريخ الإصدار'));
      expect(report, isNot(contains("'Generated: \$generatedDate'")));
      expect(report, contains('<w:bidi/>'));
      expect(report, contains('readingOrder="2"'));
      expect(activity, contains('Intent.ACTION_CREATE_DOCUMENT'));
      expect(activity, contains('safecontracts/files'));
      expect(activity, isNot(contains('safecontracts_exports')));
      expect(pubspec, contains('version: 0.3.22+22'));
      expect(profile, contains('PackageInfo.fromPlatform()'));
      expect(
        profile,
        isNot(contains("static const appVersion = '0.3.12+17'")),
      );
    });
  });
}
