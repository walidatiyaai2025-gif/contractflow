import 'dart:io';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_design.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_tokens.dart';

void main() {
  test('B055 central typography scale is applied to Arabic and English theme', () {
    final source = File(
      'lib/features/ui/safecontracts_theme.dart',
    ).readAsStringSync();

    expect(source, contains('GoogleFonts.cairoTextTheme()'));
    expect(source, contains('GoogleFonts.interTextTheme()'));
    expect(
      source,
      contains('fontSize: SafeContractsTypography.headlineSmall'),
    );
    expect(
      source,
      contains('fontSize: SafeContractsTypography.titleLarge'),
    );
    expect(
      source,
      contains('fontSize: SafeContractsTypography.headlineMedium'),
    );
    expect(
      source,
      contains('fontSize: SafeContractsTypography.labelSmall'),
    );
    expect(
      source,
      contains('titleTextStyle: textTheme.titleLarge?.copyWith('),
    );
  });

  testWidgets('B056 shared section title consumes compact headline token', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData(
          textTheme: const TextTheme(
            headlineSmall: TextStyle(
              fontSize: SafeContractsTypography.headlineSmall,
              height: SafeContractsTypography.headlineHeight,
            ),
          ),
        ),
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
