from pathlib import Path


def replace_once(path: str, old: str, new: str) -> None:
    p = Path(path)
    text = p.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f"{path}: expected one match, got {count}: {old[:120]!r}")
    p.write_text(text.replace(old, new, 1))


tokens = "mobile/lib/features/ui/safecontracts_tokens.dart"
replace_once(
    tokens,
    """  static const labelHeight = 1.22;
}
""",
    """  static const labelHeight = 1.22;

  /// Bounded width adaptation for compact phone surfaces. This deliberately
  /// does not override the user's accessibility text scale; it only trims the
  /// repository-owned base metrics on the narrowest supported widths.
  static double viewportScale(double viewportWidth) {
    if (viewportWidth <= 320) return 0.92;
    if (viewportWidth <= 360) return 0.96;
    return 1.0;
  }
}
""",
)

design = "mobile/lib/features/ui/safecontracts_design.dart"
replace_once(
    design,
    "import 'package:flutter/material.dart';\n",
    "import 'package:flutter/material.dart';\n\nimport 'safecontracts_tokens.dart';\n",
)
replace_once(
    design,
    """    final textTheme = Theme.of(context).textTheme;
    return Container(
""",
    """    final textTheme = Theme.of(context).textTheme;
    final viewportScale = SafeContractsTypography.viewportScale(
      MediaQuery.sizeOf(context).width,
    );
    return Container(
""",
)
replace_once(
    design,
    """                  style: textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.3,
                  ),
""",
    """                  style: textTheme.titleLarge?.copyWith(
                    color: Colors.white,
                    fontSize: SafeContractsTypography.titleLarge * viewportScale,
                    height: SafeContractsTypography.titleHeight,
                    fontWeight: FontWeight.w800,
                    letterSpacing: -0.3,
                  ),
""",
)
replace_once(
    design,
    """                    style: textTheme.bodySmall?.copyWith(
                      color: Colors.white.withValues(alpha: 0.78),
                      height: 1.45,
                    ),
""",
    """                    style: textTheme.bodySmall?.copyWith(
                      color: Colors.white.withValues(alpha: 0.78),
                      fontSize: SafeContractsTypography.bodySmall * viewportScale,
                      height: SafeContractsTypography.bodyHeight,
                    ),
""",
)
# Section title has its own build; add width scale there.
old_section = """  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
"""
new_section = """  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    final viewportScale = SafeContractsTypography.viewportScale(
      MediaQuery.sizeOf(context).width,
    );
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
"""
# There are multiple Widget build snippets; target the one after SafeContractsSectionTitle.
p = Path(design)
text = p.read_text()
section_start = text.index('final class SafeContractsSectionTitle')
a = text.index(old_section, section_start)
if a < 0:
    raise SystemExit('SafeContractsSectionTitle build marker missing')
text = text[:a] + text[a:].replace(old_section, new_section, 1)
p.write_text(text)
replace_once(
    design,
    """                style: textTheme.headlineSmall?.copyWith(
                  color: SafeContractsVisual.ink,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.5,
                ),
""",
    """                style: textTheme.headlineSmall?.copyWith(
                  color: SafeContractsVisual.ink,
                  fontSize: SafeContractsTypography.headlineSmall * viewportScale,
                  height: SafeContractsTypography.headlineHeight,
                  fontWeight: FontWeight.w800,
                  letterSpacing: -0.5,
                ),
""",
)
replace_once(
    design,
    """                  style: textTheme.bodyMedium?.copyWith(
                    color: SafeContractsVisual.muted,
                  ),
""",
    """                  style: textTheme.bodyMedium?.copyWith(
                    color: SafeContractsVisual.muted,
                    fontSize: SafeContractsTypography.bodyMedium * viewportScale,
                    height: SafeContractsTypography.bodyHeight,
                  ),
""",
)

