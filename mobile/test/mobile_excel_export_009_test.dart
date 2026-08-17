import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
import 'package:safecontracts_mobile/features/export/mobile_excel_export.dart';

import 'fake_api_transport.dart';

void main() {
  group('SC-P9-009 mobile Excel export', () {
    test(
      'downloads server workbook with the current dashboard filters',
      () async {
        final transport = FakeApiTransport(_exportHandler);
        final saver = _MemoryExportSaver();
        final controller = MobileExcelExportController(
          repository: MobileExcelExportRepository(_client(transport)),
          filtersProvider: () => const DashboardFilters(
            customerId: 7,
            contractId: 70,
            status: 'overdue',
            dueFrom: '2026-08-01',
            dueTo: '2026-08-31',
          ),
          canExport: true,
          saver: saver,
        );

        await controller.downloadCurrentFilters();

        expect(controller.state, ExcelExportState.ready);
        expect(controller.errorMessage, isNull);
        expect(controller.lastExport?.filename, 'SafeContracts-report.xlsx');
        expect(controller.lastExport?.rowCounts['payments'], 3);
        expect(controller.savedPath, '/app-cache/SafeContracts-report.xlsx');
        expect(saver.saved?.bytes, orderedEquals(_xlsxBytes));

        final request = transport.requests.single;
        expect(request.method, 'GET');
        expect(request.uri.path, endsWith('/safecontracts/v1/reports/excel'));
        expect(request.uri.queryParameters['customer_id'], '7');
        expect(request.uri.queryParameters['contract_id'], '70');
        expect(request.uri.queryParameters['status'], 'overdue');
        expect(request.uri.queryParameters['due_from'], '2026-08-01');
        expect(request.uri.queryParameters['due_to'], '2026-08-31');
        controller.dispose();
      },
    );

    test(
      'does not call the export endpoint when capability is unavailable',
      () async {
        final transport = FakeApiTransport(_exportHandler);
        final controller = MobileExcelExportController(
          repository: MobileExcelExportRepository(_client(transport)),
          filtersProvider: () => const DashboardFilters(),
          canExport: false,
          saver: _MemoryExportSaver(),
        );

        await controller.downloadCurrentFilters();

        expect(controller.state, ExcelExportState.error);
        expect(controller.errorMessage, contains('not authorized'));
        expect(transport.requests, isEmpty);
        controller.dispose();
      },
    );

    test('normalizes server filenames before writing to app storage', () async {
      final export = MobileExcelExport.fromData(
        _exportData(filename: '../../SafeContracts report.xlsx'),
      );

      expect(export.filename, 'SafeContracts_report.xlsx');
    });

    test(
      'rejects non-XLSX payloads instead of saving arbitrary content',
      () async {
        expect(
          () => MobileExcelExport.fromData(<String, Object?>{
            ..._exportData(),
            'content_base64': base64Encode(utf8.encode('not-a-workbook')),
          }),
          throwsFormatException,
        );
      },
    );
  });
}

const _xlsxBytes = <int>[0x50, 0x4b, 0x03, 0x04, 0x01, 0x02, 0x03];

SafeContractsApiClient _client(SafeContractsTransport transport) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    ),
    transport: transport,
  );
}

ApiTransportResponse _exportHandler(Uri uri) {
  if (!uri.path.endsWith('/reports/excel')) {
    return ApiTransportResponse(
      statusCode: 404,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'code': 'not_found',
        'message': 'Not found',
        'data': <String, Object?>{'status': 404},
      }),
    );
  }
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': _exportData(),
      'meta': <String, Object?>{'api_version': 'v1', 'download': true},
    }),
  );
}

Map<String, Object?> _exportData({
  String filename = 'SafeContracts-report.xlsx',
}) {
  return <String, Object?>{
    'filename': filename,
    'content_type': MobileExcelExport.xlsxContentType,
    'encoding': 'base64',
    'content_base64': base64Encode(_xlsxBytes),
    'filters': <String, Object?>{
      'customer_id': 7,
      'contract_id': 70,
      'status': 'overdue',
      'due_from': '2026-08-01',
      'due_to': '2026-08-31',
    },
    'row_counts': <String, Object?>{
      'customers': 1,
      'contracts': 1,
      'payments': 3,
      'collections': 1,
      'followups': 2,
    },
  };
}

final class _MemoryExportSaver implements ExcelExportSaver {
  MobileExcelExport? saved;

  @override
  Future<String> save(MobileExcelExport export) async {
    saved = export;
    return '/app-cache/${export.filename}';
  }
}
