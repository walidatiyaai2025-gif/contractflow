import 'dart:math' as math;

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../ui/safecontracts_design.dart';
import 'mobile_landing.dart';

/// Additive landing composition for Alkenzy ADV 0.3.2. It is deliberately a
/// single viewport (no ScrollView) so the primary authentication actions are
/// always visible on supported phone sizes.
final class PremiumLandingScreen extends StatefulWidget {
  const PremiumLandingScreen({
    required this.controller,
    required this.languageCode,
    required this.onLanguageChanged,
    required this.onSignIn,
    super.key,
  });

  final MobileLandingController controller;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;
  final VoidCallback onSignIn;

  @override
  State<PremiumLandingScreen> createState() => _PremiumLandingScreenState();
}

final class _PremiumLandingScreenState extends State<PremiumLandingScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback(
      (_) => widget.controller.ensureLoaded(),
    );
  }

  @override
  Widget build(BuildContext context) {
    final arabic = widget.languageCode.toLowerCase() == 'ar';
    return Scaffold(
      backgroundColor: const Color(0xFF092B49),
      body: AnimatedBuilder(
        animation: widget.controller,
        builder: (context, child) {
          final content = widget.controller.content;
          return SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final compact = constraints.maxHeight < 720;
                return Container(
                  width: double.infinity,
                  height: constraints.maxHeight,
                  decoration: const BoxDecoration(
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        Color(0xFFDAE5F1),
                        Color(0xFF7595B7),
                        Color(0xFF173A5E),
                        Color(0xFF082B4A),
                      ],
                      stops: [0, .30, .64, 1],
                    ),
                  ),
                  child: Padding(
                    padding: EdgeInsets.fromLTRB(
                      compact ? 18 : 22,
                      compact ? 10 : 14,
                      compact ? 18 : 22,
                      compact ? 12 : 18,
                    ),
                    child: Column(
                      children: [
                        Row(
                          children: [
                            const SafeContractsBrandMark(
                              size: 42,
                              borderRadius: 12,
                            ),
                            const SizedBox(width: 10),
                            Expanded(
                              child: Text(
                                content.brandName,
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 17,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ),
                            _LanguageButton(
                              arabic: arabic,
                              onPressed: () => widget.onLanguageChanged(
                                arabic ? 'en' : 'ar',
                              ),
                            ),
                          ],
                        ),
                        SizedBox(height: compact ? 4 : 8),
                        Expanded(
                          flex: compact ? 7 : 8,
                          child: const _FinanceIllustration(),
                        ),
                        Flexible(
                          flex: compact ? 4 : 5,
                          child: Column(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Text(
                                arabic
                                    ? 'الكنزي: ريادة في تخطيط\nالمقاولات وتمويل العقود'
                                    : 'Alkenzy: leadership in contract\nplanning and finance',
                                textAlign: TextAlign.center,
                                maxLines: 2,
                                style: TextStyle(
                                  color: const Color(0xFFF0D4C2),
                                  fontSize: compact ? 20 : 24,
                                  height: 1.25,
                                  fontWeight: FontWeight.w900,
                                  shadows: const [
                                    Shadow(
                                      color: Color(0x55051C30),
                                      blurRadius: 10,
                                      offset: Offset(0, 3),
                                    ),
                                  ],
                                ),
                              ),
                              SizedBox(height: compact ? 6 : 10),
                              Text(
                                arabic
                                    ? 'شريكك الموثوق لبناء مستقبلك المالي'
                                    : 'Your trusted partner for a stronger financial future',
                                textAlign: TextAlign.center,
                                maxLines: 2,
                                style: TextStyle(
                                  color: Colors.white.withValues(alpha: .88),
                                  fontSize: compact ? 12 : 14,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                              SizedBox(height: compact ? 6 : 10),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: List.generate(
                                  4,
                                  (index) => Container(
                                    width: index == 0 ? 18 : 6,
                                    height: 6,
                                    margin: const EdgeInsets.symmetric(horizontal: 3),
                                    decoration: BoxDecoration(
                                      color: index == 0
                                          ? const Color(0xFFE5B09C)
                                          : Colors.white.withValues(alpha: .48),
                                      borderRadius: BorderRadius.circular(99),
                                    ),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                        SizedBox(height: compact ? 6 : 10),
                        _PrimaryButton(
                          label: content.signInLabel.resolve(widget.languageCode),
                          onPressed: widget.onSignIn,
                        ),
                        SizedBox(height: compact ? 7 : 10),
                        _SecondaryButton(
                          label: arabic ? 'إنشاء حساب جديد' : 'Create new account',
                          onPressed: widget.onSignIn,
                        ),
                        if (widget.controller.state == MobileLandingState.loading)
                          const Padding(
                            padding: EdgeInsets.only(top: 6),
                            child: LinearProgressIndicator(
                              minHeight: 2,
                              color: Color(0xFFE0A38F),
                              backgroundColor: Colors.transparent,
                            ),
                          ),
                      ],
                    ),
                  ),
                );
              },
            ),
          );
        },
      ),
    );
  }
}

final class _FinanceIllustration extends StatelessWidget {
  const _FinanceIllustration();

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final w = constraints.maxWidth;
        final h = constraints.maxHeight;
        return Stack(
          alignment: Alignment.center,
          children: [
            Positioned(
              left: w * .03,
              right: w * .03,
              bottom: h * .08,
              height: h * .52,
              child: Transform(
                alignment: Alignment.center,
                transform: Matrix4.identity()
                  ..setEntry(3, 2, .0012)
                  ..rotateX(-.78),
                child: Container(
                  decoration: BoxDecoration(
                    borderRadius: BorderRadius.circular(18),
                    gradient: LinearGradient(
                      begin: Alignment.topLeft,
                      end: Alignment.bottomRight,
                      colors: [
                        Colors.white.withValues(alpha: .23),
                        const Color(0xFF284C71).withValues(alpha: .62),
                      ],
                    ),
                    border: Border.all(
                      color: Colors.white.withValues(alpha: .22),
                    ),
                    boxShadow: const [
                      BoxShadow(
                        color: Color(0x5504192B),
                        blurRadius: 26,
                        offset: Offset(0, 16),
                      ),
                    ],
                  ),
                ),
              ),
            ),
            Positioned(
              bottom: h * .18,
              child: SizedBox(
                width: math.min(w * .76, 300),
                height: h * .66,
                child: CustomPaint(painter: const _PremiumBarsPainter()),
              ),
            ),
            Positioned(
              right: w * .12,
              top: h * .16,
              child: _Coin(size: math.min(54, w * .15), label: '↗'),
            ),
            Positioned(
              left: w * .09,
              top: h * .28,
              child: _Coin(size: math.min(42, w * .12), label: '%'),
            ),
            Positioned(
              right: w * .22,
              bottom: h * .10,
              child: _Coin(size: math.min(58, w * .16), label: '¤'),
            ),
          ],
        );
      },
    );
  }
}

