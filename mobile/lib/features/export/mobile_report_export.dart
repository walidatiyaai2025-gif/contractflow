import 'dart:convert';

import 'package:arabic_reshaper/arabic_reshaper.dart';
import 'package:archive/archive.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
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

  String get arabicTitle => switch (this) {
        MobileReportType.contracts => 'تقرير العقود',
        MobileReportType.payments => 'تقرير الدفعات',
        MobileReportType.customers => 'تقرير العملاء',
        MobileReportType.finance => 'التقرير المالي',
        MobileReportType.attachments => 'تقرير المرفقات',
        MobileReportType.notifications => 'تقرير الإشعارات',
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
    if (value is String || value is num || value is bool) {
      return value.toString();
    }
    return jsonEncode(value);
  }
}

final class MobileReportDocumentBuilder {
  static const Map<String, String> _arabicColumns = <String, String>{
    'id': 'الرقم',
    'media_id': 'رقم الملف',
    'contract_number': 'رقم العقد',
    'counterparty_name': 'الطرف',
    'status': 'الحالة',
    'start_date': 'تاريخ البداية',
    'end_date': 'تاريخ النهاية',
    'base_value': 'قيمة العقد',
    'currency_code': 'العملة',
    'financial_direction': 'الاتجاه المالي',
    'reference': 'المرجع',
    'due_date': 'تاريخ الاستحقاق',
    'expected_payment_date': 'تاريخ السداد المتوقع',
    'original_amount': 'المبلغ الأصلي',
    'paid_amount': 'المبلغ المدفوع',
    'remaining_amount': 'المبلغ المتبقي',
    'internal_code': 'الكود الداخلي',
    'name': 'الاسم',
    'contact_name': 'جهة الاتصال',
    'email': 'البريد الإلكتروني',
    'phone': 'الهاتف',
    'is_active': 'نشط',
    'obligation_count': 'عدد الالتزامات',
    'original_total': 'إجمالي الالتزامات',
    'settled_total': 'إجمالي المسدد',
    'outstanding_total': 'إجمالي المتبقي',
    'overdue_total': 'إجمالي المتأخر',
    'due_today_total': 'مستحق اليوم',
    'due_30_total': 'مستحق خلال 30 يومًا',
    'label': 'اسم الملف',
    'role': 'نوع المرفق',
    'mime_type': 'نوع الملف',
    'url': 'الرابط',
    'created_at': 'تاريخ الإنشاء',
    'payment_id': 'رقم الدفعة',
    'template_code': 'قالب الإشعار',
    'scheduled_for': 'تاريخ الجدولة',
    'is_read': 'مقروء',
  };

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
        final header = rowIndex == 0;
        final rtl = _containsArabic(value);
        final styleId = header ? (rtl ? 3 : 1) : (rtl ? 2 : 0);
        cells.add(
          '<c t="inlineStr" s="$styleId"><is><t xml:space="preserve">${_xml(value)}</t></is></c>',
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
            '<cellXfs count="4">'
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0" applyAlignment="1"><alignment horizontal="right" readingOrder="2"/></xf>'
            '<xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyAlignment="1"><alignment horizontal="right" readingOrder="2"/></xf>'
            '</cellXfs></styleSheet>',
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
    String wordCell(String value, {bool bold = false}) {
      final rtl = _containsArabic(value);
      final p = rtl ? '<w:pPr><w:bidi/><w:jc w:val="right"/></w:pPr>' : '';
      final r = StringBuffer('<w:rPr>');
      if (bold) r.write('<w:b/>');
      if (rtl) r.write('<w:rtl/>');
      r.write('</w:rPr>');
      return '<w:tc><w:p>$p<w:r>${r.toString()}'
          '<w:t xml:space="preserve">${_xml(value)}</w:t></w:r></w:p></w:tc>';
    }

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
    final brand = pw.MemoryImage(
      (await rootBundle.load('assets/brand/alkenzy_adv.png'))
          .buffer
          .asUint8List(),
    );
    final generatedDate = DateTime.now().toIso8601String().substring(0, 10);
    final document = pw.Document();
    final limitedRows = data.rows.take(250).toList(growable: false);
    final pageFormat =
        data.columns.length > 6 ? PdfPageFormat.a4.landscape : PdfPageFormat.a4;

    final navy = PdfColor.fromInt(0xff102a43);
    final navyDeep = PdfColor.fromInt(0xff0b2238);
    final cream = PdfColor.fromInt(0xfff7f1e8);
    final creamRaised = PdfColor.fromInt(0xfffffbf5);
    final rose = PdfColor.fromInt(0xffc8956c);
    final ink = PdfColor.fromInt(0xff263238);
    final muted = PdfColor.fromInt(0xff6d7478);
    final contour = PdfColor.fromInt(0xffddd4c8);
    final white = PdfColors.white;

    String reshapeArabic(String value) =>
        ArabicReshaper.instance.reshape(value);

    pw.Widget arabicText(
      String value, {
      double fontSize = 8,
      bool bold = false,
      PdfColor? color,
      pw.TextAlign align = pw.TextAlign.right,
    }) {
      return pw.Directionality(
        textDirection: pw.TextDirection.rtl,
        child: pw.Text(
          reshapeArabic(value),
          textDirection: pw.TextDirection.rtl,
          textAlign: align,
          style: pw.TextStyle(
            font: bold ? medium : regular,
            fontSize: fontSize,
            color: color ?? ink,
          ),
        ),
      );
    }

    pw.Widget cellText(
      String value, {
      required bool header,
      PdfColor? color,
    }) {
      final rtl = _containsArabic(value);
      final rendered = rtl ? reshapeArabic(value) : value;
      return pw.Directionality(
        textDirection: rtl ? pw.TextDirection.rtl : pw.TextDirection.ltr,
        child: pw.Text(
          rendered,
          textDirection: rtl ? pw.TextDirection.rtl : pw.TextDirection.ltr,
          textAlign: rtl ? pw.TextAlign.right : pw.TextAlign.left,
          maxLines: 4,
          style: pw.TextStyle(
            font: header ? medium : regular,
            fontSize: header ? 7.1 : 6.7,
            color: color ?? ink,
          ),
        ),
      );
    }

    final visualIndexes = List<int>.generate(data.columns.length, (i) => i)
        .reversed
        .toList(growable: false);
    final widths = <int, pw.TableColumnWidth>{
      for (var i = 0; i < visualIndexes.length; i++)
        i: const pw.FlexColumnWidth(1),
    };

    document.addPage(
      pw.MultiPage(
        pageFormat: pageFormat,
        margin: const pw.EdgeInsets.fromLTRB(24, 22, 24, 24),
        theme: pw.ThemeData.withFont(base: regular, bold: medium),
        header: (context) => pw.Container(
          margin: const pw.EdgeInsets.only(bottom: 13),
          decoration: pw.BoxDecoration(
            color: navyDeep,
            borderRadius: pw.BorderRadius.circular(9),
          ),
          child: pw.Stack(
            children: <pw.Widget>[
              pw.Positioned(
                left: 0,
                top: 0,
                bottom: 0,
                child: pw.Container(width: 7, color: rose),
              ),
              pw.Padding(
                padding: const pw.EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 14,
                ),
                child: pw.Row(
                  crossAxisAlignment: pw.CrossAxisAlignment.center,
                  children: <pw.Widget>[
                    pw.Expanded(
                      child: pw.Column(
                        crossAxisAlignment: pw.CrossAxisAlignment.end,
                        children: <pw.Widget>[
                          arabicText(
                            data.type.arabicTitle,
                            fontSize: 15,
                            bold: true,
                            color: white,
                          ),
                          pw.SizedBox(height: 3),
                          arabicText(
                            'نظام إدارة العقود والمستحقات',
                            fontSize: 8,
                            color: PdfColor.fromInt(0xffe5ddd3),
                          ),
                        ],
                      ),
                    ),
                    pw.SizedBox(width: 15),
                    pw.Container(
                      width: 58,
                      height: 46,
                      padding: const pw.EdgeInsets.all(5),
                      decoration: pw.BoxDecoration(
                        color: creamRaised,
                        borderRadius: pw.BorderRadius.circular(7),
                      ),
                      child: pw.Image(brand, fit: pw.BoxFit.contain),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
        footer: (context) => pw.Container(
          margin: const pw.EdgeInsets.only(top: 10),
          padding: const pw.EdgeInsets.only(top: 7),
          decoration: pw.BoxDecoration(
            border: pw.Border(top: pw.BorderSide(color: contour, width: 0.5)),
          ),
          child: pw.Row(
            children: <pw.Widget>[
              pw.Text(
                'ALKENZY ADV  •  Safe Contracts',
                style: pw.TextStyle(font: medium, fontSize: 6.5, color: navy),
              ),
              pw.Spacer(),
              arabicText(
                'صفحة ${context.pageNumber} من ${context.pagesCount}',
                fontSize: 6.5,
                bold: true,
                color: muted,
              ),
            ],
          ),
        ),
        build: (context) => <pw.Widget>[
          pw.Container(
            padding: const pw.EdgeInsets.symmetric(horizontal: 13, vertical: 9),
            decoration: pw.BoxDecoration(
              color: cream,
              borderRadius: pw.BorderRadius.circular(7),
              border: pw.Border.all(color: contour, width: 0.5),
            ),
            child: pw.Row(
              children: <pw.Widget>[
                arabicText(
                  'تاريخ الإصدار: $generatedDate',
                  fontSize: 7,
                  color: muted,
                ),
                pw.Spacer(),
                arabicText(
                  'عدد السجلات: ${data.rows.length}',
                  fontSize: 7,
                  bold: true,
                  color: navy,
                ),
              ],
            ),
          ),
          pw.SizedBox(height: 11),
          pw.Table(
            columnWidths: widths,
            border: pw.TableBorder.all(color: contour, width: 0.45),
            children: <pw.TableRow>[
              pw.TableRow(
                decoration: pw.BoxDecoration(color: navy),
                children: visualIndexes
                    .map(
                      (index) => pw.Padding(
                        padding: const pw.EdgeInsets.symmetric(
                          horizontal: 5,
                          vertical: 7,
                        ),
                        child: cellText(
                          _arabicColumns[data.columns[index]] ??
                              data.columns[index],
                          header: true,
                          color: white,
                        ),
                      ),
                    )
                    .toList(growable: false),
              ),
              ...limitedRows.indexed.map(
                (entry) => pw.TableRow(
                  decoration: pw.BoxDecoration(
                    color: entry.$1.isEven ? creamRaised : cream,
                  ),
                  children: visualIndexes
                      .map(
                        (index) => pw.Padding(
                          padding: const pw.EdgeInsets.symmetric(
                            horizontal: 5,
                            vertical: 6,
                          ),
                          child: cellText(
                            index < entry.$2.length ? entry.$2[index] : '',
                            header: false,
                          ),
                        ),
                      )
                      .toList(growable: false),
                ),
              ),
            ],
          ),
          if (data.rows.length > limitedRows.length) ...<pw.Widget>[
            pw.SizedBox(height: 9),
            pw.Container(
              width: double.infinity,
              padding: const pw.EdgeInsets.all(8),
              decoration: pw.BoxDecoration(
                color: cream,
                borderRadius: pw.BorderRadius.circular(6),
              ),
              child: arabicText(
                'تم عرض أول ${limitedRows.length} سجلًا من إجمالي ${data.rows.length} سجلًا في ملف PDF.',
                fontSize: 7,
                color: muted,
              ),
            ),
          ],
        ],
      ),
    );
    return document.save();
  }

  bool _containsArabic(String value) => RegExp(
        r'[\u0600-\u06FF\u0750-\u077F\u08A0-\u08FF]',
      ).hasMatch(value);

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
