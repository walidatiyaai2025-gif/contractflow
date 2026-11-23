import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Android release enables optimized R8 and resource shrinking', () {
    final source = File('android-release/app-build.gradle.kts')
        .readAsStringSync();
    expect(source, contains('isMinifyEnabled = true'));
    expect(source, contains('isShrinkResources = true'));
    expect(source, contains('proguard-android-optimize.txt'));
    expect(source, contains('proguard-rules.pro'));
    expect(source, isNot(contains('isMinifyEnabled = false')));
    expect(source, isNot(contains('isShrinkResources = false')));
  });

  test('R8 keeps only diagnostic attributes, never disables optimization', () {
    final rules = File('android-release/proguard-rules.pro')
        .readAsStringSync();
    final directives = rules.split('\n')
        .map((line) => line.trim())
        .where((line) => line.startsWith('-'))
        .toList();
    expect(directives, contains('-keepattributes SourceFile,LineNumberTable'));
    expect(directives, isNot(contains('-dontobfuscate')));
    expect(directives, isNot(contains('-dontshrink')));
    expect(directives, isNot(contains('-dontoptimize')));
    expect(
      directives.where((line) => line.startsWith('-dontwarn ')).toList(),
      [
        '-dontwarn com.amazon.privacypass.PrivacyPass',
        '-dontwarn com.amazon.privacypass.VerificationContext',
        '-dontwarn com.amazon.privacypass.callback.AttestAPICallback',
      ],
    );
    expect(directives.any((line) => line.startsWith('-keep class **')),
        isFalse);
  });

  test('generated Android scaffold copies the same committed R8 file', () {
    final boot = File('../scripts/bootstrap_android.sh')
        .readAsStringSync();
    expect(boot, contains('PROGUARD_TEMPLATE='));
    expect(boot, contains('cp "\$PROGUARD_TEMPLATE" android/app/proguard-rules.pro'));
    expect(boot, contains('android/app/proguard-rules.pro'));
  });

  test('new release retains AdMob and stable app login root', () {
    final pubspec = File('pubspec.yaml').readAsStringSync();
    final app = File('lib/main.dart').readAsStringSync();
    final ads = File('lib/features/ads/alkenzy_ad_units.dart')
        .readAsStringSync();
    expect(pubspec, contains('version: 0.3.34+35'));
    expect(app, contains('runApp(SafeContractsApp('));
    expect(app, isNot(contains('runApp(SafeContractsAdsHost(')));
    expect(ads, contains('ca-app-pub-3218037275900725/1295000750'));
    expect(ads, contains('ca-app-pub-3218037275900725/4638303214'));
  });
}
