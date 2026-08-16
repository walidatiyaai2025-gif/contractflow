import 'dart:math' as math;

import 'package:flutter/material.dart';

abstract final class SafeContractsVisual {
  static const background = Color(0xFFF6F0E4);
  static const surface = Color(0xFFFFFEFA);
  static const ink = Color(0xFF25282D);
  static const muted = Color(0xFF6F706F);
  static const navy = Color(0xFF234F7C);
  static const navySoft = Color(0xFFDCE9F8);
  static const green = Color(0xFF50AE7B);
  static const greenSoft = Color(0xFFDDF1E6);
  static const red = Color(0xFFD95F57);
  static const redSoft = Color(0xFFF8E0DE);
  static const amber = Color(0xFFF1A84C);
  static const amberSoft = Color(0xFFFFECD1);
  static const outline = Color(0xFFD8D0C3);
  static const contour = Color(0xFFE4D8C7);

  static const radius = 22.0;
  static const compactRadius = 16.0;
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

final class SafeContractsSurface extends StatelessWidget {
  const SafeContractsSurface({
    required this.child,
    this.padding = const EdgeInsets.all(18),
    this.margin = EdgeInsets.zero,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry margin;

  @override
  Widget build(BuildContext context) {
    return Container(
      margin: margin,
      padding: padding,
      decoration: BoxDecoration(
        color: SafeContractsVisual.surface,
        borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
        border: Border.all(
          color: SafeContractsVisual.outline.withValues(alpha: 0.78),
        ),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1F5E5142),
            blurRadius: 22,
            offset: Offset(0, 8),
          ),
        ],
      ),
      child: child,
    );
  }
}

final class SafeContractsSectionTitle extends StatelessWidget {
  const SafeContractsSectionTitle({
    required this.title,
    this.subtitle,
    super.key,
  });

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Column(
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
      ..color = SafeContractsVisual.contour.withValues(alpha: 0.48)
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