final class _PremiumBarsPainter extends CustomPainter {
  const _PremiumBarsPainter();

  @override
  void paint(Canvas canvas, Size size) {
    final heights = [
      size.height * .28,
      size.height * .42,
      size.height * .63,
      size.height * .82,
    ];
    final widths = size.width * .13;
    final gap = size.width * .085;
    final start = size.width * .08;
    final paints = [
      const Color(0xFF4E7CA8),
      const Color(0xFFC3B0B0),
      const Color(0xFF557FA7),
      const Color(0xFFD1A7A8),
    ];
    for (var i = 0; i < heights.length; i++) {
      final x = start + i * (widths + gap);
      final rect = RRect.fromRectAndRadius(
        Rect.fromLTWH(x, size.height - heights[i], widths, heights[i]),
        const Radius.circular(4),
      );
      final shader = LinearGradient(
        begin: Alignment.topCenter,
        end: Alignment.bottomCenter,
        colors: [paints[i].withValues(alpha: .95), paints[i].withValues(alpha: .48)],
      ).createShader(rect.outerRect);
      canvas.drawRRect(rect, Paint()..shader = shader);
      canvas.drawRRect(
        rect,
        Paint()
          ..style = PaintingStyle.stroke
          ..strokeWidth = 1.1
          ..color = Colors.white.withValues(alpha: .40),
      );
    }

    final path = Path()
      ..moveTo(size.width * .12, size.height * .70)
      ..lineTo(size.width * .34, size.height * .55)
      ..lineTo(size.width * .50, size.height * .60)
      ..lineTo(size.width * .69, size.height * .32)
      ..lineTo(size.width * .87, size.height * .18);
    canvas.drawPath(
      path,
      Paint()
        ..style = PaintingStyle.stroke
        ..strokeWidth = 5
        ..strokeCap = StrokeCap.round
        ..strokeJoin = StrokeJoin.round
        ..color = const Color(0xFFD7A5A2),
    );
    final end = Offset(size.width * .87, size.height * .18);
    canvas.drawCircle(end, 7, Paint()..color = const Color(0xFFE6B5AD));
  }

