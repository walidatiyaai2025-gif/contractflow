import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('crash rescue is a strictly newer Play update from pre-R8 build 34', () {
    final spec = File('pubspec.yaml').readAsStringSync();
    expect(spec, contains('version: 0.3.37+37'));
    expect(spec, isNot(contains('version: 0.3.34+35')));
    expect(spec, isNot(contains('version: 0.3.35+36')));
  });

  test('emergency release restores proven pre-R8 Gradle behavior', () {
    final gradle = File('android-release/app-build.gradle.kts')
        .readAsStringSync();
    expect(gradle, contains('isMinifyEnabled = false'));
    expect(gradle, contains('isShrinkResources = false'));
    expect(gradle, isNot(contains('isMinifyEnabled = true')));
    expect(gradle, isNot(contains('isShrinkResources = true')));
    expect(gradle, isNot(contains('proguard-android-optimize.txt')));
    expect(gradle, contains(
        'applicationId = "com.safecontracts.safecontracts_mobile"'));
  });

  test('emergency rescue retains production login and AdMob configuration', () {
    final main = File('lib/main.dart').readAsStringSync();
    final ads = File('lib/features/ads/alkenzy_ad_units.dart')
        .readAsStringSync();
    final implementation = File('lib/features/ads/mobile_ads.dart')
        .readAsStringSync();
    expect(main, contains('runApp(SafeContractsApp('));
    expect(main, isNot(contains('SafeContractsAdsHost(')));
    expect(ads, contains('ca-app-pub-3218037275900725/1295000750'));
    expect(ads, contains('ca-app-pub-3218037275900725/6129806290'));
    expect(implementation, contains("'SC_ALKENZY_ADS_ENABLED'"));
    expect(implementation, contains("'SC_ALKENZY_ADS_TEST_MODE'"));
    expect(implementation, contains('defaultValue: false'));
  });

  test('rescue excludes the untested edge and force-update startup changes', () {
    final activity =
        File('android-release/MainActivity.kt').readAsStringSync();
    final app = File('lib/app.dart').readAsStringSync();
    expect(activity, isNot(contains('enableEdgeToEdge()')));
    expect(app, isNot(contains('MobileForceUpdateGate(')));
    expect(File('lib/features/update/mobile_force_update.dart').existsSync(),
        isFalse);
  });
}
