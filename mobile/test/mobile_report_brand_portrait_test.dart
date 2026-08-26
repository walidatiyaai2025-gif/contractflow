import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('PDF reports keep ALKENZY identity in portrait without losing RTL', () {
    final report =
        File('lib/features/export/mobile_report_export.dart').readAsStringSync();

    expect(report, contains("rootBundle.load('assets/brand/alkenzy_adv.png')"));
    expect(report, contains('pw.MemoryImage('));
    expect(report, contains("'ALKENZY ADV'"));
    expect(report, contains('pageFormat: PdfPageFormat.a4,'));
    expect(report, isNot(contains('PdfPageFormat.a4.landscape')));
    expect(report, contains('Generated: \$generatedDate'));
    expect(report, contains('ArabicReshaper.instance.reshape'));
    expect(report, contains('pw.TextDirection.rtl'));
  });
}
