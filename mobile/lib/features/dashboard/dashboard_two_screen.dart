import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_design.dart';
import 'dashboard_controller.dart';
import 'dashboard_models.dart';

/// Additive Alkenzy ADV 0.3.2 dashboard inspired by the approved premium
/// reference. The existing dashboard remains unchanged and accessible.
final class DashboardTwoScreen extends StatelessWidget {
  const DashboardTwoScreen({
    required this.controller,
    required this.currency,
    super.key,
  });

  final DashboardController controller;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        final overview = controller.overview;
        final lists = controller.lists;
        if (overview == null && controller.state == DashboardLoadState.loading) {
          return const Center(child: CircularProgressIndicator());
        }
        if (overview == null) {
          return _DashboardError(
            message: controller.errorMessage,
            onRetry: controller.refresh,
          );
        }

        final contracts = overview.contracts;
        final customers = contracts.where((item) => item.isCustomer).length;
        final suppliers = contracts.where((item) => item.isSupplier).length;
        final recent = <DashboardRecord>[
          ...?lists?.payments.take(2),
          ...?lists?.contracts.take(2),
          ...?lists?.collections.take(1),
        ].take(4).toList(growable: false);

        return RefreshIndicator(
          onRefresh: controller.refresh,
          color: SafeContractsVisual.roseGold,
          child: ListView(
            physics: const AlwaysScrollableScrollPhysics(),
            padding: const EdgeInsets.fromLTRB(16, 14, 16, 110),
            children: [
              _OverviewCard(
                totalContracts: overview.kpis.contractCount,
                customers: customers,
                suppliers: suppliers,
              ),
              const SizedBox(height: 14),
              _MoneyStrip(
                collected: overview.kpis.collectedTotal,
                remaining: overview.kpis.remainingTotal,
                overdue: overview.kpis.overdueExposure,
                currency: currency,
              ),
              const SizedBox(height: 18),
              _SectionTitle(
                icon: Icons.insights_rounded,
                title: l10n.isArabic ? 'توزيع العقود' : 'Contract mix',
                subtitle: l10n.isArabic
                    ? 'كل أنواع العقود الظاهرة لحسابك'
                    : 'All visible contract counterparty types',
              ),
              const SizedBox(height: 10),
              _ContractMixChart(customers: customers, suppliers: suppliers),
              const SizedBox(height: 18),
              _SectionTitle(
                icon: Icons.history_rounded,
                title: l10n.isArabic ? 'آخر الأنشطة' : 'Recent activity',
                subtitle: l10n.isArabic
                    ? 'العقود والدفعات والتحصيلات من بيانات النظام'
                    : 'Contracts, payments and settlements from system data',
              ),
              const SizedBox(height: 10),
              if (recent.isEmpty)
                _EmptyActivity(arabic: l10n.isArabic)
              else
                ...recent.map((record) => _ActivityTile(record: record)),
            ],
          ),
        );
      },
    );
  }
}

final class _OverviewCard extends StatelessWidget {
  const _OverviewCard({
    required this.totalContracts,
    required this.customers,
    required this.suppliers,
  });

