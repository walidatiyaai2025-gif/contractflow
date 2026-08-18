import 'package:flutter/material.dart';

abstract final class SafeContractsBrand {
  static const name = 'Enterprise Safe Contracts';
  static const assetPath = 'assets/brand/safe_contracts_identity.jpg';
}

final class SafeContractsBrandMark extends StatelessWidget {
  const SafeContractsBrandMark({
    this.size = 48,
    this.borderRadius = 14,
    this.semanticLabel,
    super.key,
  });

  final double size;
  final double borderRadius;
  final String? semanticLabel;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      image: true,
      label: semanticLabel ?? SafeContractsBrand.name,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(borderRadius),
        child: Image.asset(
          SafeContractsBrand.assetPath,
          width: size,
          height: size,
          fit: BoxFit.cover,
          gaplessPlayback: true,
          filterQuality: FilterQuality.high,
          excludeFromSemantics: true,
        ),
      ),
    );
  }
}
