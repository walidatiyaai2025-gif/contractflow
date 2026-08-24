import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import 'safecontracts_design.dart';
import 'safecontracts_tokens.dart';

enum SafeContractsButtonVariant { primary, accent, outline, ghost, danger }

final class SafeContractsNavigationItem<T> {
  const SafeContractsNavigationItem({
    required this.value,
    required this.label,
    required this.icon,
    this.selectedIcon,
  });

  final T value;
  final String label;
  final IconData icon;
  final IconData? selectedIcon;
}

final class SafeContractsDrawerItem<T> {
  const SafeContractsDrawerItem({
    required this.value,
    required this.label,
    required this.icon,
  });

  final T value;
  final String label;
  final IconData icon;
}

final class SafeContractsScaffold extends StatelessWidget {
  const SafeContractsScaffold({
    required this.body,
    this.appBar,
    this.drawer,
    this.bottomNavigationBar,
    this.floatingActionButton,
    this.floatingActionButtonLocation,
    this.backgroundColor = SafeContractsVisual.background,
    this.extendBody = false,
    super.key,
  });

  final PreferredSizeWidget? appBar;
  final Widget body;
  final Widget? drawer;
  final Widget? bottomNavigationBar;
  final Widget? floatingActionButton;
  final FloatingActionButtonLocation? floatingActionButtonLocation;
  final Color backgroundColor;
  final bool extendBody;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: backgroundColor,
      appBar: appBar,
      drawer: drawer,
      extendBody: extendBody,
      bottomNavigationBar: bottomNavigationBar,
      floatingActionButton: floatingActionButton,
      floatingActionButtonLocation: floatingActionButtonLocation,
      body: body,
    );
  }
}

final class SafeContractsHeader extends StatelessWidget {
  const SafeContractsHeader({
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
    return SafeContractsPremiumHeader(
      title: title,
      subtitle: subtitle,
      leading: leading,
      trailing: trailing,
      compact: compact,
    );
  }
}

final class SafeContractsSection extends StatelessWidget {
  const SafeContractsSection({
    required this.title,
    required this.child,
    this.subtitle,
    this.trailing,
    this.spacing = SafeContractsSpacing.md,
    super.key,
  });

  final String title;
  final String? subtitle;
  final Widget? trailing;
  final Widget child;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: SafeContractsSectionTitle(
                title: title,
                subtitle: subtitle,
              ),
            ),
            if (trailing != null) ...[
              const SizedBox(width: SafeContractsSpacing.sm),
              trailing!,
            ],
          ],
        ),
        SizedBox(height: spacing),
        child,
      ],
    );
  }
}

