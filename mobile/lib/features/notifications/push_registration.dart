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

  bool get backendRegistered =>
      backendState == MobilePushBackendState.registered;
}

abstract interface class PushMessagingGateway {
  Future<MobilePushPermissionState> requestPermission();
  Future<String?> getToken();
  Stream<String> get onTokenRefresh;
  Future<void> deleteToken();
}

final class FirebasePushMessagingGateway implements PushMessagingGateway {
  FirebasePushMessagingGateway([FirebaseMessaging? messaging])
      : _messagingOverride = messaging;

  final FirebaseMessaging? _messagingOverride;

  FirebaseMessaging get _messaging =>
      _messagingOverride ?? FirebaseMessaging.instance;

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
      AuthorizationStatus.denied || AuthorizationStatus.deniedPermanently =>
        MobilePushPermissionState.denied,
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
  Future<void>? _startInFlight;
  Future<void> _registrationQueue = Future<void>.value();
  bool _started = false;
  bool _permissionRequested = false;
  bool _disposed = false;

  Future<void> start() async {
    if (_disposed || status.value.backendRegistered) {
      return;
    }
    final existing = _startInFlight;
    if (existing != null) {
      return existing;
    }
    final future = _start();
    _startInFlight = future;
    try {
      await future;
    } finally {
      if (identical(_startInFlight, future)) {
        _startInFlight = null;
      }
    }
  }

  Future<void> _start() async {
    if (!_permissionRequested) {
      _permissionRequested = true;
      final permission = await _messaging.requestPermission();
      if (_disposed) return;
      status.value = MobilePushRegistrationSnapshot(
        permission: permission,
        tokenAcquired: false,
        backendState: MobilePushBackendState.idle,
        errorCode: null,
      );
      if (permission != MobilePushPermissionState.authorized &&
          permission != MobilePushPermissionState.provisional) {
        return;
      }
    }

    if (!_started) {
      _started = true;
      _refreshSubscription = _messaging.onTokenRefresh.listen(
        (token) => unawaited(_enqueueRegistration(token)),
      );
    }

    final token = await _messaging.getToken();
    if (_disposed) return;
    if (token == null || token.trim().isEmpty) {
      status.value = MobilePushRegistrationSnapshot(
        permission: status.value.permission,
        tokenAcquired: false,
        backendState: MobilePushBackendState.error,
        errorCode: 'push_token_unavailable',
      );
      return;
    }
    await _enqueueRegistration(token);
  }

  Future<void> _enqueueRegistration(String token) {
    _registrationQueue = _registrationQueue.then(
      (_) => _registerToken(token),
      onError: (_) => _registerToken(token),
    );
    return _registrationQueue;
  }

  Future<void> _registerToken(String rawToken) async {
    if (_disposed) return;
    final token = rawToken.trim();
    if (token.isEmpty || token == _registeredToken) return;

    status.value = MobilePushRegistrationSnapshot(
      permission: status.value.permission,
      tokenAcquired: true,
      backendState: MobilePushBackendState.registering,
      errorCode: null,
    );

    Object? lastError;
    for (var attempt = 0; attempt <= _retrySchedule.length; attempt++) {
      try {
        await client.post(
          'devices/register',
          body: <String, Object?>{
            'token': token,
            'platform': defaultTargetPlatform.name,
          },
        );
        if (_disposed) return;
        _registeredToken = token;
        status.value = MobilePushRegistrationSnapshot(
          permission: status.value.permission,
          tokenAcquired: true,
          backendState: MobilePushBackendState.registered,
          errorCode: null,
        );
        return;
      } on Object catch (error) {
        lastError = error;
        if (attempt >= _retrySchedule.length) break;
        await _retryDelay(_retrySchedule[attempt]);
        if (_disposed) return;
      }
    }

    if (_disposed) return;
    status.value = MobilePushRegistrationSnapshot(
      permission: status.value.permission,
      tokenAcquired: true,
      backendState: MobilePushBackendState.error,
      errorCode: lastError is ApiException
          ? lastError.code
          : 'push_registration_failed',
    );
  }

  Future<void> dispose() async {
    if (_disposed) return;
    _disposed = true;
    await _refreshSubscription?.cancel();
    _refreshSubscription = null;
    status.dispose();
  }
}
