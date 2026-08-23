import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../ui/safecontracts_design.dart';
import 'dashboard_controller.dart';
import 'dashboard_models.dart';
import 'monthly_dashboard_summary.dart';

final class DashboardContextScreen extends StatefulWidget {
  const DashboardContextScreen({
    required this.controller,
    required this.currency,
    this.onOpenPayments,
    super.key,
  });

  final DashboardController controller;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;

  @override
  State<DashboardContextScreen> createState() => _DashboardContextScreenState();
}

final class _DashboardContextScreenState extends State<DashboardContextScreen> {
  late final MonthlyDashboardRepository _monthlyRepository;
  late int _year;
  late int _month;
  MonthlyDashboardSnapshot? _snapshot;
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _year = now.year;
    _month = now.month;
    _monthlyRepository =
        MonthlyDashboardRepository(widget.controller.repository.client);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      unawaited(_applyMonth(refreshDashboard: true));
    });
  }

  Future<void> _applyMonth({required bool refreshDashboard}) async {
    setState(() {
      _loading = true;
      _error = null;
    });
    final from = _isoDate(DateTime(_year, _month, 1));
    final to = _isoDate(DateTime(_year, _month + 1, 0));
    try {
      final tasks = <Future<void>>[
        _monthlyRepository.load(year: _year, month: _month).then((value) {
          _snapshot = value;
        }),
        if (refreshDashboard) widget.controller.setDueRange(from, to),
      ];
      await Future.wait<void>(tasks);
    } on Object catch (error) {
      _error = error.toString();
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  Future<void> _refresh() => _applyMonth(refreshDashboard: true);

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final entity = _selectedEntity(widget.controller);
        final contract = _selectedContract(widget.controller);
        return SafeContractsBackdrop(
          child: RefreshIndicator(
            onRefresh: _refresh,
            color: SafeContractsVisual.navy,
            child: ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
              children: [
                _MonthSelector(
                  year: _year,
                  month: _month,
                  busy: _loading,
                  onYearChanged: (value) {
                    setState(() => _year = value);
                    unawaited(_applyMonth(refreshDashboard: true));
                  },
                  onMonthChanged: (value) {
                    setState(() => _month = value);
                    unawaited(_applyMonth(refreshDashboard: true));
                  },
                ),
                const SizedBox(height: 12),
                if (_loading && _snapshot == null)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 40),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (_error != null && _snapshot == null)
                  _MonthlyError(message: _error!, onRetry: _refresh)
                else if (_snapshot != null) ...[
                  _MonthlyCards(snapshot: _snapshot!),
                  if (_error != null) ...[
                    const SizedBox(height: 10),
                    _InlineWarning(message: _error!),
                  ],
                  if (_loading) ...[
                    const SizedBox(height: 10),
                    const LinearProgressIndicator(minHeight: 2),
                  ],
                  const SizedBox(height: 18),
                  _DirectionLane(
                    title: context.scL10n.isArabic
                        ? 'العقود المستحقة لنا'
                        : 'Receivable contracts',
                    subtitle: context.scL10n.isArabic
                        ? 'المبالغ التي نتوقع تحصيلها من العملاء خلال الشهر.'
                        : 'Amounts expected from customers in the selected month.',
                    color: SafeContractsVisual.green,
                    summary: _snapshot!.receivable,
                    positive: true,
                  ),
                  const SizedBox(height: 12),
                  _DirectionLane(
                    title: context.scL10n.isArabic
                        ? 'العقود المستحقة علينا'
                        : 'Payable contracts',
                    subtitle: context.scL10n.isArabic
                        ? 'المبالغ المطلوب سدادها للموردين خلال الشهر.'
                        : 'Amounts payable to suppliers in the selected month.',
                    color: SafeContractsVisual.red,
                    summary: _snapshot!.payable,
                    positive: false,
                  ),
                ],
                if (entity != null) ...[
                  const SizedBox(height: 14),
                  _EntityContextBanner(
                    entityName: entity,
                    contractNumber: contract,
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  String? _selectedEntity(DashboardController controller) {
    final customerId = controller.filters.customerId;
    if (customerId == null) return null;
    final remembered = controller.selectedCustomerName?.trim();
    if (remembered != null && remembered.isNotEmpty) return remembered;
    final customers =
        controller.overview?.customers ?? const <CustomerOption>[];
    for (final customer in customers) {
      if (customer.id == customerId) return customer.name;
    }
    return '#$customerId';
  }

  String? _selectedContract(DashboardController controller) {
    final contractId = controller.filters.contractId;
    if (contractId == null) return null;
    final remembered = controller.selectedContractNumber?.trim();
    if (remembered != null && remembered.isNotEmpty) return remembered;
    for (final contract in controller.availableContracts) {
      if (contract.id == contractId) return contract.contractNumber;
    }
    return '#$contractId';
  }
}

final class _MonthSelector extends StatelessWidget {
  const _MonthSelector({
    required this.year,
    required this.month,
    required this.busy,
    required this.onYearChanged,
    required this.onMonthChanged,
  });

  final int year;
  final int month;
  final bool busy;
  final ValueChanged<int> onYearChanged;
  final ValueChanged<int> onMonthChanged;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final currentYear = DateTime.now().year;
    return SafeContractsSurface(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      elevated: false,
      child: Row(
        children: [
          const Icon(Icons.calendar_month_rounded,
              color: SafeContractsVisual.navy),
          const SizedBox(width: 10),
          Expanded(
            child: DropdownButtonFormField<int>(
              initialValue: year,
              decoration: InputDecoration(
                labelText: ar ? 'السنة' : 'Year',
                isDense: true,
              ),
              items: [
                for (var value = currentYear - 5;
                    value <= currentYear + 2;
                    value++)
                  DropdownMenuItem(value: value, child: Text('$value')),
              ],
              onChanged: busy
                  ? null
                  : (value) => value == null ? null : onYearChanged(value),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: DropdownButtonFormField<int>(
              initialValue: month,
              decoration: InputDecoration(
                labelText: ar ? 'الشهر' : 'Month',
                isDense: true,
              ),
              items: [
                for (var value = 1; value <= 12; value++)
                  DropdownMenuItem(
                    value: value,
                    child: Text(_monthName(value, ar)),
                  ),
              ],
              onChanged: busy
                  ? null
                  : (value) => value == null ? null : onMonthChanged(value),
            ),
          ),
        ],
      ),
    );
  }
}

final class _MonthlyCards extends StatelessWidget {
  const _MonthlyCards({required this.snapshot});

  final MonthlyDashboardSnapshot snapshot;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return LayoutBuilder(
      builder: (context, constraints) {
        final gap = 10.0;
        final columns = constraints.maxWidth >= 760 ? 4 : 2;
        final width = (constraints.maxWidth - gap * (columns - 1)) / columns;
        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [
            SizedBox(
              width: width,
              child: _SplitCard(
                title: ar ? 'العقود' : 'Contracts',
                accent: SafeContractsVisual.champagne,
                left: _CardValue(
                  label: ar ? 'عملاء' : 'Customers',
                  value: '${snapshot.customerContracts}',
                  caption: ar ? 'عقود العملاء' : 'Customer contracts',
                ),
                right: _CardValue(
                  label: ar ? 'موردين' : 'Suppliers',
                  value: '${snapshot.supplierContracts}',
                  caption: ar ? 'عقود الموردين' : 'Supplier contracts',
                ),
              ),
            ),
            SizedBox(
              width: width,
              child: _SplitCard(
                title: ar ? 'المستحق لنا هذا الشهر' : 'Receivable this month',
                accent: SafeContractsVisual.green,
                left: _CardValue(
                  label: ar ? 'دفعات مستحقة' : 'Due payments',
                  value: '${snapshot.receivable.dueCount}',
                  caption: ar ? 'عدد الدفعات' : 'payment(s)',
                  valueColor: SafeContractsVisual.greenDeep,
                ),
                right: _CardValue(
                  label: ar ? 'متوقع الدفع' : 'Expected payment',
                  money: snapshot.receivable.outstanding,
                  caption: ar
                      ? 'إجمالي المتوقع تحصيله'
                      : 'Expected receivable balance',
                  valueColor: SafeContractsVisual.greenDeep,
                ),
              ),
            ),
            SizedBox(
              width: width,
              child: _SplitCard(
                title: ar ? 'المستحق علينا هذا الشهر' : 'Payable this month',
                accent: SafeContractsVisual.red,
                left: _CardValue(
                  label: ar ? 'مبالغ مسددة' : 'Amounts paid',
                  money: snapshot.payable.settled,
                  caption: ar ? 'تم سدادها بالفعل' : 'Already settled',
                  valueColor: SafeContractsVisual.redDeep,
                ),
                right: _CardValue(
                  label: ar ? 'دفعات مستحقة' : 'Amounts still due',
                  money: snapshot.payable.outstanding,
                  caption: ar ? 'إجمالي المستحق' : 'Total outstanding',
                  valueColor: SafeContractsVisual.redDeep,
                ),
              ),
            ),
            SizedBox(
              width: width,
              child: _GeneralAccountCard(
                title: ar ? 'الحساب العام' : 'General account',
                money: snapshot.generalAccount,
                caption: ar
                    ? 'قيمة عقود الشهر ناقص المبالغ التي تم سدادها.'
                    : 'Monthly contract value minus settled amounts.',
              ),
            ),
          ],
        );
      },
    );
  }
}

final class _CardValue {
  const _CardValue({
    required this.label,
    required this.caption,
    this.value,
    this.money,
    this.valueColor,
  });

  final String label;
  final String caption;
  final String? value;
  final List<MonthlyDashboardMoney>? money;
  final Color? valueColor;
}

final class _SplitCard extends StatelessWidget {
  const _SplitCard({
    required this.title,
    required this.accent,
    required this.left,
    required this.right,
  });

  final String title;
  final Color accent;
  final _CardValue left;
  final _CardValue right;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 166),
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: accent.withValues(alpha: .55)),
        boxShadow: const [
          BoxShadow(
              color: Color(0x175A4638),
              blurRadius: 16,
              offset: Offset(0, 6)),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          Container(height: 3, color: accent),
          Padding(
            padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
            child: Text(
              title,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                    color: SafeContractsVisual.ink,
                    fontWeight: FontWeight.w900,
                  ),
            ),
          ),
          const Divider(height: 1, color: SafeContractsVisual.outline),
          Expanded(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Expanded(child: _CardHalf(value: left)),
                const VerticalDivider(
                    width: 1, color: SafeContractsVisual.outline),
                Expanded(child: _CardHalf(value: right)),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _CardHalf extends StatelessWidget {
  const _CardHalf({required this.value});

  final _CardValue value;

  @override
  Widget build(BuildContext context) {
    final money = value.money ?? const <MonthlyDashboardMoney>[];
    return Padding(
      padding: const EdgeInsets.all(10),
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            value.label,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: SafeContractsVisual.muted,
                  fontWeight: FontWeight.w800,
                ),
          ),
          const SizedBox(height: 6),
          if (value.value != null)
            Text(
              value.value!,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: value.valueColor ?? SafeContractsVisual.navy,
                    fontWeight: FontWeight.w900,
                  ),
            )
          else if (money.isEmpty)
            Text(
              '0.00',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    color: value.valueColor ?? SafeContractsVisual.navy,
                    fontWeight: FontWeight.w900,
                  ),
            )
          else
            ...money.take(2).map(
                  (item) => FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: AlignmentDirectional.centerStart,
                    child: Text(
                      item.display,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: value.valueColor ?? SafeContractsVisual.navy,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                  ),
                ),
          const SizedBox(height: 4),
          Text(
            value.caption,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.muted,
                  fontSize: 10,
                ),
          ),
        ],
      ),
    );
  }
}

