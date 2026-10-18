from pathlib import Path
import base64
import hashlib


def require_replace(text: str, old: str, new: str, label: str) -> str:
    if old in text:
        return text.replace(old, new, 1)
    if new in text:
        return text
    raise SystemExit(f"{label} marker not found")


# Materialize the owner-supplied ALKENZY report reference from the
# source-controlled optimized derivative. This is a resize/compression of the
# supplied 1436x2048 JPEG only; the artwork is not redesigned.
encoded = Path("mobile/assets/brand/report_background_480.b64")
background_path = Path("mobile/assets/brand/report_background.jpg")
raw = base64.b64decode(encoded.read_text().strip(), validate=True)
digest = hashlib.sha256(raw).hexdigest()
expected = "8435e52a54c731e2a0baaa184c9d5d1e84b1579da234d12cdd228df2d7b7b1bd"
if digest != expected:
    raise SystemExit(f"report background checksum mismatch: {digest}")
if not raw.startswith(b"\xff\xd8") or not raw.endswith(b"\xff\xd9"):
    raise SystemExit("report background is not a complete JPEG")
background_path.write_bytes(raw)

# Contracts: the backend/payment model supports customer+payable obligations.
# Mobile must accept either authoritative AP/AR direction rather than throwing
# away the whole contracts page because of counterparty type.
contracts_path = Path("mobile/lib/features/contracts/contracts.dart")
contracts = contracts_path.read_text()
marker = "const _supportedCounterpartyTypes = <String>{'customer', 'supplier'};\n"
financial_marker = "const _supportedFinancialDirections = <String>{'receivable', 'payable'};\n"
if financial_marker not in contracts:
    if marker not in contracts:
        raise SystemExit("contracts type marker not found")
    contracts = contracts.replace(marker, marker + financial_marker, 1)
old_direction = """    if ((type == 'supplier' && direction != 'payable') ||
        (type == 'customer' && direction != 'receivable')) {
      throw const FormatException(
        'contract.financial_direction conflicts with counterparty type.',
      );
    }
"""
new_direction = """    if (!_supportedFinancialDirections.contains(direction)) {
      throw const FormatException('contract.financial_direction is invalid.');
    }
"""
contracts = require_replace(
    contracts,
    old_direction,
    new_direction,
    "contract financial-direction validation",
)
contracts_path.write_text(contracts)

screen_path = Path("mobile/lib/features/contracts/contracts_screen.dart")
screen = screen_path.read_text()
old_empty = """                  : context.scL10n.t('No contracts match the current filters.'),
"""
new_empty = """                  : (page.scope == 'assigned' &&
                          controller.activeFilterCount == 0
                      ? (context.scL10n.isArabic
                          ? 'لا توجد عقود مسندة لهذا الحساب حاليًا.'
                          : 'No contracts are currently assigned to this account.')
                      : context.scL10n
                          .t('No contracts match the current filters.')),
"""
screen = require_replace(screen, old_empty, new_empty, "contracts empty-state")
screen_path.write_text(screen)

