import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_design.dart';
import 'finance.dart';

final class FinanceScreen extends StatefulWidget {
  const FinanceScreen({required this.controller, super.key});

  final FinanceController controller;

  @override
  State<FinanceScreen> createState() => _FinanceScreenState();
}

final class _FinanceScreenState extends State<FinanceScreen> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  void didUpdateWidget(FinanceScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      unawaited(widget.controller.ensureLoaded());
    }
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final controller = widget.controller;
        if (controller.state == FinanceLoadState.loading &&
            controller.overview == null) {
          return const Center(child: CircularProgressIndicator());
        }
        if (controller.state == FinanceLoadState.error &&
            controller.overview == null) {
          return _FinanceError(
            message: context.scL10n.rawMessage(
              controller.errorMessage ?? 'Unable to load finance data.',
            ),
            onRetry: () => unawaited(controller.refresh()),
          );
        }
        final overview = controller.overview;
        if (overview == null) return const SizedBox.shrink();
        return RefreshIndicator(
          onRefresh: controller.refresh,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 28),
            children: [
              _FinanceHero(controller: controller),
              const SizedBox(height: 14),
              _FinanceFilters(controller: controller, overview: overview),
              if (controller.state == FinanceLoadState.loading) ...[
                const SizedBox(height: 8),
                const LinearProgressIndicator(),
              ],
              if (controller.state == FinanceLoadState.error) ...[
                const SizedBox(height: 8),
                MaterialBanner(
                  content: Text(
                    context.scL10n.rawMessage(
                      controller.errorMessage ?? 'Refresh failed.',
                    ),
                  ),
                  actions: const [SizedBox.shrink()],
                ),
              ],
              const SizedBox(height: 18),
              _FinanceSectionTitle(
                eyebrow: context.scL10n.isArabic
                    ? 'المركز المالي'
                    : 'Financial position',
                title: context.scL10n.isArabic
                    ? 'الدائن والمدين حسب العملة'
                    : 'AP / AR by currency',
              ),
              const SizedBox(height: 10),
              _SummaryGrid(rows: overview.summary),
              const SizedBox(height: 22),
              if (overview.actionCenter.isNotEmpty) ...[
                _FinanceSectionTitle(
                  eyebrow: context.scL10n.isArabic
                      ? 'يحتاج متابعة'
                      : 'Needs attention',
                  title: context.scL10n.isArabic
                      ? 'مركز الإجراءات المالية'
                      : 'Finance Action Center',
                ),
                const SizedBox(height: 10),
                _ActionCenter(
                  items: overview.actionCenter,
                  controller: controller,
                ),
                const SizedBox(height: 22),
              ],
              _FinanceSectionTitle(
                eyebrow:
                    context.scL10n.isArabic ? 'أعمار الأرصدة' : 'Balance age',
                title: context.scL10n.isArabic ? 'Aging' : 'Aging',
              ),
              const SizedBox(height: 10),
              _AgingGrid(
                rows: overview.aging,
                controller: controller,
              ),
              const SizedBox(height: 22),
              if (overview.cashFlow.isNotEmpty) ...[
                _FinanceSectionTitle(
                  eyebrow: context.scL10n.isArabic
                      ? 'التدفق المتوقع'
                      : 'Expected movement',
                  title: context.scL10n.isArabic
                      ? 'التدفق النقدي القادم'
                      : 'Upcoming cash flow',
                ),
                const SizedBox(height: 10),
                _CashFlowList(rows: overview.cashFlow),
                const SizedBox(height: 22),
              ],
              _FinanceSectionTitle(
                eyebrow:
                    context.scL10n.isArabic ? 'قائمة العمل' : 'Work queue',
                title: context.scL10n.isArabic
                    ? 'الالتزامات المالية'
                    : 'Financial obligations',
              ),
              const SizedBox(height: 10),
              _ObligationList(rows: controller.obligations),
              const SizedBox(height: 18),
              SafeContractsSurface(
                padding: const EdgeInsets.all(14),
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Icon(
                      Icons.verified_user_outlined,
                      color: SafeContractsVisual.green,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        context.scL10n.isArabic
                            ? 'الأرصدة وحالات السداد وAging محسوبة على الخادم. التطبيق يعرض البيانات المصرح بها فقط ولا يعيد حسابها.'
                            : 'Balances, settlement states and Aging are computed by the server. The app only presents authorized values and does not recompute them.',
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _FinanceHero extends StatelessWidget {
  const _FinanceHero({required this.controller});

  final FinanceController controller;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        padding: const EdgeInsets.all(20),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 54,
              height: 54,
              decoration: BoxDecoration(
                color: SafeContractsVisual.navySoft,
                borderRadius: BorderRadius.circular(18),
              ),
              child: const Icon(
                Icons.account_balance_wallet_outlined,
                color: SafeContractsVisual.navy,
              ),
            ),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    context.scL10n.isArabic ? 'المالية' : 'Finance',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                  const SizedBox(height: 5),
                  Text(
                    context.scL10n.isArabic
                        ? 'الحسابات المدينة والدائنة منفصلة حسب الصلاحية والعملة.'
                        : 'Accounts Receivable and Accounts Payable stay separated by authorization and currency.',
                    style: TextStyle(color: SafeContractsVisual.muted),
                  ),
                  const SizedBox(height: 10),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: [
                      if (controller.canViewReceivables)
                        _DirectionChip(direction: 'receivable'),
                      if (controller.canViewPayables)
                        _DirectionChip(direction: 'payable'),
                    ],
                  ),
                ],
              ),
            ),
            IconButton(
              tooltip: context.scL10n.t('Refresh'),
              onPressed: controller.state == FinanceLoadState.loading
                  ? null
                  : () => unawaited(controller.refresh()),
              icon: const Icon(Icons.refresh_rounded),
            ),
          ],
        ),
      );
}

