import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../ui/safecontracts_components.dart';
import '../ui/safecontracts_design.dart';
import '../ui/safecontracts_tokens.dart';
import 'mobile_landing.dart';

final class AlkenzyCompanyWelcomeScreen extends StatefulWidget {
  const AlkenzyCompanyWelcomeScreen({
    required this.controller,
    required this.languageCode,
    required this.onSignIn,
    this.onLanguageChanged,
    super.key,
  });

  final MobileLandingController controller;
  final String languageCode;
  final VoidCallback onSignIn;
  final ValueChanged<String>? onLanguageChanged;

  @override
  State<AlkenzyCompanyWelcomeScreen> createState() =>
      _AlkenzyCompanyWelcomeScreenState();
}

final class _AlkenzyCompanyWelcomeScreenState
    extends State<AlkenzyCompanyWelcomeScreen> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
  }

  @override
  void didUpdateWidget(AlkenzyCompanyWelcomeScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.controller != widget.controller) {
      unawaited(widget.controller.ensureLoaded());
    }
  }

  @override
  Widget build(BuildContext context) {
    final arabic = widget.languageCode.toLowerCase().startsWith('ar');
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final content = widget.controller.content;
        return Scaffold(
          backgroundColor: SafeContractsVisual.navyDeep,
          body: Stack(
            fit: StackFit.expand,
            children: [
              const _LandingBackground(),
              SafeArea(
                child: RefreshIndicator(
                  onRefresh: widget.controller.refresh,
                  color: SafeContractsVisual.roseGold,
                  child: LayoutBuilder(
                    builder: (context, constraints) {
                      final horizontal = constraints.maxWidth <= 360
                          ? SafeContractsSpacing.screenNarrow
                          : SafeContractsSpacing.screen;
                      return SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: EdgeInsets.fromLTRB(
                          horizontal,
                          SafeContractsSpacing.sm,
                          horizontal,
                          SafeContractsSpacing.xl,
                        ),
                        child: Center(
                          child: ConstrainedBox(
                            constraints: const BoxConstraints(maxWidth: 720),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                _LandingHeader(
                                  content: content,
                                  languageCode: widget.languageCode,
                                  onLanguageChanged: widget.onLanguageChanged,
                                ),
                                const SizedBox(height: SafeContractsSpacing.lg),
                                _LandingHero(
                                  content: content,
                                  languageCode: widget.languageCode,
                                ),
                                if (widget.controller.state ==
                                    MobileLandingState.loading) ...[
                                  const SizedBox(
                                    height: SafeContractsSpacing.sm,
                                  ),
                                  const LinearProgressIndicator(minHeight: 2),
                                ],
                                if (widget.controller.usingFallback) ...[
                                  const SizedBox(
                                    height: SafeContractsSpacing.sm,
                                  ),
                                  _FallbackNotice(
                                    arabic: arabic,
                                    onRetry: () =>
                                        unawaited(widget.controller.refresh()),
                                  ),
                                ],
                                const SizedBox(height: SafeContractsSpacing.lg),
                                _ExperienceStrip(
                                  years: content.experienceYears,
                                  arabic: arabic,
                                ),
                                const SizedBox(height: SafeContractsSpacing.lg),
                                _ServiceGrid(
                                  content: content,
                                  languageCode: widget.languageCode,
                                ),
                                const SizedBox(height: SafeContractsSpacing.xl),
                                SafeContractsButton(
                                  key: const Key('companyWelcomeSignIn'),
                                  label: content.signInLabel.resolve(
                                    widget.languageCode,
                                  ),
                                  icon: Icons.login_rounded,
                                  onPressed: widget.onSignIn,
                                  variant: SafeContractsButtonVariant.accent,
                                ),
                                const SizedBox(height: SafeContractsSpacing.sm),
                                OutlinedButton.icon(
                                  key: const Key('companyWelcomeLearnMore'),
                                  onPressed: () => _showAbout(content),
                                  style: OutlinedButton.styleFrom(
                                    foregroundColor:
                                        SafeContractsVisual.champagne,
                                    side: const BorderSide(
                                      color: SafeContractsVisual.champagne,
                                    ),
                                  ),
                                  icon: const Icon(Icons.info_outline_rounded),
                                  label: Text(
                                    content.learnMoreLabel.resolve(
                                      widget.languageCode,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: SafeContractsSpacing.md),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    const Icon(
                                      Icons.verified_user_outlined,
                                      color: SafeContractsVisual.champagne,
                                      size: SafeContractsIconSizes.xs,
                                    ),
                                    const SizedBox(
                                      width: SafeContractsSpacing.xs,
                                    ),
                                    Flexible(
                                      child: Text(
                                        arabic
                                            ? 'الدخول للنظام متاح فقط للمستخدمين المصرح لهم.'
                                            : 'System access is restricted to authorized business users.',
                                        textAlign: TextAlign.center,
                                        style: TextStyle(
                                          color: Colors.white.withValues(
                                            alpha: 0.62,
                                          ),
                                          fontSize: 12,
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ],
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _showAbout(MobileLandingContent content) {
    final arabic = widget.languageCode.toLowerCase().startsWith('ar');
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      builder: (context) => SingleChildScrollView(
        child: SafeContractsBottomSheetShell(
          title: content.brandName,
          subtitle: content.agencyName.resolve(widget.languageCode),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                content.summary.resolve(widget.languageCode),
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      height: 1.65,
                    ),
              ),
              const SizedBox(height: SafeContractsSpacing.lg),
              _AboutTile(
                icon: Icons.phone_outlined,
                label: arabic ? 'تواصل معنا' : 'Contact',
                value: content.phones.join('  •  '),
              ),
              const SizedBox(height: SafeContractsSpacing.sm),
              _AboutTile(
                icon: Icons.location_on_outlined,
                label: arabic ? 'عنوان المكتب' : 'Office address',
                value: content.officeAddress.resolve(widget.languageCode),
              ),
              const SizedBox(height: SafeContractsSpacing.lg),
              SafeContractsButton(
                label: arabic ? 'إغلاق' : 'Close',
                onPressed: () => Navigator.of(context).pop(),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _LandingHeader extends StatelessWidget {
  const _LandingHeader({
    required this.content,
    required this.languageCode,
    required this.onLanguageChanged,
  });

  final MobileLandingContent content;
  final String languageCode;
  final ValueChanged<String>? onLanguageChanged;

  @override
  Widget build(BuildContext context) {
    final selected = languageCode.toLowerCase().startsWith('ar') ? 'ar' : 'en';
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(6),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(SafeContractsRadii.md),
            border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
          ),
          child: const SafeContractsBrandMark(size: 46, borderRadius: 13),
        ),
        const SizedBox(width: SafeContractsSpacing.sm),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                content.brandName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
              ),
              Text(
                content.agencyName.resolve(languageCode),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: SafeContractsVisual.champagne,
                    ),
              ),
            ],
          ),
        ),
        const SizedBox(width: SafeContractsSpacing.xs),
        SegmentedButton<String>(
          segments: const <ButtonSegment<String>>[
            ButtonSegment<String>(value: 'en', label: Text('EN')),
            ButtonSegment<String>(value: 'ar', label: Text('ع')),
          ],
          selected: <String>{selected},
          onSelectionChanged: onLanguageChanged == null
              ? null
              : (selection) => onLanguageChanged!(selection.first),
          showSelectedIcon: false,
          style: ButtonStyle(
            visualDensity: VisualDensity.compact,
            foregroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? SafeContractsVisual.navyDeep
                  : Colors.white,
            ),
            backgroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? SafeContractsVisual.roseGoldSoft
                  : Colors.white.withValues(alpha: 0.06),
            ),
            side: WidgetStatePropertyAll(
              BorderSide(color: Colors.white.withValues(alpha: 0.15)),
            ),
          ),
        ),
      ],
    );
  }
}

final class _LandingHero extends StatelessWidget {
  const _LandingHero({
    required this.content,
    required this.languageCode,
  });

  final MobileLandingContent content;
  final String languageCode;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 24, 20, 22),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(SafeContractsRadii.xl),
        border: Border.all(color: Colors.white.withValues(alpha: 0.10)),
        boxShadow: SafeContractsShadows.navy,
      ),
      child: Stack(
        children: [
          PositionedDirectional(
            top: -72,
            end: -64,
            child: Container(
              width: 196,
              height: 196,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: SafeContractsVisual.roseGold.withValues(alpha: 0.08),
                border: Border.all(
                  color: SafeContractsVisual.champagne.withValues(alpha: 0.14),
                ),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              SafeContractsStatusChip(
                label: languageCode.toLowerCase().startsWith('ar')
                    ? 'Alkenzy ADV • إدارة أعمال'
                    : 'Alkenzy ADV • Business operations',
                tone: SafeContractsStatusTone.warning,
                icon: Icons.auto_awesome_outlined,
              ),
              const SizedBox(height: SafeContractsSpacing.lg),
              Text(
                content.headline.resolve(languageCode),
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      height: 1.15,
                    ),
              ),
              const SizedBox(height: SafeContractsSpacing.xs),
              Text(
                content.highlight.resolve(languageCode),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: SafeContractsVisual.roseGoldSoft,
                      fontWeight: FontWeight.w800,
                    ),
              ),
              const SizedBox(height: SafeContractsSpacing.sm),
              Text(
                content.summary.resolve(languageCode),
                maxLines: 5,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.white.withValues(alpha: 0.73),
                      height: 1.6,
                    ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _ExperienceStrip extends StatelessWidget {
  const _ExperienceStrip({required this.years, required this.arabic});

  final int years;
  final bool arabic;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Container(
            padding: const EdgeInsets.all(SafeContractsSpacing.md),
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.07),
              borderRadius: BorderRadius.circular(SafeContractsRadii.md),
              border: Border.all(color: Colors.white.withValues(alpha: 0.10)),
            ),
            child: Row(
              children: [
                const Icon(
                  Icons.workspace_premium_outlined,
                  color: SafeContractsVisual.champagne,
                ),
                const SizedBox(width: SafeContractsSpacing.sm),
                Expanded(
                  child: Text(
                    arabic ? '$years+ سنوات خبرة' : '$years+ years experience',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ],
    );
  }
}

final class _ServiceGrid extends StatelessWidget {
  const _ServiceGrid({
    required this.content,
    required this.languageCode,
  });

  final MobileLandingContent content;
  final String languageCode;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 560 ? 4 : 2;
        const spacing = SafeContractsSpacing.sm;
        final width =
            (constraints.maxWidth - spacing * (columns - 1)) / columns;
        return Wrap(
          spacing: spacing,
          runSpacing: spacing,
          children: [
            for (final service in content.services)
              SizedBox(
                width: width,
                child: _ServiceCard(
                  service: service,
                  languageCode: languageCode,
                ),
              ),
          ],
        );
      },
    );
  }
}

final class _ServiceCard extends StatelessWidget {
  const _ServiceCard({required this.service, required this.languageCode});

  final MobileLandingService service;
  final String languageCode;

  @override
  Widget build(BuildContext context) {
    return Container(
      constraints: const BoxConstraints(minHeight: 142),
      padding: const EdgeInsets.all(SafeContractsSpacing.sm),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.065),
        borderRadius: BorderRadius.circular(SafeContractsRadii.md),
        border: Border.all(color: Colors.white.withValues(alpha: 0.10)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: SafeContractsVisual.roseGold.withValues(alpha: 0.14),
              borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
            ),
            child: Icon(
              _serviceIcon(service.key),
              color: SafeContractsVisual.roseGoldSoft,
              size: SafeContractsIconSizes.sm,
            ),
          ),
          const SizedBox(height: SafeContractsSpacing.sm),
          Text(
            service.title.resolve(languageCode),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: SafeContractsSpacing.xxs),
          Text(
            service.subtitle.resolve(languageCode),
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.60),
                  height: 1.35,
                ),
          ),
        ],
      ),
    );
  }
}

