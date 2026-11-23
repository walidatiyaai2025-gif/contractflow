import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/update/mobile_force_update.dart';

const minimumPolicy = AndroidUpdatePolicy(
  enabled: true,
  minBuild: 36,
  latestBuild: 36,
  messageAr: 'يجب تحديث التطبيق',
  messageEn: 'Please update',
);

void main() {
  test('only versionCode below server minimum is blocked', () {
    expect(minimumPolicy.blocks(34), isTrue);
    expect(minimumPolicy.blocks(35), isTrue);
    expect(minimumPolicy.blocks(36), isFalse);
    expect(minimumPolicy.blocks(37), isFalse);
    expect(minimumPolicy.blocks(0), isFalse);
    expect(const AndroidUpdatePolicy(
      enabled: false,
      minBuild: 36,
      latestBuild: 36,
      messageAr: '',
      messageEn: '',
    ).blocks(35), isFalse);
  });

  test('invalid response or downgrade cannot force-update users', () {
    expect(AndroidUpdatePolicy.fromData({
      'schema_version': 1,
      'platform': 'android',
      'enabled': true,
      'min_build': 36,
      'latest_build': 36,
      'message_ar': '',
      'message_en': '',
    }).blocks(35), isTrue);
    expect(AndroidUpdatePolicy.fromData({
      'schema_version': 1,
      'platform': 'android',
      'enabled': true,
      'min_build': 100,
      'latest_build': 36,
      'message_ar': '',
      'message_en': '',
    }).blocks(35), isFalse);
    expect(
      () => AndroidUpdatePolicy.fromData({
        'schema_version': 1,
        'platform': 'android',
        'enabled': true,
        'min_build': 'not-an-int',
        'latest_build': 36,
        'message_ar': '',
        'message_en': '',
      }),
      throwsFormatException,
    );
  });

  test('unavailable pre-login endpoint fails open but retains known block',
      () async {
    var fail = false;
    final gate = MobileForceUpdateController(
      fetchPolicy: () async {
        if (fail) throw Exception('offline');
        return minimumPolicy;
      },
      installedBuild: () async => 35,
    );
    await gate.refresh();
    expect(gate.state, MobileUpdateState.required);
    fail = true;
    await gate.refresh();
    expect(gate.state, MobileUpdateState.required);
    gate.dispose();

    final offline = MobileForceUpdateController(
      fetchPolicy: () async => throw Exception('endpoint not installed'),
      installedBuild: () async => 35,
    );
    await offline.refresh();
    expect(offline.state, MobileUpdateState.allowed);
    offline.dispose();
  });

  testWidgets('mandatory update blocks the view before login without dismissal',
      (tester) async {
    final gate = MobileForceUpdateController(
      fetchPolicy: () async => minimumPolicy,
      installedBuild: () async => 35,
    );
    await gate.refresh();
    await tester.pumpWidget(MaterialApp(
      home: MobileForceUpdateGate(
        controller: gate,
        languageCode: 'ar',
        child: const Scaffold(body: Text('Sign in')),
      ),
    ));
    expect(find.text('تحديث التطبيق مطلوب'), findsOneWidget);
    expect(find.text('التحديث من Google Play'), findsOneWidget);
    expect(find.text('Sign in'), findsNothing);
    expect(find.text('تخطي'), findsNothing);
    await tester.pumpWidget(const SizedBox.shrink());
    gate.dispose();
  });

  test('Android edge-to-edge supports Android 15/16 and keeps safe footer',
      () {
    final native = File('android-release/MainActivity.kt').readAsStringSync();
    final gradle = File('android-release/app-build.gradle.kts')
        .readAsStringSync();
    final footer = File('lib/features/navigation/app_shell.dart')
        .readAsStringSync();
    expect(native, contains('enableEdgeToEdge()'));
    expect(native, contains('LAYOUT_IN_DISPLAY_CUTOUT_MODE_ALWAYS'));
    expect(native, isNot(contains('LAYOUT_IN_DISPLAY_CUTOUT_MODE_SHORT_EDGES')));
    expect(gradle, contains('androidx.activity:activity-ktx:1.10.1'));
    expect(footer, contains('SafeArea('));
    expect(File('pubspec.yaml').readAsStringSync(),
        contains('version: 0.3.35+36'));
  });

  test('force update policy uses fixed official Play store listing', () {
    expect(alkenzyPlayUrl, contains('com.safecontracts.safecontracts_mobile'));
    final app = File('lib/app.dart').readAsStringSync();
    expect(app, contains('MobileForceUpdateGate('));
    expect(app, contains('AppEnvironmentName.production'));
    final bridge = File('../wordpress-plugin/alkenzy-force-update/alkenzy-force-update.php')
        .readAsStringSync();
    expect(bridge, contains('/mobile-version'));
    expect(bridge, contains("'enabled' => false"));
  });
}