final class SafeContractsCard extends StatelessWidget {
  const SafeContractsCard({
    required this.child,
    this.padding = const EdgeInsets.all(SafeContractsSpacing.md),
    this.margin = EdgeInsets.zero,
    this.accent,
    this.elevated = true,
    this.onTap,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final EdgeInsetsGeometry margin;
  final Color? accent;
  final bool elevated;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final card = SafeContractsSurface(
      padding: padding,
      margin: margin,
      accent: accent,
      elevated: elevated,
      child: child,
    );
    if (onTap == null) return card;
    return Semantics(
      button: true,
      child: InkWell(
        borderRadius: BorderRadius.circular(SafeContractsRadii.lg),
        onTap: onTap,
        child: card,
      ),
    );
  }
}

final class SafeContractsButton extends StatelessWidget {
  const SafeContractsButton({
    required this.label,
    required this.onPressed,
    this.icon,
    this.variant = SafeContractsButtonVariant.primary,
    this.expand = true,
    this.loading = false,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final SafeContractsButtonVariant variant;
  final bool expand;
  final bool loading;

  @override
  Widget build(BuildContext context) {
    final callback = loading ? null : onPressed;
    final child = Row(
      mainAxisSize: expand ? MainAxisSize.max : MainAxisSize.min,
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        if (loading)
          const SizedBox.square(
            dimension: 18,
            child: CircularProgressIndicator(strokeWidth: 2),
          )
        else if (icon != null)
          Icon(icon, size: SafeContractsIconSizes.sm),
        if (loading || icon != null) const SizedBox(width: SafeContractsSpacing.xs),
        Flexible(
          child: Text(
            label,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
          ),
        ),
      ],
    );

    final button = switch (variant) {
      SafeContractsButtonVariant.primary => FilledButton(
          onPressed: callback,
          child: child,
        ),
      SafeContractsButtonVariant.accent => FilledButton(
          onPressed: callback,
          style: FilledButton.styleFrom(
            backgroundColor: SafeContractsVisual.roseGold,
          ),
          child: child,
        ),
      SafeContractsButtonVariant.outline => OutlinedButton(
          onPressed: callback,
          child: child,
        ),
      SafeContractsButtonVariant.ghost => TextButton(
          onPressed: callback,
          child: child,
        ),
      SafeContractsButtonVariant.danger => FilledButton(
          onPressed: callback,
          style: FilledButton.styleFrom(
            backgroundColor: SafeContractsVisual.red,
          ),
          child: child,
        ),
    };

    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}

final class SafeContractsStatusChip extends StatelessWidget {
  const SafeContractsStatusChip({
    required this.label,
    this.status,
    this.tone,
    this.icon,
    super.key,
  });

  final String label;
  final String? status;
  final SafeContractsStatusTone? tone;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    final colors = _statusColors(tone, status);
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: colors.background,
        borderRadius: BorderRadius.circular(SafeContractsRadii.pill),
        border: Border.all(color: colors.border),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (icon != null) ...[
            Icon(icon, size: SafeContractsIconSizes.xs, color: colors.foreground),
            const SizedBox(width: SafeContractsSpacing.xxs),
          ],
          Flexible(
            child: Text(
              label,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: colors.foreground,
                    fontWeight: FontWeight.w800,
                  ),
            ),
          ),
        ],
      ),
    );
  }
}

final class SafeContractsFilterChip extends StatelessWidget {
  const SafeContractsFilterChip({
    required this.label,
    required this.selected,
    required this.onSelected,
    this.icon,
    super.key,
  });

  final String label;
  final bool selected;
  final ValueChanged<bool> onSelected;
  final IconData? icon;

  @override
  Widget build(BuildContext context) {
    return FilterChip(
      selected: selected,
      onSelected: onSelected,
      avatar: icon == null ? null : Icon(icon, size: SafeContractsIconSizes.xs),
      label: Text(label, maxLines: 1, overflow: TextOverflow.ellipsis),
    );
  }
}

final class SafeContractsSearchBar extends StatelessWidget {
  const SafeContractsSearchBar({
    required this.hintText,
    this.controller,
    this.onChanged,
    this.onSubmitted,
    this.onClear,
    this.enabled = true,
    super.key,
  });

  final String hintText;
  final TextEditingController? controller;
  final ValueChanged<String>? onChanged;
  final ValueChanged<String>? onSubmitted;
  final VoidCallback? onClear;
  final bool enabled;

  @override
  Widget build(BuildContext context) {
    return TextField(
      controller: controller,
      enabled: enabled,
      onChanged: onChanged,
      onSubmitted: onSubmitted,
      textInputAction: TextInputAction.search,
      decoration: InputDecoration(
        hintText: hintText,
        prefixIcon: const Icon(Icons.search_rounded),
        suffixIcon: onClear == null
            ? null
            : IconButton(
                tooltip: MaterialLocalizations.of(context).deleteButtonTooltip,
                onPressed: onClear,
                icon: const Icon(Icons.close_rounded),
              ),
      ),
    );
  }
}

final class SafeContractsAmount extends StatelessWidget {
  const SafeContractsAmount({
    required this.amount,
    this.currency,
    this.compact = false,
    this.emphasis = true,
    super.key,
  });

  final String amount;
  final String? currency;
  final bool compact;
  final bool emphasis;

