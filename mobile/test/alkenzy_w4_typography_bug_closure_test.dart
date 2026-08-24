import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_design.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_theme.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_tokens.dart';

void main() {
  test('B055 central typography scale is compact in English and Arabic', () {
    for (final languageCode in const <String>['en', 'ar']) {
      final theme = SafeContractsTheme.build(languageCode);
      final textTheme = theme.textTheme;

      expect(
        textTheme.displayLarge?.fontSize,
        SafeContractsTypography.displayLarge,
      );
      expect(
        textTheme.headlineSmall?.fontSize,
        SafeContractsTypography.headlineSmall,
      );
      expect(
        textTheme.titleLarge?.fontSize,
        SafeContractsTypography.titleLarge,
      );
      expect(
        textTheme.headlineMedium?.fontSize,
        SafeContractsTypography.headlineMedium,
      );
      expect(
        textTheme.labelSmall?.fontSize,
        SafeContractsTypography.labelSmall,
      );
      expect(
        theme.appBarTheme.titleTextStyle?.fontSize,
        SafeContractsTypography.titleLarge,
      );
    }
  });

  testWidgets('B056 shared section title consumes compact headline token', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: SafeContractsTheme.build('en'),
        home: const Scaffold(
          body: SafeContractsSectionTitle(title: 'Due information'),
        ),
      ),
    );

    final title = tester.widget<Text>(find.text('Due information'));
    expect(title.style?.fontSize, SafeContractsTypography.headlineSmall);
    expect(title.style?.height, SafeContractsTypography.headlineHeight);
    expect(tester.takeException(), isNull);
  });

  test('B056-B059 Payment Details stays bound to centralized text roles', () {
    final source = File(
      'lib/features/payments/payments_screen.dart',
    ).readAsStringSync();

    expect(source, contains("'Due information'"));
    expect(source, contains('SafeContractsSectionTitle('));
    expect(source, contains('SafeContractsPremiumHeader('));
    expect(
      source,
      contains('Theme.of(context).textTheme.headlineMedium?.copyWith('),
    );
    expect(
      source,
      contains('Theme.of(context).textTheme.labelSmall?.copyWith('),
    );

    expect(SafeContractsTypography.headlineSmall, lessThan(24));
    expect(SafeContractsTypography.titleLarge, lessThan(22));
    expect(SafeContractsTypography.headlineMedium, lessThan(28));
    expect(SafeContractsTypography.labelSmall, lessThan(11));
  });
}
