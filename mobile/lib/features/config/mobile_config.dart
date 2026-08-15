import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

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

final class SafeContractsMobileConfig {
  const SafeContractsMobileConfig({
    required this.supportText,
    required this.defaultPageSize,
    required this.features,
  });

  const SafeContractsMobileConfig.defaults()
      : supportText = '',
        defaultPageSize = 25,
        features = const MobileFeatureFlags.defaults();

  static const maxSupportTextLength = 500;

  final String supportText;
  final int defaultPageSize;
  final MobileFeatureFlags features;

  factory SafeContractsMobileConfig.fromData(Object? value) {
    final data = apiObjectMap(value, 'mobile_config.data');
    final features = _optionalObjectMap(data['features']);
    final configuredPageSize = _pageSize(data['default_page_size']);

    return SafeContractsMobileConfig(
      supportText: _supportText(data['support_text']),
      defaultPageSize: configuredPageSize.clamp(10, 100).toInt(),
      features: MobileFeatureFlags(
        excelExport: features['excel_export'] == true,
        pushNotifications: features['push_notifications'] == true,
        collectionEntry: features['collection_entry'] == true,
      ),
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
      state = MobileConfigState.ready;
    } on Object catch (error) {
      config = const SafeContractsMobileConfig.defaults();
      errorMessage = error.toString();
      state = MobileConfigState.error;
    }
    notifyListeners();
  }
}

Map<String, Object?> _optionalObjectMap(Object? value) {
  if (value == null) {
    return const <String, Object?>{};
  }
  try {
    return apiObjectMap(value, 'mobile_config.features');
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
