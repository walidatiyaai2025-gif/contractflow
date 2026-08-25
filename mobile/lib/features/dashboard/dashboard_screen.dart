import 'dart:async';
import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../ui/safecontracts_design.dart';
import 'dashboard_controller.dart';
import 'dashboard_models.dart';

final class DashboardScreen extends StatelessWidget {
  const DashboardScreen({
    required this.controller,
    this.currency = const MobileCurrencyConfig.defaults(),
    this.onOpenPayments,
    super.key,
  });

  final DashboardController controller;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final overview = controller.overview;
        if (controller.state == DashboardLoadState.loading &&
            overview == null) {
          return const SafeContractsBackdrop(
            child: Center(child: CircularProgressIndicator()),
          );
        }
        if (controller.state == DashboardLoadState.error && overview == null) {
          return SafeContractsBackdrop(
            child: _DashboardError(
              message: l10n.rawMessage(
                controller.errorMessage ?? 'Unable to load dashboard.',
              ),
              onRetry: () => unawaited(controller.refresh()),
            ),
          );
        }
        if (overview == null) {
          return SafeContractsBackdrop(
            child: Center(child: Text(l10n.t('Dashboard is not loaded yet.'))),
          );
        }

        final width = MediaQuery.sizeOf(context).width;
        final horizontalPadding = width <= 360 ? 10.0 : 14.0;
        return SafeContractsBackdrop(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            color: SafeContractsVisual.navy,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.fromLTRB(
                horizontalPadding,
                8,
                horizontalPadding,
                24,
              ),
              children: [
                if (controller.state == DashboardLoadState.loading)
                  const LinearProgressIndicator(
                    minHeight: 2,
                    color: SafeContractsVisual.navy,
                  ),
                if (controller.state == DashboardLoadState.error) ...[
                  _InlineError(
                    message: l10n.rawMessage(
                      controller.errorMessage ?? 'Dashboard refresh failed.',
                    ),
                    onRetry: () => unawaited(controller.refresh()),
                  ),
                  const SizedBox(height: 8),
                ],
                _CompactSummary(kpis: overview.kpis, currency: currency),
                const SizedBox(height: 8),
                _CompactKpiRow(
                  kpis: overview.kpis,
                  currency: currency,
                ),
                const SizedBox(height: 8),
                _GlobalPeriodFilter(controller: controller),
                const SizedBox(height: 8),
                _DashboardTabs(controller: controller),
                const SizedBox(height: 8),
                _TabSwipeRegion(
                  controller: controller,
                  child: _ActiveTab(
                    controller: controller,
                    overview: overview,
                    lists: controller.lists,
                    currency: currency,
                    onOpenPayments: onOpenPayments,
                  ),
                ),
              ],
            ),
          ),
        );
      },
    );
  }
}

final class _CompactSummary extends StatelessWidget {
  const _CompactSummary({required this.kpis, required this.currency});

