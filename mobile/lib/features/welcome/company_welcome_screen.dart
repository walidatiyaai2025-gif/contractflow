import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../ui/safecontracts_components.dart';
import '../ui/safecontracts_design.dart';
import '../ui/safecontracts_tokens.dart';
import 'mobile_landing.dart';
import '../ads/alkenzy_rewarded_fun.dart';
import '../ads/alkenzy_ad_placements.dart';
import '../ads/alkenzy_ad_units.dart';

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
    // App Open is preloaded for anonymous landing visitors, never shown
    // automatically on initial page load.
    unawaited(AlkenzyFullScreenAds.instance.prepareAppOpen());
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
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    // Account for system insets before choosing the density.
                    // The public landing is a single viewport, never a list.
                    final height = constraints.maxHeight;
                    final narrow = constraints.maxWidth < 352;
                    final veryShort = height < 620;
                    final compact = height < 690;
                    final gap = veryShort
                        ? 4.0
                        : compact
                        ? 7.0
                        : 10.0;
                    // A standard AdMob 320x50 creative must remain unscaled.
                    // On 320-351px phones remove horizontal page margins.
                    final horizontal = narrow
                        ? 0.0
                        : constraints.maxWidth < 390
                        ? SafeContractsSpacing.screenNarrow
                        : SafeContractsSpacing.screen;
                    final heroHeight = (height * (veryShort ? 0.14 : 0.22))
                        .clamp(64.0, compact ? 140.0 : 196.0);
                    return Center(
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 520),
                        child: Padding(
                          padding: EdgeInsets.fromLTRB(
                            horizontal,
                            veryShort ? 0 : 4,
                            horizontal,
                            veryShort ? 0 : 6,
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
                                onRefresh: () =>
                                    unawaited(widget.controller.refresh()),
                                onLanguageChanged: widget.onLanguageChanged,
                              ),
                              SizedBox(height: gap),
                              if (content.images.isEmpty)
                                SizedBox(
                                  height: heroHeight,
                                  child: Center(
                                    child: _BrandMedallion(
                                      size: (heroHeight - 4).clamp(60.0, 92.0),
                                    ),
                                  ),
                                )
                              else
                                _LandingImageGallery(
                                  key: ValueKey<String>(
                                    content.images
                                        .map((image) => image.id)
                                        .join(','),
                                  ),
                                  images: content.images,
                                  languageCode: widget.languageCode,
                                  imageHeight: heroHeight,
                                ),
                              // Keep a full 320x50 banner plus its native
                              // 12px vertical insets. No overlapping buttons
                              // while the asynchronous creative is loading.
                              const SizedBox(
                                height: 74,
                                child: AlkenzyInlineBanner(
                                  key: Key('alkenzyLandingSliderBanner'),
                                  slot: AlkenzyBannerSlot.landingSlider,
                                ),
                              ),
                              SizedBox(height: gap / 2),
                              Text.rich(
                                TextSpan(
                                  children: [
                                    TextSpan(
                                      text: arabic
                                          ? 'مرحباً بك في '
                                          : 'Welcome to ',
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
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                textAlign: TextAlign.center,
                                style: Theme.of(context).textTheme.headlineSmall
                                    ?.copyWith(
                                      fontSize: veryShort ? 17 : 19,
                                      color: SafeContractsVisual.ink,
                                      fontWeight: FontWeight.w900,
                                      height: 1.12,
                                    ),
                              ),
                              SizedBox(height: gap / 2),
                              Text(
                                content.headline.resolve(widget.languageCode),
                                textAlign: TextAlign.center,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: Theme.of(context).textTheme.titleSmall
                                    ?.copyWith(
                                      color: SafeContractsVisual.roseGoldDark,
                                      fontWeight: FontWeight.w800,
                                    ),
                              ),
                              if (!veryShort) ...[
                                SizedBox(height: gap / 2),
                                Text(
                                  content.summary.resolve(widget.languageCode),
                                  textAlign: TextAlign.center,
                                  maxLines: compact ? 1 : 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: Theme.of(context).textTheme.bodySmall
                                      ?.copyWith(
                                        color: SafeContractsVisual.muted,
                                        height: 1.2,
                                      ),
                                ),
                              ],
                              if (widget.controller.state ==
                                  MobileLandingState.loading)
                                const LinearProgressIndicator(minHeight: 2),
                              if (widget.controller.usingFallback)
                                _FallbackNotice(
                                  arabic: arabic,
                                  onRetry: () =>
                                      unawaited(widget.controller.refresh()),
                                ),
                              SizedBox(height: gap),
                              SizedBox(
                                height: veryShort
                                    ? 54
                                    : compact
                                    ? 62
                                    : 68,
                                child: Row(
                                  children: [
                                    Expanded(
                                      child: _ReferenceFeatureTile(
                                        icon: Icons.description_outlined,
                                        title: arabic
                                            ? 'إدارة العقود'
                                            : 'Contracts',
                                        description: arabic
                                            ? 'أنشئ العقود وتابع حالتها من البداية حتى الإغلاق.'
                                            : 'Create contracts and follow every stage through closure.',
                                      ),
                                    ),
                                    SizedBox(width: gap),
                                    Expanded(
                                      child: _ReferenceFeatureTile(
                                        icon: Icons
                                            .account_balance_wallet_outlined,
                                        title: arabic
                                            ? 'الإدارة المالية'
                                            : 'Finance',
                                        description: arabic
                                            ? 'تابع المستحقات والمدفوعات والتحصيلات في مساحة عمل واحدة.'
                                            : 'Track receivables, payables and collections in one workspace.',
                                      ),
                                    ),
                                    SizedBox(width: gap),
                                    Expanded(
                                      child: _ReferenceFeatureTile(
                                        icon: Icons.analytics_outlined,
                                        title: arabic ? 'التقارير' : 'Reports',
                                        description: arabic
                                            ? 'استخدم بيانات النظام الحقيقية لاتخاذ قرارات تشغيلية أوضح.'
                                            : 'Use live system data for focused operational decisions.',
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                              const Spacer(),
                              AlkenzyRewardedFunButton(
                                languageCode: widget.languageCode,
                              ),
                              SizedBox(height: gap),
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
                style: Theme.of(
                  context,
                ).textTheme.bodyLarge?.copyWith(height: 1.6),
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
    required this.onRefresh,
    required this.onLanguageChanged,
  });

  final String languageCode;
  final String learnMoreLabel;
  final VoidCallback onLearnMore;
  final VoidCallback onRefresh;
  final ValueChanged<String>? onLanguageChanged;

  @override
  Widget build(BuildContext context) {
    final selected = languageCode.toLowerCase().startsWith('ar') ? 'ar' : 'en';
    return Row(
      children: [
        Flexible(
          child: TextButton(
            key: const Key('companyWelcomeLearnMore'),
            onPressed: onLearnMore,
            child: Text(
              learnMoreLabel,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ),
        const Spacer(),
        IconButton(
          tooltip: selected == 'ar' ? 'تحديث الصفحة' : 'Refresh landing',
          onPressed: onRefresh,
          visualDensity: VisualDensity.compact,
          icon: const Icon(Icons.refresh_rounded, size: 20),
        ),
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
  const _BrandMedallion({this.size = 92});

  final double size;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Container(
        width: size,
        height: size,
        padding: const EdgeInsets.all(SafeContractsSpacing.xs),
        decoration: BoxDecoration(
          color: SafeContractsVisual.surface,
          shape: BoxShape.circle,
          border: Border.all(color: SafeContractsVisual.champagne),
          boxShadow: SafeContractsShadows.card,
        ),
        child: SafeContractsBrandMark(size: size * 0.66, borderRadius: 16),
      ),
    );
  }
}

final class _LandingImageGallery extends StatefulWidget {
  const _LandingImageGallery({
    required this.images,
    required this.languageCode,
    required this.imageHeight,
    super.key,
  });

  final List<MobileLandingImage> images;
  final String languageCode;
  final double imageHeight;

  @override
  State<_LandingImageGallery> createState() => _LandingImageGalleryState();
}

final class _LandingImageGalleryState extends State<_LandingImageGallery> {
  late final PageController _pageController;
  var _currentPage = 0;

  @override
  void initState() {
    super.initState();
    _pageController = PageController(
      viewportFraction: widget.images.length > 1 ? 0.94 : 1,
    );
  }

  @override
  void didUpdateWidget(_LandingImageGallery oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (_currentPage >= widget.images.length) {
      _currentPage = 0;
      WidgetsBinding.instance.addPostFrameCallback((_) {
        if (_pageController.hasClients) _pageController.jumpToPage(0);
      });
    }
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final arabic = widget.languageCode.toLowerCase().startsWith('ar');
    return Column(
      children: [
        SizedBox(
          height: widget.imageHeight,
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: SafeContractsVisual.surface,
              borderRadius: BorderRadius.circular(SafeContractsRadii.lg),
              border: Border.all(color: SafeContractsVisual.champagne),
              boxShadow: SafeContractsShadows.card,
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(SafeContractsRadii.lg - 1),
              child: Stack(
                fit: StackFit.expand,
                children: [
                  PageView.builder(
                    key: const Key('companyWelcomeImageCarousel'),
                    controller: _pageController,
                    itemCount: widget.images.length,
                    onPageChanged: (index) {
                      if (mounted) setState(() => _currentPage = index);
                    },
                    itemBuilder: (context, index) {
                      final image = widget.images[index];
                      final fallbackLabel = arabic
                          ? 'صورة اللاندنج ${index + 1}'
                          : 'Landing image ${index + 1}';
                      return Semantics(
                        image: true,
                        label: image.alt.isEmpty ? fallbackLabel : image.alt,
                        child: Padding(
                          padding: EdgeInsetsDirectional.only(
                            end: widget.images.length > 1 ? 7 : 0,
                          ),
                          child: Image.network(
                            image.url,
                            key: Key('companyWelcomeImage-${image.id}'),
                            fit: BoxFit.cover,
                            filterQuality: FilterQuality.medium,
                            loadingBuilder: (context, child, progress) {
                              if (progress == null) return child;
                              return const ColoredBox(
                                color: SafeContractsVisual.surfaceWarm,
                                child: Center(
                                  child: CircularProgressIndicator(
                                    color: SafeContractsVisual.roseGold,
                                    strokeWidth: 2,
                                  ),
                                ),
                              );
                            },
                            errorBuilder: (context, error, stackTrace) {
                              return _LandingImageFallback(
                                label: image.alt.isEmpty
                                    ? fallbackLabel
                                    : image.alt,
                                arabic: arabic,
                                compact: widget.imageHeight < 120,
                              );
                            },
                          ),
                        ),
                      );
                    },
                  ),
                  const PositionedDirectional(
                    top: 10,
                    start: 10,
                    child: SafeContractsBrandMark(size: 42, borderRadius: 12),
                  ),
                ],
              ),
            ),
          ),
        ),
        if (widget.images.length > 1) ...[
          const SizedBox(height: SafeContractsSpacing.sm),
          Semantics(
            key: const Key('companyWelcomeImagePosition'),
            label: arabic
                ? 'الصورة ${_currentPage + 1} من ${widget.images.length}'
                : 'Image ${_currentPage + 1} of ${widget.images.length}',
            child: Row(
              mainAxisAlignment: MainAxisAlignment.center,
              children: List<Widget>.generate(
                widget.images.length,
                (index) => AnimatedContainer(
                  key: Key('companyWelcomeImageDot-$index'),
                  duration: SafeContractsMotion.fast,
                  width: index == _currentPage ? 18 : 7,
                  height: 7,
                  margin: const EdgeInsets.symmetric(horizontal: 3),
                  decoration: BoxDecoration(
                    color: index == _currentPage
                        ? SafeContractsVisual.navy
                        : SafeContractsVisual.outline,
                    borderRadius: BorderRadius.circular(
                      SafeContractsRadii.pill,
                    ),
                  ),
                ),
              ),
            ),
          ),
        ],
      ],
    );
  }
}

final class _LandingImageFallback extends StatelessWidget {
  const _LandingImageFallback({
    required this.label,
    required this.arabic,
    required this.compact,
  });

  final String label;
  final bool arabic;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            SafeContractsVisual.surfaceWarm,
            SafeContractsVisual.navySoft,
          ],
        ),
      ),
      child: compact
          ? Tooltip(
              message: label,
              child: const Center(
                child: Icon(
                  Icons.image_not_supported_outlined,
                  color: SafeContractsVisual.roseGoldDark,
                  size: 24,
                ),
              ),
            )
          : Center(
              child: Padding(
                padding: const EdgeInsets.all(SafeContractsSpacing.lg),
                child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(
                Icons.image_not_supported_outlined,
                color: SafeContractsVisual.roseGoldDark,
                size: 34,
              ),
              const SizedBox(height: SafeContractsSpacing.xs),
              Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: SafeContractsVisual.navy,
                  fontWeight: FontWeight.w800,
                ),
              ),
              const SizedBox(height: SafeContractsSpacing.xxs),
              Text(
                arabic ? 'تعذر تحميل الصورة' : 'Image unavailable',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
              ),
            ],
                ),
              ),
            ),
    );
  }
}