  @override
  Widget build(BuildContext context) {
    final style = (compact
            ? Theme.of(context).textTheme.titleMedium
            : Theme.of(context).textTheme.titleLarge)
        ?.copyWith(
      color: SafeContractsVisual.navyDeep,
      fontWeight: emphasis ? FontWeight.w900 : FontWeight.w700,
    );
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: AlignmentDirectional.centerStart,
      child: Text(
        currency == null || currency!.trim().isEmpty
            ? amount
            : '$amount ${currency!.trim()}',
        style: style,
      ),
    );
  }
}

final class SafeContractsBottomNavigation<T> extends StatelessWidget {
  const SafeContractsBottomNavigation({
    required this.items,
    required this.selected,
    required this.onSelected,
    this.moreLabel,
    this.onMore,
    super.key,
  });

  final List<SafeContractsNavigationItem<T>> items;
  final T selected;
  final ValueChanged<T> onSelected;
  final String? moreLabel;
  final VoidCallback? onMore;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Container(
        decoration: const BoxDecoration(
          color: SafeContractsVisual.surface,
          border: Border(top: BorderSide(color: SafeContractsVisual.outline)),
          boxShadow: SafeContractsShadows.navigation,
        ),
        padding: const EdgeInsets.fromLTRB(6, 7, 6, 5),
        child: Row(
          children: [
            for (final item in items)
              Expanded(
                child: _NavigationTile(
                  label: item.label,
                  icon: item.icon,
                  selectedIcon: item.selectedIcon,
                  selected: item.value == selected,
                  onTap: () => onSelected(item.value),
                ),
              ),
            if (onMore != null && moreLabel != null)
              Expanded(
                child: _NavigationTile(
                  label: moreLabel!,
                  icon: Icons.grid_view_rounded,
                  selected: false,
                  onTap: onMore!,
                ),
              ),
          ],
        ),
      ),
    );
  }
}

final class _NavigationTile extends StatelessWidget {
  const _NavigationTile({
    required this.label,
    required this.icon,
    required this.selected,
    required this.onTap,
    this.selectedIcon,
  });