  final DashboardKpis kpis;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final scheduled = _moneyDouble(kpis.scheduledTotal);
    final collected = _moneyDouble(kpis.collectedTotal);
    final overdue = _moneyDouble(kpis.overdueExposure);
    return SafeContractsSurface(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
      child: Row(
        children: [
          SizedBox.square(
            dimension: 82,
            child: CustomPaint(
              painter: _CompactFinancialRingPainter(
                scheduled: scheduled,
                collected: collected,
                overdue: overdue,
              ),
              child: const Center(
                child: Icon(
                  Icons.account_balance_wallet_outlined,
                  size: 23,
                  color: SafeContractsVisual.navy,
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  _copy(l10n, 'Financial performance', 'الأداء المالي'),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: SafeContractsVisual.navy,
                        fontWeight: FontWeight.w900,
                        fontSize: 15,
                        height: 1.15,
                      ),
                ),
                const SizedBox(height: 5),
                Text(
                  _copy(
                    l10n,
                    'Total account balance',
                    'إجمالي رصيد الحساب',
                  ),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: SafeContractsVisual.muted,
                        fontSize: 10,
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 1),
                FittedBox(
                  fit: BoxFit.scaleDown,
                  alignment: AlignmentDirectional.centerStart,
                  child: Text(
                    l10n.money(kpis.remainingTotal, currency),
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          color: SafeContractsVisual.ink,
                          fontSize: 20,
                          height: 1.05,
                          fontWeight: FontWeight.w900,
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

final class _CompactFinancialRingPainter extends CustomPainter {
  const _CompactFinancialRingPainter({
    required this.scheduled,
    required this.collected,
    required this.overdue,
  });

  final double scheduled;
  final double collected;
  final double overdue;

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final radius = math.min(size.width, size.height) / 2 - 8;
    final track = Paint()
      ..color = SafeContractsVisual.outline.withValues(alpha: 0.55)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 9
      ..strokeCap = StrokeCap.round;
    final collectedPaint = Paint()
      ..color = SafeContractsVisual.green
      ..style = PaintingStyle.stroke
      ..strokeWidth = 9
      ..strokeCap = StrokeCap.round;
    final overduePaint = Paint()
      ..color = SafeContractsVisual.red
      ..style = PaintingStyle.stroke
      ..strokeWidth = 5
      ..strokeCap = StrokeCap.round;
    canvas.drawCircle(center, radius, track);
    final base = math.max(1.0, scheduled);
    final collectedRatio = (collected / base).clamp(0.0, 1.0);
    canvas.drawArc(
      Rect.fromCircle(center: center, radius: radius),
      -math.pi / 2,
      math.pi * 2 * collectedRatio,
      false,
      collectedPaint,
    );
    if (overdue > 0) {
      final overdueRatio = (overdue / base).clamp(0.0, 1.0);
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: radius - 10),
        -math.pi / 2,
        math.pi * 2 * overdueRatio,
        false,
        overduePaint,
      );
    }
  }

  @override
  bool shouldRepaint(_CompactFinancialRingPainter oldDelegate) =>
      oldDelegate.scheduled != scheduled ||
      oldDelegate.collected != collected ||
      oldDelegate.overdue != overdue;
}

final class _CompactKpiRow extends StatelessWidget {
  const _CompactKpiRow({required this.kpis, required this.currency});

  final DashboardKpis kpis;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final items = <_CompactKpi>[
      _CompactKpi(
        label: _copy(l10n, 'Total', 'العقود'),
        value: '${kpis.contractCount}',
        icon: Icons.folder_copy_outlined,
        color: SafeContractsVisual.champagne,
      ),
      _CompactKpi(
        label: _copy(l10n, 'Scheduled', 'المجدول'),
        value: l10n.money(kpis.scheduledTotal, currency),
        icon: Icons.event_note_outlined,
        color: SafeContractsVisual.navy,
      ),
      _CompactKpi(
        label: _copy(l10n, 'Collected', 'المحصل'),
        value: l10n.money(kpis.collectedTotal, currency),
        icon: Icons.south_west_rounded,
        color: SafeContractsVisual.green,
      ),
      _CompactKpi(
        label: _copy(l10n, 'Remaining', 'المتبقي'),
        value: l10n.money(kpis.remainingTotal, currency),
        icon: Icons.schedule_rounded,
        color: SafeContractsVisual.roseGold,
      ),
    ];
    return LayoutBuilder(
      builder: (context, constraints) {
        final compact = constraints.maxWidth < 620;
        final columns = compact ? 2 : 4;
        final spacing = compact ? 8.0 : 6.0;
        final cardWidth =
            (constraints.maxWidth - (spacing * (columns - 1))) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final item in items)
              SizedBox(
                width: cardWidth,
                height: compact ? 94 : 76,
                child: _CompactKpiCard(item: item),
              ),
          ],
        );
      },
    );
  }
}

final class _CompactKpi {
  const _CompactKpi({
    required this.label,
    required this.value,
    required this.icon,
    required this.color,
  });