final class _GeneralAccountCard extends StatelessWidget {
  const _GeneralAccountCard({
    required this.title,
    required this.money,
    required this.caption,
  });

  final String title;
  final List<MonthlyDashboardMoney> money;
  final String caption;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 166),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(18),
        boxShadow: const [
          BoxShadow(
              color: Color(0x33092944),
              blurRadius: 20,
              offset: Offset(0, 8)),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: Colors.white.withValues(alpha: .78),
                  fontWeight: FontWeight.w800,
                ),
          ),
          const Spacer(),
          if (money.isEmpty)
            const Text(
              '0.00',
              style: TextStyle(
                  color: Color(0xFF55D59A),
                  fontSize: 27,
                  fontWeight: FontWeight.w900),
            )
          else
            ...money.take(3).map(
                  (item) => FittedBox(
                    fit: BoxFit.scaleDown,
                    alignment: AlignmentDirectional.centerStart,
                    child: Text(
                      item.display,
                      style: const TextStyle(
                        color: Color(0xFF55D59A),
                        fontSize: 23,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ),
          const SizedBox(height: 7),
          Text(
            caption,
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(
                color: Colors.white.withValues(alpha: .70), fontSize: 10),
          ),
        ],
      ),
    );
  }
}

final class _DirectionLane extends StatelessWidget {
  const _DirectionLane({
    required this.title,
    required this.subtitle,
    required this.color,
    required this.summary,
    required this.positive,
  });

