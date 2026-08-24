import 'package:flutter/material.dart';

import 'safecontracts_design.dart';

/// Geometry, spacing and reusable presentation primitives derived from the
/// locked Alkenzy ADV mobile references. Business colors remain sourced from
/// [SafeContractsVisual] so the application keeps one color authority.
abstract final class AlkenzyReferenceTokens {
  static const pagePadding = 18.0;
  static const compactPagePadding = 14.0;
  static const sectionGap = 14.0;
  static const itemGap = 10.0;
  static const fieldGap = 12.0;
  static const cardRadius = 18.0;
  static const compactRadius = 14.0;
  static const pillRadius = 999.0;
  static const bottomActionHeight = 52.0;
  static const navHeight = 70.0;

  static const softShadow = <BoxShadow>[
    BoxShadow(
      color: Color(0x185A4638),
      blurRadius: 18,
      offset: Offset(0, 7),
    ),
  ];

  static const premiumShadow = <BoxShadow>[
    BoxShadow(
      color: Color(0x30092944),
      blurRadius: 28,
      offset: Offset(0, 12),
    ),
  ];
}

final class AlkenzyReferencePage extends StatelessWidget {
  const AlkenzyReferencePage({
    required this.child,
    this.background = SafeContractsVisual.background,
    this.padding,
    this.safeArea = true,
    super.key,
  });

  final Widget child;
  final Color background;
  final EdgeInsetsGeometry? padding;
  final bool safeArea;

  @override
  Widget build(BuildContext context) {
    final body = Padding(
      padding: padding ??
          const EdgeInsets.symmetric(
            horizontal: AlkenzyReferenceTokens.pagePadding,
            vertical: 14,
          ),
      child: child,
    );
    return ColoredBox(
      color: background,
      child: safeArea ? SafeArea(child: body) : body,
    );
  }
}

final class AlkenzyReferenceTopBar extends StatelessWidget {
  const AlkenzyReferenceTopBar({
    required this.title,
    this.leading,
    this.trailing,
    this.dark = false,
    super.key,
  });

  final String title;
  final Widget? leading;
  final Widget? trailing;
  final bool dark;

  @override
  Widget build(BuildContext context) {
    final foreground = dark ? Colors.white : SafeContractsVisual.ink;
    return SizedBox(
      height: 50,
      child: Row(
        children: [
          SizedBox(width: 42, child: leading),
          Expanded(
            child: Text(
              title,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    color: foreground,
                    fontWeight: FontWeight.w900,
                  ),
            ),
          ),
          SizedBox(width: 42, child: trailing),
        ],
      ),
    );
  }
}

final class AlkenzyReferenceCard extends StatelessWidget {
  const AlkenzyReferenceCard({
    required this.child,
    this.padding = const EdgeInsets.all(14),
    this.accent,
    this.background = SafeContractsVisual.surface,
    this.radius = AlkenzyReferenceTokens.cardRadius,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color? accent;
  final Color background;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return Container(
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(radius),
        border: Border.all(
          color: accent?.withValues(alpha: 0.34) ??
              SafeContractsVisual.outline.withValues(alpha: 0.72),
        ),
        boxShadow: AlkenzyReferenceTokens.softShadow,
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        children: [
          if (accent != null)
            PositionedDirectional(
              top: 0,
              bottom: 0,
              start: 0,
              child: Container(width: 3, color: accent),
            ),
          Padding(padding: padding, child: child),
        ],
      ),
    );
  }
}

final class AlkenzyReferencePill extends StatelessWidget {
  const AlkenzyReferencePill({
    required this.label,
    this.icon,
    this.selected = false,
    this.tone = SafeContractsVisual.navy,
    super.key,
  });

  final String label;
  final IconData? icon;
  final bool selected;
  final Color tone;

  @override
  Widget build(BuildContext context) {
    final foreground = selected ? Colors.white : tone;
    return AnimatedContainer(
      duration: const Duration(milliseconds: 160),
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 7),
      decoration: BoxDecoration(
        color: selected ? tone : tone.withValues(alpha: 0.09),
        borderRadius: BorderRadius.circular(AlkenzyReferenceTokens.pillRadius),
        border: Border.all(color: tone.withValues(alpha: selected ? 0 : 0.18)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: 15, color: foreground),
            const SizedBox(width: 5),
          ],
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: foreground,
                  fontWeight: FontWeight.w800,
                ),
          ),
        ],
      ),
    );
  }
}

final class AlkenzyReferencePrimaryButton extends StatelessWidget {
  const AlkenzyReferencePrimaryButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.accent = false,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool accent;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      height: AlkenzyReferenceTokens.bottomActionHeight,
      width: double.infinity,
      child: FilledButton.icon(
        onPressed: onPressed,
        style: FilledButton.styleFrom(
          backgroundColor:
              accent ? SafeContractsVisual.roseGoldDark : SafeContractsVisual.navy,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius:
                BorderRadius.circular(AlkenzyReferenceTokens.compactRadius),
          ),
        ),
        icon: icon == null ? const SizedBox.shrink() : Icon(icon, size: 20),
        label: Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
      ),
    );
  }
}

final class AlkenzyReferenceFeatureTile extends StatelessWidget {
  const AlkenzyReferenceFeatureTile({
    required this.icon,
    required this.title,
    required this.description,
    super.key,
  });

  final IconData icon;
  final String title;
  final String description;

  @override
  Widget build(BuildContext context) {
    return AlkenzyReferenceCard(
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.center,
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: SafeContractsVisual.surfaceWarm,
              borderRadius: BorderRadius.circular(13),
              border: Border.all(color: SafeContractsVisual.champagne),
            ),
            child: const IconTheme(
              data: IconThemeData(color: SafeContractsVisual.navy, size: 22),
              child: SizedBox(),
            ),
          ),
          Transform.translate(
            offset: const Offset(-42, 0),
            child: SizedBox(
              width: 42,
              height: 42,
              child: Icon(icon, color: SafeContractsVisual.navy, size: 22),
            ),
          ),
          const SizedBox(width: -30),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: SafeContractsVisual.ink,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 3),
                Text(
                  description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
                        height: 1.45,
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
