import 'package:flutter/material.dart';

final class SafeTextDirection {
  const SafeTextDirection._();

  static TextDirection forLanguageCode(String? languageCode) {
    return languageCode?.toLowerCase() == 'ar'
        ? TextDirection.rtl
        : TextDirection.ltr;
  }
}

final class SafeResponsiveBody extends StatelessWidget {
  const SafeResponsiveBody({
    required this.child,
    this.maxWidth = 960,
    super.key,
  });

  final Widget child;
  final double maxWidth;

  @override
  Widget build(BuildContext context) {
    final width = MediaQuery.sizeOf(context).width;
    final horizontalPadding = width < 420
        ? 12.0
        : width < 720
            ? 16.0
            : 24.0;
    return Align(
      alignment: Alignment.topCenter,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: Padding(
          padding: EdgeInsets.symmetric(horizontal: horizontalPadding),
          child: child,
        ),
      ),
    );
  }
}

final class SafeResponsiveColumns extends StatelessWidget {
  const SafeResponsiveColumns({
    required this.children,
    this.breakpoint = 720,
    this.spacing = 12,
    super.key,
  });

  final List<Widget> children;
  final double breakpoint;
  final double spacing;

  @override
  Widget build(BuildContext context) {
    final wide = MediaQuery.sizeOf(context).width >= breakpoint;
    if (!wide) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: _withSpacing(children, spacing, vertical: true),
      );
    }
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: _withSpacing(
        children.map((child) => Expanded(child: child)).toList(growable: false),
        spacing,
        vertical: false,
      ),
    );
  }
}

List<Widget> _withSpacing(
  List<Widget> children,
  double spacing, {
  required bool vertical,
}) {
  final result = <Widget>[];
  for (var index = 0; index < children.length; index++) {
    if (index > 0) {
      result.add(vertical ? SizedBox(height: spacing) : SizedBox(width: spacing));
    }
    result.add(children[index]);
  }
  return result;
}
