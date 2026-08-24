import 'dart:convert';
import 'dart:typed_data';

import 'package:archive/archive.dart';
import 'package:cross_file/cross_file.dart';
import 'package:excel/excel.dart' as xl;
import 'package:flutter/material.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/localization/safecontracts_localizations.dart';

@immutable
final class ReportGrid {
  const ReportGrid({
    required this.title,
    required this.columns,
    required this.rows,
    required this.fileStem,
    this.subtitle,
  });

  final String title;
  final String? subtitle;
  final List<String> columns;
  final List<List<String>> rows;
  final String fileStem;
}

enum ReportOutputFormat { pdf, word, excel }

Future<void> showReportOutputDialog(
  BuildContext context, {
  required ReportGrid report,
}) async {
  final ar = context.scL10n.isArabic;
  final format = await showDialog<ReportOutputFormat>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: const Icon(Icons.print_outlined),
      title: Text(ar ? 'طباعة / تصدير' : 'Print / Export'),
      content: Text(
        ar
            ? 'اختر الصيغة. سيتم إخراج السجلات المعروضة في الجدول الحالي فقط.'
            : 'Choose a format. Only the records currently shown in this grid will be output.',
      ),
      actionsAlignment: MainAxisAlignment.stretch,
      actions: [
        _FormatButton(
          icon: Icons.picture_as_pdf_outlined,
          label: 'PDF',
          onPressed: () => Navigator.pop(
            dialogContext,
            ReportOutputFormat.pdf,
          ),
        ),
        _FormatButton(
          icon: Icons.description_outlined,
          label: ar ? 'Word' : 'Word',
          onPressed: () => Navigator.pop(
            dialogContext,
            ReportOutputFormat.word,
          ),
        ),
        _FormatButton(
          icon: Icons.table_chart_outlined,
          label: 'Excel',
          onPressed: () => Navigator.pop(
            dialogContext,
            ReportOutputFormat.excel,
          ),
        ),
        TextButton(
          onPressed: () => Navigator.pop(dialogContext),
          child: Text(ar ? 'إلغاء' : 'Cancel'),
        ),
      ],
    ),
  );
  if (format == null || !context.mounted) return;

  try {
    switch (format) {
      case ReportOutputFormat.pdf:
        await _printPdf(report, ar: ar);
      case ReportOutputFormat.word:
        await _shareBytes(
          _buildDocx(report, ar: ar),
          '${report.fileStem}.docx',
          'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
          report.title,
        );
      case ReportOutputFormat.excel:
        await _shareBytes(
          _buildExcel(report),
          '${report.fileStem}.xlsx',
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          report.title,
        );
    }
  } on Object catch (error) {
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ar
              ? 'تعذر إنشاء الملف: $error'
              : 'Unable to create output: $error',
        ),
      ),
    );
  }
}

