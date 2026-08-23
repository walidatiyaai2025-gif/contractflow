import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../ui/safecontracts_design.dart';
import 'mobile_landing.dart';

/// Additive no-scroll landing for the Alkenzy ADV 0.3.2 premium flow.
///
/// The existing company welcome screen is intentionally retained in source.
/// This variant keeps all primary controls visible in one viewport, matching
/// the supplied premium reference without changing authentication behavior.
final class PremiumCompactWelcomeScreen extends StatefulWidget {
  const PremiumCompactWelcomeScreen({
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
  State<PremiumCompactWelcomeScreen> createState() =>
      _PremiumCompactWelcomeScreenState();
}

final class _PremiumCompactWelcomeScreenState
    extends State<PremiumCompactWelcomeScreen> {
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
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
              const _PremiumBackdrop(),
              SafeArea(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final compact = constraints.maxHeight < 720;
                    return Padding(
                      padding: EdgeInsets.fromLTRB(
                        constraints.maxWidth < 380 ? 14 : 20,
                        compact ? 8 : 14,
                        constraints.maxWidth < 380 ? 14 : 20,
                        compact ? 10 : 16,
                      ),
                      child: Column(
                        children: [
                          _Header(
                            content: content,
                            languageCode: widget.languageCode,
                            onLanguageChanged: widget.onLanguageChanged,
                          ),
                          SizedBox(height: compact ? 10 : 16),
                          Expanded(
                            child: _HeroCard(
                              content: content,
                              languageCode: widget.languageCode,
                              compact: compact,
                            ),
                          ),
                          SizedBox(height: compact ? 10 : 14),
                          _Dots(compact: compact),
                          SizedBox(height: compact ? 8 : 12),
                          FilledButton.icon(
                            key: const Key('companyWelcomeSignIn'),
                            onPressed: widget.onSignIn,
                            style: FilledButton.styleFrom(
                              minimumSize: Size.fromHeight(compact ? 48 : 54),
                              backgroundColor: SafeContractsVisual.roseGold,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                            icon: const Icon(Icons.login_rounded),
                            label: Text(
                              content.signInLabel.resolve(widget.languageCode),
                              style: const TextStyle(fontWeight: FontWeight.w900),
                            ),
                          ),
                          SizedBox(height: compact ? 7 : 9),
                          OutlinedButton.icon(
                            key: const Key('companyWelcomeLearnMore'),
                            onPressed: () => _showAbout(content, ar),
                            style: OutlinedButton.styleFrom(
                              minimumSize: Size.fromHeight(compact ? 44 : 50),
                              foregroundColor: Colors.white,
                              side: BorderSide(
                                color: Colors.white.withValues(alpha: 0.68),
                              ),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(14),
                              ),
                            ),
                            icon: const Icon(Icons.auto_awesome_outlined),
                            label: Text(
                              content.learnMoreLabel.resolve(widget.languageCode),
                              style: const TextStyle(fontWeight: FontWeight.w800),
                            ),
                          ),
                          if (widget.controller.state ==
                              MobileLandingState.loading) ...[
                            const SizedBox(height: 5),
                            const LinearProgressIndicator(minHeight: 2),
                          ],
                        ],
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Future<void> _showAbout(MobileLandingContent content, bool ar) {
    return showModalBottomSheet<void>(
      context: context,
      useSafeArea: true,
      isScrollControlled: true,
      backgroundColor: SafeContractsVisual.surface,
      builder: (context) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const SafeContractsBrandMark(size: 52, borderRadius: 15),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    content.brandName,
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          color: SafeContractsVisual.navy,
                          fontWeight: FontWeight.w900,
                        ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 16),
            Text(
              content.summary.resolve(widget.languageCode),
              style: Theme.of(context).textTheme.bodyLarge?.copyWith(height: 1.55),
            ),
            if (content.phones.isNotEmpty) ...[
              const SizedBox(height: 14),
              Row(
                children: [
                  const Icon(
                    Icons.phone_outlined,
                    color: SafeContractsVisual.roseGold,
                  ),
                  const SizedBox(width: 8),
                  Expanded(child: Text(content.phones.join('  •  '))),
                ],
              ),
            ],
            const SizedBox(height: 18),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(),
              child: Text(ar ? 'إغلاق' : 'Close'),
            ),
          ],
        ),
      ),
    );
  }
}

final class _Header extends StatelessWidget {
  const _Header({
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
        const SafeContractsBrandMark(size: 42, borderRadius: 12),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                content.brandName,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
              ),
              Text(
                content.agencyName.resolve(languageCode),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: SafeContractsVisual.champagne,
                    ),
              ),
            ],
          ),
        ),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'en', label: Text('EN')),
            ButtonSegment(value: 'ar', label: Text('ع')),
          ],
          selected: {selected},
          showSelectedIcon: false,
          onSelectionChanged: onLanguageChanged == null
              ? null
              : (selection) => onLanguageChanged!(selection.first),
          style: const ButtonStyle(visualDensity: VisualDensity.compact),
        ),
      ],
    );
  }
}

