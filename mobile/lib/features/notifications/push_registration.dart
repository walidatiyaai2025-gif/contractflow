import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

enum MobilePushPermissionState { unknown, authorized, provisional, denied }

enum MobilePushBackendState { idle, registering, registered, error }

final class MobilePushRegistrationSnapshot {
  const MobilePushRegistrationSnapshot({
    required this.permission,
    required this.tokenAcquired,
    required this.backendState,
    required this.errorCode,
  });

  const MobilePushRegistrationSnapshot.initial()
      : permission = MobilePushPermissionState.unknown,
        tokenAcquired = false,
        backendState = MobilePushBackendState.idle,
        errorCode = null;

  final MobilePushPermissionState permission;
  final bool tokenAcquired;
  final MobilePushBackendState backendState;
  final String? errorCode;

  bool get backendRegistered => backendState == MobilePushBackendState.registered;
}

abstract interface class PushMessagingGateway {
  Future<MobilePushPermissionState> requestPermission();
  Future<String?> getToken();
  Stream<String> get onTokenRefresh;
  Future<void> deleteToken();
}

final class FirebasePushMessagingGateway implements PushMessagingGateway {
  FirebasePushMessagingGateway([FirebaseMessaging? messaging])
      : _messaging = messaging ?? FirebaseMessaging.instance;

  final FirebaseMessaging _messaging;

  @override
  Future<MobilePushPermissionState> requestPermission() async {
    final settings = await _messaging.requestPermission(
      alert: true,
      badge: true,
      sound: true,
    );
    return switch (settings.authorizationStatus) {
      AuthorizationStatus.authorized => MobilePushPermissionState.authorized,
      AuthorizationStatus.provisional => MobilePushPermissionState.provisional,
      AuthorizationStatus.denied => MobilePushPermissionState.denied,
      AuthorizationStatus.notDetermined => MobilePushPermissionState.unknown,
    };
  }

  @override
  Future<String?> getToken() => _messaging.getToken();

  @override
  Stream<String> get onTokenRefresh => _messaging.onTokenRefresh;

  @override
  Future<void> deleteToken() => _messaging.deleteToken();
}

typedef PushRetryDelay = Future<void> Function(Duration duration);

final class MobilePushRegistration {
  MobilePushRegistration({
    required this.client,
    PushMessagingGateway? messaging,
    PushRetryDelay? retryDelay,
  })  : _messaging = messaging ?? FirebasePushMessagingGateway(),
        _retryDelay = retryDelay ?? Future<void>.delayed;

  static const _retrySchedule = <Duration>[
    Duration(seconds: 1),
    Duration(seconds: 3),
  ];

  final SafeContractsApiClient client;
  final PushMessagingGateway _messaging;
  final PushRetryDelay _retryDelay;
  final ValueNotifier<MobilePushRegistrationSnapshot> status = ValueNotifier(
    const MobilePushRegistrationSnapshot.initial(),
  );

  StreamSubscription<String>? _refreshSubscription;
  String? _registeredToken;
  bool _started = false;
  bool _disposed = false;

  Future<void> start() async {
    if (_disposed) {
      return;
    }
    if (!_started) {
      _started = true;
      _refreshSubscription = _messaging.onTokenRefresh.listen(
        (token) => unawaited(_registerTokenWithRetry(token)),
        onError: (_) => _setError('fcm_token_refresh_failed'),
      );
    }

    try {
      final permission = await _messaging.requestPermission();
      _setStatus(
        permission: permission,
        tokenAcquired: status.value.tokenAcquired,
        backendState: status.value.backendState,
        errorCode: status.value.errorCode,
      );
    } on Object {
      // Permission prompting is independent from FCM installation registration.
      // Keep trying to register the device so the profile can diagnose state.
      _setStatus(
        permission: MobilePushPermissionState.unknown,
        tokenAcquired: status.value.tokenAcquired,
        backendState: status.value.backendState,
        errorCode: 'notification_permission_unavailable',
      );
    }

    await _acquireAndRegisterWithRetry();
  }