/// Three readable, tap-accessible feature cards share a single short row.
/// Longer marketing copy is available in a modal, not hidden below the fold.
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
    return Tooltip(
      message: description,
      child: SafeContractsCard(
        elevated: false,
        padding: const EdgeInsets.symmetric(horizontal: 4, vertical: 4),
        onTap: () => showModalBottomSheet<void>(
          context: context,
          showDragHandle: true,
          builder: (dialogContext) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(SafeContractsSpacing.lg),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Icon(icon, color: SafeContractsVisual.navy, size: 28),
                  const SizedBox(height: SafeContractsSpacing.sm),
                  Text(title, style: Theme.of(context).textTheme.titleMedium),
                  const SizedBox(height: SafeContractsSpacing.sm),
                  Text(
                    description,
                    textAlign: TextAlign.center,
                    style: Theme.of(context).textTheme.bodyMedium,
                  ),
                ],
              ),
            ),
          ),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(icon, color: SafeContractsVisual.navy, size: 20),
            const SizedBox(height: 2),
            Text(
              title,
              maxLines: 1,
              textAlign: TextAlign.center,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: SafeContractsVisual.navyDeep,
                fontWeight: FontWeight.w800,
              ),
            ),
          ],
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
    final message = arabic
        ? 'محتوى مؤقت؛ اضغط لإعادة التحميل'
        : 'Offline content — tap to retry';
    return Tooltip(
      message: message,
      child: InkWell(
        onTap: onRetry,
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.cloud_off_outlined,
              size: 14,
              color: SafeContractsVisual.amber,
            ),
            const SizedBox(width: 4),
            Flexible(
              child: Text(
                message,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: SafeContractsVisual.ink,
                  fontSize: 10,
                ),
              ),
            ),
          ],
        ),
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
