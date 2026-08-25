import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  group('final device closure source contracts', () {
    test('biometric login is visible and remembered token is gated', () {
      final login =
          File('lib/features/auth/login_screen.dart').readAsStringSync();
      final tokenStore =
          File('lib/core/auth/mobile_token_store.dart').readAsStringSync();
      final biometric =
          File('lib/features/auth/biometric_auth.dart').readAsStringSync();

      expect(login, contains("Key('biometricLogin')"));
      expect(login, contains('الدخول بالبصمة'));
      expect(login, contains('Sign in with fingerprint'));
      expect(tokenStore, contains('if (!_persistentUnlocked) return null'));
      expect(tokenStore, contains('unlockPersistent'));
      expect(biometric, contains('biometricOnly: true'));
    });

    test('reports expose required data sets and Excel Word PDF formats', () {
      final export = File('lib/features/export/mobile_report_export.dart')
          .readAsStringSync();
      final screen = File('lib/features/export/mobile_excel_export_screen.dart')
          .readAsStringSync();

      for (final report in <String>[
        'contracts',
        'payments',
        'customers',
        'finance',
        'attachments',
        'notifications',
      ]) {
        expect(export, contains('MobileReportType.$report'));
      }
      for (final format in <String>['xlsx', 'docx', 'pdf']) {
        expect(export, contains('MobileReportFormat.$format'));
      }
      expect(screen, contains('التقارير والطباعة'));
      expect(screen, contains('Android Save As'));
      expect(screen, isNot(contains('Saved in app cache:')));
    });

    test('Android bridge uses explicit document picker instead of silent cache',
        () {
      final activity =
          File('android-release/MainActivity.kt').readAsStringSync();
      expect(activity, contains('safecontracts/files'));
      expect(activity, contains('Intent.ACTION_CREATE_DOCUMENT'));
      expect(activity, contains('FlutterFragmentActivity'));
      expect(activity, isNot(contains('safecontracts_exports')));
    });
  });
}