# Reports: Arabic column labels in XLSX/DOCX/PDF and the official portrait
# ALKENZY background in PDF.
report_path = Path("mobile/lib/features/export/mobile_report_export.dart")
report = report_path.read_text()
method_marker = "  Uint8List _xlsx(MobileReportData data) {\n"
if "String _reportHeaderLabel(String column)" not in report:
    helpers = """  String _reportHeaderLabel(String column) {
    const labels = <String, String>{
      'id': 'المعرف',
      'contract_number': 'رقم العقد',
      'counterparty_name': 'العميل / المورد',
      'customer_name': 'العميل',
      'supplier_name': 'المورد',
      'financial_direction': 'نوع الدفعة',
      'base_value': 'قيمة العقد',
      'original_amount': 'المبلغ',
      'paid_amount': 'المبلغ المدفوع',
      'remaining_amount': 'المبلغ المتبقي',
      'currency_code': 'العملة',
      'reference': 'المرجع',
      'due_date': 'تاريخ الاستحقاق',
      'expected_payment_date': 'تاريخ الدفع المتوقع',
      'start_date': 'تاريخ البداية',
      'end_date': 'تاريخ النهاية',
      'created_at': 'تاريخ الإنشاء',
      'scheduled_for': 'موعد الإشعار',
      'status': 'الحالة',
      'internal_code': 'الكود الداخلي',
      'name': 'الاسم',
      'contact_name': 'جهة الاتصال',
      'email': 'البريد الإلكتروني',
      'phone': 'الهاتف',
      'is_active': 'نشط',
      'obligation_count': 'عدد الالتزامات',
      'original_total': 'الإجمالي الأصلي',
      'settled_total': 'إجمالي المسدد',
      'outstanding_total': 'إجمالي المستحق',
      'overdue_total': 'إجمالي المتأخر',
      'due_today_total': 'مستحق اليوم',
      'due_30_total': 'مستحق خلال 30 يومًا',
      'media_id': 'معرف المرفق',
      'label': 'العنوان',
      'role': 'النوع',
      'mime_type': 'نوع الملف',
      'url': 'الرابط',
      'payment_id': 'معرف الدفعة',
      'template_code': 'قالب الإشعار',
      'is_read': 'مقروء',
    };
    return labels[column] ?? column;
  }

  String _reportTitle(MobileReportType type) => switch (type) {
        MobileReportType.contracts => 'تقرير العقود',
        MobileReportType.payments => 'تقرير الدفعات',
        MobileReportType.customers => 'تقرير العملاء',
        MobileReportType.finance => 'التقرير المالي',
        MobileReportType.attachments => 'تقرير المرفقات',
        MobileReportType.notifications => 'تقرير الإشعارات',
      };

"""
    if method_marker not in report:
        raise SystemExit("report xlsx marker not found")
    report = report.replace(method_marker, helpers + method_marker, 1)

report = require_replace(
    report,
    "    final allRows = <List<String>>[data.columns, ...data.rows];\n",
    "    final allRows = <List<String>>[\n      data.columns.map(_reportHeaderLabel).toList(growable: false),\n      ...data.rows,\n    ];\n",
    "xlsx report headers",
)
report = require_replace(
    report,
    "      '<w:tr>${data.columns.map((v) => wordCell(v, bold: true)).join()}</w:tr>',\n",
    "      '<w:tr>${data.columns.map(_reportHeaderLabel).map((v) => wordCell(v, bold: true)).join()}</w:tr>',\n",
    "docx report headers",
)

