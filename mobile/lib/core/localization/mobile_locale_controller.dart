import 'package:flutter/widgets.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class MobileLocaleStore {
  Future<String?> readLanguageCode();
  Future<void> writeLanguageCode(String languageCode);
}

final class SecureMobileLocaleStore implements MobileLocaleStore {
  const SecureMobileLocaleStore({this.storage = const FlutterSecureStorage()});

  static const _key = 'safecontracts_mobile_language';

  final FlutterSecureStorage storage;

  @override
  Future<String?> readLanguageCode() => storage.read(key: _key);

  @override
  Future<void> writeLanguageCode(String languageCode) =>
      storage.write(key: _key, value: languageCode);
}

final class MobileLocaleController extends ChangeNotifier {
  MobileLocaleController({
    MobileLocaleStore? store,
    String initialLanguageCode = 'en',
  })  : _store = store ?? const SecureMobileLocaleStore(),
        _locale = Locale(_normalize(initialLanguageCode));

  final MobileLocaleStore _store;
  Locale _locale;

  Locale get locale => _locale;
  String get languageCode => _locale.languageCode;

  Future<void> load() async {
    try {
      final stored = await _store.readLanguageCode();
      if (stored == null) return;
      _setLocal(_normalize(stored));
    } on Object {
      // A storage failure must never prevent the app from booting. The
      // in-memory language remains the safe constructor default.
    }
  }

  Future<void> setLanguageCode(String languageCode) async {
    final normalized = _normalize(languageCode);
    _setLocal(normalized);
    try {
      await _store.writeLanguageCode(normalized);
    } on Object {
      // Keep the user's current in-memory choice even if secure persistence
      // is temporarily unavailable.
    }
  }

  void _setLocal(String languageCode) {
    if (_locale.languageCode == languageCode) return;
    _locale = Locale(languageCode);
    notifyListeners();
  }

  static String _normalize(String languageCode) =>
      languageCode.trim().toLowerCase() == 'ar' ? 'ar' : 'en';
}
