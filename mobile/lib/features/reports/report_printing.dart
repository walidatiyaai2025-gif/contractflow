import 'dart:convert';
import 'dart:typed_data';

import 'package:archive/archive.dart';
import 'package:cross_file/cross_file.dart';
import 'package:excel/excel.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:pdf/pdf.dart';
import 'package:pdf/widgets.dart' as pw;
import 'package:printing/printing.dart';
import 'package:share_plus/share_plus.dart';

import '../../core/localization/safecontracts_localizations.dart';

enum ReportOutput { pdf, word, excel }

@immutable
final class ReportGrid {
  const ReportGrid({
    required this.title,
    required this.columns,
    required this.rows,
    this.subtitle,
    this.fileStem,
  });

  final String title;
  final String? subtitle;
  final List<String> columns;
  final List<List<String>> rows;
  final String? fileStem;

  String get safeFileStem {
    final base = (fileStem ?? title)
        .trim()
        .replaceAll(RegExp(r'[^A-Za-z0-9_-]+'), '_')
        .replaceAll(RegExp(r'_+'), '_')
        .replaceAll(RegExp(r'^_|_$'), '');
    return base.isEmpty ? 'alkenzy_report' : base.toLowerCase();
  }
}

Future<void> showReportOutputDialog(
  BuildContext context, {
  required ReportGrid report,
}) async {
  final ar = context.scL10n.isArabic;
  final selected = await showDialog<ReportOutput>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      title: Text(ar ? 'طباعة / تصدير' : 'Print / export'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          _OutputOption(
            icon: Icons.picture_as_pdf_outlined,
            title: 'PDF',
            subtitle: ar ? 'فتح نافذة الطباعة' : 'Open the system print dialog',
            onTap: () => Navigator.pop(dialogContext, ReportOutput.pdf),
          ),
          _OutputOption(
            icon: Icons.description_outlined,
            title: 'Word',
            subtitle: ar ? 'إنشاء ملف DOCX ومشاركته' : 'Create and share a DOCX file',
            onTap: () => Navigator.pop(dialogContext, ReportOutput.word),
          ),
          _OutputOption(
            icon: Icons.grid_on_outlined,
            title: 'Excel',
            subtitle: ar ? 'إنشاء ملف XLSX ومشاركته' : 'Create and share an XLSX file',
            onTap: () => Navigator.pop(dialogContext, ReportOutput.excel),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(dialogContext),
          child: Text(ar ? 'إلغاء' : 'Cancel'),
        ),
      ],
    ),
  );
  if (selected == null || !context.mounted) return;
  try {
    switch (selected) {
      case ReportOutput.pdf:
        await Printing.layoutPdf(
          name: '${report.safeFileStem}.pdf',
          onLayout: (format) => _buildPdf(report, format, ar: ar),
        );
        break;
      case ReportOutput.word:
        final bytes = _buildDocx(report, ar: ar);
        await _shareBytes(
          bytes,
          '${report.safeFileStem}.docx',
          'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
          ar ? 'تقرير Alkenzy ADV' : 'Alkenzy ADV report',
        );
        break;
      case ReportOutput.excel:
        final bytes = _buildXlsx(report);
        await _shareBytes(
          bytes,
          '${report.safeFileStem}.xlsx',
          'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
          ar ? 'تقرير Alkenzy ADV' : 'Alkenzy ADV report',
        );
        break;
    }
  } on Object catch (error) {
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ar
              ? 'تعذر إنشاء الملف: $error'
              : 'Unable to create the report: $error',
        ),
      ),
    );
  }
}

Future<Uint8List> _buildPdf(
  ReportGrid report,
  PdfPageFormat format, {
  required bool ar,
}) async {
  final regularData = await rootBundle.load('assets/fonts/Cairo-Regular.ttf');
  final mediumData = await rootBundle.load('assets/fonts/Cairo-Medium.ttf');
  final regular = pw.Font.ttf(regularData);
  final medium = pw.Font.ttf(mediumData);
  final document = pw.Document(
    theme: pw.ThemeData.withFont(base: regular, bold: medium),
  );
  document.addPage(
    pw.MultiPage(
      pageFormat: format,
      margin: const pw.EdgeInsets.all(24),
      textDirection: ar ? pw.TextDirection.rtl : pw.TextDirection.ltr,
      build: (context) => [
        pw.Text(
          report.title,
          style: pw.TextStyle(font: medium, fontSize: 18),
        ),
        if (report.subtitle != null && report.subtitle!.trim().isNotEmpty) ...[
          pw.SizedBox(height: 4),
          pw.Text(report.subtitle!, style: const pw.TextStyle(fontSize: 9)),
        ],
        pw.SizedBox(height: 12),
        _pdfTable(report, medium: medium),
        pw.SizedBox(height: 10),
        pw.Text(
          ar
              ? 'عدد السجلات: ${report.rows.length}'
              : 'Rows: ${report.rows.length}',
          style: const pw.TextStyle(fontSize: 8),
        ),
      ],
    ),
  );
  return document.save();
}

pw.Widget _pdfTable(ReportGrid report, {required pw.Font medium}) {
  pw.Widget cell(String text, {bool header = false}) => pw.Padding(
        padding: const pw.EdgeInsets.symmetric(horizontal: 4, vertical: 5),
        child: pw.Text(
          text,
          style: pw.TextStyle(
            font: header ? medium : null,
            fontSize: header ? 8 : 7,
          ),
        ),
      );
  return pw.Table(
    border: pw.TableBorder.all(color: PdfColors.grey400, width: 0.4),
    children: [
      pw.TableRow(
        decoration: const pw.BoxDecoration(color: PdfColors.grey200),
        children: report.columns.map((value) => cell(value, header: true)).toList(),
      ),
      for (final row in report.rows)
        pw.TableRow(
          children: List.generate(
            report.columns.length,
            (index) => cell(index < row.length ? row[index] : ''),
          ),
        ),
    ],
  );
}

Uint8List _buildXlsx(ReportGrid report) {
  final excel = Excel.createExcel();
  final sheetName = 'Report';
  final sheet = excel[sheetName];
  final defaultSheet = excel.getDefaultSheet();
  if (defaultSheet != null && defaultSheet != sheetName) {
    excel.delete(defaultSheet);
  }
  sheet.appendRow(report.columns.map(TextCellValue.new).toList());
  for (final row in report.rows) {
    sheet.appendRow(
      List.generate(
        report.columns.length,
        (index) => TextCellValue(index < row.length ? row[index] : ''),
      ),
    );
  }
  for (var index = 0; index < report.columns.length; index++) {
    sheet.setColumnWidth(index, 20);
  }
  final bytes = excel.encode();
  if (bytes == null) throw StateError('Unable to encode Excel workbook.');
  return Uint8List.fromList(bytes);
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
  return ZipEncoder().encodeBytes(archive);
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

final class _OutputOption extends StatelessWidget {
  const _OutputOption({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ListTile(
        contentPadding: EdgeInsets.zero,
        leading: CircleAvatar(
          child: Icon(icon),
        ),
        title: Text(title, style: const TextStyle(fontWeight: FontWeight.w800)),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right_rounded),
        onTap: onTap,
      );
}
