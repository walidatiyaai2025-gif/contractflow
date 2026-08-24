import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../ui/alkenzy_reference_components.dart';
import '../ui/safecontracts_design.dart';
import 'mobile_landing.dart';

/// Reference-aligned entry page for Alkenzy ADV.
///
/// This keeps the existing landing controller and authentication entry point
/// intact while adopting the locked cream/navy/copper onboarding composition.
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
          backgroundColor: SafeContractsVisual.background,
          body: Stack(
            fit: StackFit.expand,
            children: [
              const _ReferenceBackdrop(),
              SafeArea(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final compactWidth = constraints.maxWidth < 360;
                    return SingleChildScrollView(
                      padding: EdgeInsets.fromLTRB(
                        compactWidth ? 14 : 20,
                        10,
                        compactWidth ? 14 : 20,
                        20,
                      ),
                      child: ConstrainedBox(
                        constraints: BoxConstraints(
                          minHeight: constraints.maxHeight - 30,
                          maxWidth: 520,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _ReferenceHeader(
                              languageCode: widget.languageCode,
                              onLanguageChanged: widget.onLanguageChanged,
                              learnMoreLabel: content.learnMoreLabel
                                  .resolve(widget.languageCode),
                              onLearnMore: () => _showAbout(content, ar),
                            ),
                            const SizedBox(height: 26),
                            const _BrandMedallion(),
                            const SizedBox(height: 20),
                            Text.rich(
                              TextSpan(
                                children: [
                                  TextSpan(
                                    text:
                                        ar ? 'مرحباً بك في\n' : 'Welcome to\n',
                                  ),
                                  const TextSpan(
                                    text: '${SafeContractsBrand.name} ',
                                    style: TextStyle(
                                      color: SafeContractsVisual.navy,
                                      fontWeight: FontWeight.w900,
                                    ),
                                  ),
                                ],
                              ),
                              textAlign: TextAlign.center,
                              style: Theme.of(context)
                                  .textTheme
                                  .headlineMedium
                                  ?.copyWith(
                                    color: SafeContractsVisual.ink,
                                    fontWeight: FontWeight.w900,
                                    height: 1.18,
                                    letterSpacing: -0.6,
                                  ),
                            ),
                            const SizedBox(height: 10),
                            Text(
                              content.summary.resolve(widget.languageCode),
                              textAlign: TextAlign.center,
                              maxLines: 3,
                              overflow: TextOverflow.ellipsis,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyMedium
                                  ?.copyWith(
                                    color: SafeContractsVisual.muted,
                                    height: 1.65,
                                  ),
                            ),
                            const SizedBox(height: 22),
                            AlkenzyReferenceFeatureTile(
                              icon: Icons.contract_outlined,
                              title: ar
                                  ? 'إدارة العقود بسهولة'
                                  : 'Contract control',
                              description: ar
                                  ? 'أنشئ العقود وتابع حالتها من البداية حتى الإغلاق.'
                                  : 'Create contracts and follow every stage through closure.',
                            ),
                            const SizedBox(height: 10),
                            AlkenzyReferenceFeatureTile(
                              icon: Icons.account_balance_wallet_outlined,
                              title: ar ? 'تحكم مالي ذكي' : 'Smart finance',
                              description: ar
                                  ? 'تابع المستحقات والمدفوعات والتحصيلات لحظة بلحظة.'
                                  : 'Track receivables, payables and collections in one place.',
                            ),
                            const SizedBox(height: 10),
                            AlkenzyReferenceFeatureTile(
                              icon: Icons.analytics_outlined,
                              title: ar
                                  ? 'تقارير وبيانات دقيقة'
                                  : 'Clear reporting',
                              description: ar
                                  ? 'رؤى وتقارير تساعدك على اتخاذ القرار من بيانات النظام الحقيقية.'
                                  : 'Use live system data for focused operational decisions.',
                            ),
                            const SizedBox(height: 18),
                            const _ProgressDots(),
                            const SizedBox(height: 18),
                            AlkenzyReferencePrimaryButton(
                              key: const Key('companyWelcomeSignIn'),
                              label: ar ? 'ابدأ الآن' : 'Get started',
                              icon: ar
                                  ? Icons.arrow_back_rounded
                                  : Icons.arrow_forward_rounded,
                              onPressed: widget.onSignIn,
                            ),
                            if (widget.controller.state ==
                                MobileLandingState.loading) ...[
                              const SizedBox(height: 10),
                              const LinearProgressIndicator(minHeight: 2),
                            ],
                          ],
                        ),
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
              style:
                  Theme.of(context).textTheme.bodyLarge?.copyWith(height: 1.55),
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

final class _ReferenceHeader extends StatelessWidget {
  const _ReferenceHeader({
    required this.languageCode,
    required this.learnMoreLabel,
    required this.onLearnMore,
    required this.onLanguageChanged,
  });

  final String languageCode;
  final String learnMoreLabel;
  final VoidCallback onLearnMore;
  final ValueChanged<String>? onLanguageChanged;

  @override
  Widget build(BuildContext context) {
    final selected = languageCode.toLowerCase() == 'ar' ? 'ar' : 'en';
    return Row(
      children: [
        TextButton(
          key: const Key('companyWelcomeLearnMore'),
          onPressed: onLearnMore,
          child: Text(
            learnMoreLabel,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        const Spacer(),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'ar', label: Text('ع')),
            ButtonSegment(value: 'en', label: Text('EN')),
          ],
          selected: {selected},
          showSelectedIcon: false,
          onSelectionChanged: onLanguageChanged == null
              ? null
              : (selection) => onLanguageChanged!(selection.first),
          style: ButtonStyle(
            visualDensity: VisualDensity.compact,
            foregroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? Colors.white
                  : SafeContractsVisual.navy,
            ),
            backgroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? SafeContractsVisual.navy
                  : SafeContractsVisual.surface,
            ),
          ),
        ),
      ],
    );
  }
}

final class _BrandMedallion extends StatelessWidget {
  const _BrandMedallion();

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: 92,
        height: 92,
        padding: const EdgeInsets.all(12),
        decoration: BoxDecoration(
          color: SafeContractsVisual.surface,
          shape: BoxShape.circle,
          border: Border.all(color: SafeContractsVisual.champagne),
          boxShadow: AlkenzyReferenceTokens.softShadow,
        ),
        child: const SafeContractsBrandMark(size: 68, borderRadius: 20),
      ),
    );
  }
}

final class _ProgressDots extends StatelessWidget {
  const _ProgressDots();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List.generate(
        4,
        (index) => AnimatedContainer(
          duration: const Duration(milliseconds: 160),
          width: index == 0 ? 17 : 7,
          height: 7,
          margin: const EdgeInsets.symmetric(horizontal: 3),
          decoration: BoxDecoration(
            color: index == 0
                ? SafeContractsVisual.navy
                : SafeContractsVisual.outline,
            borderRadius: BorderRadius.circular(99),
          ),
        ),
      ),
    );
  }
}

final class _ReferenceBackdrop extends StatelessWidget {
  const _ReferenceBackdrop();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            Color(0xFFFFFCF7),
            SafeContractsVisual.backgroundRaised,
            SafeContractsVisual.background,
          ],
        ),
      ),
    );
  }
}
