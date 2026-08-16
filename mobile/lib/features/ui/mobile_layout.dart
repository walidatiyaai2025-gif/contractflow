import 'package:flutter/material.dart';

import 'safecontracts_design.dart';

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

bool safeContractsIsRtlLanguage(String languageCode) {
  final normalized = languageCode.trim().toLowerCase();
  return normalized == 'ar' ||
      normalized.startsWith('ar-') ||
      normalized.startsWith('ar_');
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
    final direction = safeContractsIsRtlLanguage(languageCode)
        ? TextDirection.rtl
        : TextDirection.ltr;
    return Directionality(textDirection: direction, child: child);
  }
}

final class SafeContractsAdaptiveBody extends StatelessWidget {
  const SafeContractsAdaptiveBody({
    required this.child,
    this.maxWidth = 1040,
    super.key,
  }) : assert(maxWidth > 0);

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    return SafeContractsBackdrop(
      child: LayoutBuilder(
        builder: (context, constraints) {
          final breakpoint = safeContractsBreakpoint(constraints.maxWidth);
          final horizontalPadding = switch (breakpoint) {
            SafeContractsBreakpoint.narrow => 18.0,
            SafeContractsBreakpoint.medium => 26.0,
            SafeContractsBreakpoint.wide => 36.0,
          };
          final verticalPadding = switch (breakpoint) {
            SafeContractsBreakpoint.narrow => 18.0,
            SafeContractsBreakpoint.medium => 22.0,
            SafeContractsBreakpoint.wide => 28.0,
          };
          return Align(
            alignment: Alignment.topCenter,
            child: ConstrainedBox(
              constraints: BoxConstraints(maxWidth: maxWidth),
              child: Padding(
                padding: EdgeInsets.symmetric(
                  horizontal: horizontalPadding,
                  vertical: verticalPadding,
                ),
                child: child,
              ),
            ),
          );
        },
      ),
    );
  }
}
