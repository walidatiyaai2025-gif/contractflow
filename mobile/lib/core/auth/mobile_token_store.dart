import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class MobileTokenStore {
  Future<String?> read();
  Future<void> write(String token, {bool persistent = true});
  Future<void> clear();
}

final class SecureMobileTokenStore implements MobileTokenStore {
  SecureMobileTokenStore({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'enterprise_safecontracts.mobile.bearer_token';
  final FlutterSecureStorage _storage;
  String? _sessionValue;

  @override
  Future<String?> read() async {
    final session = _sessionValue?.trim();
    if (session != null && session.isNotEmpty) {
      return session;
    }
    final value = await _storage.read(key: _key);
    final token = value?.trim();
    return token == null || token.isEmpty ? null : token;
  }

  @override
  Future<void> write(String token, {bool persistent = true}) async {
    final normalized = token.trim();
    if (!RegExp(r'^scm_[A-Za-z0-9_-]{43}$').hasMatch(normalized)) {
      throw const FormatException(
        'Enterprise Safe Contracts mobile token is invalid.',
      );
    }
    _sessionValue = normalized;
    if (persistent) {
      await _storage.write(key: _key, value: normalized);
    } else {
      await _storage.delete(key: _key);
    }
  }

  @override
  Future<void> clear() async {
    _sessionValue = null;
    await _storage.delete(key: _key);
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