  final String title;
  final String subtitle;
  final Color color;
  final MonthlyDirectionSummary summary;
  final bool positive;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    return SafeContractsSurface(
      accent: color,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  color: color,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 3),
          Text(subtitle,
              style: const TextStyle(color: SafeContractsVisual.muted)),
          const SizedBox(height: 14),
          _LaneMetric(
            label: ar ? 'عدد الدفعات' : 'Payments',
            value: '${summary.paymentCount}',
            color: color,
          ),
          _LaneMoneyMetric(
            label: ar ? 'المجدول' : 'Scheduled',
            values: summary.scheduled,
            color: color,
            sign: positive ? '+' : '−',
          ),
          _LaneMoneyMetric(
            label: ar
                ? (positive ? 'المحصل' : 'المسدد')
                : (positive ? 'Collected' : 'Paid'),
            values: summary.settled,
            color: color,
            sign: positive ? '+' : '−',
          ),
          _LaneMoneyMetric(
            label: ar ? 'المتبقي' : 'Outstanding',
            values: summary.outstanding,
            color: color,
            sign: positive ? '+' : '−',
          ),
        ],
      ),
    );
  }
}

final class _LaneMetric extends StatelessWidget {
  const _LaneMetric(
      {required this.label, required this.value, required this.color});

  final String label;
  final String value;
  final Color color;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          children: [
            Expanded(
                child: Text(label,
                    style: const TextStyle(color: SafeContractsVisual.muted))),
            Text(value,
                style: TextStyle(color: color, fontWeight: FontWeight.w900)),
          ],
        ),
      );
}