final class _FormatButton extends StatelessWidget {
  const _FormatButton({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => OutlinedButton.icon(
        onPressed: onPressed,
        icon: Icon(icon),
        label: Text(label),
      );
}

Future<void> _printPdf(ReportGrid report, {required bool ar}) async {
  final document = pw.Document();
  final regular = await PdfGoogleFonts.cairoRegular();
  final bold = await PdfGoogleFonts.cairoBold();
  final direction = ar ? pw.TextDirection.rtl : pw.TextDirection.ltr;
  document.addPage(
    pw.MultiPage(
      pageFormat: PdfPageFormat.a4.landscape,
      margin: const pw.EdgeInsets.all(22),
      textDirection: direction,
      theme: pw.ThemeData.withFont(base: regular, bold: bold),
      build: (_) => [
        pw.Text(
          report.title,
          style: pw.TextStyle(font: bold, fontSize: 18),
        ),
        if (report.subtitle != null && report.subtitle!.trim().isNotEmpty) ...[
          pw.SizedBox(height: 4),
          pw.Text(report.subtitle!, style: const pw.TextStyle(fontSize: 9)),
        ],
        pw.SizedBox(height: 12),
        pw.TableHelper.fromTextArray(
          headers: report.columns,
          data: report.rows,
          headerStyle: pw.TextStyle(font: bold, fontSize: 8),
          cellStyle: const pw.TextStyle(fontSize: 7),
          headerDecoration: const pw.BoxDecoration(color: PdfColors.grey200),
          cellAlignment: ar
              ? pw.Alignment.centerRight
              : pw.Alignment.centerLeft,
        ),
        pw.SizedBox(height: 10),
        pw.Text(
          ar ? 'عدد السجلات: ${report.rows.length}' : 'Rows: ${report.rows.length}',
          style: const pw.TextStyle(fontSize: 8),
        ),
      ],
    ),
  );
  await Printing.layoutPdf(
    name: '${report.fileStem}.pdf',
    onLayout: (_) => document.save(),
  );
}

Uint8List _buildExcel(ReportGrid report) {
  final workbook = xl.Excel.createExcel();
  final defaultSheet = workbook.getDefaultSheet();
  final safeName = _safeSheetName(report.title);
  final sheet = workbook.rename(defaultSheet ?? 'Sheet1', safeName);
  sheet.appendRow(
    report.columns.map((value) => xl.TextCellValue(value)).toList(),
  );
  for (final row in report.rows) {
    sheet.appendRow(row.map((value) => xl.TextCellValue(value)).toList());
  }
  final bytes = workbook.encode();
  if (bytes == null) throw StateError('Excel encoder returned no bytes.');
  return Uint8List.fromList(bytes);
}

String _safeSheetName(String value) {
  var result = value.replaceAll(RegExp(r'[\\/*?:\[\]]'), ' ').trim();
  if (result.isEmpty) result = 'Report';
  if (result.length > 31) result = result.substring(0, 31);
  return result;
}

Uint8List _buildDocx(ReportGrid report, {required bool ar}) {
  final archive = Archive();
  archive.addFile(ArchiveFile.string('[Content_Types].xml', '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
<Default Extension="xml" ContentType="application/xml"/>
<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>'''));
  archive.addFile(ArchiveFile.string('_rels/.rels', '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>'''));
  archive.addFile(ArchiveFile.string('word/_rels/document.xml.rels', '''<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>'''));

  final direction = ar ? '<w:bidi/>' : '';
  final buffer = StringBuffer()
    ..write('<?xml version="1.0" encoding="UTF-8" standalone="yes"?>')
    ..write('<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>')
    ..write('<w:p><w:pPr>$direction</w:pPr><w:r><w:rPr><w:b/><w:sz w:val="32"/></w:rPr><w:t>${_xml(report.title)}</w:t></w:r></w:p>');
  if (report.subtitle != null && report.subtitle!.trim().isNotEmpty) {
    buffer.write('<w:p><w:pPr>$direction</w:pPr><w:r><w:t>${_xml(report.subtitle!)}</w:t></w:r></w:p>');
  }
  buffer.write('<w:tbl><w:tblPr><w:tblBorders>'
      '<w:top w:val="single" w:sz="4" w:color="B7B7B7"/>'
      '<w:left w:val="single" w:sz="4" w:color="B7B7B7"/>'
      '<w:bottom w:val="single" w:sz="4" w:color="B7B7B7"/>'
      '<w:right w:val="single" w:sz="4" w:color="B7B7B7"/>'
      '<w:insideH w:val="single" w:sz="4" w:color="D9D9D9"/>'
      '<w:insideV w:val="single" w:sz="4" w:color="D9D9D9"/>'
      '</w:tblBorders></w:tblPr>');
  void row(List<String> values, {bool header = false}) {
    buffer.write('<w:tr>');
    for (var index = 0; index < report.columns.length; index++) {
      final value = index < values.length ? values[index] : '';
      buffer.write('<w:tc><w:p><w:pPr>$direction</w:pPr><w:r>');
      if (header) buffer.write('<w:rPr><w:b/></w:rPr>');
      buffer.write('<w:t xml:space="preserve">${_xml(value)}</w:t></w:r></w:p></w:tc>');
    }
    buffer.write('</w:tr>');
  }
  row(report.columns, header: true);
  for (final values in report.rows) {
    row(values);
  }
  buffer
    ..write('</w:tbl>')
    ..write('<w:p><w:pPr>$direction</w:pPr><w:r><w:t>${_xml(ar ? 'عدد السجلات: ${report.rows.length}' : 'Rows: ${report.rows.length}')}</w:t></w:r></w:p>')
    ..write('<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="720" w:right="720" w:bottom="720" w:left="720"/></w:sectPr>')
    ..write('</w:body></w:document>');
  archive.addFile(ArchiveFile.string('word/document.xml', buffer.toString()));
  final bytes = ZipEncoder().encode(archive);
  if (bytes == null) throw StateError('Word encoder returned no bytes.');
  return Uint8List.fromList(bytes);
}

Future<void> _shareBytes(
  Uint8List bytes,
  String fileName,
  String mimeType,
  String title,
) async {
  await SharePlus.instance.share(
    ShareParams(
      title: title,
      subject: title,
      files: [XFile.fromData(bytes, mimeType: mimeType)],
      fileNameOverrides: [fileName],
    ),
  );
}

String _xml(String value) => const HtmlEscape(HtmlEscapeMode.element).convert(value);

final class GridPrintButton extends StatelessWidget {
  const GridPrintButton({
    required this.report,
    this.busy = false,
    this.compact = false,
    super.key,
  });

  final ReportGrid report;
  final bool busy;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    if (compact) {
      return IconButton.filledTonal(
        tooltip: ar ? 'طباعة' : 'Print',
        onPressed: busy || report.rows.isEmpty
            ? null
            : () => showReportOutputDialog(context, report: report),
        icon: const Icon(Icons.print_outlined),
      );
    }
    return FilledButton.tonalIcon(
      onPressed: busy || report.rows.isEmpty
          ? null
          : () => showReportOutputDialog(context, report: report),
      icon: const Icon(Icons.print_outlined),
      label: Text(ar ? 'طباعة' : 'Print'),
    );
  }
}
