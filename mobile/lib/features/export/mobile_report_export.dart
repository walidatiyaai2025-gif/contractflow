import 'dart:convert';
import 'dart:typed_data';

import 'package:archive/archive.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:pdf/widgets.dart' as pw;

import '../../core/api/api_client.dart';
import '../../core/api/api_transport.dart';
import '../dashboard/dashboard_models.dart';

enum MobileReportType {
  contracts,
  payments,
  customers,
  finance,
  attachments,
  notifications,
}

enum MobileReportFormat { xlsx, docx, pdf }

extension MobileReportTypeInfo on MobileReportType {
  String get fileStem => switch (this) {
        MobileReportType.contracts => 'contracts',
        MobileReportType.payments => 'payments',
        MobileReportType.customers => 'customers',
        MobileReportType.finance => 'financial',
        MobileReportType.attachments => 'attachments',
        MobileReportType.notifications => 'notifications',
      };

  String get title => switch (this) {
        MobileReportType.contracts => 'Contracts report',
        MobileReportType.payments => 'Payments report',
        MobileReportType.customers => 'Customers report',
        MobileReportType.finance => 'Financial report',
        MobileReportType.attachments => 'Attachments report',
        MobileReportType.notifications => 'Notifications report',
      };
}

extension MobileReportFormatInfo on MobileReportFormat {
  String get extension => name;

  String get mimeType => switch (this) {
        MobileReportFormat.xlsx =>
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        MobileReportFormat.docx =>
          'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        MobileReportFormat.pdf => 'application/pdf',
      };
}

final class MobileReportData {
  const MobileReportData({
    required this.type,
    required this.columns,
    required this.rows,
  });

  final MobileReportType type;
  final List<String> columns;
  final List<List<String>> rows;
}

final class MobileReportDocument {
  const MobileReportDocument({
    required this.filename,
    required this.mimeType,
    required this.bytes,
  });

  final String filename;
  final String mimeType;
  final Uint8List bytes;
}

final class MobileReportRepository {
  MobileReportRepository(this.client);

  final SafeContractsApiClient client;

  Future<MobileReportData> load(
    MobileReportType type,
    DashboardFilters filters,
  ) async {
    filters.validate();
    return switch (type) {
      MobileReportType.contracts => _list(
          type,
          'contracts',
          <String, String>{
            ...filters.toQuery(includeDueRange: false),
            'page': '1',
            'per_page': '100',
          },
          const <String>[
            'contract_number',
            'counterparty_name',
            'status',
            'start_date',
            'end_date',
            'base_value',
            'currency_code',
            'financial_direction',
          ],
        ),
      MobileReportType.payments => _list(
          type,
          'payments',
          <String, String>{
            ...filters.toQuery(),
            'page': '1',
            'per_page': '100',
          },
          const <String>[
            'contract_number',
            'counterparty_name',
            'reference',
            'due_date',
            'expected_payment_date',
            'original_amount',
            'paid_amount',
            'remaining_amount',
            'status',
            'currency_code',
          ],
        ),
      MobileReportType.customers => _list(
          type,
          'customers',
          <String, String>{
            if (filters.customerId != null)
              'customer_id': filters.customerId.toString(),
            'page': '1',
            'per_page': '100',
          },
          const <String>[
            'internal_code',
            'name',
            'contact_name',
            'email',
            'phone',
            'is_active',
          ],
        ),
      MobileReportType.finance => _list(
          type,
          'finance/summary',
          filters.toQuery(),
          const <String>[
            'financial_direction',
            'currency_code',
            'obligation_count',
            'original_total',
            'settled_total',
            'outstanding_total',
            'overdue_total',
            'due_today_total',
            'due_30_total',
          ],
        ),
      MobileReportType.attachments => _attachments(filters),
      MobileReportType.notifications => _notifications(filters),
    };
  }

  Future<MobileReportData> _list(
    MobileReportType type,
    String path,
    Map<String, String> query,
    List<String> columns,
  ) async {
    final response = await client.get(path, query: query);
    final raw = apiObjectList(response.data, 'reports.${type.name}.data');
    final maps = raw
        .map((item) => apiObjectMap(item, 'reports.${type.name}.row'))
        .toList(growable: false);
    return _fromMaps(type, columns, maps);
  }

  Future<MobileReportData> _attachments(DashboardFilters filters) async {
    final contractId = filters.contractId;
    if (contractId == null) {
      throw const FormatException(
        'Choose a contract before downloading the attachments report.',
      );
    }
    final response = await client.get('contracts/$contractId/media');
    final root = apiObjectMap(response.data, 'reports.attachments.data');
    final raw = apiObjectList(
      root['attachments'],
      'reports.attachments.items',
    );
    final maps = raw
        .map((item) => apiObjectMap(item, 'reports.attachments.row'))
        .toList(growable: false);
    return _fromMaps(
      MobileReportType.attachments,
      const <String>[
        'id',
        'media_id',
        'label',
        'role',
        'mime_type',
        'url',
        'created_at',
      ],
      maps,
    );
  }