  @override
  bool shouldRepaint(covariant CustomPainter oldDelegate) => false;
}

final class _Coin extends StatelessWidget {
  const _Coin({required this.size, required this.label});
  final double size;
  final String label;
  @override
  Widget build(BuildContext context) => Container(
        width: size,
        height: size,
        alignment: Alignment.center,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [Color(0xFFF4EBEF), Color(0xFFA9B9D2)],
          ),
          border: Border.all(color: Colors.white.withValues(alpha: .55)),
          boxShadow: const [
            BoxShadow(
              color: Color(0x4406192B),
              blurRadius: 13,
              offset: Offset(0, 6),
            ),
          ],
        ),
        child: Text(
          label,
          style: TextStyle(
            color: SafeContractsVisual.navy,
            fontSize: size * .37,
            fontWeight: FontWeight.w900,
          ),
        ),
      );
}

final class _LanguageButton extends StatelessWidget {
  const _LanguageButton({required this.arabic, required this.onPressed});
  final bool arabic;
  final VoidCallback onPressed;
  @override
  Widget build(BuildContext context) => IconButton.filledTonal(
        tooltip: arabic ? 'English' : 'العربية',
        onPressed: onPressed,
        style: IconButton.styleFrom(
          backgroundColor: Colors.white.withValues(alpha: .12),
          foregroundColor: Colors.white,
        ),
        icon: const Icon(Icons.language_rounded),
      );
}

final class _PrimaryButton extends StatelessWidget {
  const _PrimaryButton({required this.label, required this.onPressed});
  final String label;
  final VoidCallback onPressed;
  @override
  Widget build(BuildContext context) => SizedBox(
        width: double.infinity,
        height: 48,
        child: FilledButton(
          onPressed: onPressed,
          style: FilledButton.styleFrom(
            backgroundColor: const Color(0xFFD5937F),
            foregroundColor: Colors.white,
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
          ),
          child: Text(label, style: const TextStyle(fontWeight: FontWeight.w900)),
        ),
      );
}

final class _SecondaryButton extends StatelessWidget {
  const _SecondaryButton({required this.label, required this.onPressed});
  final String label;
  final VoidCallback onPressed;
  @override
  Widget build(BuildContext context) => SizedBox(
        width: double.infinity,
        height: 46,
        child: OutlinedButton(
          onPressed: onPressed,
          style: OutlinedButton.styleFrom(
            foregroundColor: Colors.white,
            side: BorderSide(color: Colors.white.withValues(alpha: .82)),
            shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(9)),
          ),
          child: Text(label, style: const TextStyle(fontWeight: FontWeight.w800)),
        ),
      );
}
