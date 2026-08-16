import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/mobile_layout.dart';
import '../ui/mobile_states.dart';
import 'deep_link.dart';
import 'notifications.dart';

final class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({
    required this.controller,
    this.onOpenDeepLink,
    super.key,
  });

  final NotificationsController controller;
  final ValueChanged<SafeContractsDeepLink>? onOpenDeepLink;

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

final class _NotificationsScreenState extends State<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final controller = widget.controller;
        final page = controller.currentPage;
        if (controller.state == NotificationsLoadState.loading &&
            page == null) {
          return SafeContractsStateView(
            kind: MobileStateKind.loading,
            message: l10n.t('Loading notifications…'),
          );
        }
        if (controller.state == NotificationsLoadState.error) {
          final message =
              controller.errorMessage ?? 'Notifications are unavailable.';
          final offline = message.toLowerCase().contains('unreachable') ||
              message.toLowerCase().contains('timed out');
          return SafeContractsStateView(
            kind: offline ? MobileStateKind.offline : MobileStateKind.error,
            message: l10n.rawMessage(message),
            onRetry: () => unawaited(controller.refresh()),
          );
        }
        if (page == null || page.notifications.isEmpty) {
          return SafeContractsStateView(
            kind: MobileStateKind.empty,
            message: l10n.t('No notifications are available for this account.'),
            onRetry: () => unawaited(controller.refresh()),
          );
        }

        return SafeContractsAdaptiveBody(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              itemCount: page.notifications.length + 1,
              separatorBuilder: (context, index) => const Divider(height: 1),
              itemBuilder: (context, index) {
                if (index == page.notifications.length) {
                  return _PagingControls(controller: controller);
                }
                final notification = page.notifications[index];
                final read = controller.isRead(notification.id);
                return ListTile(
                  leading: Icon(
                    read
                        ? Icons.notifications_none_outlined
                        : Icons.notifications_active_outlined,
                  ),
                  title: Text(notification.templateCode),
                  subtitle: Text(
                    '${l10n.paymentNumber(notification.paymentId)}\n'
                    '${notification.scheduledFor}',
                  ),
                  isThreeLine: true,
                  trailing: read
                      ? Text(l10n.t('Read'))
                      : Badge(label: Text(l10n.t('New'))),
                  onTap: () => unawaited(_openNotification(notification)),
                );
              },
            ),
          ),
        );
      },
    );
  }

  Future<void> _openNotification(
    SafeContractsNotification notification,
  ) async {
    final link = await widget.controller.openNotification(notification);
    if (!mounted || link == null) return;
    widget.onOpenDeepLink?.call(link);
  }
}

final class _PagingControls extends StatelessWidget {
  const _PagingControls({required this.controller});

  final NotificationsController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final page = controller.currentPage;
    if (page == null) return const SizedBox.shrink();
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 16),
      child: Wrap(
        alignment: WrapAlignment.center,
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 12,
        runSpacing: 8,
        children: [
          OutlinedButton.icon(
            onPressed: page.page > 1
                ? () => unawaited(controller.previousPage())
                : null,
            icon: const Icon(Icons.chevron_left),
            label: Text(l10n.t('Previous')),
          ),
          Text(l10n.pageNumber(page.page)),
          OutlinedButton.icon(
            onPressed:
                page.hasMore ? () => unawaited(controller.nextPage()) : null,
            icon: const Icon(Icons.chevron_right),
            label: Text(l10n.t('Next')),
          ),
        ],
      ),
    );
  }
}
