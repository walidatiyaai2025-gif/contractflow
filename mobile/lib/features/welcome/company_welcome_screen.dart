import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../ui/safecontracts_design.dart';
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
    final ar = widget.languageCode.toLowerCase() == 'ar';
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final content = widget.controller.content;
        return Scaffold(
          backgroundColor: SafeContractsVisual.navyDeep,
          body: Stack(
            fit: StackFit.expand,
            children: [
              const _PremiumLandingBackground(),
              SafeArea(
                child: RefreshIndicator(
                  onRefresh: widget.controller.refresh,
                  color: SafeContractsVisual.roseGold,
                  child: LayoutBuilder(
                    builder: (context, constraints) {
                      final horizontal = constraints.maxWidth < 380 ? 14.0 : 20.0;
                      return SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: EdgeInsets.fromLTRB(horizontal, 12, horizontal, 28),
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
                                const SizedBox(height: 18),
                                _Hero(content: content, ar: ar),
                                const SizedBox(height: 16),
                                if (widget.controller.state ==
                                    MobileLandingState.loading)
                                  const LinearProgressIndicator(minHeight: 2),
                                if (widget.controller.usingFallback) ...[
                                  const SizedBox(height: 10),
                                  _FallbackNotice(
                                    ar: ar,
                                    onRetry: () =>
                                        unawaited(widget.controller.refresh()),
                                  ),
                                ],
                                const SizedBox(height: 22),
                                _ServiceGrid(content: content, ar: ar),
                                const SizedBox(height: 24),
                                FilledButton.icon(
                                  key: const Key('companyWelcomeSignIn'),
                                  onPressed: widget.onSignIn,
                                  style: FilledButton.styleFrom(
                                    minimumSize: const Size.fromHeight(58),
                                    backgroundColor: SafeContractsVisual.roseGold,
                                    foregroundColor: Colors.white,
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(16),
                                    ),
                                  ),
                                  icon: const Icon(Icons.login_rounded),
                                  label: Text(
                                    content.signInLabel.resolve(
                                      widget.languageCode,
                                    ),
                                    style: const TextStyle(
                                      fontSize: 17,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 10),
                                OutlinedButton.icon(
                                  key: const Key('companyWelcomeLearnMore'),
                                  onPressed: () => _showAbout(content),
                                  style: OutlinedButton.styleFrom(
                                    minimumSize: const Size.fromHeight(54),
                                    foregroundColor: SafeContractsVisual.champagne,
                                    side: const BorderSide(
                                      color: SafeContractsVisual.champagne,
                                    ),
                                    shape: RoundedRectangleBorder(
                                      borderRadius: BorderRadius.circular(16),
                                    ),
                                  ),
                                  icon: const Icon(Icons.info_outline_rounded),
                                  label: Text(
                                    content.learnMoreLabel.resolve(
                                      widget.languageCode,
                                    ),
                                    style: const TextStyle(
                                      fontWeight: FontWeight.w800,
                                    ),
                                  ),
                                ),
                                const SizedBox(height: 18),
                                Row(
                                  mainAxisAlignment: MainAxisAlignment.center,
                                  children: [
                                    const Icon(
                                      Icons.verified_user_outlined,
                                      color: SafeContractsVisual.champagne,
                                      size: 19,
                                    ),
                                    const SizedBox(width: 7),
                                    Flexible(
                                      child: Text(
                                        ar
                                            ? 'الدخول للنظام متاح فقط للمستخدمين المصرح لهم.'
                                            : 'System access is restricted to authorized business users.',
                                        textAlign: TextAlign.center,
                                        style: TextStyle(
                                          color: Colors.white.withValues(
                                            alpha: 0.64,
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
    final ar = widget.languageCode.toLowerCase() == 'ar';
    return showModalBottomSheet<void>(
      context: context,
      isScrollControlled: true,
      useSafeArea: true,
      backgroundColor: SafeContractsVisual.surface,
      builder: (context) {
        return SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Row(
                children: [
                  const SafeContractsBrandMark(size: 54, borderRadius: 16),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          content.brandName,
                          style: Theme.of(context).textTheme.titleLarge?.copyWith(
                                color: SafeContractsVisual.navyDeep,
                                fontWeight: FontWeight.w900,
                              ),
                        ),
                        Text(
                          content.agencyName.resolve(widget.languageCode),
                          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                                color: SafeContractsVisual.muted,
                              ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 18),
              Text(
                content.summary.resolve(widget.languageCode),
                style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      height: 1.65,
                    ),
              ),
              const SizedBox(height: 18),
              _AboutTile(
                icon: Icons.phone_outlined,
                label: ar ? 'تواصل معنا' : 'Contact',
                value: content.phones.join('  •  '),
              ),
              const SizedBox(height: 10),
              _AboutTile(
                icon: Icons.location_on_outlined,
                label: ar ? 'عنوان المكتب' : 'Office address',
                value: content.officeAddress.resolve(widget.languageCode),
              ),
              const SizedBox(height: 18),
              FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: Text(ar ? 'إغلاق' : 'Close'),
              ),
            ],
          ),
        );
      },
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
    final selected = languageCode.toLowerCase() == 'ar' ? 'ar' : 'en';
    return Row(
      children: [
        Container(
          padding: const EdgeInsets.all(7),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(17),
            border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
          ),
          child: const SafeContractsBrandMark(size: 48, borderRadius: 13),
        ),
        const SizedBox(width: 11),
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
        const SizedBox(width: 8),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'en', label: Text('EN')),
            ButtonSegment(value: 'ar', label: Text('ع')),
          ],
          selected: {selected},
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

final class _Hero extends StatelessWidget {
  const _Hero({required this.content, required this.ar});

  final MobileLandingContent content;
  final bool ar;

  @override
  Widget build(BuildContext context) {
    final language = ar ? 'ar' : 'en';
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 26, 20, 22),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: Colors.white.withValues(alpha: 0.10)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x55000000),
            blurRadius: 32,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: Stack(
        children: [
          PositionedDirectional(
            top: -64,
            end: -60,
            child: Container(
              width: 190,
              height: 190,
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: SafeContractsVisual.roseGold.withValues(alpha: 0.10),
                border: Border.all(
                  color: SafeContractsVisual.champagne.withValues(alpha: 0.20),
                ),
              ),
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
                decoration: BoxDecoration(
                  color: SafeContractsVisual.roseGold.withValues(alpha: 0.15),
                  borderRadius: BorderRadius.circular(99),
                  border: Border.all(
                    color: SafeContractsVisual.roseGold.withValues(alpha: 0.38),
                  ),
                ),
                child: Text(
                  ar
                      ? 'أكثر من ${content.experienceYears} سنوات خبرة'
                      : '${content.experienceYears}+ years of experience',
                  style: const TextStyle(
                    color: SafeContractsVisual.champagne,
                    fontWeight: FontWeight.w800,
                    fontSize: 12,
                  ),
                ),
              ),
              const SizedBox(height: 18),
              Text(
                content.headline.resolve(language),
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      height: 1.12,
                      letterSpacing: ar ? 0 : -0.7,
                    ),
              ),
              const SizedBox(height: 8),
              Text(
                content.highlight.resolve(language),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: SafeContractsVisual.roseGoldSoft,
                      fontWeight: FontWeight.w800,
                    ),
              ),
              const SizedBox(height: 14),
              Text(
                content.summary.resolve(language),
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.white.withValues(alpha: 0.74),
                      height: 1.65,
                    ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _ServiceGrid extends StatelessWidget {
  const _ServiceGrid({required this.content, required this.ar});

  final MobileLandingContent content;
  final bool ar;

  @override
  Widget build(BuildContext context) {
    final language = ar ? 'ar' : 'en';
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 560 ? 4 : 2;
        const spacing = 10.0;
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
                  languageCode: language,
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
      constraints: const BoxConstraints(minHeight: 150),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white.withValues(alpha: 0.07),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: Colors.white.withValues(alpha: 0.11)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 38,
            height: 38,
            decoration: BoxDecoration(
              color: SafeContractsVisual.roseGold.withValues(alpha: 0.15),
              borderRadius: BorderRadius.circular(12),
            ),
            child: Icon(
              _serviceIcon(service.key),
              color: SafeContractsVisual.roseGoldSoft,
              size: 21,
            ),
          ),
          const SizedBox(height: 11),
          Text(
            service.title.resolve(languageCode),
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 4),
          Text(
            service.subtitle.resolve(languageCode),
            maxLines: 3,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: Colors.white.withValues(alpha: 0.62),
                  height: 1.4,
                ),
          ),
        ],
      ),
    );
  }
}

final class _FallbackNotice extends StatelessWidget {
  const _FallbackNotice({required this.ar, required this.onRetry});

  final bool ar;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(11),
      decoration: BoxDecoration(
        color: SafeContractsVisual.amber.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(13),
        border: Border.all(
          color: SafeContractsVisual.amber.withValues(alpha: 0.34),
        ),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.cloud_off_outlined,
            color: SafeContractsVisual.champagne,
            size: 20,
          ),
          const SizedBox(width: 9),
          Expanded(
            child: Text(
              ar
                  ? 'تعذر تحديث محتوى الصفحة من ووردبريس؛ يتم عرض النسخة المدمجة مؤقتاً.'
                  : 'WordPress landing content is temporarily unavailable; bundled copy is shown.',
              style: TextStyle(
                color: Colors.white.withValues(alpha: 0.82),
                fontSize: 12,
              ),
            ),
          ),
          TextButton(
            onPressed: onRetry,
            style: TextButton.styleFrom(
              foregroundColor: SafeContractsVisual.champagne,
            ),
            child: Text(ar ? 'إعادة' : 'Retry'),
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
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: SafeContractsVisual.navySoft,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Row(
        children: [
          Icon(icon, color: SafeContractsVisual.navy),
          const SizedBox(width: 10),
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
                const SizedBox(height: 2),
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

final class _PremiumLandingBackground extends StatelessWidget {
  const _PremiumLandingBackground();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Color(0xFF071F35),
            SafeContractsVisual.navyDeep,
            Color(0xFF061726),
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