final class _FinanceFilters extends StatelessWidget {
  const _FinanceFilters({required this.controller, required this.overview});

  final FinanceController controller;
  final FinanceOverview overview;

  @override
  Widget build(BuildContext context) {
    final currencies = <String>{
      for (final row in overview.summary) row.currencyCode,
      for (final row in overview.aging) row.currencyCode,
    }.toList()
      ..sort();
    final hasFilters = controller.direction.isNotEmpty ||
        controller.currencyCode.isNotEmpty ||
        controller.status.isNotEmpty ||
        controller.agingBucket.isNotEmpty;
    return SafeContractsSurface(
      padding: const EdgeInsets.all(14),
      child: Wrap(
        spacing: 8,
        runSpacing: 8,
        crossAxisAlignment: WrapCrossAlignment.center,
        children: [
          ChoiceChip(
            label: Text(context.scL10n.isArabic ? 'الكل' : 'All'),
            selected: controller.direction.isEmpty,
            onSelected: controller.state == FinanceLoadState.loading
                ? null
                : (_) => unawaited(controller.setDirection('')),
          ),
          if (controller.canViewReceivables)
            ChoiceChip(
              label: Text(
                context.scL10n.isArabic ? 'مدين AR' : 'Receivable AR',
              ),
              selected: controller.direction == 'receivable',
              onSelected: controller.state == FinanceLoadState.loading
                  ? null
                  : (_) => unawaited(controller.setDirection('receivable')),
            ),
          if (controller.canViewPayables)
            ChoiceChip(
              label: Text(
                context.scL10n.isArabic ? 'دائن AP' : 'Payable AP',
              ),
              selected: controller.direction == 'payable',
              onSelected: controller.state == FinanceLoadState.loading
                  ? null
                  : (_) => unawaited(controller.setDirection('payable')),
            ),
          for (final currency in currencies)
            FilterChip(
              label: Text(currency),
              selected: controller.currencyCode == currency,
              onSelected: controller.state == FinanceLoadState.loading
                  ? null
                  : (selected) => unawaited(
                        controller.setCurrency(selected ? currency : ''),
                      ),
            ),
          FilterChip(
            label: Text(
              context.scL10n.isArabic ? 'متأخر' : 'Overdue',
            ),
            selected: controller.status == 'overdue',
            onSelected: controller.state == FinanceLoadState.loading
                ? null
                : (selected) => unawaited(
                      controller.setStatus(selected ? 'overdue' : ''),
                    ),
          ),
          if (hasFilters)
            TextButton.icon(
              onPressed: controller.state == FinanceLoadState.loading
                  ? null
                  : () {
                      controller.clearFilters();
                      unawaited(controller.refresh());
                    },
              icon: const Icon(Icons.filter_alt_off_rounded),
              label: Text(context.scL10n.t('Clear')),
            ),
        ],
      ),
    );
  }
}

final class _SummaryGrid extends StatelessWidget {
  const _SummaryGrid({required this.rows});

  final List<FinanceSummaryRow> rows;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) {
      return _EmptyFinance(
        text: context.scL10n.isArabic
            ? 'لا توجد التزامات مالية ضمن النطاق المصرح به.'
            : 'No financial obligations match the authorized scope.',
      );
    }
    return LayoutBuilder(
      builder: (context, constraints) {
        final width = constraints.maxWidth;
        final columns = width >= 1050 ? 3 : width >= 650 ? 2 : 1;
        final cardWidth = (width - ((columns - 1) * 12)) / columns;
        return Wrap(
          spacing: 12,
          runSpacing: 12,
          children: [
            for (final row in rows)
              SizedBox(
                width: cardWidth,
                child: _SummaryCard(row: row),
              ),
          ],
        );
      },
    );
  }
}

