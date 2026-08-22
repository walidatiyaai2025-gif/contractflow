enum MobileAdProvider { admob, applovin }

final class MobileAdvertisingConfig {
  const MobileAdvertisingConfig({
    required this.enabled,
    required this.testMode,
    required this.bannerEnabled,
    required this.provider,
    required this.adMobBannerAdUnitId,
    required this.appLovinSdkKey,
    required this.appLovinBannerAdUnitId,
    required this.privacyPolicyUrl,
    required this.termsUrl,
  });

  const MobileAdvertisingConfig.defaults()
      : enabled = false,
        testMode = true,
        bannerEnabled = true,
        provider = MobileAdProvider.admob,
        adMobBannerAdUnitId = '',
        appLovinSdkKey = '',
        appLovinBannerAdUnitId = '',
        privacyPolicyUrl = '',
        termsUrl = '';

  static const androidTestBannerAdUnitId =
      'ca-app-pub-3940256099942544/6300978111';

  final bool enabled;
  final bool testMode;
  final bool bannerEnabled;
  final MobileAdProvider provider;
  final String adMobBannerAdUnitId;
  final String appLovinSdkKey;
  final String appLovinBannerAdUnitId;
  final String privacyPolicyUrl;
  final String termsUrl;

  // Backward-compatible alias for callers/tests written before provider support.
  String get bannerAdUnitId => adMobBannerAdUnitId;

  bool get canRequestBanner {
    if (!enabled || !bannerEnabled) return false;
    return switch (provider) {
      MobileAdProvider.admob => testMode || _isAdMobAdUnitId(adMobBannerAdUnitId),
      MobileAdProvider.applovin =>
        _isAppLovinToken(appLovinSdkKey, 20, 256) &&
            _isAppLovinToken(appLovinBannerAdUnitId, 8, 128),
    };
  }

  String get effectiveAndroidAdMobBannerAdUnitId =>
      testMode ? androidTestBannerAdUnitId : adMobBannerAdUnitId;

  factory MobileAdvertisingConfig.fromData(Object? value) {
    final data = _optionalObjectMap(value);
    final legacyAdMobUnit = data['admob_banner_ad_unit_id'] ??
        data['banner_ad_unit_id'];
    return MobileAdvertisingConfig(
      enabled: data['enabled'] == true,
      testMode: data['test_mode'] != false,
      bannerEnabled: data['banner_enabled'] != false,
      provider: _provider(data['provider']),
      adMobBannerAdUnitId: _normalizedAdMobUnitId(legacyAdMobUnit),
      appLovinSdkKey: _normalizedAppLovinToken(
        data['applovin_sdk_key'],
        20,
        256,
      ),
      appLovinBannerAdUnitId: _normalizedAppLovinToken(
        data['applovin_banner_ad_unit_id'],
        8,
        128,
      ),
      privacyPolicyUrl: _normalizedUrl(data['privacy_policy_url']),
      termsUrl: _normalizedUrl(data['terms_url']),
    );
  }
}

Map<String, Object?> _optionalObjectMap(Object? value) {
  if (value is Map<String, Object?>) {
    return value;
  }
  if (value is Map) {
    return value.map<String, Object?>(
      (key, item) => MapEntry(key.toString(), item),
    );
  }
  return const <String, Object?>{};
}

MobileAdProvider _provider(Object? value) {
  if (value is String && value.trim().toLowerCase() == 'applovin') {
    return MobileAdProvider.applovin;
  }
  return MobileAdProvider.admob;
}

String _normalizedAdMobUnitId(Object? value) {
  if (value is! String) return '';
  final normalized = value.trim();
  return _isAdMobAdUnitId(normalized) ? normalized : '';
}

bool _isAdMobAdUnitId(String value) {
  return RegExp(r'^ca-app-pub-\d{16}/\d{10}$').hasMatch(value);
}

String _normalizedAppLovinToken(Object? value, int min, int max) {
  if (value is! String) return '';
  final normalized = value.trim();
  return _isAppLovinToken(normalized, min, max) ? normalized : '';
}

bool _isAppLovinToken(String value, int min, int max) {
  if (value.length < min || value.length > max) return false;
  return !value.contains(RegExp(r'[\s\x00-\x1F\x7F]'));
}

String _normalizedUrl(Object? value) {
  if (value is! String) return '';
  final normalized = value.trim();
  final uri = Uri.tryParse(normalized);
  if (uri == null ||
      !(uri.scheme == 'https' || uri.scheme == 'http') ||
      uri.host.isEmpty) {
    return '';
  }
  return uri.toString();
}