  final int totalContracts;
  final int customers;
  final int suppliers;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    final totalTyped = math.max(1, customers + suppliers);
    final customerRatio = customers / totalTyped;
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF16334D), Color(0xFF32444F), Color(0xFF514B47)],
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: const [
          BoxShadow(
            color: Color(0x2C18354D),
            blurRadius: 24,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                padding: const EdgeInsets.all(9),
                decoration: BoxDecoration(
                  color: Colors.white.withValues(alpha: .10),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(Icons.dashboard_customize_rounded,
                    color: Colors.white),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  arabic ? 'نظرة عامة على التقدم' : 'Executive overview',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 18),
          Row(
            children: [
              Expanded(
                child: _DarkMetric(
                  label: arabic ? 'إجمالي العقود' : 'All contracts',
                  value: '$totalContracts',
                  icon: Icons.folder_copy_rounded,
                ),
              ),
              const SizedBox(width: 14),
              SizedBox(
                width: 122,
                height: 122,
                child: Stack(
                  alignment: Alignment.center,
                  children: [
                    CustomPaint(
                      size: const Size.square(122),
                      painter: _RingPainter(progress: customerRatio),
                    ),
                    Column(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        Text(
                          '$customers',
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 28,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        Text(
                          arabic ? 'عملاء' : 'customers',
                          style: TextStyle(
                            color: Colors.white.withValues(alpha: .72),
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              const Icon(Icons.local_shipping_outlined,
                  color: Color(0xFFE6B09E), size: 18),
              const SizedBox(width: 6),
              Text(
                arabic ? '$suppliers عقود موردين' : '$suppliers supplier contracts',
                style: TextStyle(
                  color: Colors.white.withValues(alpha: .82),
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _DarkMetric extends StatelessWidget {
  const _DarkMetric({
    required this.label,
    required this.value,
    required this.icon,
  });

  final String label;
  final String value;
  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: .10),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white.withValues(alpha: .08)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, color: const Color(0xFFE6B09E), size: 22),
          const SizedBox(height: 10),
          Text(label,
              style: TextStyle(
                  color: Colors.white.withValues(alpha: .72), fontSize: 12)),
          const SizedBox(height: 3),
          Text(value,
              style: const TextStyle(
                  color: Colors.white,
                  fontSize: 30,
                  fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

final class _MoneyStrip extends StatelessWidget {
  const _MoneyStrip({
    required this.collected,
    required this.remaining,
    required this.overdue,
    required this.currency,
  });

  final String collected;
  final String remaining;
  final String overdue;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return Row(
      children: [
        Expanded(
          child: _MoneyChip(
            icon: Icons.south_west_rounded,
            label: arabic ? 'المحصل' : 'Collected',
            value: _money(collected, currency),
            background: const Color(0xFFEAF6EF),
            foreground: const Color(0xFF17704B),
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _MoneyChip(
            icon: Icons.account_balance_wallet_outlined,
            label: arabic ? 'المتبقي' : 'Remaining',
            value: _money(remaining, currency),
            background: const Color(0xFFF2ECE8),
            foreground: SafeContractsVisual.navy,
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: _MoneyChip(
            icon: Icons.warning_amber_rounded,
            label: arabic ? 'متأخر' : 'Overdue',
            value: _money(overdue, currency),
            background: const Color(0xFFFFEDEA),
            foreground: const Color(0xFFA73A31),
          ),
        ),
      ],
    );
  }
}

final class _MoneyChip extends StatelessWidget {
  const _MoneyChip({
    required this.icon,
    required this.label,
    required this.value,
    required this.background,
    required this.foreground,
  });
  final IconData icon;
  final String label;
  final String value;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 92),
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(17),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 19, color: foreground),
          const SizedBox(height: 7),
          Text(label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(color: foreground, fontSize: 10)),
          Text(value,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: TextStyle(
                  color: foreground,
                  fontSize: 12,
                  fontWeight: FontWeight.w900)),
        ],
      ),
    );
  }
}

final class _ContractMixChart extends StatelessWidget {
  const _ContractMixChart({required this.customers, required this.suppliers});
  final int customers;
  final int suppliers;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    final maxValue = math.max(1, math.max(customers, suppliers));
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 18, 16, 14),
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(20),
        border: Border.all(color: SafeContractsVisual.outline),
        boxShadow: const [
          BoxShadow(color: Color(0x145A4638), blurRadius: 18, offset: Offset(0, 7)),
        ],
      ),
      child: SizedBox(
        height: 170,
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
          children: [
            _Bar(
              label: arabic ? 'عملاء' : 'Customers',
              value: customers,
              ratio: customers / maxValue,
              icon: Icons.people_alt_outlined,
              color: const Color(0xFF315E7E),
            ),
            _Bar(
              label: arabic ? 'موردين' : 'Suppliers',
              value: suppliers,
              ratio: suppliers / maxValue,
              icon: Icons.local_shipping_outlined,
              color: const Color(0xFFC78975),
            ),
          ],
        ),
      ),
    );
  }
}

final class _Bar extends StatelessWidget {
  const _Bar({
    required this.label,
    required this.value,
    required this.ratio,
    required this.icon,
    required this.color,
  });
  final String label;
  final int value;
  final double ratio;
  final IconData icon;
  final Color color;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 98,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.end,
        children: [
          Text('$value', style: const TextStyle(fontWeight: FontWeight.w900)),
          const SizedBox(height: 5),
          Container(
            width: 55,
            height: math.max(12, 102 * ratio),
            decoration: BoxDecoration(
              color: color,
              borderRadius: const BorderRadius.vertical(top: Radius.circular(12)),
              boxShadow: [
                BoxShadow(
                  color: color.withValues(alpha: .22),
                  blurRadius: 12,
                  offset: const Offset(0, 5),
                )
              ],
            ),
          ),
          const SizedBox(height: 8),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(icon, size: 15, color: color),
              const SizedBox(width: 4),
              Flexible(
                child: Text(label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontSize: 11, fontWeight: FontWeight.w700)),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _SectionTitle extends StatelessWidget {
  const _SectionTitle({required this.icon, required this.title, required this.subtitle});
  final IconData icon;
  final String title;
  final String subtitle;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: BoxDecoration(
            color: SafeContractsVisual.roseGoldSoft,
            borderRadius: BorderRadius.circular(12),
          ),
          child: Icon(icon, color: SafeContractsVisual.navy, size: 20),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(title,
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: SafeContractsVisual.navy, fontWeight: FontWeight.w900)),
              Text(subtitle,
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: SafeContractsVisual.muted)),
            ],
          ),
        ),
      ],
    );
  }
}