final class _SummaryCard extends StatelessWidget {
  const _SummaryCard({required this.row});

  final FinanceSummaryRow row;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Expanded(child: _DirectionChip(direction: row.direction)),
                Text(
                  row.currencyCode,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                ),
              ],
            ),
            const SizedBox(height: 14),
            _FinanceMetric(
              label: context.scL10n.isArabic ? 'المتبقي' : 'Outstanding',
              value: _amount(row.currencyCode, row.outstandingTotal),
              emphasized: true,
            ),
            _FinanceMetric(
              label: row.direction == 'payable'
                  ? (context.scL10n.isArabic ? 'المدفوع' : 'Paid')
                  : (context.scL10n.isArabic ? 'المستلم' : 'Received'),
              value: _amount(row.currencyCode, row.settledTotal),
            ),
            _FinanceMetric(
              label: context.scL10n.isArabic ? 'المتأخر' : 'Overdue',
              value: _amount(row.currencyCode, row.overdueTotal),
              alert: row.overdueCount > 0,
            ),
            _FinanceMetric(
              label: context.scL10n.isArabic ? 'خلال 7 أيام' : 'Due in 7 days',
              value: _amount(row.currencyCode, row.due7Total),
            ),
            const SizedBox(height: 6),
            Text(
              '${row.obligationCount} ${context.scL10n.isArabic ? 'التزام' : 'obligations'}',
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                    color: SafeContractsVisual.muted,
                  ),
            ),
          ],
        ),
      );
}

final class _ActionCenter extends StatelessWidget {
  const _ActionCenter({required this.items, required this.controller});

  final List<FinanceActionItem> items;
  final FinanceController controller;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        padding: const EdgeInsets.all(10),
        child: Column(
          children: [
            for (final item in items)
              ListTile(
                leading: Icon(
                  item.kind == 'overdue'
                      ? Icons.warning_amber_rounded
                      : Icons.schedule_rounded,
                  color: item.kind == 'overdue'
                      ? SafeContractsVisual.red
                      : SafeContractsVisual.amber,
                ),
                title: Text(_actionLabel(context, item)),
                subtitle: Text(
                  '${item.currencyCode} • ${item.count} ${context.scL10n.isArabic ? 'بنود' : 'items'}',
                ),
                trailing: Text(
                  _amount(item.currencyCode, item.amount),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
                onTap: controller.state == FinanceLoadState.loading
                    ? null
                    : () => unawaited(controller.applyAction(item)),
              ),
          ],
        ),
      );
}

final class _AgingGrid extends StatelessWidget {
  const _AgingGrid({required this.rows, required this.controller});

  final List<FinanceAgingRow> rows;
  final FinanceController controller;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) {
      return _EmptyFinance(
        text: context.scL10n.isArabic
            ? 'لا توجد أرصدة مستحقة للـ Aging الحالي.'
            : 'No outstanding balances for the current Aging view.',
      );
    }
    return Wrap(
      spacing: 10,
      runSpacing: 10,
      children: [
        for (final row in rows)
          ActionChip(
            avatar: Icon(
              row.bucket == 'current'
                  ? Icons.check_circle_outline_rounded
                  : Icons.timelapse_rounded,
              size: 18,
            ),
            label: Text(
              '${_directionShort(row.direction)} · ${row.currencyCode} · ${row.bucket}\n${_amount(row.currencyCode, row.outstandingTotal)} · ${row.obligationCount}',
            ),
            onPressed: controller.state == FinanceLoadState.loading
                ? null
                : () {
                    if (controller.allowedDirections.contains(row.direction)) {
                      controller.direction = row.direction;
                      controller.currencyCode = row.currencyCode;
                      unawaited(controller.setAgingBucket(row.bucket));
                    }
                  },
          ),
      ],
    );
  }
}

final class _CashFlowList extends StatelessWidget {
  const _CashFlowList({required this.rows});

  final List<FinanceCashFlowRow> rows;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        padding: const EdgeInsets.symmetric(vertical: 6),
        child: Column(
          children: [
            for (final row in rows.take(20))
              ListTile(
                dense: true,
                leading: Icon(
                  row.kind == 'inflow'
                      ? Icons.south_west_rounded
                      : Icons.north_east_rounded,
                  color: row.kind == 'inflow'
                      ? SafeContractsVisual.green
                      : SafeContractsVisual.amber,
                ),
                title: Text(row.dueDate),
                subtitle: Text(
                  '${_directionShort(row.direction)} • ${row.currencyCode} • ${row.obligationCount}',
                ),
                trailing: Text(
                  _amount(row.currencyCode, row.expectedAmount),
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
          ],
        ),
      );
}

final class _ObligationList extends StatelessWidget {
  const _ObligationList({required this.rows});

