import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/runtime_translation_overrides.dart';
import '../ads/mobile_ads_config.dart';

final class MobileFeatureFlags {
  const MobileFeatureFlags({
    required this.excelExport,
    required this.pushNotifications,
    required this.collectionEntry,
  });

  const MobileFeatureFlags.defaults()
      : excelExport = false,
        pushNotifications = false,
        collectionEntry = false;

  final bool excelExport;
  final bool pushNotifications;
  final bool collectionEntry;
}

final class MobileCurrencyConfig {
  const MobileCurrencyConfig({required this.code, required this.symbol});

  const MobileCurrencyConfig.defaults()
      : code = '',
        symbol = '';

  final String code;
  final String symbol;

  String get displayToken => symbol.isNotEmpty ? symbol : code;

  factory MobileCurrencyConfig.fromData(Object? value) {
    final data = _optionalObjectMap(value, 'mobile_config.currency');
    return MobileCurrencyConfig(
      code: _currencyCode(data['code']),
      symbol: _currencySymbol(data['symbol']),
    );
  }
}

final class MobileStoreLinks {
  const MobileStoreLinks({
    required this.privacyPolicy,
    required this.terms,
    required this.accountDeletion,
    required this.support,
  });

  const MobileStoreLinks.defaults()
      : privacyPolicy = '',
        terms = '',
        accountDeletion = '',
        support = '';

  final String privacyPolicy;
  final String terms;
  final String accountDeletion;
  final String support;

  bool get hasAny =>
      privacyPolicy.isNotEmpty ||
      terms.isNotEmpty ||
      accountDeletion.isNotEmpty ||
      support.isNotEmpty;

  factory MobileStoreLinks.fromData(Object? value) {
    final data = _optionalObjectMap(value, 'mobile_config.store_links');
    return MobileStoreLinks(
      privacyPolicy: _publicHttpUrl(data['privacy_policy']),
      terms: _publicHttpUrl(data['terms']),
      accountDeletion: _publicHttpUrl(data['account_deletion']),
      support: _publicHttpUrl(data['support']),
    );
  }
}

final class SafeContractsMobileConfig {
  const SafeContractsMobileConfig({
    required this.supportText,
    required this.defaultPageSize,
    this.currency = const MobileCurrencyConfig.defaults(),
    required this.features,
    this.ads = const MobileAdvertisingConfig.defaults(),
    this.storeLinks = const MobileStoreLinks.defaults(),
    this.translationOverrides = const {
      'en': <String, String>{},
      'ar': <String, String>{},
    },
  });

  const SafeContractsMobileConfig.defaults()
      : supportText = '',
        defaultPageSize = 25,
        currency = const MobileCurrencyConfig.defaults(),
        features = const MobileFeatureFlags.defaults(),
        ads = const MobileAdvertisingConfig.defaults(),
        storeLinks = const MobileStoreLinks.defaults(),
        translationOverrides = const {
          'en': <String, String>{},
          'ar': <String, String>{},
        };

  static const maxSupportTextLength = 500;

  final String supportText;
  final int defaultPageSize;
  final MobileCurrencyConfig currency;
  final MobileFeatureFlags features;
  final MobileAdvertisingConfig ads;
  final MobileStoreLinks storeLinks;
  final Map<String, Map<String, String>> translationOverrides;

  factory SafeContractsMobileConfig.fromData(Object? value) {
    final data = apiObjectMap(value, 'mobile_config.data');
    final features =
        _optionalObjectMap(data['features'], 'mobile_config.features');
    final configuredPageSize = _pageSize(data['default_page_size']);
    final translationData = _optionalObjectMap(
      data['translation_overrides'],
      'mobile_config.translation_overrides',
    );

    return SafeContractsMobileConfig(
      supportText: _supportText(data['support_text']),
      defaultPageSize: configuredPageSize.clamp(10, 100).toInt(),
      currency: MobileCurrencyConfig.fromData(data['currency']),
      features: MobileFeatureFlags(
        excelExport: features['excel_export'] == true,
        pushNotifications: features['push_notifications'] == true,
        collectionEntry: features['collection_entry'] == true,
      ),
      ads: MobileAdvertisingConfig.fromData(data['ads']),
      storeLinks: MobileStoreLinks.fromData(data['store_links']),
      translationOverrides: <String, Map<String, String>>{
        'en': SafeContractsRuntimeTranslations.parseLanguage(
          translationData['en'],
        ),
        'ar': SafeContractsRuntimeTranslations.parseLanguage(
          translationData['ar'],
        ),
      },
    );
  }
}

enum MobileConfigState { idle, loading, ready, error }

final class MobileConfigController extends ChangeNotifier {
  MobileConfigController(this.client);

  final SafeContractsApiClient client;

  MobileConfigState state = MobileConfigState.idle;
  SafeContractsMobileConfig config = const SafeContractsMobileConfig.defaults();
  String? errorMessage;

  Future<void> load() async {
    state = MobileConfigState.loading;
    errorMessage = null;
    notifyListeners();

    try {
      final envelope = await client.get('mobile-config');
      config = SafeContractsMobileConfig.fromData(envelope.data);
      SafeContractsRuntimeTranslations.replace(config.translationOverrides);
      state = MobileConfigState.ready;
    } on Object catch (error) {
      config = const SafeContractsMobileConfig.defaults();
      SafeContractsRuntimeTranslations.clear();
      errorMessage = error.toString();
      state = MobileConfigState.error;
    }
    notifyListeners();
  }
}

Map<String, Object?> _optionalObjectMap(Object? value, String field) {
  if (value == null) {
    return const <String, Object?>{};
  }
  try {
    return apiObjectMap(value, field);
  } on FormatException {
    return const <String, Object?>{};
  }
}

String _supportText(Object? value) {
  if (value is! String) {
    return '';
  }
  final normalized = value.trim();
  if (normalized.length > SafeContractsMobileConfig.maxSupportTextLength) {
    return '';
  }
  return normalized;
}

String _currencyCode(Object? value) {
  if (value is! String) return '';
  final normalized = value.trim().toUpperCase();
  return RegExp(r'^[A-Z]{3}$').hasMatch(normalized) ? normalized : '';
}

String _currencySymbol(Object? value) {
  if (value is! String) return '';
  final normalized = value.trim();
  if (normalized.length > 16 || normalized.contains(RegExp(r'[\r\n\x00]'))) {
    return '';
  }
  return normalized;
}

String _publicHttpUrl(Object? value) {
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

int _pageSize(Object? value) {
  if (value is int && value >= 10 && value <= 200) {
    return value;
  }
  if (value is String) {
    final parsed = int.tryParse(value);
    if (parsed != null && parsed >= 10 && parsed <= 200) {
      return parsed;
    }
  }
  return 25;
}
