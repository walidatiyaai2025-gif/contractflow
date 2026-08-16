final class SafeContractsRuntimeTranslations {
  SafeContractsRuntimeTranslations._();

  static const int maxEntriesPerLanguage = 1000;
  static const int maxSourceLength = 5000;
  static const int maxTranslationLength = 10000;

  static Map<String, Map<String, String>> _overrides = const {
    'en': <String, String>{},
    'ar': <String, String>{},
  };

  static void replace(Map<String, Map<String, String>> value) {
    _overrides = <String, Map<String, String>>{
      'en': Map<String, String>.unmodifiable(value['en'] ?? const {}),
      'ar': Map<String, String>.unmodifiable(value['ar'] ?? const {}),
    };
  }

  static void clear() => replace(const {
        'en': <String, String>{},
        'ar': <String, String>{},
      });

  static String? lookup(String languageCode, String source) {
    final language = languageCode.toLowerCase() == 'ar' ? 'ar' : 'en';
    final value = _overrides[language]?[source]?.trim();
    return value == null || value.isEmpty ? null : value;
  }

  static Map<String, String> parseLanguage(Object? value) {
    if (value is! Map) return const <String, String>{};
    final clean = <String, String>{};
    for (final entry in value.entries.take(maxEntriesPerLanguage)) {
      if (entry.key is! String || entry.value is! String) continue;
      final source = (entry.key as String).trim();
      final translation = (entry.value as String).trim();
      if (source.isEmpty ||
          translation.isEmpty ||
          source.length > maxSourceLength ||
          translation.length > maxTranslationLength ||
          source.contains('\u0000') ||
          translation.contains('\u0000')) {
        continue;
      }
      clean[source] = translation;
    }
    return Map<String, String>.unmodifiable(clean);
  }
}
