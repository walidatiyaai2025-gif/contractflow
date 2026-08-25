import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class MobileTokenStore {
  Future<String?> read();
  Future<void> write(String token, {bool persistent = true});
  Future<void> clear();
}

final class SecureMobileTokenStore implements MobileTokenStore {
  SecureMobileTokenStore({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const storageKey = 'safecontracts.mobile.bearer_token';
  final FlutterSecureStorage _storage;
  String? _sessionValue;
  bool _persistentUnlocked = false;

  bool get persistentUnlocked => _persistentUnlocked;

  Future<String?> readPersistent() async {
    final value = await _storage.read(key: storageKey);
    final token = value?.trim();
    return token == null || token.isEmpty ? null : token;
  }

  Future<bool> hasPersistentToken() async => (await readPersistent()) != null;

  Future<void> persistCurrentForBiometric() async {
    final token = _sessionValue?.trim();
    if (token == null || token.isEmpty) {
      throw const StateError(
          'No authenticated session is available to secure.');
    }
    await _storage.write(key: storageKey, value: token);
    _persistentUnlocked = true;
  }

  void unlockPersistent() {
    _persistentUnlocked = true;
  }

  void lockPersistent() {
    _persistentUnlocked = false;
    _sessionValue = null;
  }

  @override
  Future<String?> read() async {
    final session = _sessionValue?.trim();
    if (session != null && session.isNotEmpty) {
      return session;
    }
    if (!_persistentUnlocked) return null;
    return readPersistent();
  }

  @override
  Future<void> write(String token, {bool persistent = true}) async {
    final normalized = token.trim();
    if (!RegExp(r'^scm_[A-Za-z0-9_-]{43}$').hasMatch(normalized)) {
      throw const FormatException('SafeContracts mobile token is invalid.');
    }
    _sessionValue = normalized;
    _persistentUnlocked = true;
    if (persistent) {
      await _storage.write(key: storageKey, value: normalized);
    } else {
      await _storage.delete(key: storageKey);
    }
  }

  @override
  Future<void> clear() async {
    _sessionValue = null;
    _persistentUnlocked = false;
    await _storage.delete(key: storageKey);
  }
}

final class MemoryMobileTokenStore implements MobileTokenStore {
  MemoryMobileTokenStore([this._value]);

  String? _value;
  bool? lastPersistentWrite;

  @override
  Future<String?> read() async => _value;

  @override
  Future<void> write(String token, {bool persistent = true}) async {
    _value = token;
    lastPersistentWrite = persistent;
  }

  @override
  Future<void> clear() async {
    _value = null;
  }
}
