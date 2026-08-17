import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('SC-MOBILE-056 visual identity is shared across shell and pages', () {
    final design = File('lib/features/ui/safecontracts_design.dart')
        .readAsStringSync();
    final layout = File('lib/features/ui/mobile_layout.dart')
        .readAsStringSync();
    final shell = File('lib/features/navigation/app_shell.dart')
        .readAsStringSync();
    final brand = File('lib/core/branding/safe_contracts_brand.dart')
        .readAsStringSync();

    expect(design, contains('SafeContractsBackdrop'));
    expect(design, contains('_TopographicPainter'));
    expect(design, contains('SafeContractsSurface'));
    expect(design, contains('SafeContractsVisual.navy'));
    expect(design, contains('SafeContractsVisual.green'));
    expect(design, contains('SafeContractsVisual.red'));
    expect(design, contains('SafeContractsVisual.amber'));

    expect(layout, contains('SafeContractsBackdrop'));
    expect(shell, contains('_SafeContractsBottomNavigation'));
    expect(shell, contains('SafeContractsBackdrop'));
    expect(shell, contains('SafeContractsBrand.name'));
    expect(shell, contains('SafeContractsBrandMark'));
    expect(brand, contains("static const name = 'Alkenzy ADV';"));
    expect(
      brand,
      contains("static const assetPath = 'assets/brand/alkenzy_adv.png';"),
    );
    expect(shell, contains('MobileDestination.dashboard'));
    expect(shell, contains('MobileDestination.contracts'));
    expect(shell, contains('MobileDestination.payments'));
  });
}
