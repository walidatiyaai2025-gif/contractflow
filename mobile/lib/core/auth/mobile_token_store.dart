import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class MobileTokenStore {
  Future<String?> read();
  Future<void> write(String token);
  Future<void> clear();
}

final class SecureMobileTokenStore implements MobileTokenStore {
  SecureMobileTokenStore({FlutterSecureStorage? storage})
      : _storage = storage ?? FlutterSecureStorage();

  static const _key = 'safecontracts.mobile.bearer_token';
  final FlutterSecureStorage _storage;

  @override
  Future<String?> read() async {
    final value = await _storage.read(key: _key);
    final token = value?.trim();
    return token == null || token.isEmpty ? null : token;
  }

  @override
  Future<void> write(String token) async {
    final normalized = token.trim();
    if (!RegExp(r'^scm_[A-Za-z0-9_-]{43}$').hasMatch(normalized)) {
      throw const FormatException('SafeContracts mobile token is invalid.');
    }
    await _storage.write(key: _key, value: normalized);
  }

  @override
  Future<void> clear() => _storage.delete(key: _key);
}

final class MemoryMobileTokenStore implements MobileTokenStore {
  MemoryMobileTokenStore([this._value]);

  String? _value;

  @override
  Future<String?> read() async => _value;

  @override
  Future<void> write(String token) async {
    _value = token;
  }

  @override
  Future<void> clear() async {
    _value = null;
  }
}