pdf_start = report.index("  Future<Uint8List> _pdf(MobileReportData data) async {")
pdf_end = report.index("  bool _containsArabic", pdf_start)
new_pdf = r'''  Future<Uint8List> _pdf(MobileReportData data) async {
    final regular = pw.Font.ttf(
      await rootBundle.load('assets/fonts/Cairo-Regular.ttf'),
    );
    final medium = pw.Font.ttf(
      await rootBundle.load('assets/fonts/Cairo-Medium.ttf'),
    );
    final background = pw.MemoryImage(
      (await rootBundle.load('assets/brand/report_background.jpg'))
          .buffer
          .asUint8List(),
    );
    final generatedDate = DateTime.now().toIso8601String().substring(0, 10);
    final document = pw.Document();
    final limitedRows = data.rows.take(250).toList(growable: false);
    final reportColumns =
        data.columns.map(_reportHeaderLabel).toList(growable: false);

    pw.Widget cellText(String value, {required bool header}) {
      final rtl = _containsArabic(value);
      final rendered = rtl ? ArabicReshaper.instance.reshape(value) : value;
      return pw.Directionality(
        textDirection: rtl ? pw.TextDirection.rtl : pw.TextDirection.ltr,
        child: pw.Text(
          rendered,
          textDirection: rtl ? pw.TextDirection.rtl : pw.TextDirection.ltr,
          textAlign: rtl ? pw.TextAlign.right : pw.TextAlign.left,
          style: pw.TextStyle(
            font: header ? medium : regular,
            fontSize: header ? 5.5 : 4.9,
          ),
        ),
      );
    }

    pw.Widget rtlTitle(String value) {
      return pw.Directionality(
        textDirection: pw.TextDirection.rtl,
        child: pw.Text(
          ArabicReshaper.instance.reshape(value),
          textDirection: pw.TextDirection.rtl,
          textAlign: pw.TextAlign.center,
          style: pw.TextStyle(font: medium, fontSize: 14),
        ),
      );
    }

    final widths = <int, pw.TableColumnWidth>{
      for (var i = 0; i < reportColumns.length; i++)
        i: const pw.FlexColumnWidth(1),
    };

    document.addPage(
      pw.MultiPage(
        pageTheme: pw.PageTheme(
          pageFormat: PdfPageFormat.a4,
          margin: const pw.EdgeInsets.fromLTRB(48, 165, 48, 95),
          theme: pw.ThemeData.withFont(base: regular, bold: medium),
          buildBackground: (context) => pw.FullPage(
            ignoreMargins: true,
            child: pw.Stack(
              children: <pw.Widget>[
                pw.Positioned(
                  left: 0,
                  top: 0,
                  right: 0,
                  bottom: 0,
                  child: pw.Image(background, fit: pw.BoxFit.cover),
                ),
                pw.Positioned(
                  left: 55,
                  right: 55,
                  top: 142,
                  height: 50,
                  child: pw.Container(color: PdfColor.fromInt(0xfffbfaf6)),
                ),
                pw.Positioned(
                  right: 42,
                  bottom: 72,
                  width: 330,
                  height: 25,
                  child: pw.Container(color: PdfColor.fromInt(0xfffbfaf6)),
                ),
              ],
            ),
          ),
        ),
        header: (context) => pw.Padding(
          padding: const pw.EdgeInsets.only(bottom: 12),
          child: pw.Column(
            crossAxisAlignment: pw.CrossAxisAlignment.center,
            children: <pw.Widget>[
              rtlTitle(_reportTitle(data.type)),
              pw.SizedBox(height: 3),
              pw.Text(
                generatedDate,
                style: pw.TextStyle(font: regular, fontSize: 7),
              ),
            ],
          ),
        ),
        footer: (context) => pw.Align(
          alignment: pw.Alignment.centerRight,
          child: pw.Text(
            'Generated: $generatedDate • ${context.pageNumber}/${context.pagesCount}',
            style: pw.TextStyle(font: regular, fontSize: 6),
          ),
        ),
        build: (context) => <pw.Widget>[
          pw.Container(
            color: PdfColors.white,
            padding: const pw.EdgeInsets.all(4),
            child: pw.Table(
              columnWidths: widths,
              border: pw.TableBorder.all(width: 0.3),
              children: <pw.TableRow>[
                pw.TableRow(
                  children: reportColumns
                      .map(
                        (value) => pw.Padding(
                          padding: const pw.EdgeInsets.all(2),
                          child: cellText(value, header: true),
                        ),
                      )
                      .toList(growable: false),
                ),
                ...limitedRows.map(
                  (row) => pw.TableRow(
                    children: row
                        .map(
                          (value) => pw.Padding(
                            padding: const pw.EdgeInsets.all(2),
                            child: cellText(value, header: false),
                          ),
                        )
                        .toList(growable: false),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
    return document.save();
  }

'''
report = report[:pdf_start] + new_pdf + report[pdf_end:]
report_path.write_text(report)

