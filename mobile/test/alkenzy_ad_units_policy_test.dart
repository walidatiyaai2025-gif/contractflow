import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ads/alkenzy_ad_placements.dart';
import 'package:safecontracts_mobile/features/ads/alkenzy_ad_units.dart';

void main() {
  test('owner-authorized guest landing banner plus seven existing placements have exact IDs', () {
    expect(AlkenzyAdUnits.banners, {
      AlkenzyBannerSlot.landingSlider: 'ca-app-pub-3218037275900725/8818395498',
      AlkenzyBannerSlot.homeFinancial:
          'ca-app-pub-3218037275900725/1295000750',
      AlkenzyBannerSlot.homeActivity:
          'ca-app-pub-3218037275900725/2203711564',
      AlkenzyBannerSlot.contractsList:
          'ca-app-pub-3218037275900725/8512427056',
      AlkenzyBannerSlot.contractDetails:
          'ca-app-pub-3218037275900725/3865323776',
      AlkenzyBannerSlot.customersList:
          'ca-app-pub-3218037275900725/3882916907',
      AlkenzyBannerSlot.customersDetail:
          'ca-app-pub-3218037275900725/6979853539',
      AlkenzyBannerSlot.suppliersList:
          'ca-app-pub-3218037275900725/5666771860',
    });
    expect(AlkenzyAdUnits.banners.values.toSet().length, 8);
    expect(AlkenzyAdUnits.banners[AlkenzyBannerSlot.landingSlider], 'ca-app-pub-3218037275900725/8818395498');
    expect(AlkenzyAdUnits.banners.values.where((id) => id == 'ca-app-pub-3218037275900725/8818395498').length, 1);
  });

  test('guest landing banner is directly below slider, before headline', () {
    final source =
        File('lib/features/welcome/company_welcome_screen.dart').readAsStringSync();
    final slider = source.indexOf('_LandingImageGallery(', source.indexOf('Widget build'));
    final placement = source.indexOf("slot: AlkenzyBannerSlot.landingSlider");
    final headline = source.indexOf('Text.rich(', placement);
    expect(slider, greaterThan(-1));
    expect(placement, greaterThan(slider));
    expect(headline, greaterThan(placement));
    expect(source, contains("key: Key('alkenzyLandingSliderBanner')"));
    expect(AlkenzyAdUnits.banner(
      AlkenzyBannerSlot.landingSlider, testMode: true),
      AlkenzyAdUnits.testBanner);
    expect(AlkenzyAdUnits.banner(
      AlkenzyBannerSlot.landingSlider, testMode: false),
      'ca-app-pub-3218037275900725/8818395498');
  });

  test('test mode always uses Google official test inventory', () {
    for (final slot in AlkenzyBannerSlot.values) {
      expect(AlkenzyAdUnits.banner(slot, testMode: true),
          AlkenzyAdUnits.testBanner);
    }
    expect(AlkenzyAdUnits.interstitial(reports: true, testMode: true),
        AlkenzyAdUnits.testInterstitial);
    expect(AlkenzyAdUnits.interstitial(reports: false, testMode: true),
        AlkenzyAdUnits.testInterstitial);
    expect(AlkenzyAdUnits.appOpenUnit(testMode: true),
        AlkenzyAdUnits.testAppOpen);
  });

  test('owner in-app placements never inherit WordPress provider or test mode', () {
    final source = File('lib/features/ads/mobile_ads.dart').readAsStringSync();
    final guardStart = source.indexOf('bool get canUseGooglePlacements =>');
    final guardEnd = source.indexOf('/// Shared UMP consent', guardStart);
    expect(guardStart, greaterThan(-1));
    expect(guardEnd, greaterThan(guardStart));
    final guard = source.substring(guardStart, guardEnd);
    expect(guard, contains('_ownedUnitsEnabled'));
    expect(guard, isNot(contains('_config.enabled')));
    expect(guard, isNot(contains('_config.provider')));
    expect(source, contains("'SC_ALKENZY_ADS_TEST_MODE'"));
    expect(source, contains('defaultValue: false'));
    expect(source, contains('showPrivacyOptions()'));
  });

  test('full-screen formats have the owner IDs and no dashboard-only unit', () {
    expect(AlkenzyAdUnits.appId,
        'ca-app-pub-3218037275900725~7401372044');
    expect(AlkenzyAdUnits.appOpen,
        'ca-app-pub-3218037275900725/6129806290');
    expect(AlkenzyAdUnits.interstitialMain,
        'ca-app-pub-3218037275900725/4638303214');
    expect(AlkenzyAdUnits.interstitialReports,
        'ca-app-pub-3218037275900725/9633937038');
    final source = File('lib/features/ads/alkenzy_ad_placements.dart')
        .readAsStringSync();
    expect(source, contains('_minInterstitialGap = Duration(minutes: 5)'));
    expect(source, contains('_naturalActions < 3'));
    expect(source, isNot(contains('_interstitialsShown')));
    expect(source, contains('_minAppOpenGap = Duration(minutes: 20)'));
    expect(source, contains('now.difference(_appStartedAt) < _minInterstitialGap'));
    expect(source, isNot(contains('Timer.periodic(')));
  });

  testWidgets('placement does not reserve space or load ad with ads disabled',
      (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: AlkenzyInlineBanner(slot: AlkenzyBannerSlot.homeFinancial),
        ),
      ),
    );
    expect(find.byType(AlkenzyInlineBanner), findsOneWidget);
    expect(find.byType(SizedBox), findsWidgets);
    await tester.pump();
    expect(tester.takeException(), isNull);
  });

  test('ad placements do not wrap the authentication root', () {
    final main = File('lib/main.dart').readAsStringSync();
    expect(main, contains('runApp(SafeContractsApp('));
    expect(main, isNot(contains('SafeContractsAdsHost(')));
    final source = File('lib/features/navigation/app_shell.dart')
        .readAsStringSync();
    expect(source, contains('showAppOpenOnForeground()'));
    final app = File('lib/app.dart').readAsStringSync();
    expect(app, contains('onGuestLanding'));
    expect(app, contains('SessionState.unauthenticated'));
    expect(app, contains('AlkenzyRewardedFun.instance.busy'));
    final landing = File('lib/features/welcome/company_welcome_screen.dart')
        .readAsStringSync();
    expect(landing, contains('AlkenzyFullScreenAds.instance.prepareAppOpen()'));
    expect(source, contains('ModalRoute.of(context)?.isCurrent == true'));
  });
}
