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
          return const _FinanceLoading();
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
        if (overview == null) {
          return _FinanceError(
            message: context.scL10n.isArabic
                ? 'لا توجد بيانات مالية متاحة لهذا الحساب.'
                : 'No finance data is available for this account.',
            onRetry: () => unawaited(controller.refresh()),
          );
        }

        return SafeContractsBackdrop(
          child: RefreshIndicator(
            onRefresh: controller.refresh,
            color: SafeContractsVisual.navy,
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.fromLTRB(14, 10, 14, 28),
              children: [
                _FinanceHero(controller: controller),
                if (controller.state == FinanceLoadState.loading) ...[
                  const SizedBox(height: 8),
                  const LinearProgressIndicator(minHeight: 2),
                ],
                if (controller.state == FinanceLoadState.error &&
                    controller.errorMessage != null) ...[
                  const SizedBox(height: 10),
                  _RefreshWarning(
                    message: context.scL10n.rawMessage(
                      controller.errorMessage!,
                    ),
                  ),
                ],
                const SizedBox(height: 14),
                _FinanceFilters(controller: controller, overview: overview),
                const SizedBox(height: 20),
                _FinanceSectionTitle(
                  eyebrow: context.scL10n.isArabic
                      ? 'المركز المالي'
                      : 'Financial position',
                  title: context.scL10n.isArabic
                      ? 'المستحقات والواجب دفعه حسب العملة'
                      : 'Receivables / payables by currency',
                ),
                const SizedBox(height: 10),
                _SummaryGrid(rows: overview.summary),
                if (overview.actionCenter.isNotEmpty) ...[
                  const SizedBox(height: 22),
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
                ],
                const SizedBox(height: 22),
                _FinanceSectionTitle(
                  eyebrow: context.scL10n.isArabic
                      ? 'أعمار الأرصدة'
                      : 'Balance age',
                  title: context.scL10n.isArabic ? 'أعمار الاستحقاق' : 'Aging',
                ),
                const SizedBox(height: 10),
                _AgingGrid(rows: overview.aging, controller: controller),
                if (overview.cashFlow.isNotEmpty) ...[
                  const SizedBox(height: 22),
                  _FinanceSectionTitle(
                    eyebrow: context.scL10n.isArabic
                        ? 'التدفق المتوقع'
                        : 'Expected movement',
                    title: context.scL10n.isArabic
                        ? 'التدفق النقدي القادم'
                        : 'Upcoming cash flow',
                  ),
                  const SizedBox(height: 10),
                  _CashFlowPanel(rows: overview.cashFlow),
                ],
                const SizedBox(height: 22),
                _FinanceSectionTitle(
                  eyebrow: context.scL10n.isArabic
                      ? 'قائمة العمل'
                      : 'Work queue',
                  title: context.scL10n.isArabic
                      ? 'الالتزامات المالية'
                      : 'Financial obligations',
                ),
                const SizedBox(height: 10),
                _ObligationList(rows: controller.obligations),
                const SizedBox(height: 18),
                SafeContractsSurface(
                  elevated: false,
                  padding: const EdgeInsets.all(13),
                  accent: SafeContractsVisual.green,
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.verified_user_outlined,
                        color: SafeContractsVisual.greenDeep,
                      ),
                      const SizedBox(width: 9),
                      Expanded(
                        child: Text(
                          context.scL10n.isArabic
                              ? 'الأرصدة وحالات السداد وأعمار الاستحقاق محسوبة على الخادم. التطبيق يعرض القيم المصرح بها كما وردت ولا ينشئ حسابات مالية موازية.'
                              : 'Balances, settlement states and aging are computed by the server. Mobile presents the authorized values and does not create parallel accounting calculations.',
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: SafeContractsVisual.muted,
                              ),
                        ),
                      ),
                    ],
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

final class _FinanceHero extends StatelessWidget {
  const _FinanceHero({required this.controller});

  final FinanceController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return SafeContractsPremiumHeader(
      title: l10n.isArabic ? 'المالية' : 'Finance',
      subtitle: l10n.isArabic
          ? 'مستحقات العملاء وواجبات الموردين منفصلة حسب الصلاحية والعملة'
          : 'Customer receivables and supplier payables stay separated by authorization and currency',
      leading: Container(
        width: 44,
        height: 44,
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: 0.12),
          borderRadius: BorderRadius.circular(14),
        ),
        child: const Icon(
          Icons.account_balance_wallet_outlined,
          color: Colors.white,
        ),
      ),
      trailing: IconButton(
        tooltip: l10n.t('Refresh'),
        onPressed: controller.state == FinanceLoadState.loading
            ? null
            : () => unawaited(controller.refresh()),
        icon: const Icon(Icons.refresh_rounded, color: Colors.white),
      ),
    );
  }
}

