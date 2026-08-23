import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/mobile_states.dart';
import '../ui/safecontracts_design.dart';
import 'deep_link.dart';
import 'notifications.dart';

enum _NotificationFilter { all, unread, read }

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
  _NotificationFilter _filter = _NotificationFilter.all;

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
        if (controller.state == NotificationsLoadState.error && page == null) {
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

        final unread =
            page.notifications.where((item) => !_isRead(item)).length;
        final read = page.notifications.length - unread;
        final urgent = _firstUnread(page.notifications);
        final visible = page.notifications.where((item) {
          return switch (_filter) {
            _NotificationFilter.all => true,
            _NotificationFilter.unread => !_isRead(item),
            _NotificationFilter.read => _isRead(item),
          };
        }).toList(growable: false);

        return SafeContractsBackdrop(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            color: SafeContractsVisual.navy,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 28),
              children: [
                _PremiumNotificationHeader(
                  unread: unread,
                  isArabic: l10n.isArabic,
                ),
                if (controller.state == NotificationsLoadState.loading) ...[
                  const SizedBox(height: 8),
                  const LinearProgressIndicator(minHeight: 2),
                ],
                if (controller.state == NotificationsLoadState.error &&
                    controller.errorMessage != null) ...[
                  const SizedBox(height: 10),
                  _RefreshWarning(
                    message: l10n.rawMessage(controller.errorMessage!),
                  ),
                ],
                if (urgent != null) ...[
                  const SizedBox(height: 14),
                  _UrgentCard(
                    notification: urgent,
                    paymentLabel: l10n.paymentNumber(urgent.paymentId),
                    isArabic: l10n.isArabic,
                    onTap: () => unawaited(_openNotification(urgent)),
                  ),
                ],
                const SizedBox(height: 16),
                _NotificationOverview(
                  total: page.notifications.length,
                  unread: unread,
                  read: read,
                  isArabic: l10n.isArabic,
                ),
                const SizedBox(height: 20),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: SafeContractsSectionTitle(
                        title: l10n.isArabic ? 'الإشعارات' : 'Notifications',
                        subtitle: l10n.isArabic
                            ? 'رتّب ما يحتاج انتباهك أولاً'
                            : 'Prioritize what needs your attention',
                      ),
                    ),
                    IconButton.filledTonal(
                      tooltip: l10n.t('Refresh'),
                      onPressed:
                          controller.state == NotificationsLoadState.loading
                              ? null
                              : () => unawaited(controller.refresh()),
                      icon: const Icon(Icons.refresh_rounded),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                _Filters(
                  selected: _filter,
                  unread: unread,
                  read: read,
                  isArabic: l10n.isArabic,
                  onChanged: (value) => setState(() => _filter = value),
                ),
                const SizedBox(height: 12),
                if (visible.isEmpty)
                  SafeContractsSurface(
                    elevated: false,
                    child: Column(
                      children: [
                        const Icon(
                          Icons.notifications_none_rounded,
                          size: 38,
                          color: SafeContractsVisual.muted,
                        ),
                        const SizedBox(height: 8),
                        Text(
                          l10n.isArabic
                              ? 'لا توجد إشعارات ضمن هذا الفلتر.'
                              : 'No notifications match this filter.',
                          textAlign: TextAlign.center,
                        ),
                      ],
                    ),
                  )
                else
                  ...visible.map(
                    (item) => Padding(
                      padding: const EdgeInsets.only(bottom: 10),
                      child: _NotificationCard(
                        notification: item,
                        paymentLabel: l10n.paymentNumber(item.paymentId),
                        read: _isRead(item),
                        isArabic: l10n.isArabic,
                        onTap: () => unawaited(_openNotification(item)),
                      ),
                    ),
                  ),
                const SizedBox(height: 4),
                _PagingControls(controller: controller),
              ],
            ),
          ),
        );
      },
    );
  }

  bool _isRead(SafeContractsNotification item) =>
      item.isRead || widget.controller.isRead(item.id);

  SafeContractsNotification? _firstUnread(
    List<SafeContractsNotification> notifications,
  ) {
    for (final item in notifications) {
      if (!_isRead(item)) return item;
    }
    return null;
  }

  Future<void> _openNotification(SafeContractsNotification notification) async {
    final link = await widget.controller.openNotification(notification);
    if (!mounted || link == null) return;
    widget.onOpenDeepLink?.call(link);
  }
}

final class _PremiumNotificationHeader extends StatelessWidget {
  const _PremiumNotificationHeader({
    required this.unread,
    required this.isArabic,
  });