# Wire the existing NotificationSoundSettingsPage into production WordPress.
plugin_path = Path("wordpress-plugin/safecontracts/src/Plugin.php")
plugin = plugin_path.read_text()
plugin = require_replace(
    plugin,
    "use SafeContracts\\Admin\\NotificationSettingsPage;\n",
    "use SafeContracts\\Admin\\NotificationSettingsPage;\nuse SafeContracts\\Admin\\NotificationSoundSettingsPage;\n",
    "notification sound settings import",
) if "use SafeContracts\\Admin\\NotificationSoundSettingsPage;\n" not in plugin else plugin
plugin = require_replace(
    plugin,
    "        add_action('admin_menu', [NotificationSettingsPage::class, 'register'], 32);\n",
    "        add_action('admin_menu', [NotificationSettingsPage::class, 'register'], 32);\n        add_action('admin_menu', [NotificationSoundSettingsPage::class, 'register'], 33);\n",
    "notification sound settings menu",
) if "[NotificationSoundSettingsPage::class, 'register']" not in plugin else plugin
plugin = require_replace(
    plugin,
    "        add_action('admin_post_' . NotificationSettingsPage::SAVE_ACTION, [NotificationSettingsPage::class, 'handleSave']);\n",
    "        add_action('admin_post_' . NotificationSettingsPage::SAVE_ACTION, [NotificationSettingsPage::class, 'handleSave']);\n        add_action('admin_post_' . NotificationSoundSettingsPage::SAVE_ACTION, [NotificationSoundSettingsPage::class, 'handleSave']);\n",
    "notification sound settings save action",
) if "NotificationSoundSettingsPage::SAVE_ACTION" not in plugin else plugin
plugin_path.write_text(plugin)

# Source-level regression contracts used in addition to the full Flutter suite.
Path("mobile/test/mobile_report_brand_portrait_test.dart").write_text(
    """import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('PDF reports use the official ALKENZY portrait background and Arabic labels', () {
    final report = File('lib/features/export/mobile_report_export.dart').readAsStringSync();

    expect(report, contains(\"rootBundle.load('assets/brand/report_background.jpg')\"));
    expect(report, contains('pageTheme: pw.PageTheme('));
    expect(report, contains('pageFormat: PdfPageFormat.a4,'));
    expect(report, isNot(contains('PdfPageFormat.a4.landscape')));
    expect(report, contains('buildBackground: (context) => pw.FullPage('));
    expect(report, contains(\"'contract_number': 'رقم العقد'\"));
    expect(report, contains(\"'counterparty_name': 'العميل / المورد'\"));
    expect(report, contains(\"'financial_direction': 'نوع الدفعة'\"));
    expect(report, contains('ArabicReshaper.instance.reshape'));
    expect(report, contains('pw.TextDirection.rtl'));
  });
}
"""
)
Path("mobile/test/contracts_scope_visibility_663_test.dart").write_text(
    """import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('contracts accept valid AP/AR direction and assigned scope is explicit', () {
    final model = File('lib/features/contracts/contracts.dart').readAsStringSync();
    final screen = File('lib/features/contracts/contracts_screen.dart').readAsStringSync();

    expect(model, contains(\"const _supportedFinancialDirections = <String>{'receivable', 'payable'}\"));
    expect(model, contains('contract.financial_direction is invalid.'));
    expect(model, isNot(contains('contract.financial_direction conflicts with counterparty type.')));
    expect(screen, contains(\"page.scope == 'assigned'\"));
    expect(screen, contains('لا توجد عقود مسندة لهذا الحساب حاليًا.'));
  });
}
"""
)

checks = {
    contracts_path: ["_supportedFinancialDirections", "contract.financial_direction is invalid."],
    screen_path: ["page.scope == 'assigned'", "لا توجد عقود مسندة لهذا الحساب حاليًا."],
    report_path: ["assets/brand/report_background.jpg", "buildBackground: (context) => pw.FullPage(", "'contract_number': 'رقم العقد'"],
    plugin_path: ["NotificationSoundSettingsPage", "NotificationSoundSettingsPage::SAVE_ACTION"],
}
for path, needles in checks.items():
    value = path.read_text()
    for needle in needles:
        if needle not in value:
            raise SystemExit(f"missing closure marker {needle!r} in {path}")