  final String label;
  final String value;
  final IconData icon;
  final Color color;
}

final class _CompactKpiCard extends StatelessWidget {
  const _CompactKpiCard({required this.item});

  final _CompactKpi item;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 6, vertical: 7),
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(13),
        border: Border.all(color: SafeContractsVisual.outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Row(
              children: [
                Icon(item.icon, size: 15, color: item.color),
                const SizedBox(width: 3),
                Expanded(
                  child: FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: AlignmentDirectional.centerStart,
                    child: Text(
                      item.value,
                      style: const TextStyle(
                        color: SafeContractsVisual.ink,
                        fontWeight: FontWeight.w900,
                        fontSize: 13,
                        height: 1,
                      ),
                    ),
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 3),
          Text(
            item.label,
            maxLines: 2,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: SafeContractsVisual.muted,
                  fontSize: 10.5,
                  height: 1.12,
                  fontWeight: FontWeight.w700,
                ),
          ),
        ],
      ),
    );
  }
}

final class _GlobalPeriodFilter extends StatelessWidget {
  const _GlobalPeriodFilter({required this.controller});

  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final busy = controller.state == DashboardLoadState.loading;
    final currentYear = DateTime.now().year;
    final years = <int>[
      for (var year = currentYear + 1; year >= 2000; year--) year,
    ];
    return SafeContractsSurface(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
      child: Row(
        children: [
          Expanded(
            child: _CompactDropdown<int>(
              icon: Icons.calendar_today_outlined,
              value: controller.selectedYear,
              hint: _copy(l10n, 'Year', 'السنة'),
              enabled: !busy,
              items: [
                _DropdownValue<int>(
                  value: null,
                  label: _copy(l10n, 'All years', 'كل السنوات'),
                ),
                ...years.map(
                  (year) => _DropdownValue<int>(
                    value: year,
                    label: '$year',
                  ),
                ),
              ],
              onChanged: (value) => unawaited(
                controller.selectPeriod(
                  year: value,
                  month: value == null ? null : controller.selectedMonth,
                ),
              ),
            ),
          ),
          const SizedBox(width: 6),
          Expanded(
            child: _CompactDropdown<int>(
              icon: Icons.date_range_outlined,
              value: controller.selectedMonth,
              hint: _copy(l10n, 'Month', 'الشهر'),
              enabled: !busy && controller.selectedYear != null,
              items: [
                _DropdownValue<int>(
                  value: null,
                  label: _copy(l10n, 'All months', 'كل الشهور'),
                ),
                for (var month = 1; month <= 12; month++)
                  _DropdownValue<int>(
                    value: month,
                    label: _monthLabel(l10n, month),
                  ),
              ],
              onChanged: (value) => unawaited(
                controller.selectPeriod(
                  year: controller.selectedYear,
                  month: value,
                ),
              ),
            ),
          ),
          if (controller.selectedYear != null) ...[
            const SizedBox(width: 4),
            IconButton(
              tooltip: _copy(l10n, 'Clear period', 'مسح الفترة'),
              constraints: const BoxConstraints.tightFor(width: 36, height: 36),
              padding: EdgeInsets.zero,
              visualDensity: VisualDensity.compact,
              onPressed:
                  busy ? null : () => unawaited(controller.selectPeriod()),
              icon: const Icon(Icons.close_rounded, size: 18),
            ),
          ],
        ],
      ),
    );
  }
}

final class _DropdownValue<T> {
  const _DropdownValue({required this.value, required this.label});

  final T? value;
  final String label;
}

final class _CompactDropdown<T> extends StatelessWidget {
  const _CompactDropdown({
    required this.icon,
    required this.value,
    required this.hint,
    required this.enabled,
    required this.items,
    required this.onChanged,
  });

