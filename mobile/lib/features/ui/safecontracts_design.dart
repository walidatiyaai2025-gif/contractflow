import 'dart:math' as math;

import 'package:flutter/material.dart';

abstract final class SafeContractsVisual {
  static const background = Color(0xFFF4EFE7);
  static const backgroundRaised = Color(0xFFF9F5EF);
  static const surface = Color(0xFFFFFCF8);
  static const surfaceWarm = Color(0xFFF4E8DC);
  static const ink = Color(0xFF172534);
  static const muted = Color(0xFF74706C);

  static const navy = Color(0xFF0E3353);
  static const navyRaised = Color(0xFF163F63);
  static const navyDeep = Color(0xFF092944);
  static const navySoft = Color(0xFFDCE6EF);

  static const roseGold = Color(0xFFC98A7B);
  static const roseGoldDark = Color(0xFFAE6C61);
  static const roseGoldSoft = Color(0xFFF1D8D0);
  static const champagne = Color(0xFFE8D1B2);

  static const green = Color(0xFF269363);
  static const greenDeep = Color(0xFF167448);
  static const greenSoft = Color(0xFFDDF1E6);
  static const red = Color(0xFFC94545);
  static const redDeep = Color(0xFFA92F35);
  static const redSoft = Color(0xFFF8DEDC);
  static const amber = Color(0xFFD99437);
  static const amberSoft = Color(0xFFFFECD1);

  static const outline = Color(0xFFD8CDC1);
  static const contour = Color(0xFFE5D8CA);

  static const radius = 22.0;
  static const compactRadius = 16.0;

  static const premiumHeaderGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[navyDeep, navy, navyRaised],
  );

  static const roseGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[Color(0xFFF3DDD5), Color(0xFFC98A7B)],
  );
}

final class SafeContractsBackdrop extends StatelessWidget {
  const SafeContractsBackdrop({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(color: SafeContractsVisual.background),
      child: Stack(
        fit: StackFit.expand,
        children: [
          const IgnorePointer(
            child: RepaintBoundary(
              child: CustomPaint(painter: _TopographicPainter()),
            ),
          ),
          child,
        ],
      ),
    );
  }
}

final class SafeContractsPremiumHeader extends StatelessWidget {
  const SafeContractsPremiumHeader({
    required this.title,
    this.subtitle,
    this.leading,
    this.trailing,
    this.compact = false,
    super.key,
  });

  final String title;
  final String? subtitle;
  final Widget? leading;
  final Widget? trailing;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Container(
      width: double.infinity,
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 16 : 20,
        vertical: compact ? 14 : 18,
      ),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
        boxShadow: const [
          BoxShadow(
            color: Color(0x33092944),
            blurRadius: 28,
            offset: Offset(0, 12),
          ),
        ],
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          if (leading != null) ...[
            leading!,
            const SizedBox(width: 13),
          ],
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.3,
                  ),
                ),
                if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                  const SizedBox(height: 3),
                  Text(
                    subtitle!,
                    maxLines: compact ? 1 : 2,
                    overflow: TextOverflow.ellipsis,
                    style: textTheme.bodySmall?.copyWith(
                      color: Colors.white.withValues(alpha: 0.78),
                      height: 1.45,
                    ),
                  ),
                ],
              ],
            ),
          ),
          if (trailing != null) ...[
            const SizedBox(width: 12),
            trailing!,
          ],
        ],
      ),
    );
  }
}

