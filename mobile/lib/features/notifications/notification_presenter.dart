import 'dart:async';

import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/services.dart';

final class MobileNotificationPresenter {
  MobileNotificationPresenter._();

  static const MethodChannel _channel = MethodChannel(
    'enterprise_safecontracts/notifications',
  );
  static StreamSubscription<RemoteMessage>? _subscription;

  static Future<void> start() async {
    await _subscription?.cancel();
    _subscription = FirebaseMessaging.onMessage.listen((message) {
      unawaited(_show(message));
    });
  }

  static Future<void> dispose() async {
    await _subscription?.cancel();
    _subscription = null;
  }

  static Future<void> _show(RemoteMessage message) async {
    final notification = message.notification;
    final title = (notification?.title ?? '').trim();
    final body = (notification?.body ?? '').trim();
    if (title.isEmpty || body.isEmpty) return;

    final iconKey = (message.data['icon_key'] ?? 'contract_due').trim();
    final stableId = message.messageId?.hashCode ??
        DateTime.now().millisecondsSinceEpoch.remainder(0x7fffffff);
    try {
      await _channel.invokeMethod<bool>('showNotification', <String, Object?>{
        'id': stableId & 0x7fffffff,
        'title': title,
        'body': body,
        'iconKey': iconKey,
      });
    } on PlatformException {
      // The Firebase message is still available to the app even if the native
      // presentation bridge is unavailable on a non-Android test platform.
    } on MissingPluginException {
      // Unit/widget tests and unsupported platforms intentionally have no
      // Android notification bridge.
    }
  }
}