  final IconData icon;
  final T? value;
  final String hint;
  final bool enabled;
  final List<_DropdownValue<T>> items;
  final ValueChanged<T?> onChanged;

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 38,
      padding: const EdgeInsetsDirectional.only(start: 8, end: 5),
      decoration: BoxDecoration(
        color: SafeContractsVisual.background.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(11),
        border: Border.all(color: SafeContractsVisual.outline),
      ),
      child: Row(
        children: [
          Icon(icon, size: 15, color: SafeContractsVisual.navy),
          const SizedBox(width: 5),
          Expanded(
            child: DropdownButtonHideUnderline(
              child: DropdownButton<T>(
                value: value,
                isExpanded: true,
                isDense: true,
                hint: Text(hint),
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: SafeContractsVisual.ink,
                      fontSize: 10.5,
                      fontWeight: FontWeight.w700,
                    ),
                onChanged: enabled ? onChanged : null,
                items: items
                    .map(
                      (item) => DropdownMenuItem<T>(
                        value: item.value,
                        child: Text(item.label),
                      ),
                    )
                    .toList(growable: false),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

final class _DashboardTabs extends StatelessWidget {
  const _DashboardTabs({required this.controller});

  final DashboardController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final compact = MediaQuery.sizeOf(context).width <= 360;
    return Container(
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: SafeContractsVisual.outline),
      ),
      child: Row(
        children: [
          for (var index = 0; index < DashboardTab.values.length; index++) ...[
            Expanded(
              child: _DashboardTabButton(
                label: _tabLabel(l10n, DashboardTab.values[index]),
                selected: controller.selectedTab == DashboardTab.values[index],
                compact: compact,
                onTap: () => controller.selectTab(DashboardTab.values[index]),
              ),
            ),
            if (index != DashboardTab.values.length - 1)
              const SizedBox(width: 3),
          ],
        ],
      ),
    );
  }
}

final class _DashboardTabButton extends StatelessWidget {
  const _DashboardTabButton({
    required this.label,
    required this.selected,
    required this.compact,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final bool compact;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      selected: selected,
      button: true,
      child: InkWell(
        borderRadius: BorderRadius.circular(10),
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          height: 35,
          alignment: Alignment.center,
          padding: const EdgeInsets.symmetric(horizontal: 3),
          decoration: BoxDecoration(
            color: selected ? SafeContractsVisual.navy : Colors.transparent,
            borderRadius: BorderRadius.circular(10),
          ),
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: Text(
              label,
              maxLines: 1,
              style: TextStyle(
                color: selected ? Colors.white : SafeContractsVisual.muted,
                fontSize: compact ? 9.2 : 10.5,
                fontWeight: selected ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ),
        ),
      ),
    );
  }
}

final class _TabSwipeRegion extends StatelessWidget {
  const _TabSwipeRegion({required this.controller, required this.child});

  final DashboardController controller;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      behavior: HitTestBehavior.translucent,
      onHorizontalDragEnd: (details) {
        final velocity = details.primaryVelocity ?? 0;
        if (velocity.abs() < 220) return;
        final rtl = Directionality.of(context) == TextDirection.rtl;
        final goNext = rtl ? velocity > 0 : velocity < 0;
        final current = controller.selectedTab.index;
        final next = goNext ? current + 1 : current - 1;
        if (next < 0 || next >= DashboardTab.values.length) return;
        controller.selectTab(DashboardTab.values[next]);
      },
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 180),
        child: KeyedSubtree(
          key: ValueKey<DashboardTab>(controller.selectedTab),
          child: child,
        ),
      ),
    );
  }
}

final class _ActiveTab extends StatelessWidget {
  const _ActiveTab({
    required this.controller,
    required this.overview,
    required this.lists,
    required this.currency,
    required this.onOpenPayments,
  });

  final DashboardController controller;
  final DashboardOverview overview;
  final DashboardLists? lists;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;