final class _ActivityTile extends StatelessWidget {
  const _ActivityTile({required this.record});
  final DashboardRecord record;

  @override
  Widget build(BuildContext context) {
    final visual = switch (record.type) {
      DashboardRecordType.contract =>
        (Icons.description_outlined, const Color(0xFFB56F5E), const Color(0xFFFFE8E1)),
      DashboardRecordType.payment =>
        (Icons.payments_outlined, const Color(0xFF2B6A73), const Color(0xFFE2F2F3)),
      DashboardRecordType.collection =>
        (Icons.savings_outlined, const Color(0xFF347650), const Color(0xFFE5F3EA)),
      DashboardRecordType.followUp =>
        (Icons.notifications_active_outlined, const Color(0xFF8A6A35), const Color(0xFFFFF1D8)),
    };
    return Container(
      margin: const EdgeInsets.only(bottom: 9),
      padding: const EdgeInsets.all(13),
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(17),
        border: Border.all(color: SafeContractsVisual.outline),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(color: visual.$3, borderRadius: BorderRadius.circular(13)),
            child: Icon(visual.$1, color: visual.$2, size: 21),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(record.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w900)),
                if ((record.customerName ?? '').isNotEmpty)
                  Text(record.customerName!,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(color: SafeContractsVisual.muted, fontSize: 12)),
              ],
            ),
          ),
          if ((record.status ?? '').isNotEmpty)
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
              decoration: BoxDecoration(color: visual.$3, borderRadius: BorderRadius.circular(99)),
              child: Text(record.status!,
                  style: TextStyle(color: visual.$2, fontSize: 10, fontWeight: FontWeight.w800)),
            ),
        ],
      ),
    );
  }
}

final class _EmptyActivity extends StatelessWidget {
  const _EmptyActivity({required this.arabic});
  final bool arabic;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(22),
        decoration: BoxDecoration(
          color: SafeContractsVisual.surface,
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: SafeContractsVisual.outline),
        ),
        child: Text(
          arabic ? 'لا توجد أنشطة ضمن النطاق الحالي.' : 'No recent activity in the current scope.',
          textAlign: TextAlign.center,
          style: const TextStyle(color: SafeContractsVisual.muted),
        ),
      );
}

final class _DashboardError extends StatelessWidget {
  const _DashboardError({required this.message, required this.onRetry});
  final String? message;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.cloud_off_rounded, size: 48, color: SafeContractsVisual.muted),
            const SizedBox(height: 12),
            Text(message ?? 'Dashboard is unavailable.', textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: () => onRetry(),
              icon: const Icon(Icons.refresh_rounded),
              label: Text(context.scL10n.isArabic ? 'إعادة المحاولة' : 'Retry'),
            ),
          ]),
        ),
      );
}

final class _RingPainter extends CustomPainter {
  const _RingPainter({required this.progress});
  final double progress;

  @override
  void paint(Canvas canvas, Size size) {
    final rect = Offset.zero & size;
    final stroke = size.width * .075;
    final track = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round
      ..color = Colors.white.withValues(alpha: .14);
    final foreground = Paint()
      ..style = PaintingStyle.stroke
      ..strokeWidth = stroke
      ..strokeCap = StrokeCap.round
      ..color = const Color(0xFFDCA38F);
    final inset = stroke / 2;
    final arc = rect.deflate(inset);
    canvas.drawArc(arc, -math.pi / 2, math.pi * 2, false, track);
    canvas.drawArc(
      arc,
      -math.pi / 2,
      math.pi * 2 * progress.clamp(0.0, 1.0),
      false,
      foreground,
    );
  }

  @override
  bool shouldRepaint(covariant _RingPainter oldDelegate) =>
      oldDelegate.progress != progress;
}

String _money(String raw, String currency) {
  var value = raw.trim();
  if (value.contains('.')) {
    value = value.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
  }
  return '$value $currency';
}
