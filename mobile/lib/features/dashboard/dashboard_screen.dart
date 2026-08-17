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

        final lists = controller.lists;
        return SafeContractsBackdrop(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            color: SafeContractsVisual.navy,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 10, 16, 28),
              children: [
                if (controller.state == DashboardLoadState.loading)
                  const LinearProgressIndicator(
                    minHeight: 2,
                    color: SafeContractsVisual.navy,
                  ),
                const SizedBox(height: 8),
                SafeContractsSectionTitle(
                  title: _copy(l10n, 'Quick Filters', 'فلاتر سريعة'),
                ),
                const SizedBox(height: 12),
                _QuickFilters(controller: controller, overview: overview),
                const SizedBox(height: 22),
                SafeContractsSectionTitle(
                  title: _copy(l10n, 'Filter Payments', 'تصفية الدفعات'),
                ),
                const SizedBox(height: 12),
                _DetailedFilters(controller: controller, overview: overview),
                const SizedBox(height: 22),
                if (controller.state == DashboardLoadState.error) ...[
                  _InlineError(
                    message: l10n.rawMessage(
                      controller.errorMessage ?? 'Dashboard refresh failed.',
                    ),
                  ),
                  const SizedBox(height: 12),
                ],
                _PaymentLifecycleOverview(
                  kpis: overview.kpis,
                  payments: lists?.payments ?? const <DashboardRecord>[],
                  currency: currency,
                ),
                const SizedBox(height: 28),
                if (lists != null && lists.payments.isNotEmpty) ...[
                  SafeContractsSectionTitle(
                    title: _copy(l10n, 'Your Payment Pipeline', 'مسار الدفعات'),
                  ),
                  const SizedBox(height: 14),
                  _PaymentPipeline(
                    payments: lists.payments,
                    currency: currency,
                  ),
                  const SizedBox(height: 28),
                ],
                SafeContractsSectionTitle(
                  title: _copy(l10n, 'Recent Activity', 'أحدث النشاطات'),
                ),
                const SizedBox(height: 12),
                _RecentActivity(
                  lists: lists,
                  currency: currency,
                  onOpenPayments: onOpenPayments,
                ),
                const SizedBox(height: 22),
                _OperationalSections(lists: lists, currency: currency),
              ],
            ),
          ),
        );
      },
    );
  }
}

final class _QuickFilters extends StatelessWidget {
  const _QuickFilters({required this.controller, required this.overview});

  final DashboardController controller;
  final DashboardOverview overview;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final status = controller.filters.status;
    final busy = controller.state == DashboardLoadState.loading;
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      child: Row(
        children: [
          _StatusFilterChip(
            label: _copy(l10n, 'All', 'الكل'),
            icon: Icons.qr_code_2_rounded,
            selected: status == null || status.isEmpty,
            color: SafeContractsVisual.navy,
            onSelected: busy
                ? null
                : () => unawaited(controller.selectStatus(null)),
          ),
          const SizedBox(width: 10),
          _StatusFilterChip(
            label: l10n.status('overdue'),
            icon: Icons.circle,
            selected: status == 'overdue',
            color: SafeContractsVisual.red,
            onSelected: busy
                ? null
                : () => unawaited(controller.selectStatus('overdue')),
          ),
          const SizedBox(width: 10),
          _StatusFilterChip(
            label: l10n.status('paid'),
            icon: Icons.circle,
            selected: status == 'paid',
            color: SafeContractsVisual.green,
            onSelected: busy
                ? null
                : () => unawaited(controller.selectStatus('paid')),
          ),
          const SizedBox(width: 10),
          ActionChip(
            avatar: const Icon(Icons.people_outline, size: 20),
            label: Text(
              controller.filters.customerId == null
                  ? _copy(l10n, 'Customers', 'العملاء')
                  : overview.customers
                            .where(
                              (item) =>
                                  item.id == controller.filters.customerId,
                            )
                            .map((item) => item.name)
                            .firstOrNull ??
                        _copy(l10n, 'Customers', 'العملاء'),
            ),
            backgroundColor: SafeContractsVisual.surface,
            side: const BorderSide(color: SafeContractsVisual.outline),
            shape: const StadiumBorder(),
            onPressed: busy
                ? null
                : () => _showCustomerPicker(
                    context,
                    controller: controller,
                    overview: overview,
                  ),
          ),
        ],
      ),
    );
  }
}