final class _LaneMoneyMetric extends StatelessWidget {
  const _LaneMoneyMetric({
    required this.label,
    required this.values,
    required this.color,
    required this.sign,
  });

  final String label;
  final List<MonthlyDashboardMoney> values;
  final Color color;
  final String sign;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 5),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
                child: Text(label,
                    style: const TextStyle(color: SafeContractsVisual.muted))),
            Column(
              crossAxisAlignment: CrossAxisAlignment.end,
              children: values.isEmpty
                  ? <Widget>[
                      Text('$sign 0.00',
                          style: TextStyle(
                              color: color, fontWeight: FontWeight.w900))
                    ]
                  : values
                      .map(
                        (item) => Text(
                          '$sign ${item.display.replaceFirst('− ', '')}',
                          style: TextStyle(
                              color: color, fontWeight: FontWeight.w900),
                        ),
                      )
                      .toList(growable: false),
            ),
          ],
        ),
      );
}

final class _EntityContextBanner extends StatelessWidget {
  const _EntityContextBanner({
    required this.entityName,
    required this.contractNumber,
  });

  final String entityName;
  final String? contractNumber;

  @override
  Widget build(BuildContext context) {
    final isArabic = context.scL10n.isArabic;
    return SafeContractsSurface(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
      accent: SafeContractsVisual.roseGold,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: SafeContractsVisual.roseGoldSoft,
              borderRadius: BorderRadius.circular(14),
            ),
            child: const Icon(Icons.business_rounded,
                color: SafeContractsVisual.navy),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  isArabic ? 'بيانات الجهة' : 'Dashboard entity',
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: SafeContractsVisual.muted,
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 2),
                Text(
                  entityName,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: SafeContractsVisual.navy,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 3),
                Text(
                  contractNumber == null
                      ? (isArabic
                          ? 'كل الأرقام والمؤشرات أدناه مفلترة لهذه الجهة.'
                          : 'All figures and indicators below are filtered for this entity.')
                      : (isArabic
                          ? 'العقد: $contractNumber · كل البيانات أدناه ضمن هذا النطاق.'
                          : 'Contract: $contractNumber · all data below uses this scope.'),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
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

final class _MonthlyError extends StatelessWidget {
  const _MonthlyError({required this.message, required this.onRetry});

  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => SafeContractsSurface(
        accent: SafeContractsVisual.red,
        child: Column(
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: () => unawaited(onRetry()),
              child: Text(context.scL10n.isArabic ? 'إعادة المحاولة' : 'Retry'),
            ),
          ],
        ),
      );
}

final class _InlineWarning extends StatelessWidget {
  const _InlineWarning({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(10),
        decoration: BoxDecoration(
          color: SafeContractsVisual.amberSoft,
          borderRadius: BorderRadius.circular(12),
        ),
        child: Text(message,
            style: const TextStyle(color: SafeContractsVisual.ink)),
      );
}

String _monthName(int month, bool arabic) {
  const en = <String>[
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];
  const ar = <String>[
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
  return (arabic ? ar : en)[month - 1];
}

String _isoDate(DateTime value) =>
    '${value.year.toString().padLeft(4, '0')}-${value.month.toString().padLeft(2, '0')}-${value.day.toString().padLeft(2, '0')}';