payments = "mobile/lib/features/payments/payments_screen.dart"
# Prevent duplicate list requests.
replace_once(
    payments,
    """  PaymentPage? _page;
  int _pageNumber = 1;
""",
    """  PaymentPage? _page;
  int _pageNumber = 1;
  bool _requestInFlight = false;
""",
)
replace_once(
    payments,
    """  Future<void> _load(int page, {bool background = false}) async {
    final keepVisible = background && _page != null;
""",
    """  Future<void> _load(int page, {bool background = false}) async {
    if (_requestInFlight) return;
    _requestInFlight = true;
    final keepVisible = background && _page != null;
""",
)
replace_once(
    payments,
    """    } on Object catch (error) {
      if (!mounted) return;
      if (keepVisible) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  Future<void> _open(SafeContractsPayment payment) async {
""",
    """    } on Object catch (error) {
      if (!mounted) return;
      if (keepVisible) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    } finally {
      _requestInFlight = false;
    }
  }

  Future<void> _open(SafeContractsPayment payment) async {
""",
)
# Prevent duplicate detail/retry/refresh requests.
replace_once(
    payments,
    """  String? _errorMessage;
  SafeContractsPayment? _payment;
""",
    """  String? _errorMessage;
  SafeContractsPayment? _payment;
  bool _requestInFlight = false;
""",
)
replace_once(
    payments,
    """  Future<void> _load() async {
    setState(() {
""",
    """  Future<void> _load() async {
    if (_requestInFlight) return;
    _requestInFlight = true;
    setState(() {
""",
)
replace_once(
    payments,
    """    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _payment = null;
        _loading = false;
        _errorTitle = 'Unable to load payment';
        _errorMessage = error.toString();
      });
    }
  }

  Future<void> _runAction(PaymentAction action) async {
""",
    """    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _payment = null;
        _loading = false;
        _errorTitle = 'Unable to load payment';
        _errorMessage = error.toString();
      });
    } finally {
      _requestInFlight = false;
    }
  }

  Future<void> _runAction(PaymentAction action) async {
""",
)
# Use the approved global AppBar treatment; fixes title/back-arrow contrast.
replace_once(
    payments,
    """      appBar: AppBar(
        backgroundColor: SafeContractsVisual.background,
        surfaceTintColor: Colors.transparent,
        title: Text(l10n.t('Payment details')),
      ),
""",
    """      appBar: AppBar(
        title: Text(l10n.t('Payment details')),
      ),
""",
)
# Compact payment detail density and supporting copy.
replace_once(
    payments,
    "padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),",
    "padding: const EdgeInsets.fromLTRB(14, 10, 14, 20),",
)
replace_once(payments, "const SizedBox(height: 16),\n                            _PaymentBalanceHero", "const SizedBox(height: 10),\n                            _PaymentBalanceHero")
replace_once(
    payments,
    """                            const SizedBox(height: 16),
                            SafeContractsSectionTitle(
                              title: l10n.isArabic
                                  ? 'بيانات الاستحقاق'
                                  : 'Due information',
                              subtitle: l10n.isArabic
                                  ? 'تواريخ وقيمة الدفعة كما وردت من الخادم'
                                  : 'Server-authoritative payment dates and values',
                            ),
                            const SizedBox(height: 10),
                            SafeContractsSurface(
                              padding: const EdgeInsets.all(14),
""",
    """                            const SizedBox(height: 10),
                            SafeContractsSectionTitle(
                              title: l10n.isArabic
                                  ? 'بيانات الاستحقاق'
                                  : 'Due information',
                              subtitle: l10n.isArabic
                                  ? 'التواريخ والقيم من الخادم'
                                  : 'Dates and values from the server',
                            ),
                            const SizedBox(height: 6),
                            SafeContractsSurface(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
""",
)
replace_once(
    payments,
    """                            const SizedBox(height: 16),
                            SafeContractsSectionTitle(
                              title: l10n.isArabic
                                  ? 'السياق التجاري'
                                  : 'Business context',
                            ),
                            const SizedBox(height: 10),
                            SafeContractsSurface(
                              padding: const EdgeInsets.all(14),
""",
    """                            const SizedBox(height: 10),
                            SafeContractsSectionTitle(
                              title: l10n.isArabic
                                  ? 'السياق التجاري'
                                  : 'Business context',
                            ),
                            const SizedBox(height: 6),
                            SafeContractsSurface(
                              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
""",
)
replace_once(
    payments,
    """                                        'Dates, balances and status are server-authoritative. Mobile does not recalculate receivables.',
""",
    """                                        'Server values are shown as received; mobile does not recalculate them.',
""",
)
# Compact detail rows.
replace_once(payments, "padding: const EdgeInsets.symmetric(vertical: 10),", "padding: const EdgeInsets.symmetric(vertical: 7),")
replace_once(payments, "              width: 34,\n              height: 34,", "              width: 30,\n              height: 30,")
replace_once(payments, "child: Icon(icon, size: 18, color: SafeContractsVisual.navy),", "child: Icon(icon, size: 16, color: SafeContractsVisual.navy),")
replace_once(payments, "            const SizedBox(width: 10),\n            Expanded(\n              child: Column(\n                crossAxisAlignment: CrossAxisAlignment.start,\n                children: [\n                  Text(\n                    label,", "            const SizedBox(width: 8),\n            Expanded(\n              child: Column(\n                crossAxisAlignment: CrossAxisAlignment.start,\n                children: [\n                  Text(\n                    label,")
# Primary amount: responsive + smaller currency token.
old_amount = """          Text(
            _displayMoney(context, payment.remainingAmount, currency),
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  color: SafeContractsVisual.ink,
                  fontWeight: FontWeight.w900,
                ),
          ),
"""
new_amount = """          _PaymentMoneyAmount(
            amount: payment.remainingAmount,
            currency: currency,
          ),
"""
replace_once(payments, old_amount, new_amount)
# Add the compact money widget before _DetailValue.
insert = "final class _DetailValue extends StatelessWidget {\n"
p = Path(payments)
text = p.read_text()
if text.count(insert) != 1:
    raise SystemExit('DetailValue marker mismatch')