  Future<MobileReportData> _notifications(DashboardFilters filters) async {
    final response = await client.get(
      'notifications',
      query: const <String, String>{'page': '1', 'per_page': '50'},
    );
    final raw = apiObjectList(response.data, 'reports.notifications.data');
    var maps = raw
        .map((item) => apiObjectMap(item, 'reports.notifications.row'))
        .toList(growable: false);
    if (filters.dueFrom != null || filters.dueTo != null) {
      maps = maps.where((row) {
        final created = (row['created_at'] ?? '').toString();
        final day = created.length >= 10 ? created.substring(0, 10) : created;
        if (filters.dueFrom != null && day.compareTo(filters.dueFrom!) < 0) {
          return false;
        }
        if (filters.dueTo != null && day.compareTo(filters.dueTo!) > 0) {
          return false;
        }
        return true;
      }).toList(growable: false);
    }
    return _fromMaps(
      MobileReportType.notifications,
      const <String>[
        'id',
        'payment_id',
        'template_code',
        'scheduled_for',
        'created_at',
        'is_read',
      ],
      maps,
    );
  }

  MobileReportData _fromMaps(
    MobileReportType type,
    List<String> columns,
    List<Map<String, Object?>> maps,
  ) {
    return MobileReportData(
      type: type,
      columns: List<String>.unmodifiable(columns),
      rows: List<List<String>>.unmodifiable(
        maps.map(
          (row) => List<String>.unmodifiable(
            columns.map((column) => _cell(row[column])),
          ),
        ),
      ),
    );
  }

  String _cell(Object? value) {
    if (value == null) return '';
    if (value is String || value is num || value is bool)
      return value.toString();
    return jsonEncode(value);
  }
}

final class MobileReportDocumentBuilder {
  Future<MobileReportDocument> build(
    MobileReportData data,
    MobileReportFormat format,
  ) async {
    final date = DateTime.now().toIso8601String().substring(0, 10);
    final filename = 'Alkenzy-${data.type.fileStem}-$date.${format.extension}';
    final bytes = switch (format) {
      MobileReportFormat.xlsx => _xlsx(data),
      MobileReportFormat.docx => _docx(data),
      MobileReportFormat.pdf => await _pdf(data),
    };
    return MobileReportDocument(
      filename: filename,
      mimeType: format.mimeType,
      bytes: bytes,
    );
  }

  Uint8List _xlsx(MobileReportData data) {
    final allRows = <List<String>>[data.columns, ...data.rows];
    final sheetRows = <String>[];
    for (var rowIndex = 0; rowIndex < allRows.length; rowIndex++) {
      final cells = <String>[];
      for (final value in allRows[rowIndex]) {
        final style = rowIndex == 0 ? ' s="1"' : '';
        cells.add(
          '<c t="inlineStr"$style><is><t xml:space="preserve">${_xml(value)}</t></is></c>',
        );
      }
      sheetRows.add('<row>${cells.join()}</row>');
    }
    final archive = Archive()
      ..addFile(_textFile(
        '[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            '<Default Extension="xml" ContentType="application/xml"/>'
            '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            '</Types>',
      ))
      ..addFile(_textFile(
        '_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            '</Relationships>',
      ))
      ..addFile(_textFile(
        'xl/workbook.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            '<sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>',
      ))
      ..addFile(_textFile(
        'xl/_rels/workbook.xml.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            '</Relationships>',
      ))
      ..addFile(_textFile(
        'xl/styles.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            '<fonts count="2"><font><sz val="11"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts>'
            '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            '<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            '</styleSheet>',
      ))
      ..addFile(_textFile(
        'xl/worksheets/sheet1.xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            '<sheetData>${sheetRows.join()}</sheetData></worksheet>',
      ));
    return Uint8List.fromList(ZipEncoder().encode(archive)!);
  }

  Uint8List _docx(MobileReportData data) {
    String wordCell(String value, {bool bold = false}) =>
        '<w:tc><w:p><w:r>${bold ? '<w:rPr><w:b/></w:rPr>' : ''}<w:t xml:space="preserve">${_xml(value)}</w:t></w:r></w:p></w:tc>';
    final rows = <String>[
      '<w:tr>${data.columns.map((v) => wordCell(v, bold: true)).join()}</w:tr>',
      ...data.rows.map(
        (row) => '<w:tr>${row.map((v) => wordCell(v)).join()}</w:tr>',
      ),
    ];
    final document = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
        '<w:body><w:p><w:r><w:rPr><w:b/></w:rPr><w:t>${_xml(data.type.title)}</w:t></w:r></w:p>'
        '<w:tbl>${rows.join()}</w:tbl><w:sectPr/></w:body></w:document>';
    final archive = Archive()
      ..addFile(_textFile(
        '[Content_Types].xml',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            '<Default Extension="xml" ContentType="application/xml"/>'
            '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            '</Types>',
      ))
      ..addFile(_textFile(
        '_rels/.rels',
        '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            '</Relationships>',
      ))
      ..addFile(_textFile('word/document.xml', document));
    return Uint8List.fromList(ZipEncoder().encode(archive)!);
  }