final class _FinanceFilters extends StatelessWidget {
  const _FinanceFilters({required this.controller, required this.overview});

  final FinanceController controller;
  final FinanceOverview overview;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
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
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            l10n.isArabic ? 'عرض مالي' : 'Financial view',
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: SafeContractsVisual.ink,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 9),
          Wrap(
            spacing: 7,
            runSpacing: 7,
            children: [
              ChoiceChip(
                label: Text(l10n.isArabic ? 'الكل' : 'All'),
                selected: controller.direction.isEmpty,
                onSelected: controller.state == FinanceLoadState.loading
                    ? null
                    : (_) => unawaited(controller.setDirection('')),
              ),
              if (controller.canViewReceivables)
                ChoiceChip(
                  label: Text(
                    l10n.isArabic ? 'مستحقات العملاء AR' : 'Receivables AR',
                  ),
                  selected: controller.direction == 'receivable',
                  onSelected: controller.state == FinanceLoadState.loading
                      ? null
                      : (_) => unawaited(
                            controller.setDirection('receivable'),
                          ),
                ),
              if (controller.canViewPayables)
                ChoiceChip(
                  label: Text(
                    l10n.isArabic ? 'واجبة الدفع AP' : 'Payables AP',
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
                label: Text(l10n.isArabic ? 'متأخر' : 'Overdue'),
                selected: controller.status == 'overdue',
                onSelected: controller.state == FinanceLoadState.loading
                    ? null
                    : (selected) => unawaited(
                          controller.setStatus(selected ? 'overdue' : ''),
                        ),
              ),
              if (hasFilters)
                ActionChip(
                  avatar: const Icon(Icons.filter_alt_off_rounded, size: 17),
                  label: Text(l10n.t('Clear')),
                  onPressed: controller.state == FinanceLoadState.loading
                      ? null
                      : () {
                          controller.clearFilters();
                          unawaited(controller.refresh());
                        },
                ),
            ],
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
        final columns = width >= 1050
            ? 3
            : width >= 650
                ? 2
                : 1;
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
  Widget build(BuildContext context) {
    final payable = row.direction == 'payable';
    final accent = payable
        ? SafeContractsVisual.roseGoldDark
        : SafeContractsVisual.greenDeep;
    final soft = payable
        ? SafeContractsVisual.roseGoldSoft
        : SafeContractsVisual.greenSoft;
    return SafeContractsSurface(
      padding: const EdgeInsets.all(15),
      accent: accent,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: soft,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  payable
                      ? Icons.north_east_rounded
                      : Icons.south_west_rounded,
                  color: accent,
                ),
              ),
              const SizedBox(width: 10),
              Expanded(child: _DirectionLabel(direction: row.direction)),
              Text(
                row.currencyCode,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: SafeContractsVisual.muted,
                      fontWeight: FontWeight.w900,
                    ),
              ),
            ],
          ),
          const SizedBox(height: 14),
          Text(
            context.scL10n.isArabic ? 'المتبقي' : 'Outstanding',
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
          const SizedBox(height: 2),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: AlignmentDirectional.centerStart,
            child: Text(
              _amount(context, row.currencyCode, row.outstandingTotal),
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: accent,
                    fontWeight: FontWeight.w900,
                  ),
            ),
          ),
          const SizedBox(height: 12),
          _FinanceMetric(
            label: payable
                ? (context.scL10n.isArabic ? 'مدفوعة' : 'Paid')
                : (context.scL10n.isArabic ? 'محصّل' : 'Collected'),
            value: _amount(context, row.currencyCode, row.settledTotal),
          ),
          _FinanceMetric(
            label: context.scL10n.isArabic ? 'متأخرة' : 'Overdue',
            value: _amount(context, row.currencyCode, row.overdueTotal),
            alert: row.overdueCount > 0,
          ),
          _FinanceMetric(
            label: context.scL10n.isArabic ? 'خلال 7 أيام' : 'Due in 7 days',
            value: _amount(context, row.currencyCode, row.due7Total),
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
}

