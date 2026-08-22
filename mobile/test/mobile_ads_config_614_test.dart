import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ads/mobile_ads_config.dart';

void main() {
  test('advertising defaults are fail-closed', () {
    const config = MobileAdvertisingConfig.defaults();

    expect(config.enabled, isFalse);
    expect(config.testMode, isTrue);
    expect(config.bannerEnabled, isTrue);
    expect(config.provider, MobileAdProvider.admob);
    expect(config.adMobBannerAdUnitId, isEmpty);
    expect(config.appLovinSdkKey, isEmpty);
    expect(config.appLovinBannerAdUnitId, isEmpty);
    expect(config.canRequestBanner, isFalse);
  });

  test('AdMob test mode uses Google test inventory without production unit', () {
    final config = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': true,
      'banner_enabled': true,
      'provider': 'admob',
      'admob_banner_ad_unit_id': '',
    });

    expect(config.provider, MobileAdProvider.admob);
    expect(config.canRequestBanner, isTrue);
    expect(
      config.effectiveAndroidAdMobBannerAdUnitId,
      MobileAdvertisingConfig.androidTestBannerAdUnitId,
    );
  });

  test('AdMob production mode requires a well-formed banner unit id', () {
    final invalid = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': false,
      'banner_enabled': true,
      'provider': 'admob',
      'admob_banner_ad_unit_id': 'not-an-ad-unit',
    });
    final valid = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': false,
      'banner_enabled': true,
      'provider': 'admob',
      'admob_banner_ad_unit_id': 'ca-app-pub-1234567890123456/1234567890',
    });

    expect(invalid.adMobBannerAdUnitId, isEmpty);
    expect(invalid.canRequestBanner, isFalse);
    expect(valid.canRequestBanner, isTrue);
    expect(
      valid.effectiveAndroidAdMobBannerAdUnitId,
      'ca-app-pub-1234567890123456/1234567890',
    );
  });

  test('legacy AdMob banner field remains accepted', () {
    final config = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': false,
      'banner_enabled': true,
      'banner_ad_unit_id': 'ca-app-pub-1234567890123456/1234567890',
    });

    expect(config.provider, MobileAdProvider.admob);
    expect(config.canRequestBanner, isTrue);
  });

  test('AppLovin requires SDK key and banner unit before requests', () {
    final incomplete = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': true,
      'banner_enabled': true,
      'provider': 'applovin',
      'applovin_sdk_key': '',
      'applovin_banner_ad_unit_id': 'bannerUnit1234',
    });
    final valid = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': true,
      'banner_enabled': true,
      'provider': 'applovin',
      'applovin_sdk_key':
          'sdk-key-value-long-enough-for-runtime-configuration-123456',
      'applovin_banner_ad_unit_id': 'bannerUnit1234',
      'privacy_policy_url': 'https://example.com/alkenzy-adv/privacy/',
      'terms_url': 'https://example.com/alkenzy-adv/terms/',
    });

    expect(incomplete.canRequestBanner, isFalse);
    expect(valid.provider, MobileAdProvider.applovin);
    expect(valid.canRequestBanner, isTrue);
    expect(valid.privacyPolicyUrl, contains('/privacy/'));
    expect(valid.termsUrl, contains('/terms/'));
  });

  test('missing advertising object remains disabled', () {
    final config = MobileAdvertisingConfig.fromData(null);

    expect(config.enabled, isFalse);
    expect(config.testMode, isTrue);
    expect(config.provider, MobileAdProvider.admob);
    expect(config.canRequestBanner, isFalse);
  });
}