  Future<void> retryNow() async {
    if (_disposed) {
      return;
    }
    if (!_started) {
      await start();
      return;
    }
    await _acquireAndRegisterWithRetry();
  }

  Future<void> _acquireAndRegisterWithRetry() async {
    _setStatus(
      permission: status.value.permission,
      tokenAcquired: status.value.tokenAcquired,
      backendState: MobilePushBackendState.registering,
      errorCode: null,
    );

    for (var attempt = 0; attempt <= _retrySchedule.length; attempt++) {
      try {
        final token = (await _messaging.getToken())?.trim();
        if (token == null || token.isEmpty || token.length > 4096) {
          _setError('fcm_token_unavailable', keepRegistering: true);
        } else {
          _setStatus(
            permission: status.value.permission,
            tokenAcquired: true,
            backendState: MobilePushBackendState.registering,
            errorCode: null,
          );
          final registered = await _registerToken(token);
          if (registered) {
            return;
          }
          final current = status.value;
          if (current.errorCode == 'device_registration_401' ||
              current.errorCode == 'device_registration_403') {
            break;
          }
        }
      } on Object {
        _setError('fcm_token_request_failed', keepRegistering: true);
      }

      if (attempt < _retrySchedule.length) {
        await _retryDelay(_retrySchedule[attempt]);
      }
    }

    _setStatus(
      permission: status.value.permission,
      tokenAcquired: status.value.tokenAcquired,
      backendState: MobilePushBackendState.error,
      errorCode: status.value.errorCode ?? 'device_registration_failed',
    );
  }

  Future<void> _registerTokenWithRetry(String token) async {
    final normalized = token.trim();
    if (_disposed || normalized.isEmpty || normalized.length > 4096) {
      return;
    }
    _setStatus(
      permission: status.value.permission,
      tokenAcquired: true,
      backendState: MobilePushBackendState.registering,
      errorCode: null,
    );
    for (var attempt = 0; attempt <= _retrySchedule.length; attempt++) {
      if (await _registerToken(normalized)) {
        return;
      }
      final current = status.value;
      if (current.errorCode == 'device_registration_401' ||
          current.errorCode == 'device_registration_403') {
        return;
      }
      if (attempt < _retrySchedule.length) {
        await _retryDelay(_retrySchedule[attempt]);
      }
    }
  }

  Future<bool> _registerToken(String token) async {
    try {
      await client.post(
        'devices/register',
        body: <String, Object?>{
          'token': token,
          'platform': 'android',
        },
      );
      _registeredToken = token;
      _setStatus(
        permission: status.value.permission,
        tokenAcquired: true,
        backendState: MobilePushBackendState.registered,
        errorCode: null,
      );
      return true;
    } on SafeContractsApiException catch (error) {
      _setError('device_registration_${error.statusCode}');
      return false;
    } on Object {
      _setError('device_registration_failed');
      return false;
    }
  }

  void _setError(String code, {bool keepRegistering = false}) {
    _setStatus(
      permission: status.value.permission,
      tokenAcquired: status.value.tokenAcquired,
      backendState: keepRegistering
          ? MobilePushBackendState.registering
          : MobilePushBackendState.error,
      errorCode: code,
    );
  }

  void _setStatus({
    required MobilePushPermissionState permission,
    required bool tokenAcquired,
    required MobilePushBackendState backendState,
    required String? errorCode,
  }) {
    if (_disposed) {
      return;
    }
    status.value = MobilePushRegistrationSnapshot(
      permission: permission,
      tokenAcquired: tokenAcquired,
      backendState: backendState,
      errorCode: errorCode,
    );
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
    _setStatus(
      permission: MobilePushPermissionState.unknown,
      tokenAcquired: false,
      backendState: MobilePushBackendState.idle,
      errorCode: null,
    );
  }

  Future<void> dispose() async {
    if (_disposed) {
      return;
    }
    _disposed = true;
    await _refreshSubscription?.cancel();
    _refreshSubscription = null;
    status.dispose();
  }
}
