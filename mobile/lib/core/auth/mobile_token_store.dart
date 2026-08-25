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

  Future<String?> readPersistent() async {
    final value = await _storage.read(key: storageKey);
    final token = value?.trim();
    return token == null || token.isEmpty ? null : token;
  }

  Future<bool> hasPersistentToken() async => (await readPersistent()) != null;

  @override
  Future<String?> read() async {
    final session = _sessionValue?.trim();
    if (session != null && session.isNotEmpty) {
      return session;
    }
    return readPersistent();
  }

  @override
  Future<void> write(String token, {bool persistent = true}) async {
    final normalized = token.trim();
    if (!RegExp(r'^scm_[A-Za-z0-9_-]{43}$').hasMatch(normalized)) {
      throw const FormatException('SafeContracts mobile token is invalid.');
    }
    _sessionValue = normalized;
    if (persistent) {
      await _storage.write(key: storageKey, value: normalized);
    } else {
      await _storage.delete(key: storageKey);
    }
  }

  @override
  Future<void> clear() async {
    _sessionValue = null;
    await _storage.delete(key: storageKey);
  }
}

/// Protects a remembered server-issued Bearer token behind a local biometric
/// gate after every cold app start. Passwords are never stored. A successful
/// username/password login unlocks only the current process and may persist the
/// opaque token when Remember me is enabled.
final class BiometricProtectedMobileTokenStore implements MobileTokenStore {
  BiometricProtectedMobileTokenStore(this._secureStore);

  final SecureMobileTokenStore _secureStore;
  bool _unlocked = false;

  bool get isUnlocked => _unlocked;

  Future<bool> hasPersistentToken() => _secureStore.hasPersistentToken();

  void unlock() {
    _unlocked = true;
  }

  void lock() {
    _unlocked = false;
  }

  @override
  Future<String?> read() async {
    if (!_unlocked) return null;
    return _secureStore.read();
  }

  @override
  Future<void> write(String token, {bool persistent = true}) async {
    await _secureStore.write(token, persistent: persistent);
    _unlocked = true;
  }

  @override
  Future<void> clear() async {
    _unlocked = false;
    await _secureStore.clear();
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