money_widget = r'''final class _PaymentMoneyAmount extends StatelessWidget {
  const _PaymentMoneyAmount({required this.amount, required this.currency});

  final String amount;
  final MobileCurrencyConfig currency;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final token = currency.displayToken;
    final formatted = _displayMoney(context, amount, currency);
    final numeric = token.isEmpty ? formatted : formatted.replaceFirst(token, '').trim();
    final scale = SafeContractsTypography.viewportScale(MediaQuery.sizeOf(context).width);
    final amountStyle = Theme.of(context).textTheme.headlineMedium?.copyWith(
          color: SafeContractsVisual.ink,
          fontSize: SafeContractsTypography.headlineMedium * scale,
          height: SafeContractsTypography.headlineHeight,
          fontWeight: FontWeight.w900,
        );
    final currencyStyle = Theme.of(context).textTheme.labelLarge?.copyWith(
          color: SafeContractsVisual.muted,
          fontSize: SafeContractsTypography.labelLarge * scale,
          height: SafeContractsTypography.labelHeight,
          fontWeight: FontWeight.w800,
        );
    if (token.isEmpty) {
      return Text(formatted, maxLines: 1, overflow: TextOverflow.ellipsis, style: amountStyle);
    }
    final children = l10n.isArabic
        ? <InlineSpan>[
            TextSpan(text: numeric, style: amountStyle),
            const TextSpan(text: ' '),
            TextSpan(text: token, style: currencyStyle),
          ]
        : <InlineSpan>[
            TextSpan(text: token, style: currencyStyle),
            const TextSpan(text: ' '),
            TextSpan(text: numeric, style: amountStyle),
          ];
    return FittedBox(
      fit: BoxFit.scaleDown,
      alignment: AlignmentDirectional.centerStart,
      child: Text.rich(TextSpan(children: children), maxLines: 1),
    );
  }
}

'''
p.write_text(text.replace(insert, money_widget + insert, 1))

# Need typography tokens in the payment screen.
replace_once(
    payments,
    "import '../ui/safecontracts_design.dart';\n",
    "import '../ui/safecontracts_design.dart';\nimport '../ui/safecontracts_tokens.dart';\n",
)

# Add targeted regression coverage.
test = Path("mobile/test/alkenzy_w4_density_bug_closure_test.dart")
test.write_text(r'''import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/ui/safecontracts_tokens.dart';

void main() {
  test('B066 bounded width typography preserves compact phone hierarchy', () {
    expect(SafeContractsTypography.viewportScale(320), 0.92);
    expect(SafeContractsTypography.viewportScale(360), 0.96);
    expect(SafeContractsTypography.viewportScale(375), 1.0);
    expect(SafeContractsTypography.viewportScale(430), 1.0);
  });

  test('B004/B011/B060-B061/B065/B067-B070 payment detail uses shared compact contracts', () {
    final payment = File('lib/features/payments/payments_screen.dart').readAsStringSync();
    final theme = File('lib/features/ui/safecontracts_theme.dart').readAsStringSync();
    final design = File('lib/features/ui/safecontracts_design.dart').readAsStringSync();

    expect(payment, contains("appBar: AppBar(\n        title: Text(l10n.t('Payment details'))"));
    expect(payment, isNot(contains('backgroundColor: SafeContractsVisual.background,\n        surfaceTintColor')));
    expect(payment, contains('bool _requestInFlight = false;'));
    expect(payment, contains('if (_requestInFlight) return;'));
    expect(payment, contains('padding: const EdgeInsets.symmetric(vertical: 7)'));
    expect(payment, contains('Theme.of(context).textTheme.bodyMedium?.copyWith('));
    expect(payment, contains('_PaymentMoneyAmount('));
    expect(payment, contains('SafeContractsTypography.viewportScale'));
    expect(payment, contains('Dates and values from the server'));
    expect(payment, contains('Server values are shown as received; mobile does not recalculate them.'));

    expect(theme, contains('titleTextStyle: textTheme.titleLarge?.copyWith('));
    expect(theme, contains('SafeContractsTypography.labelSmall'));
    expect(design, contains('SafeContractsTypography.headlineSmall * viewportScale'));
    expect(design, contains('SafeContractsTypography.titleLarge * viewportScale'));
  });
}
''')

print('W4 density closure patch applied')
