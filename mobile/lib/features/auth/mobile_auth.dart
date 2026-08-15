import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import '../../core/auth/mobile_token_store.dart';

enum MobileLoginState { idle, submitting, authenticated, error }

final class MobileAuthRepository {
  MobileAuthRepository({
    required this.client,
    required this.tokenStore,
  });

  final SafeContractsApiClient client;
  final MobileTokenStore tokenStore;

  Future<void> login({
    required String username,
    required String password,
  }) async {
    final normalizedUsername = username.trim();
    if (normalizedUsername.isEmpty || normalizedUsername.length > 254) {
      throw const FormatException('Username is required.');
    }
    if (password.isEmpty || password.length > 4096) {
      throw const FormatException('Password is required.');
    }

    final envelope = await client.post(
      'auth/login',
      body: <String, Object?>{
        'username': normalizedUsername,
        'password': password,
      },
    );
    final data = apiObjectMap(envelope.data, 'auth.login.data');
    if (data['token_type'] != 'Bearer') {
      throw const FormatException('SafeContracts login token type is invalid.');
    }
    final token = data['token'];
    if (token is! String ||
        !RegExp(r'^scm_[A-Za-z0-9_-]{43}$').hasMatch(token)) {
      throw const FormatException('SafeContracts login token is invalid.');
    }
    final expiresAt = data['expires_at'];
    if (expiresAt is! String || DateTime.tryParse(expiresAt) == null) {
      throw const FormatException('SafeContracts login expiry is invalid.');
    }
    await tokenStore.write(token);
  }

  Future<void> logout() async {
    try {
      final token = await tokenStore.read();
      if (token != null) {
        await client.post('auth/logout');
      }
    } finally {
      await tokenStore.clear();
    }
  }
}

final class MobileLoginController extends ChangeNotifier {
  MobileLoginController({required this.repository});

  final MobileAuthRepository repository;
  MobileLoginState state = MobileLoginState.idle;
  String? errorMessage;

  Future<bool> submit({
    required String username,
    required String password,
  }) async {
    state = MobileLoginState.submitting;
    errorMessage = null;
    notifyListeners();
    try {
      await repository.login(username: username, password: password);
      state = MobileLoginState.authenticated;
      notifyListeners();
      return true;
    } on SafeContractsApiException catch (error) {
      state = MobileLoginState.error;
      errorMessage = switch (error.statusCode) {
        401 => 'Invalid username or password.',
        403 => 'This account does not have SafeContracts access.',
        _ => error.message,
      };
      notifyListeners();
      return false;
    } on FormatException catch (error) {
      state = MobileLoginState.error;
      errorMessage = error.message;
      notifyListeners();
      return false;
    } on Object {
      state = MobileLoginState.error;
      errorMessage = 'Unable to sign in to SafeContracts.';
      notifyListeners();
      return false;
    }
  }

  void resetError() {
    errorMessage = null;
    if (state == MobileLoginState.error) {
      state = MobileLoginState.idle;
    }
    notifyListeners();
  }
}