final class _ActionCenter extends StatelessWidget {
  const _ActionCenter({required this.items, required this.controller});

  final List<FinanceActionItem> items;
  final FinanceController controller;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Column(
          children: [
            for (final item in items)
              ListTile(
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 13,
                  vertical: 3,
                ),
                leading: Container(
                  width: 38,
                  height: 38,
                  decoration: BoxDecoration(
                    color: item.kind == 'overdue'
                        ? SafeContractsVisual.redSoft
                        : SafeContractsVisual.amberSoft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Icon(
                    item.kind == 'overdue'
                        ? Icons.warning_amber_rounded
                        : Icons.schedule_rounded,
                    color: item.kind == 'overdue'
                        ? SafeContractsVisual.redDeep
                        : SafeContractsVisual.amber,
                  ),
                ),
                title: Text(
                  _actionLabel(context, item),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                subtitle: Text(
                  '${item.count} ${context.scL10n.isArabic ? 'بنود' : 'items'}',
                ),
                trailing: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 130),
                  child: Text(
                    _amount(context, item.currencyCode, item.amount),
                    textAlign: TextAlign.end,
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w900),
                  ),
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
            ? 'لا توجد أرصدة مستحقة لعرض أعمار الاستحقاق الحالي.'
            : 'No outstanding balances for the current aging view.',
      );
    }
    return Wrap(
      spacing: 8,
      runSpacing: 8,
      children: [
        for (final row in rows)
          ActionChip(
            avatar: Icon(
              row.bucket == 'current'
                  ? Icons.check_circle_outline_rounded
                  : Icons.timelapse_rounded,
              size: 18,
              color: row.bucket == 'current'
                  ? SafeContractsVisual.greenDeep
                  : SafeContractsVisual.roseGoldDark,
            ),
            label: Text(
              '${_directionShort(row.direction)} · ${row.currencyCode} · ${_agingLabel(context, row.bucket)}\n${_compactMoney(row.outstandingTotal)} · ${row.obligationCount}',
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

final class _CashFlowPanel extends StatelessWidget {
  const _CashFlowPanel({required this.rows});

  final List<FinanceCashFlowRow> rows;

  @override
  Widget build(BuildContext context) {
    final visible = rows.take(20).toList(growable: false);
    return SafeContractsSurface(
      padding: const EdgeInsets.all(13),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          _CashFlowChart(rows: visible.take(7).toList(growable: false)),
          const SizedBox(height: 12),
          const Divider(height: 1),
          const SizedBox(height: 5),
          for (final row in visible)
            ListTile(
              dense: true,
              contentPadding: const EdgeInsets.symmetric(horizontal: 2),
              leading: Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: row.kind == 'inflow'
                      ? SafeContractsVisual.greenSoft
                      : SafeContractsVisual.roseGoldSoft,
                  borderRadius: BorderRadius.circular(10),
                ),
                child: Icon(
                  row.kind == 'inflow'
                      ? Icons.south_west_rounded
                      : Icons.north_east_rounded,
                  size: 18,
                  color: row.kind == 'inflow'
                      ? SafeContractsVisual.greenDeep
                      : SafeContractsVisual.roseGoldDark,
                ),
              ),
              title: Text(row.dueDate),
              subtitle: Text(
                '${_directionShort(row.direction)} • ${row.obligationCount} ${context.scL10n.isArabic ? 'بنود' : 'items'}',
              ),
              trailing: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 130),
                child: Text(
                  _amount(context, row.currencyCode, row.expectedAmount),
                  textAlign: TextAlign.end,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(fontWeight: FontWeight.w900),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

final class _CashFlowChart extends StatelessWidget {
  const _CashFlowChart({required this.rows});

  final List<FinanceCashFlowRow> rows;

  @override
  Widget build(BuildContext context) {
    if (rows.isEmpty) return const SizedBox.shrink();
    final values = rows
        .map((row) => double.tryParse(row.expectedAmount)?.abs() ?? 0)
        .toList(growable: false);
    final maxValue = values.fold<double>(0, (max, value) {
      return value > max ? value : max;
    });

    return SizedBox(
      height: 126,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          for (var index = 0; index < rows.length; index++) ...[
            if (index > 0) const SizedBox(width: 6),
            Expanded(
              child: Column(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  Expanded(
                    child: Align(
                      alignment: Alignment.bottomCenter,
                      child: FractionallySizedBox(
                        heightFactor: maxValue <= 0
                            ? 0.08
                            : (values[index] / maxValue)
                                .clamp(0.08, 1.0)
                                .toDouble(),
                        widthFactor: 0.72,
                        child: DecoratedBox(
                          decoration: BoxDecoration(
                            color: rows[index].kind == 'inflow'
                                ? SafeContractsVisual.green
                                : SafeContractsVisual.roseGold,
                            borderRadius: const BorderRadius.vertical(
                              top: Radius.circular(8),
                            ),
                          ),
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    _shortDate(rows[index].dueDate),
                    maxLines: 1,
                    overflow: TextOverflow.fade,
                    softWrap: false,
                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                          color: SafeContractsVisual.muted,
                        ),
                  ),
                ],
              ),
            ),
          ],
        ],
      ),
    );
  }
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
      padding: const EdgeInsets.symmetric(vertical: 5),
      child: Column(
        children: [
          for (final row in rows)
            ListTile(
              contentPadding: const EdgeInsets.symmetric(
                horizontal: 13,
                vertical: 5,
              ),
              leading: Container(
                width: 40,
                height: 40,
                decoration: BoxDecoration(
                  color: row.direction == 'payable'
                      ? SafeContractsVisual.roseGoldSoft
                      : SafeContractsVisual.greenSoft,
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Icon(
                  row.counterpartyType == 'supplier'
                      ? Icons.local_shipping_outlined
                      : Icons.business_outlined,
                  color: row.direction == 'payable'
                      ? SafeContractsVisual.roseGoldDark
                      : SafeContractsVisual.greenDeep,
                ),
              ),
              title: Text(
                row.counterpartyName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const SizedBox(height: 3),
                  Text(
                    '${row.contractNumber} • ${row.reference ?? '#${row.sequenceNo}'}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                  const SizedBox(height: 4),
                  Wrap(
                    spacing: 6,
                    runSpacing: 4,
                    children: [
                      _MiniTag(
                        text: row.direction == 'payable'
                            ? (context.scL10n.isArabic
                                ? 'واجبة الدفع'
                                : 'Payable')
                            : (context.scL10n.isArabic
                                ? 'مستحقة'
                                : 'Receivable'),
                        color: row.direction == 'payable'
                            ? SafeContractsVisual.roseGoldDark
                            : SafeContractsVisual.greenDeep,
                      ),
                      _MiniTag(
                        text: context.scL10n.status(row.status),
                        color: safeContractsStatusColor(row.status),
                      ),
                      _MiniTag(
                        text: row.dueDate,
                        color: SafeContractsVisual.navy,
                      ),
                    ],
                  ),
                ],
              ),
              trailing: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 126),
                child: Text(
                  _amount(context, row.currencyCode, row.remainingAmount),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.end,
                  style: TextStyle(
                    color: row.direction == 'payable'
                        ? SafeContractsVisual.roseGoldDark
                        : SafeContractsVisual.greenDeep,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

final class _MiniTag extends StatelessWidget {
  const _MiniTag({required this.text, required this.color});

  final String text;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 3),
        decoration: BoxDecoration(
          color: color.withValues(alpha: 0.09),
          borderRadius: BorderRadius.circular(99),
        ),
        child: Text(
          text,
          style: TextStyle(
            color: color,
            fontSize: 10,
            fontWeight: FontWeight.w800,
          ),
        ),
      );
}

final class _DirectionLabel extends StatelessWidget {
  const _DirectionLabel({required this.direction});

  final String direction;

  @override
  Widget build(BuildContext context) => Text(
        direction == 'payable'
            ? (context.scL10n.isArabic ? 'واجبة الدفع AP' : 'Payables AP')
            : (context.scL10n.isArabic ? 'مستحقات العملاء AR' : 'Receivables AR'),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: SafeContractsVisual.ink,
              fontWeight: FontWeight.w900,
            ),
      );
}

final class _FinanceMetric extends StatelessWidget {
  const _FinanceMetric({
    required this.label,
    required this.value,
    this.alert = false,
  });

  final String label;
  final String value;
  final bool alert;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 4),
        child: Row(
          children: [
            Expanded(
              child: Text(
                label,
                style: const TextStyle(color: SafeContractsVisual.muted),
              ),
            ),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.end,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: alert ? SafeContractsVisual.redDeep : null,
                ),
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
  Widget build(BuildContext context) => SafeContractsSectionTitle(
        title: title,
        subtitle: eyebrow,
      );
}

final class _EmptyFinance extends StatelessWidget {
  const _EmptyFinance({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        elevated: false,
        padding: const EdgeInsets.all(18),
        child: Column(
          children: [
            Container(
              width: 46,
              height: 46,
              decoration: BoxDecoration(
                color: SafeContractsVisual.navySoft,
                borderRadius: BorderRadius.circular(14),
              ),
              child: const Icon(
                Icons.account_balance_wallet_outlined,
                color: SafeContractsVisual.navy,
              ),
            ),
            const SizedBox(height: 10),
            Text(text, textAlign: TextAlign.center),
          ],
        ),
      );
}

final class _RefreshWarning extends StatelessWidget {
  const _RefreshWarning({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        elevated: false,
        accent: SafeContractsVisual.amber,
        padding: const EdgeInsets.all(12),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(
              Icons.sync_problem_rounded,
              color: SafeContractsVisual.amber,
            ),
            const SizedBox(width: 9),
            Expanded(
              child: Text(
                message,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: SafeContractsVisual.muted,
                    ),
              ),
            ),
          ],
        ),
      );
}

final class _FinanceLoading extends StatelessWidget {
  const _FinanceLoading();

  @override
  Widget build(BuildContext context) => SafeContractsBackdrop(
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 28),
          children: [
            SafeContractsPremiumHeader(
              title: context.scL10n.isArabic ? 'المالية' : 'Finance',
              subtitle: context.scL10n.isArabic
                  ? 'جارٍ تحميل المركز المالي المصرح به…'
                  : 'Loading your authorized financial position…',
              leading: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.account_balance_wallet_outlined,
                  color: Colors.white,
                ),
              ),
            ),
            const SizedBox(height: 14),
            const _FinanceSkeleton(height: 78),
            const SizedBox(height: 12),
            const _FinanceSkeleton(height: 190),
            const SizedBox(height: 12),
            const _FinanceSkeleton(height: 150),
          ],
        ),
      );
}

final class _FinanceSkeleton extends StatelessWidget {
  const _FinanceSkeleton({required this.height});

  final double height;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        elevated: false,
        child: SizedBox(
          height: height,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 126,
                height: 14,
                decoration: BoxDecoration(
                  color: SafeContractsVisual.navySoft,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
              const SizedBox(height: 12),
              Expanded(
                child: Container(
                  decoration: BoxDecoration(
                    color: SafeContractsVisual.backgroundRaised,
                    borderRadius: BorderRadius.circular(14),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
}

final class _FinanceError extends StatelessWidget {
  const _FinanceError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => SafeContractsBackdrop(
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(14, 10, 14, 28),
          children: [
            SafeContractsPremiumHeader(
              title: context.scL10n.isArabic ? 'المالية' : 'Finance',
              subtitle: context.scL10n.isArabic
                  ? 'تعذر تحميل البيانات المالية'
                  : 'Financial data could not be loaded',
              leading: Container(
                width: 44,
                height: 44,
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: 0.12),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: const Icon(
                  Icons.account_balance_wallet_outlined,
                  color: Colors.white,
                ),
              ),
            ),
            const SizedBox(height: 16),
            SafeContractsSurface(
              accent: SafeContractsVisual.red,
              child: Column(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: BoxDecoration(
                      color: SafeContractsVisual.redSoft,
                      borderRadius: BorderRadius.circular(15),
                    ),
                    child: const Icon(
                      Icons.error_outline_rounded,
                      color: SafeContractsVisual.redDeep,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Text(message, textAlign: TextAlign.center),
                  const SizedBox(height: 14),
                  OutlinedButton.icon(
                    onPressed: onRetry,
                    icon: const Icon(Icons.refresh_rounded),
                    label: Text(context.scL10n.t('Retry')),
                  ),
                ],
              ),
            ),
          ],
        ),
      );
}

String _amount(BuildContext context, String currency, String amount) {
  final value = _compactMoney(amount);
  if (currency == 'UNSET' || currency.trim().isEmpty) return value;
  return context.scL10n.isArabic ? '$value $currency' : '$currency $value';
}

String _compactMoney(String raw) {
  final value = raw.trim();
  final match = RegExp(r'^(-?)(\d+)(?:\.(\d+))?$').firstMatch(value);
  if (match == null) return value;
  final sign = match.group(1) ?? '';
  final whole = match.group(2)!;
  var fraction = match.group(3) ?? '';
  fraction = fraction.replaceFirst(RegExp(r'0+$'), '');
  final buffer = StringBuffer();
  for (var index = 0; index < whole.length; index++) {
    if (index > 0 && (whole.length - index) % 3 == 0) {
      buffer.write(',');
    }
    buffer.write(whole[index]);
  }
  return '$sign${buffer.toString()}${fraction.isEmpty ? '' : '.$fraction'}';
}

String _directionShort(String direction) =>
    direction == 'payable' ? 'AP' : 'AR';

String _shortDate(String value) {
  final parts = value.split('-');
  if (parts.length != 3) return value;
  return '${parts[1]}/${parts[2]}';
}

String _agingLabel(BuildContext context, String bucket) {
  if (!context.scL10n.isArabic) return bucket;
  return switch (bucket) {
    'current' => 'حالي',
    '1-30' => '1–30 يوم',
    '31-60' => '31–60 يوم',
    '61-90' => '61–90 يوم',
    '90+' => 'أكثر من 90 يوم',
    _ => bucket,
  };
}

String _actionLabel(BuildContext context, FinanceActionItem item) {
  final subject = item.direction == 'payable'
      ? (context.scL10n.isArabic ? 'الواجب دفعه' : 'Payables')
      : (context.scL10n.isArabic ? 'المستحقات' : 'Receivables');
  return switch (item.kind) {
    'overdue' =>
      context.scL10n.isArabic ? '$subject المتأخر' : '$subject overdue',
    'due_today' => context.scL10n.isArabic
        ? '$subject المستحق اليوم'
        : '$subject due today',
    'due_7_days' => context.scL10n.isArabic
        ? '$subject خلال 7 أيام'
        : '$subject due in 7 days',
    _ => subject,
  };
}