final class _FallbackNotice extends StatelessWidget {
  const _FallbackNotice({required this.arabic, required this.onRetry});

  final bool arabic;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(SafeContractsSpacing.sm),
      decoration: BoxDecoration(
        color: SafeContractsVisual.amber.withValues(alpha: 0.15),
        borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
        border: Border.all(
          color: SafeContractsVisual.amber.withValues(alpha: 0.30),
        ),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.cloud_off_outlined,
            color: SafeContractsVisual.champagne,
            size: SafeContractsIconSizes.sm,
          ),
          const SizedBox(width: SafeContractsSpacing.xs),
          Expanded(
            child: Text(
              arabic
                  ? 'تعذر تحديث محتوى الصفحة؛ يتم عرض النسخة المدمجة مؤقتاً.'
                  : 'Landing content is temporarily unavailable; bundled copy is shown.',
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.80),
                fontSize: 12,
              ),
            ),
          ),
          TextButton(
            onPressed: onRetry,
            style: TextButton.styleFrom(
              foregroundColor: SafeContractsVisual.champagne,
            ),
            child: Text(arabic ? 'إعادة' : 'Retry'),
          ),
        ],
      ),
    );
  }
}

final class _AboutTile extends StatelessWidget {
  const _AboutTile({
    required this.icon,
    required this.label,
    required this.value,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(SafeContractsSpacing.sm),
      decoration: BoxDecoration(
        color: SafeContractsVisual.navySoft,
        borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
      ),
      child: Row(
        children: [
          Icon(icon, color: SafeContractsVisual.navy),
          const SizedBox(width: SafeContractsSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: const TextStyle(
                    color: SafeContractsVisual.muted,
                    fontWeight: FontWeight.w700,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: SafeContractsSpacing.xxs),
                SelectableText(
                  value,
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _LandingBackground extends StatelessWidget {
  const _LandingBackground();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: <Color>[
            Color(0xFF061B2F),
            SafeContractsVisual.navyDeep,
            Color(0xFF051522),
          ],
        ),
      ),
    );
  }
}

IconData _serviceIcon(String key) {
  return switch (key) {
    'strategy' => Icons.insights_outlined,
    'outdoor' => Icons.campaign_outlined,
    'digital' => Icons.devices_outlined,
    'television' => Icons.live_tv_outlined,
    _ => Icons.auto_awesome_outlined,
  };
}