  final List<FinanceObligation> rows;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) {
      return _EmptyFinance(
        text: context.scL10n.isArabic
            ? 'لا توجد التزامات مطابقة للفلاتر الحالية.'
            : 'No obligations match the current filters.',
      );
    }
    return SafeContractsSurface(
      padding: const EdgeInsets.symmetric(vertical: 6),
      child: Column(
        children: [
          for (final row in rows)
            ListTile(
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 14,
                vertical: 5,
              ),
              leading: CircleAvatar(
                backgroundColor: row.direction == 'payable'
                    ? SafeContractsVisual.amberSoft
                    : SafeContractsVisual.greenSoft,
                child: Icon(
                  row.counterpartyType == 'supplier'
                      ? Icons.local_shipping_outlined
                      : Icons.business_outlined,
                  color: row.direction == 'payable'
                      ? SafeContractsVisual.amber
                      : SafeContractsVisual.green,
                ),
              ),
              title: Text(row.counterpartyName),
              subtitle: Text(
                '${row.contractNumber} • ${row.reference ?? '#${row.sequenceNo}'}\n${row.dueDate} • ${row.agingBucket} • ${row.status}',
              ),
              isThreeLine: true,
              trailing: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                crossAxisAlignment: CrossAxisAlignment.end,
                children: [
                  Text(
                    _amount(row.currencyCode, row.remainingAmount),
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    row.direction == 'payable'
                        ? (context.scL10n.isArabic ? 'دائن AP' : 'AP')
                        : (context.scL10n.isArabic ? 'مدين AR' : 'AR'),
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: SafeContractsVisual.muted,
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

final class _DirectionChip extends StatelessWidget {
  const _DirectionChip({required this.direction});

  final String direction;

  @override
  Widget build(BuildContext context) => Chip(
        avatar: Icon(
          direction == 'payable'
              ? Icons.north_east_rounded
              : Icons.south_west_rounded,
          size: 16,
          color: direction == 'payable'
              ? SafeContractsVisual.amber
              : SafeContractsVisual.green,
        ),
        label: Text(
          direction == 'payable'
              ? (context.scL10n.isArabic ? 'دائن AP' : 'Payable AP')
              : (context.scL10n.isArabic ? 'مدين AR' : 'Receivable AR'),
        ),
      );
}

final class _FinanceMetric extends StatelessWidget {
  const _FinanceMetric({
    required this.label,
    required this.value,
    this.emphasized = false,
    this.alert = false,
  });

  final String label;
  final String value;
  final bool emphasized;
  final bool alert;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: TextStyle(color: SafeContractsVisual.muted),
              ),
            ),
            Text(
              value,
              style: TextStyle(
                fontWeight: emphasized ? FontWeight.w900 : FontWeight.w700,
                color: alert ? SafeContractsVisual.red : null,
              ),
            ),
          ],
        ),
      );
}

final class _FinanceSectionTitle extends StatelessWidget {
  const _FinanceSectionTitle({required this.eyebrow, required this.title});

  final String eyebrow;
  final String title;

  @override
  Widget build(BuildContext context) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            eyebrow.toUpperCase(),
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: SafeContractsVisual.muted,
                  fontWeight: FontWeight.w800,
                  letterSpacing: .8,
                ),
          ),
          const SizedBox(height: 3),
          Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
        ],
      );
}

final class _EmptyFinance extends StatelessWidget {
  const _EmptyFinance({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: [
            const Icon(Icons.inbox_outlined),
            const SizedBox(width: 10),
            Expanded(child: Text(text)),
          ],
        ),
      );
}

final class _FinanceError extends StatelessWidget {
  const _FinanceError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline_rounded, size: 44),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              OutlinedButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: Text(context.scL10n.t('Retry')),
              ),
            ],
          ),
        ),
      );
}

String _amount(String currency, String amount) => '$currency $amount';
String _directionShort(String direction) =>
    direction == 'payable' ? 'AP' : 'AR';

String _actionLabel(BuildContext context, FinanceActionItem item) {
  final subject = item.direction == 'payable'
      ? (context.scL10n.isArabic ? 'الدائن' : 'Payables')
      : (context.scL10n.isArabic ? 'المدين' : 'Receivables');
  return switch (item.kind) {
    'overdue' => context.scL10n.isArabic ? '$subject المتأخر' : '$subject overdue',
    'due_today' => context.scL10n.isArabic
        ? '$subject المستحق اليوم'
        : '$subject due today',
    'due_7_days' => context.scL10n.isArabic
        ? '$subject خلال 7 أيام'
        : '$subject due in 7 days',
    _ => subject,
  };
}
