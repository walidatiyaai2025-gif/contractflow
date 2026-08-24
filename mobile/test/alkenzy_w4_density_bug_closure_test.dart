import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_tokens.dart';

void main() {
  test('B066 bounded width typography preserves compact phone hierarchy', () {
    expect(SafeContractsTypography.viewportScale(320), 0.92);
    expect(SafeContractsTypography.viewportScale(360), 0.96);
    expect(SafeContractsTypography.viewportScale(375), 1.0);
    expect(SafeContractsTypography.viewportScale(430), 1.0);
  });

  test(
      'B004/B011/B060-B061/B065/B067-B070 payment detail uses shared compact contracts',
      () {
    final payment =
        File('lib/features/payments/payments_screen.dart').readAsStringSync();
    final theme =
        File('lib/features/ui/safecontracts_theme.dart').readAsStringSync();
    final design =
        File('lib/features/ui/safecontracts_design.dart').readAsStringSync();

    expect(
        payment,
        contains(
            "appBar: AppBar(\n        title: Text(l10n.t('Payment details'))"));
    expect(
        payment,
        isNot(contains(
            'backgroundColor: SafeContractsVisual.background,\n        surfaceTintColor')));
    expect(payment, contains('bool _requestInFlight = false;'));
    expect(payment, contains('if (_requestInFlight) return;'));
    expect(
        payment, contains('padding: const EdgeInsets.symmetric(vertical: 7)'));
    expect(
        payment, contains('Theme.of(context).textTheme.bodyMedium?.copyWith('));
    expect(payment, contains('_PaymentMoneyAmount('));
    expect(payment, contains('SafeContractsTypography.viewportScale'));
    expect(payment,
        contains('fontSize: SafeContractsTypography.labelLarge * scale'));
    expect(payment, contains('Dates and values from the server'));
    expect(
        payment,
        contains(
            'Dates, balances and status are server-authoritative. Mobile does not recalculate them.'));

    expect(theme, contains('titleTextStyle: textTheme.titleLarge?.copyWith('));
    expect(theme, contains('foregroundColor: Colors.white'));
    expect(theme, contains('SafeContractsTypography.labelSmall'));
    expect(design,
        contains('SafeContractsTypography.headlineSmall * viewportScale'));
    expect(
        design, contains('SafeContractsTypography.titleLarge * viewportScale'));
  });
}
