final class MobileAdvertisingConfig {
  const MobileAdvertisingConfig({
    required this.enabled,
    required this.testMode,
    required this.bannerEnabled,
    required this.bannerAdUnitId,
  });

  const MobileAdvertisingConfig.defaults()
      : enabled = false,
        testMode = true,
        bannerEnabled = true,
        bannerAdUnitId = '';

  static const androidTestBannerAdUnitId =
      'ca-app-pub-3940256099942544/6300978111';

  final bool enabled;
  final bool testMode;
  final bool bannerEnabled;
  final String bannerAdUnitId;

  bool get canRequestBanner =>
      enabled &&
      bannerEnabled &&
      (testMode || _isAdMobAdUnitId(bannerAdUnitId));

  String get effectiveAndroidBannerAdUnitId =>
      testMode ? androidTestBannerAdUnitId : bannerAdUnitId;

  factory MobileAdvertisingConfig.fromData(Object? value) {
    final data = _optionalObjectMap(value);
    final configuredUnitId = _normalizedAdUnitId(data['banner_ad_unit_id']);
    return MobileAdvertisingConfig(
      enabled: data['enabled'] == true,
      testMode: data['test_mode'] != false,
      bannerEnabled: data['banner_enabled'] != false,
      bannerAdUnitId: configuredUnitId,
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

String _normalizedAdUnitId(Object? value) {
  if (value is! String) return '';
  final normalized = value.trim();
  return _isAdMobAdUnitId(normalized) ? normalized : '';
}

bool _isAdMobAdUnitId(String value) {
  return RegExp(r'^ca-app-pub-\d{16}/\d{10}$').hasMatch(value);
}