  final String label;
  final IconData icon;
  final IconData? selectedIcon;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      button: true,
      selected: selected,
      label: label,
      child: InkWell(
        borderRadius: BorderRadius.circular(SafeContractsRadii.md),
        onTap: onTap,
        child: AnimatedContainer(
          duration: SafeContractsMotion.standard,
          curve: Curves.easeOutCubic,
          constraints: const BoxConstraints(minHeight: 55),
          padding: const EdgeInsets.symmetric(horizontal: 3, vertical: 5),
          decoration: BoxDecoration(
            color: selected
                ? SafeContractsVisual.roseGoldSoft.withValues(alpha: 0.88)
                : Colors.transparent,
            borderRadius: BorderRadius.circular(SafeContractsRadii.md),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Icon(
                selected ? (selectedIcon ?? icon) : icon,
                size: SafeContractsIconSizes.sm,
                color: selected
                    ? SafeContractsVisual.navyDeep
                    : SafeContractsVisual.muted,
              ),
              const SizedBox(height: 3),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: selected
                          ? SafeContractsVisual.navyDeep
                          : SafeContractsVisual.muted,
                      fontSize: 10.5,
                      fontWeight: selected ? FontWeight.w900 : FontWeight.w600,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class SafeContractsDrawer<T> extends StatelessWidget {
  const SafeContractsDrawer({
    required this.items,
    required this.selected,
    required this.onSelected,
    required this.workspaceLabel,
    this.footer,
    this.onLogout,
    this.logoutLabel,
    super.key,
  });

  final List<SafeContractsDrawerItem<T>> items;
  final T selected;
  final ValueChanged<T> onSelected;
  final String workspaceLabel;
  final Widget? footer;
  final VoidCallback? onLogout;
  final String? logoutLabel;

  @override
  Widget build(BuildContext context) {
    return Drawer(
      backgroundColor: SafeContractsVisual.navyDeep,
      surfaceTintColor: Colors.transparent,
      child: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 14),
              child: Row(
                children: [
                  Container(
                    padding: const EdgeInsets.all(6),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: 0.08),
                      borderRadius: BorderRadius.circular(SafeContractsRadii.md),
                      border: Border.all(
                        color: Colors.white.withValues(alpha: 0.10),
                      ),
                    ),
                    child: const SafeContractsBrandMark(
                      size: 48,
                      borderRadius: 14,
                    ),
                  ),
                  const SizedBox(width: SafeContractsSpacing.sm),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          SafeContractsBrand.name,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        const SizedBox(height: 2),
                        Text(
                          workspaceLabel,
                          maxLines: 2,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: SafeContractsVisual.champagne,
                              ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            Divider(color: Colors.white.withValues(alpha: 0.10), height: 1),
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.fromLTRB(10, 12, 10, 12),
                itemCount: items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 3),
                itemBuilder: (context, index) {
                  final item = items[index];
                  final isSelected = item.value == selected;
                  return ListTile(
                    selected: isSelected,
                    onTap: () => onSelected(item.value),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(SafeContractsRadii.md),
                    ),
                    selectedTileColor:
                        SafeContractsVisual.roseGold.withValues(alpha: 0.16),
                    leading: Icon(
                      item.icon,
                      color: isSelected
                          ? SafeContractsVisual.roseGoldSoft
                          : Colors.white.withValues(alpha: 0.72),
                    ),
                    title: Text(
                      item.label,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white,
                        fontWeight:
                            isSelected ? FontWeight.w900 : FontWeight.w600,
                      ),
                    ),
                    trailing: isSelected
                        ? const Icon(
                            Icons.circle,
                            size: 8,
                            color: SafeContractsVisual.roseGold,
                          )
                        : null,
                  );
                },
              ),
            ),
            if (footer != null)
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: SafeContractsSpacing.md,
                ),
                child: footer!,
              ),
            if (onLogout != null && logoutLabel != null)
              Padding(
                padding: const EdgeInsets.fromLTRB(10, 6, 10, 12),
                child: ListTile(
                  onTap: onLogout,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(SafeContractsRadii.md),
                  ),
                  tileColor: SafeContractsVisual.red.withValues(alpha: 0.12),
                  leading: const Icon(
                    Icons.logout_rounded,
                    color: Color(0xFFFFB4B4),
                  ),
                  title: Text(
                    logoutLabel!,
                    style: const TextStyle(
                      color: Color(0xFFFFD2D2),
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    );
  }
}

final class SafeContractsBottomSheetShell extends StatelessWidget {
  const SafeContractsBottomSheetShell({
    required this.title,
    required this.child,
    this.subtitle,
    super.key,
  });

  final String title;
  final String? subtitle;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      top: false,
      child: Padding(
        padding: EdgeInsets.fromLTRB(
          SafeContractsSpacing.screen,
          SafeContractsSpacing.xs,
          SafeContractsSpacing.screen,
          SafeContractsSpacing.lg + MediaQuery.viewInsetsOf(context).bottom,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              title,
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    color: SafeContractsVisual.navyDeep,
                    fontWeight: FontWeight.w900,
                  ),
            ),
            if (subtitle != null && subtitle!.trim().isNotEmpty) ...[
              const SizedBox(height: SafeContractsSpacing.xxs),
              Text(
                subtitle!,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: SafeContractsVisual.muted,
                    ),
              ),
            ],
            const SizedBox(height: SafeContractsSpacing.md),
            child,
          ],
        ),
      ),
    );
  }
}

final class SafeContractsEmptyState extends StatelessWidget {
  const SafeContractsEmptyState({
    required this.title,
    this.message,
    this.icon = Icons.inbox_outlined,
    this.action,
    super.key,
  });

  final String title;
  final String? message;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return _SafeContractsStateCard(
      icon: icon,
      iconColor: SafeContractsVisual.navy,
      title: title,
      message: message,
      action: action,
    );
  }
}

