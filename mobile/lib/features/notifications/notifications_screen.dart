import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/ui/async_states.dart';
import '../../core/ui/responsive.dart';
import 'deep_links.dart';
import 'mobile_notifications.dart';

final class NotificationsScreen extends StatelessWidget {
  const NotificationsScreen({
    required this.controller,
    required this.onOpenDeepLink,
    super.key,
  });

  final MobileNotificationsController controller;
  final ValueChanged<SafeDeepLink> onOpenDeepLink;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        if (controller.state == NotificationsState.loading &&
            controller.items.isEmpty) {
          return const SafeLoadingState(label: 'Loading notifications…');
        }
        if (controller.state == NotificationsState.error &&
            controller.items.isEmpty &&
            controller.failure != null) {
          return SafeErrorState(
            failure: controller.failure!,
            onRetry: controller.load,
          );
        }

        return SafeResponsiveBody(
          child: RefreshIndicator(
            onRefresh: controller.load,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.symmetric(vertical: 16),
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Notifications',
                        style: Theme.of(context).textTheme.headlineSmall,
                      ),
                    ),
                    if (controller.unreadCount > 0)
                      Badge(
                        label: Text(controller.unreadCount.toString()),
                        child: const Icon(Icons.notifications_outlined),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                if (controller.state == NotificationsState.error &&
                    controller.failure != null)
                  SafeErrorState(
                    failure: controller.failure!,
                    onRetry: controller.load,
                    compact: true,
                  ),
                if (controller.items.isEmpty)
                  const SizedBox(
                    height: 240,
                    child: SafeEmptyState(
                      message: 'No notifications are available in your inbox.',
                    ),
                  )
                else
                  ...controller.items.map(
                    (notification) => _NotificationCard(
                      notification: notification,
                      onTap: () => unawaited(
                        _open(context, notification),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _open(
    BuildContext context,
    MobileNotification notification,
  ) async {
    try {
      final deepLink = await controller.open(notification);
      if (deepLink != null) {
        onOpenDeepLink(deepLink);
      }
    } on Object catch (error) {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }
}

final class _NotificationCard extends StatelessWidget {
  const _NotificationCard({required this.notification, required this.onTap});

  final MobileNotification notification;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: ListTile(
        leading: Icon(
          notification.isRead
              ? Icons.notifications_none_outlined
              : Icons.notifications_active_outlined,
        ),
        title: Text(_title(notification.templateCode)),
        subtitle: Text(
          'Payment #${notification.paymentId} • ${notification.scheduledFor}',
        ),
        trailing: notification.isRead
            ? const Icon(Icons.chevron_right)
            : const Badge(child: Icon(Icons.chevron_right)),
        onTap: onTap,
      ),
    );
  }
}

String _title(String templateCode) {
  return switch (templateCode) {
    'payment_due_soon' => 'Payment due soon',
    'payment_due_today' => 'Payment due today',
    'payment_overdue' => 'Payment overdue',
    _ => 'SafeContracts notification',
  };
}