  Future<Uint8List> _pdf(MobileReportData data) async {
    final regular = pw.Font.ttf(
      await rootBundle.load('assets/fonts/Cairo-Regular.ttf'),
    );
    final medium = pw.Font.ttf(
      await rootBundle.load('assets/fonts/Cairo-Medium.ttf'),
    );
    final document = pw.Document();
    final limitedRows = data.rows.take(250).toList(growable: false);
    document.addPage(
      pw.MultiPage(
        theme: pw.ThemeData.withFont(base: regular, bold: medium),
        build: (context) => <pw.Widget>[
          pw.Text(
            data.type.title,
            style: pw.TextStyle(font: medium, fontSize: 18),
          ),
          pw.SizedBox(height: 12),
          pw.Table(
            border: pw.TableBorder.all(width: 0.4),
            children: <pw.TableRow>[
              pw.TableRow(
                children: data.columns
                    .map(
                      (value) => pw.Padding(
                        padding: const pw.EdgeInsets.all(4),
                        child: pw.Text(
                          value,
                          style: pw.TextStyle(font: medium, fontSize: 8),
                        ),
                      ),
                    )
                    .toList(growable: false),
              ),
              ...limitedRows.map(
                (row) => pw.TableRow(
                  children: row
                      .map(
                        (value) => pw.Padding(
                          padding: const pw.EdgeInsets.all(4),
                          child: pw.Text(value,
                              style: const pw.TextStyle(fontSize: 7)),
                        ),
                      )
                      .toList(growable: false),
                ),
              ),
            ],
          ),
        ],
      ),
    );
    return document.save();
  }

  ArchiveFile _textFile(String name, String content) {
    final bytes = utf8.encode(content);
    return ArchiveFile(name, bytes.length, bytes);
  }

  String _xml(String value) => value
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&apos;');
}

abstract interface class MobileDocumentSaveGateway {
  Future<String?> save(MobileReportDocument document);
}

final class AndroidDocumentSaveGateway implements MobileDocumentSaveGateway {
  static const MethodChannel _channel = MethodChannel('safecontracts/files');

  @override
  Future<String?> save(MobileReportDocument document) {
    return _channel.invokeMethod<String>('saveDocument', <String, Object>{
      'filename': document.filename,
      'mimeType': document.mimeType,
      'bytes': document.bytes,
    });
  }
}

enum MobileReportExportState { idle, loading, ready, error }

final class MobileReportExportController extends ChangeNotifier {
  MobileReportExportController({
    required this.repository,
    required this.filtersProvider,
    required this.canExport,
    MobileReportDocumentBuilder? builder,
    MobileDocumentSaveGateway? saveGateway,
  })  : builder = builder ?? MobileReportDocumentBuilder(),
        saveGateway = saveGateway ?? AndroidDocumentSaveGateway();

  final MobileReportRepository repository;
  final DashboardFilters Function() filtersProvider;
  final bool canExport;
  final MobileReportDocumentBuilder builder;
  final MobileDocumentSaveGateway saveGateway;

  MobileReportType reportType = MobileReportType.contracts;
  MobileReportFormat format = MobileReportFormat.xlsx;
  MobileReportExportState state = MobileReportExportState.idle;
  MobileReportDocument? lastDocument;
  String? savedLocation;
  String? errorMessage;

  void selectReportType(MobileReportType value) {
    if (reportType == value) return;
    reportType = value;
    clearResult(notify: false);
    notifyListeners();
  }

  void selectFormat(MobileReportFormat value) {
    if (format == value) return;
    format = value;
    clearResult(notify: false);
    notifyListeners();
  }

  Future<void> download() async {
    if (!canExport) {
      errorMessage = 'Report export is not authorized for this session.';
      state = MobileReportExportState.error;
      notifyListeners();
      return;
    }
    state = MobileReportExportState.loading;
    errorMessage = null;
    savedLocation = null;
    lastDocument = null;
    notifyListeners();
    try {
      final data = await repository.load(reportType, filtersProvider());
      final document = await builder.build(data, format);
      final location = await saveGateway.save(document);
      if (location == null || location.trim().isEmpty) {
        state = MobileReportExportState.idle;
        notifyListeners();
        return;
      }
      lastDocument = document;
      savedLocation = location;
      state = MobileReportExportState.ready;
    } on SafeContractsApiException catch (error) {
      errorMessage = error.message;
      state = MobileReportExportState.error;
    } on SafeContractsTransportException catch (error) {
      errorMessage = error.message;
      state = MobileReportExportState.error;
    } on PlatformException catch (error) {
      errorMessage = error.message ?? 'Unable to save the report file.';
      state = MobileReportExportState.error;
    } on FormatException catch (error) {
      errorMessage = error.message;
      state = MobileReportExportState.error;
    } on Object catch (error) {
      errorMessage = error.toString();
      state = MobileReportExportState.error;
    }
    notifyListeners();
  }

  void clearResult({bool notify = true}) {
    lastDocument = null;
    savedLocation = null;
    errorMessage = null;
    state = MobileReportExportState.idle;
    if (notify) notifyListeners();
  }
}
