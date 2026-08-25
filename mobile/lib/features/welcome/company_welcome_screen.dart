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
  final PageController _pageController = PageController();
  int _page = 0;

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
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final arabic = widget.languageCode.toLowerCase().startsWith('ar');
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final content = widget.controller.content;
        final slides = content.images.take(3).toList(growable: false);
        final pageCount = slides.isEmpty ? 3 : slides.length;
        final selected = _page.clamp(0, pageCount - 1);
        return Scaffold(
          backgroundColor: const Color(0xFFFBF7F0),
          body: SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final narrow = constraints.maxWidth < 360;
                final horizontal = narrow ? 14.0 : 18.0;
                return RefreshIndicator(
                  onRefresh: widget.controller.refresh,
                  color: SafeContractsVisual.navy,
                  child: SingleChildScrollView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    padding:
                        EdgeInsets.fromLTRB(horizontal, 10, horizontal, 22),
                    child: Center(
                      child: ConstrainedBox(
                        constraints: const BoxConstraints(maxWidth: 520),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            Row(
                              children: [
                                const SafeContractsBrandMark(
                                  size: 40,
                                  borderRadius: 11,
                                ),
                                const SizedBox(width: 10),
                                Expanded(
                                  child: Text(
                                    SafeContractsBrand.name,
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleLarge
                                        ?.copyWith(
                                          color: SafeContractsVisual.navy,
                                          fontWeight: FontWeight.w900,
                                        ),
                                  ),
                                ),
                                if (widget.onLanguageChanged != null)
                                  SegmentedButton<String>(
                                    segments: const [
                                      ButtonSegment(
                                        value: 'ar',
                                        label: Text('ع'),
                                      ),
                                      ButtonSegment(
                                        value: 'en',
                                        label: Text('EN'),
                                      ),
                                    ],
                                    selected: {arabic ? 'ar' : 'en'},
                                    showSelectedIcon: false,
                                    onSelectionChanged: (selection) => widget
                                        .onLanguageChanged!(selection.first),
                                    style: const ButtonStyle(
                                      visualDensity: VisualDensity.compact,
                                    ),
                                  ),
                              ],
                            ),
                            const SizedBox(height: 14),
                            AspectRatio(
                              aspectRatio: 0.66,
                              child: ClipRRect(
                                borderRadius: BorderRadius.circular(28),
                                child: Stack(
                                  fit: StackFit.expand,
                                  children: [
                                    PageView.builder(
                                      key: slides.isEmpty
                                          ? null
                                          : const Key(
                                              'companyWelcomeImageCarousel',
                                            ),
                                      controller: _pageController,
                                      itemCount: pageCount,
                                      onPageChanged: (value) =>
                                          setState(() => _page = value),
                                      itemBuilder: (context, index) {
                                        if (slides.isEmpty) {
                                          return const _FallbackHero();
                                        }
                                        final image = slides[index];
                                        return Image.network(
                                          image.url,
                                          key: Key(
                                            'companyWelcomeImage-${image.id}',
                                          ),
                                          fit: BoxFit.cover,
                                          errorBuilder: (_, __, ___) =>
                                              const _FallbackHero(),
                                        );
                                      },
                                    ),
                                    const DecoratedBox(
                                      decoration: BoxDecoration(
                                        gradient: LinearGradient(
                                          begin: Alignment.topCenter,
                                          end: Alignment.bottomCenter,
                                          colors: [
                                            Color(0x33000C1A),
                                            Color(0x11000C1A),
                                            Color(0xCC001523),
                                          ],
                                          stops: [0, 0.58, 1],
                                        ),
                                      ),
                                    ),
                                    PositionedDirectional(
                                      top: narrow ? 54 : 68,
                                      start: 26,
                                      end: 26,
                                      child: Text(
                                        arabic
                                            ? 'مستقبل القيادة،\nهنا الآن'
                                            : 'The future of leadership,\nis here now',
                                        textAlign: TextAlign.center,
                                        style: TextStyle(
                                          color: Colors.white,
                                          fontSize: narrow ? 34 : 42,
                                          height: 1.18,
                                          fontWeight: FontWeight.w900,
                                          shadows: const [
                                            Shadow(
                                              color: Color(0x66000000),
                                              blurRadius: 18,
                                            ),
                                          ],
                                        ),
                                      ),
                                    ),
                                    PositionedDirectional(
                                      start: 24,
                                      end: 24,
                                      bottom: 28,
                                      child: Align(
                                        alignment:
                                            AlignmentDirectional.bottomEnd,
                                        child: Text(
                                          arabic
                                              ? 'مرحباً بك في\n${SafeContractsBrand.name}'
                                              : 'Welcome to\n${SafeContractsBrand.name}',
                                          textAlign: TextAlign.start,
                                          style: TextStyle(
                                            color: const Color(0xFFF0C77B),
                                            fontSize: narrow ? 21 : 25,
                                            height: 1.12,
                                            fontWeight: FontWeight.w900,
                                          ),
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                            const SizedBox(height: 14),
                            Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: List.generate(pageCount, (index) {
                                final active = index == selected;
                                return AnimatedContainer(
                                  key: slides.isEmpty
                                      ? null
                                      : Key('companyWelcomeImageDot-$index'),
                                  duration: const Duration(milliseconds: 180),
                                  width: active ? 34 : 12,
                                  height: 12,
                                  margin:
                                      const EdgeInsets.symmetric(horizontal: 5),
                                  decoration: BoxDecoration(
                                    color: active
                                        ? SafeContractsVisual.navy
                                        : const Color(0xFFD9D0C3),
                                    borderRadius: BorderRadius.circular(99),
                                  ),
                                );
                              }),
                            ),
                            if (slides.isNotEmpty)
                              Semantics(
                                key: const Key('companyWelcomeImagePosition'),
                                label: 'Image ${selected + 1} of $pageCount',
                                child: const SizedBox.shrink(),
                              ),
                            if (widget.controller.state ==
                                MobileLandingState.loading) ...[
                              const SizedBox(height: 10),
                              const LinearProgressIndicator(minHeight: 2),
                            ],
                            const SizedBox(height: 28),
                            Row(
                              children: [
                                Expanded(
                                  child: SizedBox(
                                    height: 66,
                                    child: FilledButton.icon(
                                      key: const Key('landingContact'),
                                      onPressed: () => _showContact(content),
                                      icon: const Icon(Icons.phone_rounded),
                                      label: Text(
                                        arabic ? 'اتصل بنا' : 'Contact us',
                                        style: const TextStyle(
                                          fontSize: 18,
                                          fontWeight: FontWeight.w900,
                                        ),
                                      ),
                                      style: FilledButton.styleFrom(
                                        backgroundColor:
                                            const Color(0xFF2977BD),
                                        foregroundColor: Colors.white,
                                        shape: RoundedRectangleBorder(
                                          borderRadius:
                                              BorderRadius.circular(18),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 14),
                                Expanded(
                                  child: SizedBox(
                                    height: 66,
                                    child: FilledButton.icon(
                                      key: const Key('companyWelcomeSignIn'),
                                      onPressed: widget.onSignIn,
                                      icon: const Icon(
                                        Icons.lock_outline_rounded,
                                      ),
                                      label: Text(
                                        arabic
                                            ? 'الدخول للنظام'
                                            : 'Enter system',
                                        style: const TextStyle(
                                          fontSize: 18,
                                          fontWeight: FontWeight.w900,
                                        ),
                                      ),
                                      style: FilledButton.styleFrom(
                                        backgroundColor:
                                            const Color(0xFF3A9C52),
                                        foregroundColor: Colors.white,
                                        shape: RoundedRectangleBorder(
                                          borderRadius:
                                              BorderRadius.circular(18),
                                        ),
                                      ),
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        );
      },
    );
  }

  Future<void> _showContact(MobileLandingContent content) {
    final arabic = widget.languageCode.toLowerCase().startsWith('ar');
    return showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      useSafeArea: true,
      builder: (context) => Padding(
        padding: const EdgeInsets.fromLTRB(20, 6, 20, 28),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              arabic ? 'اتصل بنا' : 'Contact us',
              style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w900,
                  ),
            ),
            const SizedBox(height: 12),
            ...content.phones.map(
              (phone) => ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.phone_rounded),
                title: Text(phone),
              ),
            ),
            if (content.officeAddress.resolve(widget.languageCode).isNotEmpty)
              ListTile(
                contentPadding: EdgeInsets.zero,
                leading: const Icon(Icons.location_on_outlined),
                title: Text(content.officeAddress.resolve(widget.languageCode)),
              ),
          ],
        ),
      ),
    );
  }
}

final class _FallbackHero extends StatelessWidget {
  const _FallbackHero();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFF163E5B), Color(0xFF061B2F)],
        ),
      ),
      child: Center(
        child: Icon(
          Icons.auto_graph_rounded,
          size: 96,
          color: Color(0xFF8FD7EC),
        ),
      ),
    );
  }
}