final class _HeroCard extends StatelessWidget {
  const _HeroCard({
    required this.content,
    required this.languageCode,
    required this.compact,
  });

  final MobileLandingContent content;
  final String languageCode;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ar = languageCode.toLowerCase() == 'ar';
    return Container(
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x55000000),
            blurRadius: 32,
            offset: Offset(0, 14),
          ),
        ],
      ),
      clipBehavior: Clip.antiAlias,
      child: Stack(
        fit: StackFit.expand,
        children: [
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [
                  Color(0xFFB7CBE3),
                  Color(0xFF315C87),
                  Color(0xFF071F36),
                ],
              ),
            ),
          ),
          PositionedDirectional(
            top: compact ? 28 : 44,
            end: compact ? 24 : 38,
            child: const _FinanceSculpture(),
          ),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                stops: [0.25, 0.58, 1],
                colors: [
                  Colors.transparent,
                  Color(0x26000000),
                  Color(0xF0092944),
                ],
              ),
            ),
          ),
          PositionedDirectional(
            start: 20,
            end: 20,
            bottom: compact ? 16 : 24,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.center,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  content.headline.resolve(languageCode),
                  maxLines: compact ? 2 : 3,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: SafeContractsVisual.champagne,
                        fontWeight: FontWeight.w900,
                        height: 1.18,
                      ),
                ),
                const SizedBox(height: 7),
                Text(
                  content.highlight.resolve(languageCode),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: Colors.white.withValues(alpha: 0.86),
                        fontWeight: FontWeight.w600,
                      ),
                ),
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.10),
                    borderRadius: BorderRadius.circular(999),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: 0.14),
                    ),
                  ),
                  child: Text(
                    ar
                        ? '${content.experienceYears}+ سنة خبرة'
                        : '${content.experienceYears}+ years experience',
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                    ),
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

final class _FinanceSculpture extends StatelessWidget {
  const _FinanceSculpture();

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: 210,
      height: 150,
      child: Stack(
        alignment: Alignment.bottomCenter,
        children: [
          for (final item in const <({double left, double height, double width})>[
            (left: 12, height: 52, width: 34),
            (left: 60, height: 78, width: 34),
            (left: 108, height: 126, width: 36),
            (left: 158, height: 94, width: 34),
          ])
            Positioned(
              left: item.left,
              bottom: 8,
              child: Container(
                width: item.width,
                height: item.height,
                decoration: BoxDecoration(
                  gradient: const LinearGradient(
                    begin: Alignment.topLeft,
                    end: Alignment.bottomRight,
                    colors: [Color(0xFFE8F2FF), Color(0xFF5D7FA5)],
                  ),
                  borderRadius: const BorderRadius.vertical(
                    top: Radius.circular(5),
                  ),
                  border: Border.all(
                    color: Colors.white.withValues(alpha: 0.38),
                  ),
                  boxShadow: const [
                    BoxShadow(
                      color: Color(0x44000000),
                      blurRadius: 10,
                      offset: Offset(0, 6),
                    ),
                  ],
                ),
              ),
            ),
          Positioned(
            left: 22,
            top: 50,
            child: Transform.rotate(
              angle: -0.28,
              child: Container(
                width: 162,
                height: 5,
                decoration: BoxDecoration(
                  color: SafeContractsVisual.roseGoldSoft,
                  borderRadius: BorderRadius.circular(99),
                ),
              ),
            ),
          ),
          const Positioned(
            right: 2,
            top: 18,
            child: Icon(
              Icons.trending_up_rounded,
              size: 48,
              color: SafeContractsVisual.roseGoldSoft,
            ),
          ),
        ],
      ),
    );
  }
}

final class _Dots extends StatelessWidget {
  const _Dots({required this.compact});
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List<Widget>.generate(
        4,
        (index) => Container(
          width: index == 0 ? (compact ? 14 : 17) : 6,
          height: 6,
          margin: const EdgeInsets.symmetric(horizontal: 3),
          decoration: BoxDecoration(
            color: index == 0
                ? SafeContractsVisual.roseGold
                : Colors.white.withValues(alpha: 0.48),
            borderRadius: BorderRadius.circular(99),
          ),
        ),
      ),
    );
  }
}

final class _PremiumBackdrop extends StatelessWidget {
  const _PremiumBackdrop();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [Color(0xFF071E34), Color(0xFF0B3154), Color(0xFF061725)],
        ),
      ),
    );
  }
}
