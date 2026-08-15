import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import 'deep_links.dart';

final class MobileNotification {
  const MobileNotification({
    required this.id,
    required this.paymentId,
    required this.templateCode,
    required this.scheduledFor,
    required this.createdAt,
    required this.isRead,
    required this.deepLink,
  });

  final int id;
  final int paymentId;
  final String templateCode;
  final String scheduledFor;
  final String createdAt;
  final bool isRead;
  final SafeDeepLink deepLink;

  factory MobileNotification.fromData(Object? value) {
    final data = apiObjectMap(value, 'notification');
    final deepLink = SafeDeepLinkResolver.tryResolve(data['deep_link']);
    if (deepLink == null) {
      throw const FormatException('notification.deep_link is invalid.');
    }
    return MobileNotification(
      id: _positiveInt(data['id'], 'notification.id'),
      paymentId: _positiveInt(data['payment_id'], 'notification.payment_id'),
      templateCode: _safeCode(data['template_code'], 'notification.template_code'),
      scheduledFor: _safeText(data['scheduled_for'], 'notification.scheduled_for'),
      createdAt: _safeText(data['created_at'], 'notification.created_at'),
      isRead: data['is_read'] == true,
      deepLink: deepLink,
    );
  }

  MobileNotification markRead() {
    return MobileNotification(
      id: id,
      paymentId: paymentId,
      templateCode: templateCode,
      scheduledFor: scheduledFor,
      createdAt: createdAt,
      isRead: true,
      deepLink: deepLink,
    );
  }
}

final class DeviceStatus {
  const DeviceStatus({required this.activeDeviceCount, required this.platforms});

  const DeviceStatus.empty()
      : activeDeviceCount = 0,
        platforms = const <String>[];

  final int activeDeviceCount;
  final List<String> platforms;

  factory DeviceStatus.fromData(Object? value) {
    final data = apiObjectMap(value, 'device_status');
    final count = _nonNegativeInt(
      data['active_device_count'],
      'device_status.active_device_count',
    );
    final rawPlatforms = apiObjectList(data['platforms'], 'device_status.platforms');
    final platforms = <String>[];
    for (final value in rawPlatforms) {
      final platform = _safeCode(value, 'device_status.platform');
      if (!platforms.contains(platform)) {
        platforms.add(platform);
      }
    }
    return DeviceStatus(
      activeDeviceCount: count,
      platforms: List<String>.unmodifiable(platforms),
    );
  }
}

final class MobileNotificationsRepository {
  MobileNotificationsRepository(this.client);

  final SafeContractsApiClient client;

  Future<List<MobileNotification>> inbox({int page = 1, int perPage = 50}) async {
    final response = await client.get(
      'notifications',
      query: <String, String>{
        'page': page.clamp(1, 5).toString(),
        'per_page': perPage.clamp(1, 100).toString(),
      },
    );
    return List<MobileNotification>.unmodifiable(
      apiObjectList(response.data, 'notifications.data').map(
        MobileNotification.fromData,
      ),
    );
  }

  Future<void> markRead(int notificationId) async {
    await client.postJson('notifications/$notificationId/read');
  }

  Future<DeviceStatus> deviceStatus() async {
    final response = await client.get('device-status');
    return DeviceStatus.fromData(response.data);
  }
}

enum NotificationsState { idle, loading, ready, error }

final class MobileNotificationsController extends ChangeNotifier {
  MobileNotificationsController(this.repository);

  final MobileNotificationsRepository repository;

  NotificationsState state = NotificationsState.idle;
  List<MobileNotification> items = const <MobileNotification>[];
  String? errorMessage;

  int get unreadCount => items.where((item) => !item.isRead).length;

  Future<void> load({bool preserveVisibleData = true}) async {
    final previous = items;
    state = NotificationsState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      items = await repository.inbox();
      state = NotificationsState.ready;
    } on Object catch (error) {
      if (!preserveVisibleData) {
        items = const <MobileNotification>[];
      } else {
        items = previous;
      }
      errorMessage = error.toString();
      state = NotificationsState.error;
    }
    notifyListeners();
  }

  Future<SafeDeepLink?> open(MobileNotification notification) async {
    if (!notification.isRead) {
      await repository.markRead(notification.id);
      items = List<MobileNotification>.unmodifiable(
        items.map(
          (item) => item.id == notification.id ? item.markRead() : item,
        ),
      );
      notifyListeners();
    }
    return notification.deepLink;
  }
}

int _positiveInt(Object? value, String field) {
  final parsed = value is int ? value : int.tryParse(value?.toString() ?? '');
  if (parsed == null || parsed < 1) {
    throw FormatException('$field must be a positive integer.');
  }
  return parsed;
}

int _nonNegativeInt(Object? value, String field) {
  final parsed = value is int ? value : int.tryParse(value?.toString() ?? '');
  if (parsed == null || parsed < 0) {
    throw FormatException('$field must be a non-negative integer.');
  }
  return parsed;
}

String _safeText(Object? value, String field) {
  if (value is! String || value.trim().isEmpty || value.length > 191) {
    throw FormatException('$field is invalid.');
  }
  return value.trim();
}

String _safeCode(Object? value, String field) {
  final text = _safeText(value, field).toLowerCase();
  if (!RegExp(r'^[a-z0-9_.:-]+$').hasMatch(text)) {
    throw FormatException('$field is invalid.');
  }
  return text;
}