final class SafeContractsSurface extends StatelessWidget {
  const SafeContractsSurface({
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.margin = EdgeInsets.zero,
    this.accent,
    this.elevated = true,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry margin;
  final Color? accent;
  final bool elevated;

  @override
  Widget build(BuildContext context) {
    final radius = BorderRadius.circular(SafeContractsVisual.radius);
    return Container(
      margin: margin,
      decoration: BoxDecoration(
        borderRadius: radius,
        boxShadow: elevated
            ? const [
                BoxShadow(
                  color: Color(0x245A4638),
                  blurRadius: 24,
                  offset: Offset(0, 9),
                ),
              ]
            : const <BoxShadow>[],
      ),
      child: Material(
        color: SafeContractsVisual.surface,
        shape: RoundedRectangleBorder(
          borderRadius: radius,
          side: BorderSide(
            color: accent?.withValues(alpha: 0.58) ??
                SafeContractsVisual.outline.withValues(alpha: 0.82),
            width: accent == null ? 1 : 1.3,
          ),
        ),
        clipBehavior: Clip.antiAlias,
        child: Stack(
          children: [
            if (accent != null)
              PositionedDirectional(
                top: 0,
                start: 0,
                end: 0,
                child: Container(height: 4, color: accent),
              ),
            Padding(padding: padding, child: child),
          ],
        ),
      ),
    );
  }
}

final class SafeContractsMetricCard extends StatelessWidget {
  const SafeContractsMetricCard({
    required this.label,
    required this.value,
    this.caption,
    this.icon,
    this.accent = SafeContractsVisual.navy,
    this.dark = false,
    super.key,
  });

  final String label;
  final String value;
  final String? caption;
  final IconData? icon;
  final Color accent;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final foreground = dark ? Colors.white : SafeContractsVisual.ink;
    final secondary =
        dark ? Colors.white.withValues(alpha: 0.72) : SafeContractsVisual.muted;
    return Container(
      constraints: const BoxConstraints(minHeight: 116),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        gradient: dark ? SafeContractsVisual.premiumHeaderGradient : null,
        color: dark ? null : SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
        border: Border.all(
          color: dark
              ? SafeContractsVisual.navyRaised
              : accent.withValues(alpha: 0.34),
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1D5A4638),
            blurRadius: 16,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: textTheme.labelLarge?.copyWith(
                    color: secondary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              if (icon != null)
                Container(
                  width: 34,
                  height: 34,
                  decoration: BoxDecoration(
                    color: dark
                        ? Colors.white.withValues(alpha: 0.12)
                        : accent.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(11),
                  ),
                  child:
                      Icon(icon, size: 19, color: dark ? Colors.white : accent),
                ),
            ],
          ),
          const SizedBox(height: 14),
          FittedBox(
            fit: BoxFit.scaleDown,
            alignment: AlignmentDirectional.centerStart,
            child: Text(
              value,
              style: textTheme.headlineSmall?.copyWith(
                color: foreground,
                fontWeight: FontWeight.w900,
                letterSpacing: -0.5,
              ),
            ),
          ),
          if (caption != null && caption!.trim().isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              caption!,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: textTheme.bodySmall?.copyWith(color: secondary),
            ),
          ],
        ],
      ),
    );
  }
}

final class SafeContractsSectionTitle extends StatelessWidget {
  const SafeContractsSectionTitle({
    required this.title,
    this.subtitle,
    this.accent = SafeContractsVisual.roseGold,
    super.key,
  });

  final String title;
  final String? subtitle;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 4,
          height: subtitle == null ? 24 : 44,
          decoration: BoxDecoration(
            color: accent,
            borderRadius: BorderRadius.circular(99),
          ),
        ),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: textTheme.headlineSmall?.copyWith(
                  color: SafeContractsVisual.ink,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.5,
                ),
              ),
              if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  subtitle!,
                  style: textTheme.bodyMedium?.copyWith(
                    color: SafeContractsVisual.muted,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

Color safeContractsStatusColor(String? status) {
  return switch (status?.trim().toLowerCase()) {
    'paid' || 'completed' || 'active' => SafeContractsVisual.green,
    'overdue' || 'cancelled' => SafeContractsVisual.red,
    'due' || 'due_soon' || 'partially_paid' => SafeContractsVisual.amber,
    _ => SafeContractsVisual.navy,
  };
}

Color safeContractsStatusSoftColor(String? status) {
  final color = safeContractsStatusColor(status);
  if (color == SafeContractsVisual.green) return SafeContractsVisual.greenSoft;
  if (color == SafeContractsVisual.red) return SafeContractsVisual.redSoft;
  if (color == SafeContractsVisual.amber) return SafeContractsVisual.amberSoft;
  return SafeContractsVisual.navySoft;
}

extension SafeContractsIterableFirstOrNull<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}

final class _TopographicPainter extends CustomPainter {
  const _TopographicPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final paint = Paint()
      ..color = SafeContractsVisual.contour.withValues(alpha: 0.36)
      ..style = PaintingStyle.stroke
      ..strokeWidth = 1;

    _paintCluster(
      canvas,
      paint,
      Offset(size.width * 0.88, size.height * 0.13),
      math.min(size.width, size.height) * 0.34,
    );
    _paintCluster(
      canvas,
      paint,
      Offset(size.width * 0.08, size.height * 0.74),
      math.min(size.width, size.height) * 0.29,
    );
    _paintCluster(
      canvas,
      paint,
      Offset(size.width * 0.82, size.height * 0.88),
      math.min(size.width, size.height) * 0.22,
    );
  }

  void _paintCluster(Canvas canvas, Paint paint, Offset center, double radius) {
    for (var i = 0; i < 7; i++) {
      final scale = 1 - (i * 0.105);
      final path = Path();
      const segments = 48;
      for (var step = 0; step <= segments; step++) {
        final theta = (math.pi * 2 * step) / segments;
        final wobble = 1 +
            0.11 * math.sin(theta * 3 + i * 0.7) +
            0.055 * math.cos(theta * 5 - i * 0.45);
        final x = center.dx + math.cos(theta) * radius * scale * wobble;
        final y = center.dy + math.sin(theta) * radius * scale * 0.72 * wobble;
        if (step == 0) {
          path.moveTo(x, y);
        } else {
          path.lineTo(x, y);
        }
      }
      path.close();
      canvas.drawPath(path, paint);
    }
  }

  @override
  bool shouldRepaint(_TopographicPainter oldDelegate) => false;
}