  @override
  Widget build(BuildContext context) {
    return switch (controller.selectedTab) {
      DashboardTab.overview => _OverviewTab(
          lists: lists,
          currency: currency,
        ),
      DashboardTab.payments => _PaymentsTab(
          controller: controller,
          overview: overview,
          records: lists?.payments ?? const <DashboardRecord>[],
          currency: currency,
          onOpenPayments: onOpenPayments,
        ),
      DashboardTab.contracts => _RecordsTab(
          title: _copy(context.scL10n, 'Contracts', 'العقود'),
          icon: Icons.folder_copy_outlined,
          records: lists?.contracts ?? const <DashboardRecord>[],
          currency: currency,
        ),
      DashboardTab.collections => _RecordsTab(
          title: _copy(context.scL10n, 'Collections', 'التحصيلات'),
          icon: Icons.task_alt_rounded,
          records: lists?.collections ?? const <DashboardRecord>[],
          currency: currency,
        ),
    };
  }
}

final class _OverviewTab extends StatelessWidget {
  const _OverviewTab({required this.lists, required this.currency});

  final DashboardLists? lists;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final records = <DashboardRecord>[
      ...?lists?.payments,
      ...?lists?.collections,
      ...?lists?.followUps,
    ]..sort((a, b) => (b.date ?? '').compareTo(a.date ?? ''));
    return _RecordsCard(
      title: _copy(l10n, 'Recent activity', 'أحدث النشاطات'),
      icon: Icons.history_rounded,
      records: records.take(6).toList(growable: false),
      currency: currency,
    );
  }
}

final class _PaymentsTab extends StatelessWidget {
  const _PaymentsTab({
    required this.controller,
    required this.overview,
    required this.records,
    required this.currency,
    required this.onOpenPayments,
  });

