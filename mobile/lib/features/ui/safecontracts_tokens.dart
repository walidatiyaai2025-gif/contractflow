import 'package:flutter/material.dart';

/// Central Alkenzy ADV mobile design tokens.
///
/// Keep screen widgets free from one-off spacing, radii and control sizes.
abstract final class SafeContractsSpacing {
  static const xxs = 4.0;
  static const xs = 8.0;
  static const sm = 12.0;
  static const md = 16.0;
  static const lg = 20.0;
  static const xl = 24.0;
  static const xxl = 32.0;
  static const xxxl = 40.0;

  // Locked-reference page geometry.
  static const screenNarrow = 14.0;
  static const screen = 18.0;
  static const screenWide = 24.0;
}

abstract final class SafeContractsRadii {
  static const xs = 10.0;
  static const sm = 14.0;
  static const md = 18.0;
  static const lg = 18.0;
  static const xl = 28.0;
  static const pill = 999.0;
}

abstract final class SafeContractsIconSizes {
  static const xs = 16.0;
  static const sm = 20.0;
  static const md = 24.0;
  static const lg = 30.0;
  static const xl = 36.0;
}

abstract final class SafeContractsControlSizes {
  static const fieldMinHeight = 52.0;
  static const buttonMinHeight = 52.0;
  static const compactButtonMinHeight = 44.0;
  static const bottomNavigationHeight = 70.0;
  static const floatingActionButton = 58.0;
  static const touchTarget = 44.0;
}

abstract final class SafeContractsShadows {
  static const card = <BoxShadow>[
    BoxShadow(
      color: Color(0x185A4638),
      blurRadius: 18,
      offset: Offset(0, 7),
    ),
  ];

  static const elevatedCard = <BoxShadow>[
    BoxShadow(
      color: Color(0x30092944),
      blurRadius: 28,
      offset: Offset(0, 12),
    ),
  ];

  static const navy = <BoxShadow>[
    BoxShadow(
      color: Color(0x30092944),
      blurRadius: 28,
      offset: Offset(0, 12),
    ),
  ];

  static const navigation = <BoxShadow>[
    BoxShadow(
      color: Color(0x243D3028),
      blurRadius: 22,
      offset: Offset(0, -5),
    ),
  ];
}

abstract final class SafeContractsMotion {
  static const fast = Duration(milliseconds: 160);
  static const standard = Duration(milliseconds: 240);
  static const emphasized = Duration(milliseconds: 320);
}

/// Semantic status palette used by shared status components.
enum SafeContractsStatusTone { neutral, success, warning, danger, info }

final class SafeContractsStatusColors {
  const SafeContractsStatusColors({
    required this.foreground,
    required this.background,
    required this.border,
  });

  final Color foreground;
  final Color background;
  final Color border;
}