  final int unread;
  final bool isArabic;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
        boxShadow: const [
          BoxShadow(
            color: Color(0x2B092944),
            blurRadius: 20,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isArabic
                      ? 'مركز الإشعارات العاجلة'
                      : 'Urgent Notification Center',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  isArabic
                      ? 'تنبيهات العقود والمدفوعات في مكان واحد'
                      : 'Contract and payment alerts in one place',
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Colors.white.withValues(alpha: 0.72),
                      ),
                ),
              ],
            ),
          ),
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: unread > 0
                  ? SafeContractsVisual.red.withValues(alpha: 0.9)
                  : Colors.white.withValues(alpha: 0.12),
              shape: BoxShape.circle,
            ),
            child: Stack(
              clipBehavior: Clip.none,
              children: [
                const Center(
                  child: Icon(
                    Icons.notifications_active_outlined,
                    color: Colors.white,
                  ),
                ),
                if (unread > 0)
                  PositionedDirectional(
                    top: -4,
                    end: -4,
                    child: Container(
                      constraints: const BoxConstraints(minWidth: 20),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 5,
                        vertical: 2,
                      ),
                      decoration: BoxDecoration(
                        color: SafeContractsVisual.surface,
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        '$unread',
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          color: SafeContractsVisual.redDeep,
                          fontSize: 11,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _UrgentCard extends StatelessWidget {
  const _UrgentCard({
    required this.notification,
    required this.paymentLabel,
    required this.isArabic,
    required this.onTap,
  });

  final SafeContractsNotification notification;
  final String paymentLabel;
  final bool isArabic;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: SafeContractsVisual.surface,
      borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            border: Border.all(
              color: SafeContractsVisual.red.withValues(alpha: 0.35),
            ),
            borderRadius:
                BorderRadius.circular(SafeContractsVisual.compactRadius),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: const BoxDecoration(
                  color: SafeContractsVisual.redSoft,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.priority_high_rounded,
                  color: SafeContractsVisual.redDeep,
                ),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isArabic ? 'يتطلب انتباهك' : 'Needs your attention',
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: SafeContractsVisual.redDeep,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _template(notification.templateCode),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '$paymentLabel  •  ${notification.scheduledFor}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: SafeContractsVisual.muted,
                          ),
                    ),
                  ],
                ),
              ),
              const Icon(
                Icons.chevron_right_rounded,
                color: SafeContractsVisual.muted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _NotificationOverview extends StatelessWidget {
  const _NotificationOverview({
    required this.total,
    required this.unread,
    required this.read,
    required this.isArabic,
  });

  final int total;
  final int unread;
  final int read;
  final bool isArabic;

  @override
  Widget build(BuildContext context) {
    final unreadFactor = total == 0 ? 0.0 : unread / total;
    final readFactor = total == 0 ? 0.0 : read / total;
    return SafeContractsSurface(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  isArabic ? 'بيان الإشعارات' : 'Notification Overview',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w900,
                      ),
                ),
              ),
              Text(
                isArabic ? 'الإجمالي $total' : 'Total $total',
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: SafeContractsVisual.muted,
                    ),
              ),
            ],
          ),
          const SizedBox(height: 16),
          Row(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Expanded(
                child: _OverviewBar(
                  value: unread,
                  factor: unreadFactor,
                  label: isArabic ? 'غير مقروء' : 'Unread',
                  color: SafeContractsVisual.roseGold,
                ),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: _OverviewBar(
                  value: read,
                  factor: readFactor,
                  label: isArabic ? 'تمت القراءة' : 'Read',
                  color: SafeContractsVisual.navy,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _OverviewBar extends StatelessWidget {
  const _OverviewBar({
    required this.value,
    required this.factor,
    required this.label,
    required this.color,
  });

  final int value;
  final double factor;
  final String label;
  final Color color;

  @override
  Widget build(BuildContext context) {
    final height = 38.0 + factor.clamp(0.0, 1.0).toDouble() * 42.0;
    return Column(
      children: [
        Text(
          '$value',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontWeight: FontWeight.w900,
              ),
        ),
        const SizedBox(height: 6),
        AnimatedContainer(
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeOutCubic,
          width: double.infinity,
          height: height,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [color.withValues(alpha: 0.72), color],
            ),
            borderRadius: const BorderRadius.vertical(
              top: Radius.circular(12),
            ),
          ),
        ),
        const SizedBox(height: 7),
        Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: SafeContractsVisual.muted,
              ),
        ),
      ],
    );
  }
}

final class _Filters extends StatelessWidget {
  const _Filters({
    required this.selected,
    required this.unread,
    required this.read,
    required this.isArabic,
    required this.onChanged,
  });

  final _NotificationFilter selected;
  final int unread;
  final int read;
  final bool isArabic;
  final ValueChanged<_NotificationFilter> onChanged;

