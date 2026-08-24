import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/mobile_states.dart';
import '../ui/safecontracts_design.dart';
import 'deep_link.dart';
import 'notifications.dart';

enum _NotificationFilter { all, unread, read, paymentDue, overdue }

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
          final normalized = message.toLowerCase();
          final offline = normalized.contains('unreachable') ||
              normalized.contains('timed out') ||
              normalized.contains('network');
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
        final attention = _firstAttentionItem(page.notifications);
        final hasDue = page.notifications.any(_isPaymentDue);
        final hasOverdue = page.notifications.any(_isOverdue);
        if ((!hasDue && _filter == _NotificationFilter.paymentDue) ||
            (!hasOverdue && _filter == _NotificationFilter.overdue)) {
          _filter = _NotificationFilter.all;
        }
        final visible = page.notifications.where(_matchesFilter).toList(
              growable: false,
            );

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
                if (attention != null) ...[
                  const SizedBox(height: 14),
                  _AttentionCard(
                    notification: attention,
                    paymentLabel: l10n.paymentNumber(attention.paymentId),
                    isArabic: l10n.isArabic,
                    onTap: () => unawaited(_openNotification(attention)),
                  ),
                ],
                const SizedBox(height: 14),
                _NotificationOverview(
                  total: page.notifications.length,
                  unread: unread,
                  read: read,
                  isArabic: l10n.isArabic,
                ),
                const SizedBox(height: 18),
                Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Expanded(
                      child: SafeContractsSectionTitle(
                        title: l10n.isArabic ? 'الإشعارات' : 'Notifications',
                        subtitle: l10n.isArabic
                            ? 'الحالة والنوع واضحان بدون الاعتماد على اللون فقط'
                            : 'Status and type remain explicit without relying on color alone',
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
                const SizedBox(height: 10),
                _Filters(
                  selected: _filter,
                  unread: unread,
                  read: read,
                  hasDue: hasDue,
                  hasOverdue: hasOverdue,
                  isArabic: l10n.isArabic,
                  onChanged: (value) => setState(() => _filter = value),
                ),
                const SizedBox(height: 12),
                if (visible.isEmpty)
                  SafeContractsSurface(
                    elevated: false,
                    padding: const EdgeInsets.all(18),
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

  bool _matchesFilter(SafeContractsNotification item) {
    return switch (_filter) {
      _NotificationFilter.all => true,
      _NotificationFilter.unread => !_isRead(item),
      _NotificationFilter.read => _isRead(item),
      _NotificationFilter.paymentDue => _isPaymentDue(item),
      _NotificationFilter.overdue => _isOverdue(item),
    };
  }

  SafeContractsNotification? _firstAttentionItem(
    List<SafeContractsNotification> notifications,
  ) {
    for (final item in notifications) {
      if (!_isRead(item) && _isOverdue(item)) return item;
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
    return SafeContractsPremiumHeader(
      title: isArabic ? 'مركز الإشعارات' : 'Notification Center',
      subtitle: isArabic
          ? 'تنبيهات الدفعات المصرح بها في مكان واحد'
          : 'Authorized payment alerts in one focused inbox',
      leading: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(14),
        ),
        child: const Icon(
          Icons.notifications_active_outlined,
          color: Colors.white,
        ),
      ),
      trailing: Container(
        constraints: const BoxConstraints(minWidth: 36),
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
        decoration: BoxDecoration(
          color: unread > 0
              ? SafeContractsVisual.red.withValues(alpha: 0.92)
              : Colors.white.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(99),
        ),
        child: Text(
          '$unread',
          textAlign: TextAlign.center,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w900,
          ),
        ),
      ),
    );
  }
}