final class SafeContractsLoadingState extends StatelessWidget {
  const SafeContractsLoadingState({required this.label, super.key});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      label: label,
      child: _SafeContractsStateCard(
        iconWidget: const SizedBox.square(
          dimension: 30,
          child: CircularProgressIndicator(strokeWidth: 3),
        ),
        title: label,
      ),
    );
  }
}

final class SafeContractsErrorState extends StatelessWidget {
  const SafeContractsErrorState({
    required this.title,
    this.message,
    this.onRetry,
    this.retryLabel,
    super.key,
  });

  final String title;
  final String? message;
  final VoidCallback? onRetry;
  final String? retryLabel;

  @override
  Widget build(BuildContext context) {
    return _SafeContractsStateCard(
      icon: Icons.error_outline_rounded,
      iconColor: SafeContractsVisual.red,
      title: title,
      message: message,
      action: onRetry == null
          ? null
          : SafeContractsButton(
              label: retryLabel ?? 'Retry',
              onPressed: onRetry,
              variant: SafeContractsButtonVariant.outline,
            ),
    );
  }
}

final class _SafeContractsStateCard extends StatelessWidget {
  const _SafeContractsStateCard({
    required this.title,
    this.icon,
    this.iconWidget,
    this.iconColor = SafeContractsVisual.navy,
    this.message,
    this.action,
  });

  final String title;
  final IconData? icon;
  final Widget? iconWidget;
  final Color iconColor;
  final String? message;
  final Widget? action;

  @override
  Widget build(BuildContext context) {
    return SafeContractsCard(
      elevated: false,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          iconWidget ??
              Icon(icon, size: SafeContractsIconSizes.xl, color: iconColor),
          const SizedBox(height: SafeContractsSpacing.sm),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
          ),
          if (message != null && message!.trim().isNotEmpty) ...[
            const SizedBox(height: SafeContractsSpacing.xs),
            Text(
              message!,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: SafeContractsVisual.muted,
                  ),
            ),
          ],
          if (action != null) ...[
            const SizedBox(height: SafeContractsSpacing.md),
            action!,
          ],
        ],
      ),
    );
  }
}

SafeContractsStatusColors _statusColors(
  SafeContractsStatusTone? tone,
  String? status,
) {
  final resolvedTone = tone ?? switch (status?.trim().toLowerCase()) {
    'paid' || 'completed' || 'active' => SafeContractsStatusTone.success,
    'overdue' || 'cancelled' || 'error' => SafeContractsStatusTone.danger,
    'due' || 'due_soon' || 'partially_paid' => SafeContractsStatusTone.warning,
    'pending' || 'processing' => SafeContractsStatusTone.info,
    _ => SafeContractsStatusTone.neutral,
  };
  return switch (resolvedTone) {
    SafeContractsStatusTone.success => SafeContractsStatusColors(
        foreground: SafeContractsVisual.greenDeep,
        background: SafeContractsVisual.greenSoft,
        border: SafeContractsVisual.green.withValues(alpha: 0.25),
      ),
    SafeContractsStatusTone.warning => SafeContractsStatusColors(
        foreground: const Color(0xFF8C5714),
        background: SafeContractsVisual.amberSoft,
        border: SafeContractsVisual.amber.withValues(alpha: 0.28),
      ),
    SafeContractsStatusTone.danger => SafeContractsStatusColors(
        foreground: SafeContractsVisual.redDeep,
        background: SafeContractsVisual.redSoft,
        border: SafeContractsVisual.red.withValues(alpha: 0.25),
      ),
    SafeContractsStatusTone.info => SafeContractsStatusColors(
        foreground: SafeContractsVisual.navy,
        background: SafeContractsVisual.navySoft,
        border: SafeContractsVisual.navy.withValues(alpha: 0.22),
      ),
    SafeContractsStatusTone.neutral => const SafeContractsStatusColors(
        foreground: SafeContractsVisual.muted,
        background: SafeContractsVisual.backgroundRaised,
        border: SafeContractsVisual.outline,
      ),
  };
}
