import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';
import 'deep_link.dart';

enum NotificationsLoadState { idle, loading, ready, error }

final class SafeContractsNotification {
  const SafeContractsNotification({
    required this.id,
    required this.paymentId,
    required this.templateCode,
    required this.scheduledFor,
    required this.createdAt,
    required this.deepLink,
  });

  final int id;
  final int paymentId;
  final String templateCode;
  final String scheduledFor;
  final String createdAt;
  final SafeContractsDeepLink? deepLink;

  factory SafeContractsNotification.fromData(Object? value) {
    final data = apiObjectMap(value, 'notification');
    final id = _positiveInt(data['id'], 'notification.id');
    final paymentId = _positiveInt(
      data['payment_id'],
      'notification.payment_id',
    );
    final templateCode = _boundedCode(
      data['template_code'],
      'notification.template_code',
    );
    final scheduledFor = _boundedTimestamp(
      data['scheduled_for'],
      'notification.scheduled_for',
    );
    final createdAt = _boundedTimestamp(
      data['created_at'],
      'notification.created_at',
    );
    final deepLink = SafeContractsDeepLink.tryResolve(data['deep_link']);
    if (data['deep_link'] != null && deepLink == null) {
      throw const FormatException('notification.deep_link is invalid.');
    }
    if (deepLink != null &&
        (deepLink.destination != SafeContractsDeepLinkDestination.payments ||
            deepLink.resourceId != paymentId)) {
      throw const FormatException(
        'notification.deep_link does not match the authorized payment.',
      );
    }

    return SafeContractsNotification(
      id: id,
      paymentId: paymentId,
      templateCode: templateCode,
      scheduledFor: scheduledFor,
      createdAt: createdAt,
      deepLink: deepLink,
    );
  }
}

final class NotificationPage {
  const NotificationPage({
    required this.notifications,
    required this.page,
    required this.perPage,
    required this.hasMore,
  });

  final List<SafeContractsNotification> notifications;
  final int page;
  final int perPage;
  final bool hasMore;

  factory NotificationPage.fromEnvelope(ApiEnvelope envelope) {
    final rows = apiObjectList(envelope.data, 'notifications.data');
    final notifications = rows
        .map(SafeContractsNotification.fromData)
        .toList(growable: false);
    final ids = <int>{};
    for (final notification in notifications) {
      if (!ids.add(notification.id)) {
        throw const FormatException('notifications contain duplicate IDs.');
      }
    }

    final meta = envelope.meta;
    final page = _boundedInt(meta['page'], 'meta.page', 1, 5);
    final perPage = _boundedInt(meta['per_page'], 'meta.per_page', 1, 50);
    if (meta['scope'] != 'current_user') {
      throw const FormatException('notification scope metadata is invalid.');
    }

    return NotificationPage(
      notifications: List<SafeContractsNotification>.unmodifiable(notifications),
      page: page,
      perPage: perPage,
      hasMore: _boolish(meta['has_more'], 'meta.has_more'),
    );
  }
}

final class NotificationsRepository {
  NotificationsRepository(this.client);

  final SafeContractsApiClient client;

  Future<NotificationPage> loadPage({
    required int page,
    required int perPage,
  }) async {
    if (page < 1 || page > 5) {
      throw ArgumentError('Notification page must be between 1 and 5.');
    }
    if (perPage < 1 || perPage > 50) {
      throw ArgumentError('Notification page size must be between 1 and 50.');
    }
    final envelope = await client.get(
      'notifications',
      query: <String, String>{
        'page': '$page',
        'per_page': '$perPage',
      },
    );
    return NotificationPage.fromEnvelope(envelope);
  }
}

final class NotificationsController extends ChangeNotifier {
  NotificationsController({
    required this.repository,
    required int pageSize,
    required this.canAccess,
  }) : pageSize = pageSize.clamp(1, 50).toInt();

  final NotificationsRepository repository;
  final int pageSize;
  final bool canAccess;
  final Set<int> _readIds = <int>{};

  NotificationsLoadState state = NotificationsLoadState.idle;
  NotificationPage? currentPage;
  String? errorMessage;

  bool isRead(int id) => _readIds.contains(id);

  Future<void> ensureLoaded() async {
    if (state == NotificationsLoadState.idle) {
      await loadPage(1);
    }
  }

  Future<void> loadPage(int page) async {
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Notification access is not authorized for this session.';
      state = NotificationsLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || page > 5) {
      return;
    }

    state = NotificationsLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      currentPage = await repository.loadPage(page: page, perPage: pageSize);
      state = NotificationsLoadState.ready;
    } on SafeContractsApiException catch (error) {
      currentPage = null;
      errorMessage = error.message;
      state = NotificationsLoadState.error;
    } on Object catch (error) {
      currentPage = null;
      errorMessage = error.toString();
      state = NotificationsLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refresh() => loadPage(currentPage?.page ?? 1);

  Future<void> previousPage() async {
    final page = currentPage?.page ?? 1;
    if (page > 1) {
      await loadPage(page - 1);
    }
  }

  Future<void> nextPage() async {
    final page = currentPage;
    if (page != null && page.hasMore && page.page < 5) {
      await loadPage(page.page + 1);
    }
  }

  SafeContractsDeepLink? openNotification(SafeContractsNotification notification) {
    final visible = currentPage?.notifications
            .any((item) => item.id == notification.id) ??
        false;
    if (!visible) {
      return null;
    }
    _readIds.add(notification.id);
    notifyListeners();
    return notification.deepLink;
  }
}

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) {
    throw FormatException('$field must be a positive integer.');
  }
  return parsed;
}

int _boundedInt(Object? value, String field, int minimum, int maximum) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed < minimum || parsed > maximum) {
    throw FormatException('$field is outside the supported range.');
  }
  return parsed;
}

String _boundedCode(Object? value, String field) {
  if (value is! String) {
    throw FormatException('$field must be a string.');
  }
  final normalized = value.trim().toLowerCase();
  if (normalized.isEmpty ||
      normalized.length > 100 ||
      !RegExp(r'^[a-z0-9_.:-]+$').hasMatch(normalized)) {
    throw FormatException('$field is invalid.');
  }
  return normalized;
}

String _boundedTimestamp(Object? value, String field) {
  if (value is! String) {
    throw FormatException('$field must be a string.');
  }
  final normalized = value.trim();
  if (normalized.isEmpty || normalized.length > 32) {
    throw FormatException('$field is invalid.');
  }
  return normalized;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true => true,
    false => false,
    1 => true,
    0 => false,
    '1' => true,
    '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
