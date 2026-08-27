import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ALKENZY 0.3.15 mobile closure', () {
    test('bottom Contracts activation cannot preserve a false empty snapshot',
        () {
      final shell =
          File('lib/features/navigation/app_shell.dart').readAsStringSync();
      final activation =
          File('lib/features/contracts/contracts_activation.dart')
              .readAsStringSync();

      expect(shell, contains('activateForVisibleTab()'));
      expect(activation, contains('state == ContractsLoadState.error'));
      expect(activation, contains('page == null'));
      expect(activation, contains('page.contracts.isEmpty'));
      expect(activation, contains('await loadPage(1)'));
    });

    test('mobile contract cards never require an unbounded vertical flex', () {
      final screen = File('lib/features/contracts/contracts_screen.dart')
          .readAsStringSync();
      final cardStart = screen.indexOf('final class _ContractCard');
      final thumbnailStart = screen.indexOf('final class _ContractThumbnail');
      expect(cardStart, greaterThanOrEqualTo(0));
      expect(thumbnailStart, greaterThan(cardStart));
      final card = screen.substring(cardStart, thumbnailStart);

      expect(card, contains("ValueKey('contractCard-\${contract.id}')"));
      expect(card, contains('constraints: const BoxConstraints(minHeight: 164)'));
      expect(card, contains('crossAxisAlignment: CrossAxisAlignment.start'));
      expect(card, contains('height: 164'));
      expect(card, contains('mainAxisSize: MainAxisSize.min'));
      expect(card, isNot(contains('const Spacer()')));
      expect(card, isNot(contains('CrossAxisAlignment.stretch')));
    });

    test('Collections destination reads the actual settlement ledger', () {
      final shell =
          File('lib/features/navigation/app_shell.dart').readAsStringSync();
      final model =
          File('lib/features/collections/collections.dart').readAsStringSync();
      final screen = File('lib/features/collections/collections_screen.dart')
          .readAsStringSync();

      expect(shell,
          contains('MobileDestination.collections => CollectionsScreen('));
      expect(shell, contains('repository: CollectionsRepository(apiClient)'));
      expect(
        shell,
        isNot(contains('MobileDestination.collections => PaymentsScreen(')),
      );
      expect(model, contains("client.get(\n      'collections'"));
      expect(model,
          contains("amount: _money(data['amount'], 'collection.amount')"));
      expect(screen, contains('collection.amount'));
      expect(screen, contains('المبلغ المدفوع'));
      expect(screen, contains('المبلغ المحصل'));
      expect(screen, contains('سجل التحصيلات الفعلي'));
    });

    test('landing Collections tab never replaces settlement amount with zero remaining balance',
        () {
      final dashboard = File('lib/features/dashboard/dashboard_screen.dart')
          .readAsStringSync();
      expect(
        dashboard,
        contains('DashboardRecordType.collection => record.amount'),
      );
      expect(
        dashboard,
        contains(
          'DashboardRecordType.payment => record.remainingAmount ?? record.amount',
        ),
      );
      expect(
        dashboard,
        isNot(contains('final amount = record.remainingAmount ?? record.amount;')),
      );
    });

    test('PDF report is premium Arabic RTL rather than English-header output',
        () {
      final report = File('lib/features/export/mobile_report_export.dart')
          .readAsStringSync();

      expect(report, contains("MobileReportType.contracts => 'تقرير العقود'"));
      expect(report, contains("'نظام إدارة العقود والمستحقات'"));
      expect(report, contains("'تاريخ الإصدار: \$generatedDate'"));
      expect(report, contains("'عدد السجلات: \${data.rows.length}'"));
      expect(report, contains('pw.TextDirection.rtl'));
      expect(report, contains('PdfColor.fromInt(0xff102a43)'));
      expect(report, contains('PdfColor.fromInt(0xffc8956c)'));
      expect(report, contains('context.pageNumber'));
      expect(report, isNot(contains("'Generated: \$generatedDate'")));
    });
  });
}
