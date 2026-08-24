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
          backgroundColor: SafeContractsVisual.background,
          body: Stack(
            fit: StackFit.expand,
            children: [
              const _ReferenceBackdrop(),
              SafeArea(
                child: RefreshIndicator(
                  onRefresh: widget.controller.refresh,
                  color: SafeContractsVisual.roseGold,
                  child: LayoutBuilder(
                    builder: (context, constraints) {
                      final compact = constraints.maxWidth < 360;
                      final horizontal = compact
                          ? SafeContractsSpacing.screenNarrow
                          : SafeContractsSpacing.screen;
                      return SingleChildScrollView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: EdgeInsets.fromLTRB(
                          horizontal,
                          SafeContractsSpacing.xs,
                          horizontal,
                          SafeContractsSpacing.lg,
                        ),
                        child: Center(
                          child: ConstrainedBox(
                            constraints: BoxConstraints(
                              maxWidth: 520,
                              minHeight: constraints.maxHeight - 28,
                            ),
                            child: Column(
                              crossAxisAlignment: CrossAxisAlignment.stretch,
                              children: [
                                _ReferenceHeader(
                                  languageCode: widget.languageCode,
                                  learnMoreLabel: content.learnMoreLabel.resolve(
                                    widget.languageCode,
                                  ),
                                  onLearnMore: () => _showAbout(content),
                                  onLanguageChanged: widget.onLanguageChanged,
                                ),
                                const SizedBox(height: SafeContractsSpacing.xl),
                                const _BrandMedallion(),
                                const SizedBox(height: SafeContractsSpacing.lg),
                                Text.rich(
                                  TextSpan(
                                    children: [
                                      TextSpan(
                                        text: arabic
                                            ? 'مرحباً بك في\n'
                                            : 'Welcome to\n',
                                      ),
                                      const TextSpan(
                                        text: SafeContractsBrand.name,
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
                                        letterSpacing: -0.5,
                                      ),
                                ),
                                const SizedBox(height: SafeContractsSpacing.xs),
                                Text(
                                  content.headline.resolve(widget.languageCode),
                                  textAlign: TextAlign.center,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleSmall
                                      ?.copyWith(
                                        color: SafeContractsVisual.roseGoldDark,
                                        fontWeight: FontWeight.w800,
                                      ),
                                ),
                                const SizedBox(height: SafeContractsSpacing.xs),
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
                                        height: 1.6,
                                      ),
                                ),
                                if (widget.controller.state ==
                                    MobileLandingState.loading) ...[
                                  const SizedBox(height: SafeContractsSpacing.sm),
                                  const LinearProgressIndicator(minHeight: 2),
                                ],
                                if (widget.controller.usingFallback) ...[
                                  const SizedBox(height: SafeContractsSpacing.sm),
                                  _FallbackNotice(
                                    arabic: arabic,
                                    onRetry: () =>
                                        unawaited(widget.controller.refresh()),
                                  ),
                                ],
                                const SizedBox(height: SafeContractsSpacing.lg),
                                _ReferenceFeatureTile(
                                  icon: Icons.description_outlined,
                                  title: arabic
                                      ? 'إدارة العقود بسهولة'
                                      : 'Contract control',
                                  description: arabic
                                      ? 'أنشئ العقود وتابع حالتها من البداية حتى الإغلاق.'
                                      : 'Create contracts and follow every stage through closure.',
                                ),
                                const SizedBox(height: SafeContractsSpacing.xs),
                                _ReferenceFeatureTile(
                                  icon: Icons.account_balance_wallet_outlined,
                                  title: arabic
                                      ? 'تحكم مالي ذكي'
                                      : 'Smart finance',
                                  description: arabic
                                      ? 'تابع المستحقات والمدفوعات والتحصيلات في مساحة عمل واحدة.'
                                      : 'Track receivables, payables and collections in one workspace.',
                                ),
                                const SizedBox(height: SafeContractsSpacing.xs),
                                _ReferenceFeatureTile(
                                  icon: Icons.analytics_outlined,
                                  title: arabic
                                      ? 'تقارير وبيانات دقيقة'
                                      : 'Clear reporting',
                                  description: arabic
                                      ? 'استخدم بيانات النظام الحقيقية لاتخاذ قرارات تشغيلية أوضح.'
                                      : 'Use live system data for focused operational decisions.',
                                ),
                                const SizedBox(height: SafeContractsSpacing.md),
                                const _ProgressDots(),
                                const SizedBox(height: SafeContractsSpacing.md),
                                SafeContractsButton(
                                  key: const Key('companyWelcomeSignIn'),
                                  label: arabic ? 'ابدأ الآن' : 'Get started',
                                  icon: arabic
                                      ? Icons.arrow_back_rounded
                                      : Icons.arrow_forward_rounded,
                                  onPressed: widget.onSignIn,
                                  variant: SafeContractsButtonVariant.primary,
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
                      height: 1.6,
                    ),
              ),
              if (content.phones.isNotEmpty) ...[
                const SizedBox(height: SafeContractsSpacing.md),
                _AboutTile(
                  icon: Icons.phone_outlined,
                  label: arabic ? 'تواصل معنا' : 'Contact',
                  value: content.phones.join('  •  '),
                ),
              ],
              if (content.officeAddress
                  .resolve(widget.languageCode)
                  .trim()
                  .isNotEmpty) ...[
                const SizedBox(height: SafeContractsSpacing.sm),
                _AboutTile(
                  icon: Icons.location_on_outlined,
                  label: arabic ? 'عنوان المكتب' : 'Office address',
                  value: content.officeAddress.resolve(widget.languageCode),
                ),
              ],
              const SizedBox(height: SafeContractsSpacing.md),
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
    final selected = languageCode.toLowerCase().startsWith('ar') ? 'ar' : 'en';
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
          segments: const <ButtonSegment<String>>[
            ButtonSegment<String>(value: 'ar', label: Text('ع')),
            ButtonSegment<String>(value: 'en', label: Text('EN')),
          ],
          selected: <String>{selected},
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
        padding: const EdgeInsets.all(SafeContractsSpacing.sm),
        decoration: BoxDecoration(
          color: SafeContractsVisual.surface,
          shape: BoxShape.circle,
          border: Border.all(color: SafeContractsVisual.champagne),
          boxShadow: SafeContractsShadows.card,
        ),
        child: const SafeContractsBrandMark(size: 68, borderRadius: 20),
      ),
    );
  }
}

final class _ReferenceFeatureTile extends StatelessWidget {
  const _ReferenceFeatureTile({
    required this.icon,
    required this.title,
    required this.description,
  });

  final IconData icon;
  final String title;
  final String description;

  @override
  Widget build(BuildContext context) {
    return SafeContractsCard(
      elevated: false,
      padding: const EdgeInsets.all(SafeContractsSpacing.sm),
      child: Row(
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(
              color: SafeContractsVisual.surfaceWarm,
              borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
            ),
            child: Icon(icon, color: SafeContractsVisual.navy),
          ),
          const SizedBox(width: SafeContractsSpacing.sm),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        color: SafeContractsVisual.navyDeep,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: SafeContractsSpacing.xxs),
                Text(
                  description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
                        height: 1.4,
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

final class _ProgressDots extends StatelessWidget {
  const _ProgressDots();

  @override
  Widget build(BuildContext context) {
    return Row(
      mainAxisAlignment: MainAxisAlignment.center,
      children: List<Widget>.generate(
        4,
        (index) => AnimatedContainer(
          duration: SafeContractsMotion.fast,
          width: index == 0 ? 18 : 7,
          height: 7,
          margin: const EdgeInsets.symmetric(horizontal: 3),
          decoration: BoxDecoration(
            color: index == 0
                ? SafeContractsVisual.navy
                : SafeContractsVisual.outline,
            borderRadius: BorderRadius.circular(SafeContractsRadii.pill),
          ),
        ),
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
        color: SafeContractsVisual.amberSoft,
        borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
        border: Border.all(
          color: SafeContractsVisual.amber.withValues(alpha: 0.32),
        ),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.cloud_off_outlined,
            color: SafeContractsVisual.amber,
            size: SafeContractsIconSizes.sm,
          ),
          const SizedBox(width: SafeContractsSpacing.xs),
          Expanded(
            child: Text(
              arabic
                  ? 'تعذر تحديث محتوى الصفحة؛ يتم عرض النسخة المدمجة مؤقتاً.'
                  : 'Landing content is temporarily unavailable; bundled copy is shown.',
              style: const TextStyle(
                color: SafeContractsVisual.ink,
                fontSize: 12,
              ),
            ),
          ),
          TextButton(
            onPressed: onRetry,
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

final class _ReferenceBackdrop extends StatelessWidget {
  const _ReferenceBackdrop();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: <Color>[
            Color(0xFFFFFCF7),
            SafeContractsVisual.backgroundRaised,
            SafeContractsVisual.background,
          ],
        ),
      ),
    );
  }
}