final class _StatusFilterChip extends StatelessWidget {
  const _StatusFilterChip({
    required this.label,
    required this.icon,
    required this.selected,
    required this.color,
    required this.onSelected,
  });

  final String label;
  final IconData icon;
  final bool selected;
  final Color color;
  final VoidCallback? onSelected;

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      selected: selected,
      avatar: Icon(icon, size: 17, color: color),
      label: Text(label),
      selectedColor: color.withValues(alpha: 0.14),
      backgroundColor: SafeContractsVisual.surface,
      side: BorderSide(color: selected ? color : SafeContractsVisual.outline),
      shape: const StadiumBorder(),
      onSelected: onSelected == null ? null : (_) => onSelected!(),
    );
  }
}

Future<void> _showCustomerPicker(
  BuildContext context, {
  required DashboardController controller,
  required DashboardOverview overview,
}) async {
  final l10n = context.scL10n;
  await showModalBottomSheet<void>(
    context: context,
    backgroundColor: SafeContractsVisual.surface,
    showDragHandle: true,
    builder: (sheetContext) => SafeArea(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxHeight: 460),
        child: ListView(
          padding: const EdgeInsets.fromLTRB(16, 0, 16, 20),
          children: [
            ListTile(
              leading: const Icon(Icons.people_alt_outlined),
              title: Text(l10n.t('All customers')),
              selected: controller.filters.customerId == null,
              onTap: () {
                Navigator.of(sheetContext).pop();
                unawaited(controller.selectCustomer(null));
              },
            ),
            ...overview.customers.map(
              (customer) => ListTile(
                leading: const Icon(Icons.person_outline),
                title: Text(customer.name),
                selected: controller.filters.customerId == customer.id,
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  unawaited(controller.selectCustomer(customer.id));
                },
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

final class _DetailedFilters extends StatelessWidget {
  const _DetailedFilters({required this.controller, required this.overview});

  final DashboardController controller;
  final DashboardOverview overview;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final busy = controller.state == DashboardLoadState.loading;
    return SafeContractsSurface(
      padding: EdgeInsets.zero,
      child: ExpansionTile(
        tilePadding: const EdgeInsets.symmetric(horizontal: 18, vertical: 4),
        childrenPadding: const EdgeInsets.fromLTRB(18, 0, 18, 18),
        leading: const Icon(
          Icons.tune_rounded,
          color: SafeContractsVisual.navy,
        ),
        title: Text(
          _copy(l10n, 'Payment filters', 'فلاتر الدفعات'),
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        children: [
          Wrap(
            spacing: 16,
            runSpacing: 12,
            children: [
              _FilterField(
                label: l10n.t('Customer'),
                child: DropdownButton<int>(
                  value: controller.filters.customerId ?? 0,
                  isExpanded: true,
                  underline: const SizedBox.shrink(),
                  onChanged: busy
                      ? null
                      : (value) => unawaited(
                          controller.selectCustomer(value == 0 ? null : value),
                        ),
                  items: <DropdownMenuItem<int>>[
                    DropdownMenuItem(
                      value: 0,
                      child: Text(l10n.t('All customers')),
                    ),
                    ...overview.customers.map(
                      (option) => DropdownMenuItem(
                        value: option.id,
                        child: Text(option.name),
                      ),
                    ),
                  ],
                ),
              ),
              _FilterField(
                label: l10n.t('Contract'),
                child: DropdownButton<int>(
                  value: controller.filters.contractId ?? 0,
                  isExpanded: true,
                  underline: const SizedBox.shrink(),
                  onChanged: busy
                      ? null
                      : (value) => unawaited(
                          controller.selectContract(value == 0 ? null : value),
                        ),
                  items: <DropdownMenuItem<int>>[
                    DropdownMenuItem(
                      value: 0,
                      child: Text(l10n.t('All contracts')),
                    ),
                    ...controller.availableContracts.map(
                      (option) => DropdownMenuItem(
                        value: option.id,
                        child: Text(option.contractNumber),
                      ),
                    ),
                  ],
                ),
              ),
              _FilterField(
                label: l10n.t('Status'),
                child: DropdownButton<String>(
                  value: controller.filters.status ?? '',
                  isExpanded: true,
                  underline: const SizedBox.shrink(),
                  onChanged: busy
                      ? null
                      : (value) => unawaited(
                          controller.selectStatus(
                            value == null || value.isEmpty ? null : value,
                          ),
                        ),
                  items: <DropdownMenuItem<String>>[
                    DropdownMenuItem(
                      value: '',
                      child: Text(l10n.t('All statuses')),
                    ),
                    for (final value in const <String>[
                      'active',
                      'due',
                      'overdue',
                      'partially_paid',
                      'paid',
                    ])
                      DropdownMenuItem(
                        value: value,
                        child: Text(l10n.status(value)),
                      ),
                  ],
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _FilterField extends StatelessWidget {
  const _FilterField({required this.label, required this.child});

  final String label;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 220,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: Theme.of(context).textTheme.labelMedium),
          const SizedBox(height: 6),
          DecoratedBox(
            decoration: BoxDecoration(
              color: SafeContractsVisual.background.withValues(alpha: 0.55),
              borderRadius: BorderRadius.circular(14),
              border: Border.all(color: SafeContractsVisual.outline),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 12),
              child: child,
            ),
          ),
        ],
      ),
    );
  }
}

final class _PaymentLifecycleOverview extends StatelessWidget {
  const _PaymentLifecycleOverview({
    required this.kpis,
    required this.payments,
    required this.currency,
  });

  final DashboardKpis kpis;
  final List<DashboardRecord> payments;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final scheduled = _moneyDouble(kpis.scheduledTotal);
    final collected = _moneyDouble(kpis.collectedTotal);
    final overdue = _moneyDouble(kpis.overdueExposure);
    final remaining = _moneyDouble(kpis.remainingTotal);
    final pendingCount = payments.where((payment) {
      final status = payment.status?.toLowerCase();
      return status != 'paid' && status != 'overdue';
    }).length;

    return SafeContractsSurface(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            _copy(
              l10n,
              'Payment Lifecycle Overview',
              'نظرة عامة على دورة الدفعات',
            ),
            style: Theme.of(context).textTheme.headlineSmall
                ?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 18),
          LayoutBuilder(
            builder: (context, constraints) {
              final chart = SizedBox.square(
                dimension: constraints.maxWidth < 430 ? 210 : 230,
                child: CustomPaint(
                  painter: _LifecycleRingPainter(
                    scheduled: scheduled,
                    collected: collected,
                    overdue: overdue,
                    remaining: remaining,
                  ),
                  child: Center(
                    child: Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.payments_outlined,
                          color: SafeContractsVisual.navy,
                        ),
                        const SizedBox(height: 5),
                        Text(
                          '${payments.length}',
                          style: Theme.of(context).textTheme.headlineMedium
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        Text(
                          l10n.t('Payments'),
                          style: Theme.of(context).textTheme.labelMedium,
                        ),
                      ],
                    ),
                  ),
                ),
              );
              final legend = _LifecycleLegend(
                entries: [
                  _LifecycleMetric(
                    label: _copy(l10n, 'Total Scheduled', 'إجمالي المجدول'),
                    value: l10n.money(kpis.scheduledTotal, currency),
                    icon: Icons.calendar_month_outlined,
                    color: SafeContractsVisual.navy,
                  ),
                  _LifecycleMetric(
                    label: _copy(l10n, 'Total Overdue', 'إجمالي المتأخر'),
                    value: l10n.money(kpis.overdueExposure, currency),
                    icon: Icons.error_outline_rounded,
                    color: SafeContractsVisual.red,
                  ),
                  _LifecycleMetric(
                    label: _copy(l10n, 'Pending', 'قيد الانتظار'),
                    value: _copy(
                      l10n,
                      '$pendingCount payments',
                      '$pendingCount دفعات',
                    ),
                    icon: Icons.remove_circle_outline,
                    color: SafeContractsVisual.amber,
                  ),
                  _LifecycleMetric(
                    label: l10n.t('Collected'),
                    value: l10n.money(kpis.collectedTotal, currency),
                    icon: Icons.check_circle_outline,
                    color: SafeContractsVisual.green,
                  ),
                  _LifecycleMetric(
                    label: l10n.t('Remaining'),
                    value: l10n.money(kpis.remainingTotal, currency),
                    icon: Icons.schedule_outlined,
                    color: SafeContractsVisual.navy,
                  ),
                ],
              );
              if (constraints.maxWidth >= 720) {
                return Row(
                  crossAxisAlignment: CrossAxisAlignment.center,
                  children: [
                    chart,
                    const SizedBox(width: 26),
                    Expanded(child: legend),
                  ],
                );
              }
              return Column(
                children: [chart, const SizedBox(height: 20), legend],
              );
            },
          ),
        ],
      ),
    );
  }
}

final class _LifecycleMetric {
  const _LifecycleMetric({
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

final class _LifecycleLegend extends StatelessWidget {
  const _LifecycleLegend({required this.entries});

  final List<_LifecycleMetric> entries;

  @override
  Widget build(BuildContext context) {
    return Wrap(
      spacing: 12,
      runSpacing: 14,
      children: entries
          .map(
            (entry) => SizedBox(
              width: 190,
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 34,
                    height: 34,
                    decoration: BoxDecoration(
                      color: entry.color.withValues(alpha: 0.14),
                      shape: BoxShape.circle,
                    ),
                    child: Icon(entry.icon, size: 20, color: entry.color),
                  ),
                  const SizedBox(width: 10),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          entry.label,
                          style: Theme.of(context).textTheme.bodyMedium,
                        ),
                        const SizedBox(height: 2),
                        FittedBox(
                          fit: BoxFit.scaleDown,
                          alignment: AlignmentDirectional.centerStart,
                          child: Text(
                            entry.value,
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
          )
          .toList(growable: false),
    );
  }
}

final class _LifecycleRingPainter extends CustomPainter {
  const _LifecycleRingPainter({
    required this.scheduled,
    required this.collected,
    required this.overdue,
    required this.remaining,
  });

  final double scheduled;
  final double collected;
  final double overdue;
  final double remaining;

  @override
  void paint(Canvas canvas, Size size) {
    final center = size.center(Offset.zero);
    final base = math.max(1, scheduled);
    final rings = <(double, Color)>[
      ((remaining / base).clamp(0, 1), SafeContractsVisual.navy),
      ((collected / base).clamp(0, 1), SafeContractsVisual.green),
      ((overdue / base).clamp(0, 1), SafeContractsVisual.red),
    ];
    const strokeWidth = 22.0;
    var radius = math.min(size.width, size.height) / 2 - strokeWidth;
    for (final ring in rings) {
      final track = Paint()
        ..color = SafeContractsVisual.outline.withValues(alpha: 0.35)
        ..style = PaintingStyle.stroke
        ..strokeWidth = strokeWidth
        ..strokeCap = StrokeCap.round;
      final active = Paint()
        ..color = ring.$2
        ..style = PaintingStyle.stroke
        ..strokeWidth = strokeWidth
        ..strokeCap = StrokeCap.round;
      canvas.drawCircle(center, radius, track);
      canvas.drawArc(
        Rect.fromCircle(center: center, radius: radius),
        -math.pi / 2,
        math.pi * 2 * ring.$1,
        false,
        active,
      );
      radius -= 30;
    }
  }

  @override
  bool shouldRepaint(_LifecycleRingPainter oldDelegate) =>
      oldDelegate.scheduled != scheduled ||
      oldDelegate.collected != collected ||
      oldDelegate.overdue != overdue ||
      oldDelegate.remaining != remaining;
}

final class _PaymentPipeline extends StatelessWidget {
  const _PaymentPipeline({required this.payments, required this.currency});

  final List<DashboardRecord> payments;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final ordered = List<DashboardRecord>.of(payments)
      ..sort(
        (a, b) => (a.date ?? '9999-12-31').compareTo(b.date ?? '9999-12-31'),
      );
    final visible = ordered.take(5).toList(growable: false);
    return SingleChildScrollView(
      scrollDirection: Axis.horizontal,
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          for (var index = 0; index < visible.length; index++) ...[
            _PipelineNode(record: visible[index], currency: currency),
            if (index != visible.length - 1)
              Container(
                width: 32,
                height: 3,
                margin: const EdgeInsets.only(top: 47),
                decoration: BoxDecoration(
                  color: SafeContractsVisual.outline,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
          ],
        ],
      ),
    );
  }
}

final class _PipelineNode extends StatelessWidget {
  const _PipelineNode({required this.record, required this.currency});

  final DashboardRecord record;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final color = safeContractsStatusColor(record.status);
    return SizedBox(
      width: 154,
      child: Column(
        children: [
          Container(
            width: 74,
            height: 74,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              color: safeContractsStatusSoftColor(record.status),
              border: Border.all(color: color, width: 3),
              boxShadow: [
                BoxShadow(
                  color: color.withValues(alpha: 0.18),
                  blurRadius: 12,
                  spreadRadius: 2,
                ),
              ],
            ),
            child: Text(
              _compactDate(record.date),
              textAlign: TextAlign.center,
              style: TextStyle(color: color, fontWeight: FontWeight.w800),
            ),
          ),
          Container(width: 2, height: 18, color: color.withValues(alpha: 0.65)),
          Container(
            width: 150,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: SafeContractsVisual.surface,
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: color.withValues(alpha: 0.7)),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x165E5142),
                  blurRadius: 12,
                  offset: Offset(0, 5),
                ),
              ],
            ),
            child: Column(
              children: [
                if (record.customerName != null)
                  Text(
                    record.customerName!,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.labelMedium
                        ?.copyWith(fontWeight: FontWeight.w700),
                  ),
                const SizedBox(height: 4),
                Text(
                  record.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w700),
                ),
                const SizedBox(height: 5),
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(Icons.circle, size: 9, color: color),
                    const SizedBox(width: 5),
                    Flexible(
                      child: Text(
                        l10n.status(record.status ?? 'upcoming'),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
                if ((record.remainingAmount ?? record.amount) != null) ...[
                  const SizedBox(height: 5),
                  FittedBox(
                    child: Text(
                      l10n.money(
                        record.remainingAmount ?? record.amount ?? '',
                        currency,
                      ),
                      style: Theme.of(context).textTheme.labelMedium,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _RecentActivity extends StatelessWidget {
  const _RecentActivity({
    required this.lists,
    required this.currency,
    required this.onOpenPayments,
  });

  final DashboardLists? lists;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final value = lists;
    final records = <DashboardRecord>[
      ...?value?.payments,
      ...?value?.collections,
      ...?value?.followUps,
    ]..sort((a, b) => (b.date ?? '').compareTo(a.date ?? ''));
    final visible = records.take(5).toList(growable: false);
    return SafeContractsSurface(
      child: Column(
        children: [
          if (visible.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 16),
              child: Text(l10n.t('No records match the current filters.')),
            )
          else
            for (var index = 0; index < visible.length; index++) ...[
              _ActivityTile(record: visible[index], currency: currency),
              if (index != visible.length - 1)
                const Divider(height: 22, color: SafeContractsVisual.outline),
            ],
          if (onOpenPayments != null) ...[
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: const Color(0xFF9FD3F3),
                  foregroundColor: SafeContractsVisual.ink,
                  minimumSize: const Size.fromHeight(52),
                  shape: const StadiumBorder(),
                ),
                onPressed: onOpenPayments,
                child: Text(
                  _copy(l10n, 'View All Payments', 'عرض كل الدفعات'),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }
}

final class _ActivityTile extends StatelessWidget {
  const _ActivityTile({required this.record, required this.currency});

  final DashboardRecord record;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final color = safeContractsStatusColor(record.status);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 36,
          height: 36,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: safeContractsStatusSoftColor(record.status),
          ),
          child: Icon(_activityIcon(record), size: 20, color: color),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                record.title,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              if (record.customerName != null) Text(record.customerName!),
              Text(
                [
                  if ((record.remainingAmount ?? record.amount) != null)
                    l10n.money(
                      record.remainingAmount ?? record.amount ?? '',
                      currency,
                    ),
                  if (record.status != null) l10n.status(record.status!),
                  if (record.date != null) record.date!,
                ].join(' • '),
                style: Theme.of(context).textTheme.bodySmall,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

final class _OperationalSections extends StatelessWidget {
  const _OperationalSections({required this.lists, required this.currency});

  final DashboardLists? lists;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final value = lists;
    if (value == null || value.isEmpty) return const SizedBox.shrink();
    return SafeContractsSurface(
      padding: const EdgeInsets.symmetric(vertical: 4),
      child: Column(
        children: [
          _RecordExpansion(
            title: l10n.t('Contracts'),
            records: value.contracts,
            currency: currency,
          ),
          _RecordExpansion(
            title: l10n.t('Collections'),
            records: value.collections,
            currency: currency,
          ),
          _RecordExpansion(
            title: l10n.t('Follow-up'),
            records: value.followUps,
            currency: currency,
          ),
        ],
      ),
    );
  }
}

final class _RecordExpansion extends StatelessWidget {
  const _RecordExpansion({
    required this.title,
    required this.records,
    required this.currency,
  });

  final String title;
  final List<DashboardRecord> records;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    if (records.isEmpty) return const SizedBox.shrink();
    final l10n = context.scL10n;
    return ExpansionTile(
      title: Text('$title (${records.length})'),
      children: records
          .take(8)
          .map(
            (record) => ListTile(
              leading: Icon(
                _activityIcon(record),
                color: safeContractsStatusColor(record.status),
              ),
              title: Text(record.title),
              subtitle: Text(
                [
                  if (record.customerName != null) record.customerName!,
                  if (record.status != null) l10n.status(record.status!),
                  if (record.date != null) record.date!,
                ].join(' • '),
              ),
              trailing: (record.remainingAmount ?? record.amount) == null
                  ? null
                  : Text(
                      l10n.money(
                        record.remainingAmount ?? record.amount ?? '',
                        currency,
                      ),
                    ),
            ),
          )
          .toList(growable: false),
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
        padding: const EdgeInsets.all(24),
        child: SafeContractsSurface(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.error_outline,
                size: 48,
                color: SafeContractsVisual.red,
              ),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 12),
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
  const _InlineError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: SafeContractsVisual.redSoft,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: SafeContractsVisual.red),
      ),
      child: Text(
        message,
        style: const TextStyle(color: SafeContractsVisual.red),
      ),
    );
  }
}

String _copy(SafeContractsLocalizations l10n, String english, String arabic) =>
    l10n.isArabic ? arabic : english;

double _moneyDouble(String value) => double.tryParse(value.trim()) ?? 0;

String _compactDate(String? value) {
  if (value == null || value.isEmpty) return '—';
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return value;
  const months = <String>[
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
  return '${months[parsed.month - 1]}\n${parsed.day}';
}

IconData _activityIcon(DashboardRecord record) {
  return switch (record.type) {
    DashboardRecordType.contract => Icons.folder_copy_outlined,
    DashboardRecordType.payment => Icons.schedule_outlined,
    DashboardRecordType.collection => Icons.check_circle_outline,
    DashboardRecordType.followUp => Icons.notifications_active_outlined,
  };
}
