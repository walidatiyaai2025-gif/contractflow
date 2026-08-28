import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('PDF reports keep ALKENZY identity and premium Arabic RTL layout', () {
    final report = File('lib/features/export/mobile_report_export.dart')
        .readAsStringSync();

    expect(report, contains("rootBundle.load('assets/brand/alkenzy_adv.png')"));
    expect(report, contains('pw.MemoryImage('));
    expect(report, contains('ALKENZY ADV'));
    expect(report, contains('PdfPageFormat.a4'));
    expect(report, contains('PdfPageFormat.a4.landscape'));
    expect(report, contains('تاريخ الإصدار: \$generatedDate'));
    expect(report, isNot(contains('Generated: \$generatedDate')));
    expect(report, contains('ArabicReshaper.instance.reshape'));
    expect(report, contains('pw.TextDirection.rtl'));
    expect(report, contains('data.type.arabicTitle'));
    expect(report, contains('نظام إدارة العقود والمستحقات'));
    expect(report, contains('PdfColor.fromInt(0xff102a43)'));
    expect(report, contains('PdfColor.fromInt(0xffc8956c)'));
  });
}