final class _AttentionCard extends StatelessWidget {
  const _AttentionCard({
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
                width: 40,
                height: 40,
                decoration: const BoxDecoration(
                  color: SafeContractsVisual.redSoft,
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.priority_high_rounded,
                  color: SafeContractsVisual.redDeep,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      isArabic ? 'متأخر ويتطلب إجراء' : 'Overdue — action needed',
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                            color: SafeContractsVisual.redDeep,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _template(notification.templateCode, isArabic),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                            fontWeight: FontWeight.w800,
                          ),
                    ),
                    const SizedBox(height: 5),
                    Text(
                      '$paymentLabel • ${notification.scheduledFor}',
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: SafeContractsVisual.muted,
                          ),
                    ),
                  ],
                ),
              ),
              _DirectionalChevron(color: SafeContractsVisual.muted),
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
      padding: const EdgeInsets.all(15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  isArabic ? 'نظرة سريعة' : 'Notification overview',
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
          const SizedBox(height: 14),
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
              const SizedBox(width: 12),
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
    final height = 32.0 + factor.clamp(0.0, 1.0).toDouble() * 36.0;
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
              colors: [color.withValues(alpha: 0.66), color],
            ),
            borderRadius: const BorderRadius.vertical(
              top: Radius.circular(11),
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
    required this.hasDue,
    required this.hasOverdue,
    required this.isArabic,
    required this.onChanged,
  });

  final _NotificationFilter selected;
  final int unread;
  final int read;
  final bool hasDue;
  final bool hasOverdue;
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
          const SizedBox(width: 7),
          _chip(
            _NotificationFilter.unread,
            isArabic ? 'جديد $unread' : 'New $unread',
            Icons.notifications_active_outlined,
            SafeContractsVisual.roseGoldDark,
          ),
          const SizedBox(width: 7),
          _chip(
            _NotificationFilter.read,
            isArabic ? 'مقروء $read' : 'Read $read',
            Icons.done_all_rounded,
            SafeContractsVisual.greenDeep,
          ),
          if (hasDue) ...[
            const SizedBox(width: 7),
            _chip(
              _NotificationFilter.paymentDue,
              isArabic ? 'دفعات مستحقة' : 'Payment due',
              Icons.event_note_outlined,
              SafeContractsVisual.amber,
            ),
          ],
          if (hasOverdue) ...[
            const SizedBox(width: 7),
            _chip(
              _NotificationFilter.overdue,
              isArabic ? 'متأخرة' : 'Overdue',
              Icons.warning_amber_rounded,
              SafeContractsVisual.redDeep,
            ),
          ],
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
    final overdue = _isOverdue(notification);
    final accent = overdue
        ? SafeContractsVisual.redDeep
        : read
            ? SafeContractsVisual.greenDeep
            : SafeContractsVisual.roseGoldDark;
    final soft = overdue
        ? SafeContractsVisual.redSoft
        : read
            ? SafeContractsVisual.greenSoft
            : SafeContractsVisual.roseGoldSoft;
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
              color: read && !overdue
                  ? SafeContractsVisual.outline
                  : accent.withValues(alpha: 0.28),
            ),
            borderRadius:
                BorderRadius.circular(SafeContractsVisual.compactRadius),
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: soft,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  overdue
                      ? Icons.warning_amber_rounded
                      : read
                          ? Icons.notifications_none_outlined
                          : Icons.notifications_active_outlined,
                  color: accent,
                  size: 20,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            _template(notification.templateCode, isArabic),
                            maxLines: 2,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                        ),
                        const SizedBox(width: 7),
                        Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 7,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: soft,
                            borderRadius: BorderRadius.circular(99),
                          ),
                          child: Text(
                            overdue
                                ? (isArabic ? 'متأخر' : 'Overdue')
                                : read
                                    ? (isArabic ? 'مقروء' : 'Read')
                                    : (isArabic ? 'جديد' : 'New'),
                            style: TextStyle(
                              color: accent,
                              fontSize: 10,
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
              const SizedBox(width: 4),
              _DirectionalChevron(color: SafeContractsVisual.muted),
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
        ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 170),
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
        ),
      ],
    );
  }
}

final class _DirectionalChevron extends StatelessWidget {
  const _DirectionalChevron({required this.color});

  final Color color;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 7),
        child: Icon(
          Directionality.of(context) == TextDirection.rtl
              ? Icons.chevron_left_rounded
              : Icons.chevron_right_rounded,
          color: color,
        ),
      );
}

final class _RefreshWarning extends StatelessWidget {
  const _RefreshWarning({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return SafeContractsSurface(
      elevated: false,
      accent: SafeContractsVisual.amber,
      padding: const EdgeInsets.all(11),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
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
    final rtl = Directionality.of(context) == TextDirection.rtl;
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
            icon: Icon(rtl ? Icons.chevron_right : Icons.chevron_left),
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
            icon: Icon(rtl ? Icons.chevron_left : Icons.chevron_right),
            label: Text(l10n.t('Next')),
          ),
        ],
      ),
    );
  }
}

bool _isPaymentDue(SafeContractsNotification notification) {
  final code = notification.templateCode.toLowerCase();
  return code == 'payment_due' ||
      (code.contains('payment') && code.contains('due') && !_isOverdue(notification));
}

bool _isOverdue(SafeContractsNotification notification) {
  final code = notification.templateCode.toLowerCase();
  return code.contains('overdue') || code.contains('late_payment');
}

String _template(String code, bool isArabic) {
  final normalized = code.trim().toLowerCase();
  if (isArabic) {
    return switch (normalized) {
      'payment_due' => 'دفعة مستحقة',
      'payment_overdue' || 'overdue_payment' => 'دفعة متأخرة',
      _ => _humanizeTemplate(normalized),
    };
  }
  return switch (normalized) {
    'payment_due' => 'Payment due',
    'payment_overdue' || 'overdue_payment' => 'Payment overdue',
    _ => _humanizeTemplate(normalized),
  };
}

String _humanizeTemplate(String code) {
  final value = code
      .replaceAll(RegExp(r'[_\-]+'), ' ')
      .replaceAll(RegExp(r'\s+'), ' ')
      .trim();
  if (value.isEmpty) return 'Notification';
  return value[0].toUpperCase() + value.substring(1);
}
