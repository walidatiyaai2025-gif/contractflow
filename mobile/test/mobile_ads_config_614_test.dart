import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ads/mobile_ads_config.dart';

void main() {
  test('advertising defaults are fail-closed', () {
    const config = MobileAdvertisingConfig.defaults();

    expect(config.enabled, isFalse);
    expect(config.testMode, isTrue);
    expect(config.bannerEnabled, isTrue);
    expect(config.bannerAdUnitId, isEmpty);
    expect(config.canRequestBanner, isFalse);
  });

  test('test mode uses Google test inventory without a production unit id', () {
    final config = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': true,
      'banner_enabled': true,
      'banner_ad_unit_id': '',
    });

    expect(config.canRequestBanner, isTrue);
    expect(
      config.effectiveAndroidBannerAdUnitId,
      MobileAdvertisingConfig.androidTestBannerAdUnitId,
    );
  });

  test('production mode requires a well-formed AdMob banner unit id', () {
    final invalid = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': false,
      'banner_enabled': true,
      'banner_ad_unit_id': 'not-an-ad-unit',
    });
    final valid = MobileAdvertisingConfig.fromData({
      'enabled': true,
      'test_mode': false,
      'banner_enabled': true,
      'banner_ad_unit_id': 'ca-app-pub-1234567890123456/1234567890',
    });

    expect(invalid.bannerAdUnitId, isEmpty);
    expect(invalid.canRequestBanner, isFalse);
    expect(valid.canRequestBanner, isTrue);
    expect(
      valid.effectiveAndroidBannerAdUnitId,
      'ca-app-pub-1234567890123456/1234567890',
    );
  });

  test('missing advertising object remains disabled', () {
    final config = MobileAdvertisingConfig.fromData(null);

    expect(config.enabled, isFalse);
    expect(config.testMode, isTrue);
    expect(config.canRequestBanner, isFalse);
  });
}
