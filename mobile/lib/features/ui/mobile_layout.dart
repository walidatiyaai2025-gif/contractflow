import 'package:flutter/material.dart';

enum SafeContractsBreakpoint { narrow, medium, wide }

SafeContractsBreakpoint safeContractsBreakpoint(double width) {
  if (width < 600) {
    return SafeContractsBreakpoint.narrow;
  }
  if (width < 1024) {
    return SafeContractsBreakpoint.medium;
  }
  return SafeContractsBreakpoint.wide;
}

final class SafeContractsDirectionScope extends StatelessWidget {
  const SafeContractsDirectionScope({
    required this.languageCode,
    required this.child,
    super.key,
  });

  final String languageCode;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final normalized = languageCode.trim().toLowerCase();
    final direction =
        normalized == 'ar' ? TextDirection.rtl : TextDirection.ltr;
    return Directionality(textDirection: direction, child: child);
  }
}

final class SafeContractsAdaptiveBody extends StatelessWidget {
  const SafeContractsAdaptiveBody({
    required this.child,
    this.maxWidth = 960,
    super.key,
  });

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final breakpoint = safeContractsBreakpoint(constraints.maxWidth);
        final horizontalPadding = switch (breakpoint) {
          SafeContractsBreakpoint.narrow => 16.0,
          SafeContractsBreakpoint.medium => 24.0,
          SafeContractsBreakpoint.wide => 32.0,
        };
        return Align(
          alignment: Alignment.topCenter,
          child: ConstrainedBox(
            constraints: BoxConstraints(maxWidth: maxWidth),
            child: Padding(
              padding: EdgeInsets.symmetric(
                horizontal: horizontalPadding,
                vertical: 16,
              ),
              child: child,
            ),
          ),
        );
      },
    );
  }
}