  final DashboardController controller;
  final DashboardOverview overview;
  final List<DashboardRecord> records;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        _PaymentFilterBar(controller: controller, overview: overview),
        const SizedBox(height: 8),
        _RecordsCard(
          title: _copy(l10n, 'Payments', 'الدفعات'),
          icon: Icons.payments_outlined,
          records: records,
          currency: currency,
        ),
        if (onOpenPayments != null) ...[
          const SizedBox(height: 8),
          SizedBox(
            height: 42,
            child: FilledButton.icon(
              onPressed: onOpenPayments,
              style: FilledButton.styleFrom(
                backgroundColor: SafeContractsVisual.navy,
                foregroundColor: Colors.white,
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
              ),
              icon: const Icon(Icons.open_in_new_rounded, size: 17),
              label: Text(
                _copy(l10n, 'Open payments', 'فتح الدفعات'),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

final class _PaymentFilterBar extends StatelessWidget {
  const _PaymentFilterBar({required this.controller, required this.overview});

  final DashboardController controller;
  final DashboardOverview overview;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final busy = controller.state == DashboardLoadState.loading;
    final currentStatus = controller.filters.status ?? '';
    const orderedStatuses = <String>[
      '',
      'draft',
      'active',
      'upcoming',
      'due_soon',
      'due',
      'overdue',
      'partially_paid',
      'paid',
      'completed',
      'cancelled',
    ];
    final dropdownValue =
        orderedStatuses.contains(currentStatus) ? currentStatus : '';
    final advancedCount = (controller.filters.customerId == null ? 0 : 1) +
        (controller.filters.contractId == null ? 0 : 1);
    return SafeContractsSurface(
      padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
      child: Row(
        children: [
          Expanded(
            child: Container(
              height: 38,
              padding: const EdgeInsetsDirectional.only(start: 8, end: 4),
              decoration: BoxDecoration(
                color: SafeContractsVisual.background.withValues(alpha: 0.55),
                borderRadius: BorderRadius.circular(11),
                border: Border.all(color: SafeContractsVisual.outline),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.bolt_rounded,
                    size: 16,
                    color: SafeContractsVisual.navy,
                  ),
                  const SizedBox(width: 5),
                  Expanded(
                    child: DropdownButtonHideUnderline(
                      child: DropdownButton<String>(
                        value: dropdownValue,
                        isDense: true,
                        isExpanded: true,
                        onChanged: busy
                            ? null
                            : (value) => unawaited(
                                  controller.selectStatus(
                                    value == null || value.isEmpty
                                        ? null
                                        : value,
                                  ),
                                ),
                        style:
                            Theme.of(context).textTheme.labelMedium?.copyWith(
                                  color: SafeContractsVisual.ink,
                                  fontSize: 10.5,
                                  fontWeight: FontWeight.w700,
                                ),
                        items: [
                          DropdownMenuItem(
                            value: '',
                            child: Text(
                              _copy(
                                l10n,
                                'Quick filter: All',
                                'فلتر سريع: الكل',
                              ),
                            ),
                          ),
                          for (final status in orderedStatuses.skip(1))
                            DropdownMenuItem(
                              value: status,
                              child: Text(l10n.status(status)),
                            ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
          const SizedBox(width: 6),
          SizedBox(
            height: 38,
            child: OutlinedButton.icon(
              onPressed: busy
                  ? null
                  : () => _showAdvancedPaymentFilters(
                        context,
                        controller: controller,
                        overview: overview,
                      ),
              style: OutlinedButton.styleFrom(
                padding: const EdgeInsets.symmetric(horizontal: 10),
                foregroundColor: SafeContractsVisual.navy,
                side: const BorderSide(color: SafeContractsVisual.outline),
                shape: RoundedRectangleBorder(
                  borderRadius: BorderRadius.circular(11),
                ),
              ),
              icon: const Icon(Icons.tune_rounded, size: 16),
              label: Text(
                advancedCount == 0
                    ? _copy(l10n, 'Filters', 'فلاتر')
                    : _copy(
                        l10n,
                        'Filters ($advancedCount)',
                        'فلاتر ($advancedCount)',
                      ),
                style: const TextStyle(
                  fontSize: 10,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

Future<void> _showAdvancedPaymentFilters(
  BuildContext context, {
  required DashboardController controller,
  required DashboardOverview overview,
}) async {
  await showModalBottomSheet<void>(
    context: context,
    useSafeArea: true,
    showDragHandle: true,
    isScrollControlled: true,
    backgroundColor: SafeContractsVisual.surface,
    builder: (sheetContext) => _AdvancedPaymentFiltersSheet(
      controller: controller,
      customers: overview.customers,
    ),
  );
}

final class _AdvancedPaymentFiltersSheet extends StatelessWidget {
  const _AdvancedPaymentFiltersSheet({
    required this.controller,
    required this.customers,
  });

  final DashboardController controller;
  final List<CustomerOption> customers;

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final l10n = context.scL10n;
        final busy = controller.state == DashboardLoadState.loading;
        final customerValue = controller.filters.customerId ?? 0;
        final contractValue = controller.filters.contractId ?? 0;
        return Padding(
          padding: EdgeInsets.fromLTRB(
            16,
            0,
            16,
            MediaQuery.viewInsetsOf(context).bottom + 18,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                _copy(l10n, 'Payment filters', 'فلاتر الدفعات'),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: SafeContractsVisual.navy,
                      fontWeight: FontWeight.w900,
                    ),
              ),
              const SizedBox(height: 12),
              _SheetDropdown<int>(
                label: l10n.t('Customer'),
                value: customerValue,
                enabled: !busy,
                items: [
                  _SheetOption<int>(
                    value: 0,
                    label: l10n.t('All customers'),
                  ),
                  ...customers.map(
                    (customer) => _SheetOption<int>(
                      value: customer.id,
                      label: customer.name,
                    ),
                  ),
                ],
                onChanged: (value) => unawaited(
                  controller.selectCustomer(value == 0 ? null : value),
                ),
              ),
              const SizedBox(height: 10),
              _SheetDropdown<int>(
                label: l10n.t('Contract'),
                value: contractValue,
                enabled: !busy,
                items: [
                  _SheetOption<int>(
                    value: 0,
                    label: l10n.t('All contracts'),
                  ),
                  ...controller.availableContracts.map(
                    (contract) => _SheetOption<int>(
                      value: contract.id,
                      label: contract.contractNumber,
                    ),
                  ),
                ],
                onChanged: (value) => unawaited(
                  controller.selectContract(value == 0 ? null : value),
                ),
              ),
              const SizedBox(height: 12),
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: busy ||
                              (controller.filters.customerId == null &&
                                  controller.filters.contractId == null)
                          ? null
                          : () => unawaited(controller.selectCustomer(null)),
                      child: Text(_copy(l10n, 'Clear', 'مسح')),
                    ),
                  ),
                  const SizedBox(width: 8),
                  Expanded(
                    child: FilledButton(
                      onPressed:
                          busy ? null : () => Navigator.of(context).pop(),
                      style: FilledButton.styleFrom(
                        backgroundColor: SafeContractsVisual.navy,
                      ),
                      child: Text(_copy(l10n, 'Done', 'تم')),
                    ),
                  ),
                ],
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _SheetOption<T> {
  const _SheetOption({required this.value, required this.label});

  final T value;
  final String label;
}

final class _SheetDropdown<T> extends StatelessWidget {
  const _SheetDropdown({
    required this.label,
    required this.value,
    required this.enabled,
    required this.items,
    required this.onChanged,
  });

  final String label;
  final T value;
  final bool enabled;
  final List<_SheetOption<T>> items;
  final ValueChanged<T> onChanged;

  @override
  Widget build(BuildContext context) {
    final safeValue =
        items.any((item) => item.value == value) ? value : items.first.value;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
                fontWeight: FontWeight.w800,
              ),
        ),
        const SizedBox(height: 5),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 11),
          decoration: BoxDecoration(
            color: SafeContractsVisual.background.withValues(alpha: 0.55),
            borderRadius: BorderRadius.circular(12),
            border: Border.all(color: SafeContractsVisual.outline),
          ),
          child: DropdownButtonHideUnderline(
            child: DropdownButton<T>(
              value: safeValue,
              isExpanded: true,
              onChanged: enabled
                  ? (value) {
                      if (value != null) onChanged(value);
                    }
                  : null,
              items: items
                  .map(
                    (item) => DropdownMenuItem<T>(
                      value: item.value,
                      child: Text(item.label),
                    ),
                  )
                  .toList(growable: false),
            ),
          ),
        ),
      ],
    );
  }
}

final class _RecordsTab extends StatelessWidget {
  const _RecordsTab({
    required this.title,
    required this.icon,
    required this.records,
    required this.currency,
  });

  final String title;
  final IconData icon;
  final List<DashboardRecord> records;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    return _RecordsCard(
      title: title,
      icon: icon,
      records: records,
      currency: currency,
    );
  }
}

final class _RecordsCard extends StatelessWidget {
  const _RecordsCard({
    required this.title,
    required this.icon,
    required this.records,
    required this.currency,
  });

  final String title;
  final IconData icon;
  final List<DashboardRecord> records;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return SafeContractsSurface(
      padding: const EdgeInsets.fromLTRB(11, 9, 11, 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(icon, size: 18, color: SafeContractsVisual.navy),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: SafeContractsVisual.navy,
                        fontSize: 13,
                        fontWeight: FontWeight.w900,
                      ),
                ),
              ),
              Text(
                '${records.length}',
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: SafeContractsVisual.muted,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          if (records.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 18),
              child: Center(
                child: Text(
                  l10n.t('No records match the current filters.'),
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                ),
              ),
            )
          else
            for (var index = 0; index < records.length; index++) ...[
              _DashboardRecordTile(
                record: records[index],
                currency: currency,
              ),
              if (index != records.length - 1)
                const Divider(height: 10, color: SafeContractsVisual.outline),
            ],
        ],
      ),
    );
  }
}

final class _DashboardRecordTile extends StatelessWidget {
  const _DashboardRecordTile({required this.record, required this.currency});

  final DashboardRecord record;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final color = safeContractsStatusColor(record.status);
    final amount = record.remainingAmount ?? record.amount;
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 31,
            height: 31,
            decoration: BoxDecoration(
              color: safeContractsStatusSoftColor(record.status),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Icon(_activityIcon(record), size: 17, color: color),
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  record.title,
                  style: const TextStyle(
                    color: SafeContractsVisual.ink,
                    fontSize: 11.5,
                    height: 1.2,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                if (record.customerName != null) ...[
                  const SizedBox(height: 1),
                  Text(
                    record.customerName!,
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: SafeContractsVisual.muted,
                          fontSize: 9.5,
                        ),
                  ),
                ],
                const SizedBox(height: 2),
                Wrap(
                  spacing: 7,
                  runSpacing: 2,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    if (record.status != null)
                      Text(
                        l10n.status(record.status!),
                        style: TextStyle(
                          color: color,
                          fontSize: 9,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    if (record.date != null)
                      Text(
                        record.date!,
                        style: const TextStyle(
                          color: SafeContractsVisual.muted,
                          fontSize: 9,
                        ),
                      ),
                    if (amount != null)
                      Text(
                        l10n.money(amount, currency),
                        style: const TextStyle(
                          color: SafeContractsVisual.ink,
                          fontSize: 9.5,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _DashboardError extends StatelessWidget {
  const _DashboardError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(20),
        child: SafeContractsSurface(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.error_outline,
                size: 42,
                color: SafeContractsVisual.red,
              ),
              const SizedBox(height: 9),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 10),
              FilledButton(
                onPressed: onRetry,
                child: Text(context.scL10n.t('Retry')),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _InlineError extends StatelessWidget {
  const _InlineError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
      decoration: BoxDecoration(
        color: SafeContractsVisual.redSoft,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: SafeContractsVisual.red),
      ),
      child: Row(
        children: [
          Expanded(
            child: Text(
              message,
              style: const TextStyle(
                color: SafeContractsVisual.red,
                fontSize: 10,
              ),
            ),
          ),
          TextButton(
            onPressed: onRetry,
            child: Text(context.scL10n.t('Retry')),
          ),
        ],
      ),
    );
  }
}

String _tabLabel(SafeContractsLocalizations l10n, DashboardTab tab) {
  return switch (tab) {
    DashboardTab.overview => _copy(l10n, 'Overview', 'نظرة'),
    DashboardTab.payments => _copy(l10n, 'Payments', 'دفعات'),
    DashboardTab.contracts => _copy(l10n, 'Contracts', 'عقود'),
    DashboardTab.collections => _copy(l10n, 'Collections', 'تحصيل'),
  };
}

String _monthLabel(SafeContractsLocalizations l10n, int month) {
  const english = <String>[
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ];
  const arabic = <String>[
    'يناير',
    'فبراير',
    'مارس',
    'أبريل',
    'مايو',
    'يونيو',
    'يوليو',
    'أغسطس',
    'سبتمبر',
    'أكتوبر',
    'نوفمبر',
    'ديسمبر',
  ];
  return l10n.isArabic ? arabic[month - 1] : english[month - 1];
}

String _copy(SafeContractsLocalizations l10n, String english, String arabic) =>
    l10n.isArabic ? arabic : english;

double _moneyDouble(String value) => double.tryParse(value.trim()) ?? 0;

IconData _activityIcon(DashboardRecord record) {
  return switch (record.type) {
    DashboardRecordType.contract => Icons.folder_copy_outlined,
    DashboardRecordType.payment => Icons.schedule_outlined,
    DashboardRecordType.collection => Icons.check_circle_outline,
    DashboardRecordType.followUp => Icons.notifications_active_outlined,
  };
}
