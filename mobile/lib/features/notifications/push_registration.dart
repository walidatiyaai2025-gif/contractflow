import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';

import '../../core/api/api_client.dart';

final class MobilePushRegistration {
  MobilePushRegistration({
    required this.client,
    FirebaseMessaging? messaging,
  }) : _messaging = messaging ?? FirebaseMessaging.instance;

  final SafeContractsApiClient client;
  final FirebaseMessaging _messaging;
  StreamSubscription<String>? _refreshSubscription;
  String? _registeredToken;
  bool _started = false;

  Future<void> start() async {
    if (_started) {
      return;
    }
    _started = true;

    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    if (settings.authorizationStatus == AuthorizationStatus.denied) {
      return;
    }

    final token = await _messaging.getToken();
    if (token != null && token.trim().isNotEmpty) {
      await _register(token);
    }

    _refreshSubscription = _messaging.onTokenRefresh.listen(
      (token) => unawaited(_register(token)),
      onError: (_) {},
    );
  }

  Future<void> _register(String token) async {
    final normalized = token.trim();
    if (normalized.isEmpty || normalized.length > 4096) {
      return;
    }
    await client.post(
      'devices/register',
      body: <String, Object?>{
        'token': normalized,
        'platform': 'android',
      },
    );
    _registeredToken = normalized;
  }

  Future<void> revokeAndStop() async {
    await _refreshSubscription?.cancel();
    _refreshSubscription = null;
    final token = _registeredToken;
    _registeredToken = null;
    _started = false;
    if (token != null) {
      try {
        await client.post(
          'devices/revoke',
          body: <String, Object?>{'token': token},
        );
      } on Object {
        // Logout must continue even when the backend cannot revoke immediately.
      }
    }
    try {
      await _messaging.deleteToken();
    } on Object {
      // Token deletion is best-effort; server-side session revocation still wins.
    }
  }

  Future<void> dispose() async {
    await _refreshSubscription?.cancel();
    _refreshSubscription = null;
  }
}