  @override
  Widget build(BuildContext context) {
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _chip(
            _NotificationFilter.all,
            isArabic ? 'الكل' : 'All',
            Icons.filter_list_rounded,
            SafeContractsVisual.navy,
          ),
          const SizedBox(width: 8),
          _chip(
            _NotificationFilter.unread,
            isArabic ? 'جديد $unread' : 'New $unread',
            Icons.notifications_active_outlined,
            SafeContractsVisual.red,
          ),
          const SizedBox(width: 8),
          _chip(
            _NotificationFilter.read,
            isArabic ? 'مقروء $read' : 'Read $read',
            Icons.done_all_rounded,
            SafeContractsVisual.green,
          ),
        ],
      ),
    );
  }

  Widget _chip(
    _NotificationFilter value,
    String label,
    IconData icon,
    Color accent,
  ) {
    final active = selected == value;
    return FilterChip(
      selected: active,
      onSelected: (_) => onChanged(value),
      avatar: Icon(icon, size: 17, color: accent),
      label: Text(label),
      selectedColor: accent.withValues(alpha: 0.12),
      backgroundColor: SafeContractsVisual.surface,
      side: BorderSide(
        color: active ? accent : SafeContractsVisual.outline,
      ),
      shape: const StadiumBorder(),
      showCheckmark: false,
    );
  }
}

final class _NotificationCard extends StatelessWidget {
  const _NotificationCard({
    required this.notification,
    required this.paymentLabel,
    required this.read,
    required this.isArabic,
    required this.onTap,
  });

  final SafeContractsNotification notification;
  final String paymentLabel;
  final bool read;
  final bool isArabic;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final accent = read ? SafeContractsVisual.green : SafeContractsVisual.red;
    final soft =
        read ? SafeContractsVisual.greenSoft : SafeContractsVisual.redSoft;
    return Material(
      color: SafeContractsVisual.surface,
      borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            border: Border.all(
              color: read
                  ? SafeContractsVisual.outline
                  : accent.withValues(alpha: 0.26),
            ),
            borderRadius:
                BorderRadius.circular(SafeContractsVisual.compactRadius),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 38,
                height: 38,
                decoration: BoxDecoration(
                  color: soft,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  read
                      ? Icons.notifications_none_outlined
                      : Icons.notifications_active_outlined,
                  color: accent,
                  size: 20,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            _template(notification.templateCode),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context)
                                .textTheme
                                .titleSmall
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: soft,
                            borderRadius: BorderRadius.circular(20),
                          ),
                          child: Text(
                            read
                                ? (isArabic ? 'مقروء' : 'Read')
                                : (isArabic ? 'جديد' : 'New'),
                            style: TextStyle(
                              color: accent,
                              fontSize: 11,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 7),
                    Wrap(
                      spacing: 9,
                      runSpacing: 5,
                      children: [
                        _Meta(
                          icon: Icons.payments_outlined,
                          label: paymentLabel,
                        ),
                        _Meta(
                          icon: Icons.schedule_outlined,
                          label: notification.scheduledFor,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
              const Padding(
                padding: EdgeInsets.only(top: 7),
                child: Icon(
                  Icons.chevron_right_rounded,
                  color: SafeContractsVisual.muted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _Meta extends StatelessWidget {
  const _Meta({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 14, color: SafeContractsVisual.muted),
        const SizedBox(width: 4),
        Text(
          label,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: SafeContractsVisual.muted,
              ),
        ),
      ],
    );
  }
}

final class _RefreshWarning extends StatelessWidget {
  const _RefreshWarning({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: SafeContractsVisual.amberSoft,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(
          color: SafeContractsVisual.amber.withValues(alpha: 0.35),
        ),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.info_outline_rounded,
            size: 19,
            color: SafeContractsVisual.amber,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Text(message, style: Theme.of(context).textTheme.bodySmall),
          ),
        ],
      ),
    );
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
    return SafeContractsSurface(
      elevated: false,
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Wrap(
        alignment: WrapAlignment.center,
        crossAxisAlignment: WrapCrossAlignment.center,
        spacing: 10,
        runSpacing: 8,
        children: [
          OutlinedButton.icon(
            onPressed: page.page > 1
                ? () => unawaited(controller.previousPage())
                : null,
            icon: const Icon(Icons.chevron_left),
            label: Text(l10n.t('Previous')),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: SafeContractsVisual.navySoft,
              borderRadius: BorderRadius.circular(12),
            ),
            child: Text(
              l10n.pageNumber(page.page),
              style: const TextStyle(
                color: SafeContractsVisual.navy,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
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

String _template(String code) {
  final value = code
      .trim()
      .replaceAll(RegExp(r'[_\-]+'), ' ')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim();
  if (value.isEmpty) return 'Notification';
  return value[0].toUpperCase() + value.substring(1);
}
