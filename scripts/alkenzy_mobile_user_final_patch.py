from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding='utf-8')


def write(path: str, text: str) -> None:
    (ROOT / path).write_text(text, encoding='utf-8')


def replace_once(path: str, old: str, new: str) -> None:
    text = read(path)
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'FAIL {path}: expected one marker, found {count}: {old[:100]!r}')
    write(path, text.replace(old, new, 1))


def replace_regex(path: str, pattern: str, replacement: str, flags: int = 0) -> None:
    text = read(path)
    next_text, count = re.subn(pattern, lambda _: replacement, text, count=1, flags=flags)
    if count != 1:
        raise SystemExit(f'FAIL {path}: regex marker count={count}: {pattern[:120]!r}')
    write(path, next_text)


def insert_before(path: str, marker: str, addition: str) -> None:
    replace_once(path, marker, addition + marker)


# ---------------------------------------------------------------------------
# App: persistent palette + biometric startup gate.
# ---------------------------------------------------------------------------
path = 'mobile/lib/app.dart'
replace_once(
    path,
    "import 'core/auth/mobile_token_store.dart';\n",
    "import 'core/auth/biometric_auth.dart';\nimport 'core/auth/mobile_token_store.dart';\n",
)
replace_once(
    path,
    "import 'features/ui/safecontracts_theme.dart';\n",
    "import 'features/ui/safecontracts_theme.dart';\nimport 'features/ui/theme_palette.dart';\n",
)
replace_once(
    path,
    "ThemeData _theme(String languageCode) {",
    "ThemeData _theme(String languageCode, AlkenzyThemePalette palette) {",
)
replace_once(
    path,
    "  return SafeContractsTheme.build(languageCode);",
    "  return SafeContractsTheme.build(languageCode, palette);",
)
replace_once(
    path,
    "  late final MobileLandingController _landingController;\n",
    "  late final MobileLandingController _landingController;\n"
    "  late final BiometricAuthService _biometricAuth;\n"
    "  late final ThemePaletteController _themePaletteController;\n"
    "  bool _securityInitializing = true;\n"
    "  bool _biometricGate = false;\n"
    "  bool _biometricBusy = false;\n"
    "  String? _biometricMessage;\n",
)
replace_once(
    path,
    "    unawaited(_localeController.load());\n",
    "    unawaited(_localeController.load());\n"
    "    _themePaletteController = ThemePaletteController();\n"
    "    unawaited(_themePaletteController.load());\n"
    "    _biometricAuth = BiometricAuthService();\n",
)
replace_once(
    path,
    "    unawaited(_bootstrap.bootstrap());\n",
    "    unawaited(_initializeSecurity());\n",
)
insert_before(
    path,
    "  Future<void> _afterAuthenticated() async {\n",
    "  Future<void> _initializeSecurity() async {\n"
    "    String? token;\n"
    "    var biometricEnabled = false;\n"
    "    try {\n"
    "      token = await _tokenStore.read();\n"
    "      biometricEnabled = await _biometricAuth.isEnabled();\n"
    "    } on Object {\n"
    "      token = null;\n"
    "      biometricEnabled = false;\n"
    "    }\n"
    "    if (!mounted) return;\n"
    "    if (token != null && biometricEnabled) {\n"
    "      setState(() {\n"
    "        _securityInitializing = false;\n"
    "        _biometricGate = true;\n"
    "      });\n"
    "      await _runBiometricUnlock();\n"
    "      return;\n"
    "    }\n"
    "    setState(() => _securityInitializing = false);\n"
    "    await _bootstrap.bootstrap();\n"
    "  }\n\n"
    "  Future<void> _runBiometricUnlock() async {\n"
    "    if (_biometricBusy) return;\n"
    "    setState(() {\n"
    "      _biometricBusy = true;\n"
    "      _biometricMessage = null;\n"
    "    });\n"
    "    final result = await _biometricAuth.authenticate(\n"
    "      isArabic: _localeController.languageCode == 'ar',\n"
    "    );\n"
    "    if (!mounted) return;\n"
    "    if (!result.success) {\n"
    "      setState(() {\n"
    "        _biometricBusy = false;\n"
    "        _biometricMessage = result.message;\n"
    "      });\n"
    "      return;\n"
    "    }\n"
    "    setState(() {\n"
    "      _biometricBusy = false;\n"
    "      _biometricGate = false;\n"
    "      _biometricMessage = null;\n"
    "    });\n"
    "    await _bootstrap.bootstrap();\n"
    "  }\n\n"
    "  Future<void> _usePasswordInstead() async {\n"
    "    if (_biometricBusy) return;\n"
    "    setState(() => _biometricBusy = true);\n"
    "    try {\n"
    "      try {\n"
    "        await _authRepository.logout();\n"
    "      } on Object {\n"
    "        await _tokenStore.clear();\n"
    "      }\n"
    "      if (!mounted) return;\n"
    "      setState(() {\n"
    "        _biometricBusy = false;\n"
    "        _biometricGate = false;\n"
    "        _biometricMessage = null;\n"
    "      });\n"
    "      await _bootstrap.bootstrap();\n"
    "    } finally {\n"
    "      if (mounted && _biometricBusy) {\n"
    "        setState(() => _biometricBusy = false);\n"
    "      }\n"
    "    }\n"
    "  }\n\n",
)
replace_once(
    path,
    "    _localeController.dispose();\n",
    "    _localeController.dispose();\n    _themePaletteController.dispose();\n",
)
replace_once(
    path,
    "      animation: _localeController,",
    "      animation: Listenable.merge([_localeController, _themePaletteController]),",
)
replace_once(
    path,
    "        theme: _theme(_localeController.languageCode),",
    "        theme: _theme(\n          _localeController.languageCode,\n          _themePaletteController.palette,\n        ),",
)
replace_regex(
    path,
    r"        home: _BootstrapView\(\n.*?          onLogout: _logout,\n        \),",
    "        home: _securityInitializing || _biometricGate\n"
    "            ? _BiometricUnlockScreen(\n"
    "                busy: _securityInitializing || _biometricBusy,\n"
    "                languageCode: _localeController.languageCode,\n"
    "                message: _biometricMessage,\n"
    "                onRetry: _securityInitializing\n"
    "                    ? null\n"
    "                    : () => unawaited(_runBiometricUnlock()),\n"
    "                onUsePassword: _securityInitializing\n"
    "                    ? null\n"
    "                    : () => unawaited(_usePasswordInstead()),\n"
    "              )\n"
    "            : _BootstrapView(\n"
    "                environment: widget.environment,\n"
    "                controller: _bootstrap,\n"
    "                landingController: _landingController,\n"
    "                loginController: _loginController,\n"
    "                biometricAuth: _biometricAuth,\n"
    "                themePaletteController: _themePaletteController,\n"
    "                pushRegistration: _pushRegistration,\n"
    "                languageCode: _localeController.languageCode,\n"
    "                onLanguageChanged: _localeController.setLanguageCode,\n"
    "                onAuthenticated: _afterAuthenticated,\n"
    "                onReady: _startPushIfNeeded,\n"
    "                onLogout: _logout,\n"
    "              ),",
    flags=re.S,
)
insert_before(
    path,
    "final class _BootstrapView extends StatefulWidget {\n",
    "final class _BiometricUnlockScreen extends StatelessWidget {\n"
    "  const _BiometricUnlockScreen({\n"
    "    required this.busy,\n"
    "    required this.languageCode,\n"
    "    required this.message,\n"
    "    required this.onRetry,\n"
    "    required this.onUsePassword,\n"
    "  });\n\n"
    "  final bool busy;\n"
    "  final String languageCode;\n"
    "  final String? message;\n"
    "  final VoidCallback? onRetry;\n"
    "  final VoidCallback? onUsePassword;\n\n"
    "  @override\n"
    "  Widget build(BuildContext context) {\n"
    "    final ar = languageCode == 'ar';\n"
    "    return Scaffold(\n"
    "      backgroundColor: const Color(0xFF061B2F),\n"
    "      body: SafeArea(\n"
    "        child: Center(\n"
    "          child: SingleChildScrollView(\n"
    "            padding: const EdgeInsets.all(24),\n"
    "            child: ConstrainedBox(\n"
    "              constraints: const BoxConstraints(maxWidth: 430),\n"
    "              child: Column(\n"
    "                children: [\n"
    "                  const SafeContractsBrandMark(size: 76, borderRadius: 20),\n"
    "                  const SizedBox(height: 24),\n"
    "                  Text(\n"
    "                    ar ? 'الدخول بالبصمة' : 'Fingerprint sign in',\n"
    "                    textAlign: TextAlign.center,\n"
    "                    style: Theme.of(context).textTheme.headlineMedium?.copyWith(\n"
    "                          color: Colors.white,\n"
    "                          fontWeight: FontWeight.w900,\n"
    "                        ),\n"
    "                  ),\n"
    "                  const SizedBox(height: 8),\n"
    "                  Text(\n"
    "                    ar\n"
    "                        ? 'أكد هويتك ببصمة الجهاز لفتح مساحة عمل Alkenzy ADV.'\n"
    "                        : 'Confirm your fingerprint to unlock the Alkenzy ADV workspace.',\n"
    "                    textAlign: TextAlign.center,\n"
    "                    style: TextStyle(color: Colors.white.withValues(alpha: 0.72)),\n"
    "                  ),\n"
    "                  const SizedBox(height: 28),\n"
    "                  Container(\n"
    "                    width: 96,\n"
    "                    height: 96,\n"
    "                    decoration: BoxDecoration(\n"
    "                      color: Colors.white.withValues(alpha: 0.08),\n"
    "                      shape: BoxShape.circle,\n"
    "                      border: Border.all(color: Colors.white24),\n"
    "                    ),\n"
    "                    child: busy\n"
    "                        ? const Padding(\n"
    "                            padding: EdgeInsets.all(30),\n"
    "                            child: CircularProgressIndicator(color: Colors.white),\n"
    "                          )\n"
    "                        : const Icon(Icons.fingerprint_rounded, size: 58, color: Color(0xFFF1F0DE)),\n"
    "                  ),\n"
    "                  if (message != null) ...[\n"
    "                    const SizedBox(height: 18),\n"
    "                    Text(\n"
    "                      message!,\n"
    "                      textAlign: TextAlign.center,\n"
    "                      style: const TextStyle(color: Color(0xFFFFD5CD)),\n"
    "                    ),\n"
    "                  ],\n"
    "                  const SizedBox(height: 24),\n"
    "                  SizedBox(\n"
    "                    width: double.infinity,\n"
    "                    child: FilledButton.icon(\n"
    "                      onPressed: busy ? null : onRetry,\n"
    "                      icon: const Icon(Icons.fingerprint_rounded),\n"
    "                      label: Text(ar ? 'استخدام البصمة' : 'Use fingerprint'),\n"
    "                    ),\n"
    "                  ),\n"
    "                  const SizedBox(height: 8),\n"
    "                  TextButton(\n"
    "                    onPressed: busy ? null : onUsePassword,\n"
    "                    child: Text(\n"
    "                      ar ? 'الدخول بكلمة المرور بدلًا من ذلك' : 'Use password instead',\n"
    "                      style: const TextStyle(color: Color(0xFFF1F0DE)),\n"
    "                    ),\n"
    "                  ),\n"
    "                ],\n"
    "              ),\n"
    "            ),\n"
    "          ),\n"
    "        ),\n"
    "      ),\n"
    "    );\n"
    "  }\n"
    "}\n\n",
)
replace_once(
    path,
    "    required this.loginController,\n    required this.pushRegistration,",
    "    required this.loginController,\n"
    "    required this.biometricAuth,\n"
    "    required this.themePaletteController,\n"
    "    required this.pushRegistration,",
)
replace_once(
    path,
    "  final MobileLoginController loginController;\n  final MobilePushRegistration pushRegistration;",
    "  final MobileLoginController loginController;\n"
    "  final BiometricAuthService biometricAuth;\n"
    "  final ThemePaletteController themePaletteController;\n"
    "  final MobilePushRegistration pushRegistration;",
)
replace_once(
    path,
    "              pushRegistration: widget.pushRegistration,\n              languageCode: widget.languageCode,",
    "              pushRegistration: widget.pushRegistration,\n"
    "              themePaletteController: widget.themePaletteController,\n"
    "              languageCode: widget.languageCode,",
)
replace_once(
    path,
    "            controller: widget.loginController,\n            languageCode: widget.languageCode,",
    "            controller: widget.loginController,\n"
    "            biometricAuth: widget.biometricAuth,\n"
    "            languageCode: widget.languageCode,",
)

# ---------------------------------------------------------------------------
# Login: visible language control + post-password biometric enrollment prompt.
# ---------------------------------------------------------------------------
path = 'mobile/lib/features/auth/login_screen.dart'
replace_once(
    path,
    "import '../../core/branding/safe_contracts_brand.dart';\n",
    "import '../../core/auth/biometric_auth.dart';\nimport '../../core/branding/safe_contracts_brand.dart';\n",
)
replace_once(
    path,
    "    required this.onAuthenticated,\n    this.languageCode = 'en',",
    "    required this.onAuthenticated,\n    required this.biometricAuth,\n    this.languageCode = 'en',",
)
replace_once(
    path,
    "  final MobileLoginController controller;\n  final String languageCode;",
    "  final MobileLoginController controller;\n  final BiometricAuthService biometricAuth;\n  final String languageCode;",
)
replace_once(
    path,
    "      if (!success || !mounted) return;\n      _password.clear();\n      await widget.onAuthenticated();",
    "      if (!success || !mounted) return;\n"
    "      await _offerBiometricEnrollment();\n"
    "      if (!mounted) return;\n"
    "      _password.clear();\n"
    "      await widget.onAuthenticated();",
)
insert_before(
    path,
    "  @override\n  Widget build(BuildContext context) {\n",
    "  Future<void> _offerBiometricEnrollment() async {\n"
    "    if (await widget.biometricAuth.isEnabled()) return;\n"
    "    if (!await widget.biometricAuth.isAvailable() || !mounted) return;\n"
    "    final ar = context.scL10n.isArabic;\n"
    "    final enable = await showDialog<bool>(\n"
    "      context: context,\n"
    "      barrierDismissible: false,\n"
    "      builder: (dialogContext) => AlertDialog(\n"
    "        icon: const Icon(Icons.fingerprint_rounded, size: 42),\n"
    "        title: Text(ar ? 'تفعيل الدخول بالبصمة؟' : 'Enable fingerprint sign in?'),\n"
    "        content: Text(\n"
    "          ar\n"
    "              ? 'بعد التفعيل سيطلب التطبيق بصمة الجهاز في كل مرة تفتح فيها جلسة محفوظة. كلمة المرور لا يتم تخزينها.'\n"
    "              : 'Once enabled, the app will require your device fingerprint whenever you open a saved session. Your password is never stored.',\n"
    "        ),\n"
    "        actions: [\n"
    "          TextButton(\n"
    "            onPressed: () => Navigator.pop(dialogContext, false),\n"
    "            child: Text(ar ? 'ليس الآن' : 'Not now'),\n"
    "          ),\n"
    "          FilledButton.icon(\n"
    "            onPressed: () => Navigator.pop(dialogContext, true),\n"
    "            icon: const Icon(Icons.fingerprint_rounded),\n"
    "            label: Text(ar ? 'تفعيل' : 'Enable'),\n"
    "          ),\n"
    "        ],\n"
    "      ),\n"
    "    );\n"
    "    if (enable != true || !mounted) return;\n"
    "    final result = await widget.biometricAuth.authenticate(\n"
    "      isArabic: ar,\n"
    "      enrollment: true,\n"
    "    );\n"
    "    if (!mounted) return;\n"
    "    if (!result.success) {\n"
    "      ScaffoldMessenger.of(context).showSnackBar(\n"
    "        SnackBar(content: Text(result.message ?? (ar ? 'تعذر تفعيل البصمة.' : 'Unable to enable fingerprint.'))),\n"
    "      );\n"
    "      return;\n"
    "    }\n"
    "    await widget.controller.persistForBiometricLogin();\n"
    "    await widget.biometricAuth.setEnabled(true);\n"
    "    if (!mounted) return;\n"
    "    ScaffoldMessenger.of(context).showSnackBar(\n"
    "      SnackBar(\n"
    "        content: Text(ar\n"
    "            ? 'تم تفعيل الدخول بالبصمة. سيطلبها التطبيق عند الدخول التالي.'\n"
    "            : 'Fingerprint sign in is enabled and will be required next time.'),\n"
    "      ),\n"
    "    );\n"
    "  }\n\n",
)
replace_once(
    path,
    "                                        const SizedBox(\n                                          height: SafeContractsSpacing.lg,\n                                        ),\n                                        SafeContractsTextField(",
    "                                        const SizedBox(\n"
    "                                          height: SafeContractsSpacing.md,\n"
    "                                        ),\n"
    "                                        Container(\n"
    "                                          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),\n"
    "                                          decoration: BoxDecoration(\n"
    "                                            color: SafeContractsVisual.backgroundRaised,\n"
    "                                            borderRadius: BorderRadius.circular(14),\n"
    "                                            border: Border.all(color: SafeContractsVisual.outline),\n"
    "                                          ),\n"
    "                                          child: Row(\n"
    "                                            children: [\n"
    "                                              Icon(Icons.language_rounded, color: Theme.of(context).colorScheme.primary),\n"
    "                                              const SizedBox(width: 8),\n"
    "                                              Expanded(\n"
    "                                                child: Text(\n"
    "                                                  l10n.isArabic ? 'اللغة' : 'Language',\n"
    "                                                  style: const TextStyle(fontWeight: FontWeight.w800),\n"
    "                                                ),\n"
    "                                              ),\n"
    "                                              SegmentedButton<String>(\n"
    "                                                segments: const [\n"
    "                                                  ButtonSegment(value: 'en', label: Text('English')),\n"
    "                                                  ButtonSegment(value: 'ar', label: Text('العربية')),\n"
    "                                                ],\n"
    "                                                selected: <String>{selectedLanguage},\n"
    "                                                showSelectedIcon: false,\n"
    "                                                onSelectionChanged: submitting || widget.onLanguageChanged == null\n"
    "                                                    ? null\n"
    "                                                    : (selection) => widget.onLanguageChanged!(selection.first),\n"
    "                                              ),\n"
    "                                            ],\n"
    "                                          ),\n"
    "                                        ),\n"
    "                                        const SizedBox(\n"
    "                                          height: SafeContractsSpacing.lg,\n"
    "                                        ),\n"
    "                                        SafeContractsTextField(",
)

# ---------------------------------------------------------------------------
# Theme: runtime palette influences Material components and app chrome.
# ---------------------------------------------------------------------------
path = 'mobile/lib/features/ui/safecontracts_theme.dart'
replace_once(path, "import 'safecontracts_tokens.dart';\n", "import 'safecontracts_tokens.dart';\nimport 'theme_palette.dart';\n")
replace_once(
    path,
    "  static ThemeData build(String languageCode) {",
    "  static ThemeData build(\n    String languageCode,\n    AlkenzyThemePalette palette,\n  ) {",
)
replace_once(path, "    const scheme = ColorScheme.light(", "    final scheme = ColorScheme.light(")
replace_once(path, "      primary: SafeContractsVisual.navy,", "      primary: palette.primary,")
replace_once(path, "      primaryContainer: SafeContractsVisual.navySoft,", "      primaryContainer: palette.soft,")
replace_once(path, "      onPrimaryContainer: SafeContractsVisual.navyDeep,", "      onPrimaryContainer: palette.deep,")
replace_once(path, "      secondary: SafeContractsVisual.roseGold,", "      secondary: palette.accent,")
replace_once(path, "      secondaryContainer: SafeContractsVisual.roseGoldSoft,", "      secondaryContainer: palette.accent.withValues(alpha: 0.18),")
replace_once(path, "        backgroundColor: SafeContractsVisual.navy,", "        backgroundColor: palette.primary,")
replace_once(path, "            color: SafeContractsVisual.roseGold,", "            color: palette.accent,")
replace_once(path, "          backgroundColor: SafeContractsVisual.navy,", "          backgroundColor: palette.primary,")
replace_once(path, "          disabledBackgroundColor: SafeContractsVisual.navySoft,", "          disabledBackgroundColor: palette.soft,")
replace_once(path, "          backgroundColor: SafeContractsVisual.roseGold,", "          backgroundColor: palette.accent,")
replace_once(path, "          foregroundColor: SafeContractsVisual.navy,", "          foregroundColor: palette.primary,")
replace_once(path, "          side: const BorderSide(color: SafeContractsVisual.navy),", "          side: BorderSide(color: palette.primary),")
replace_once(path, "          foregroundColor: SafeContractsVisual.navy,\n          textStyle:", "          foregroundColor: palette.primary,\n          textStyle:")

# ---------------------------------------------------------------------------
# Contract model: legacy supplier fallbacks prevent wrong/blank supplier data.
# ---------------------------------------------------------------------------
path = 'mobile/lib/features/contracts/contracts.dart'
replace_once(
    path,
    "    final legacyCustomerId = _optionalPositiveInt(\n      data['customer_id'],\n      'contract.customer_id',\n    );\n    final type = _optionalText(data['counterparty_type'])?.toLowerCase() ??\n        (legacyCustomerId != null ? 'customer' : '');\n",
    "    final legacyCustomerId = _optionalPositiveInt(\n"
    "      data['customer_id'],\n"
    "      'contract.customer_id',\n"
    "    );\n"
    "    final legacySupplierId = _optionalPositiveInt(\n"
    "      data['supplier_id'],\n"
    "      'contract.supplier_id',\n"
    "    );\n"
    "    if (legacyCustomerId != null && legacySupplierId != null) {\n"
    "      throw const FormatException('contract has conflicting customer and supplier IDs.');\n"
    "    }\n"
    "    final type = _optionalText(data['counterparty_type'])?.toLowerCase() ??\n"
    "        (legacyCustomerId != null\n"
    "            ? 'customer'\n"
    "            : legacySupplierId != null\n"
    "                ? 'supplier'\n"
    "                : '');\n",
)
replace_once(
    path,
    "        (type == 'customer' ? legacyCustomerId : null);",
    "        (type == 'customer' ? legacyCustomerId : legacySupplierId);",
)
replace_once(
    path,
    "      counterpartyName: _optionalText(data['counterparty_name']) ??\n          _optionalText(data['customer_name']),",
    "      counterpartyName: _optionalText(data['counterparty_name']) ??\n"
    "          (type == 'supplier'\n"
    "              ? _optionalText(data['supplier_name'])\n"
    "              : _optionalText(data['customer_name'])),",
)

# ---------------------------------------------------------------------------
# Contracts print current visible grid.
# ---------------------------------------------------------------------------
path = 'mobile/lib/features/contracts/contracts_screen.dart'
replace_once(path, "import '../suppliers/suppliers.dart';\n", "import '../suppliers/suppliers.dart';\nimport '../reports/report_printing.dart';\n")
replace_once(
    path,
    "                onCreate: widget.controller.canCreateContract\n                    ? () => unawaited(_openCreate())\n                    : null,\n              ),",
    "                onCreate: widget.controller.canCreateContract\n"
    "                    ? () => unawaited(_openCreate())\n"
    "                    : null,\n"
    "                report: _contractsReport(context, contracts),\n"
    "              ),",
)
replace_once(
    path,
    "    required this.onCreate,\n  });",
    "    required this.onCreate,\n    required this.report,\n  });",
)
replace_once(
    path,
    "  final VoidCallback? onCreate;\n",
    "  final VoidCallback? onCreate;\n  final ReportGrid report;\n",
)
replace_once(
    path,
    "                    IconButton.filledTonal(\n                      tooltip: context.scL10n.t('Refresh contracts'),",
    "                    GridPrintButton(\n"
    "                      report: report,\n"
    "                      busy: busy,\n"
    "                      compact: true,\n"
    "                    ),\n"
    "                    const SizedBox(width: 4),\n"
    "                    IconButton.filledTonal(\n"
    "                      tooltip: context.scL10n.t('Refresh contracts'),",
)
insert_before(
    path,
    "final class _ContractsContent extends StatelessWidget {\n",
    "ReportGrid _contractsReport(\n"
    "  BuildContext context,\n"
    "  List<SafeContractsContract> contracts,\n"
    ") {\n"
    "  final ar = context.scL10n.isArabic;\n"
    "  return ReportGrid(\n"
    "    title: ar ? 'العقود المعروضة' : 'Visible contracts',\n"
    "    fileStem: 'contracts_grid',\n"
    "    columns: ar\n"
    "        ? const ['العقد', 'الطرف', 'النوع', 'الاتجاه', 'القيمة', 'العملة', 'الحالة', 'البداية', 'النهاية']\n"
    "        : const ['Contract', 'Counterparty', 'Type', 'Direction', 'Value', 'Currency', 'Status', 'Start', 'End'],\n"
    "    rows: contracts\n"
    "        .map((item) => [\n"
    "              item.contractNumber,\n"
    "              item.displayCounterparty,\n"
    "              item.counterpartyType,\n"
    "              item.financialDirection,\n"
    "              item.baseValue ?? '',\n"
    "              item.currencyCode,\n"
    "              context.scL10n.status(item.status),\n"
    "              item.startDate ?? '',\n"
    "              item.endDate ?? '',\n"
    "            ])\n"
    "        .toList(growable: false),\n"
    "  );\n"
    "}\n\n",
)

# ---------------------------------------------------------------------------
# Customers print current visible grid. Also remove accidental +500 presentation
# suffix if the source phone contains that exact separated UI artifact; valid
# phone digits remain unchanged.
# ---------------------------------------------------------------------------
path = 'mobile/lib/features/customers/customers_screen.dart'
replace_once(path, "import '../contracts/contracts.dart';\n", "import '../contracts/contracts.dart';\nimport '../reports/report_printing.dart';\n")
replace_once(
    path,
    "                      onCreate: widget.controller.canCreate\n                          ? () => unawaited(_openEditor())\n                          : null,\n                    ),",
    "                      onCreate: widget.controller.canCreate\n"
    "                          ? () => unawaited(_openEditor())\n"
    "                          : null,\n"
    "                      report: _customersReport(context, visible),\n"
    "                    ),",
)
replace_once(
    path,
    "    required this.onCreate,\n  });",
    "    required this.onCreate,\n    required this.report,\n  });",
)
replace_once(
    path,
    "  final VoidCallback? onCreate;\n\n  @override\n  Widget build(BuildContext context) {",
    "  final VoidCallback? onCreate;\n  final ReportGrid report;\n\n  @override\n  Widget build(BuildContext context) {",
)
replace_once(
    path,
    "                    IconButton.filledTonal(\n                      tooltip: context.scL10n.t('Refresh customers'),",
    "                    GridPrintButton(\n"
    "                      report: report,\n"
    "                      busy: busy,\n"
    "                      compact: true,\n"
    "                    ),\n"
    "                    IconButton.filledTonal(\n"
    "                      tooltip: context.scL10n.t('Refresh customers'),",
)
insert_before(
    path,
    "final class _CustomerBody extends StatelessWidget {\n",
    "ReportGrid _customersReport(\n"
    "  BuildContext context,\n"
    "  List<SafeContractsCustomer> customers,\n"
    ") {\n"
    "  final ar = context.scL10n.isArabic;\n"
    "  return ReportGrid(\n"
    "    title: ar ? 'العملاء المعروضون' : 'Visible customers',\n"
    "    fileStem: 'customers_grid',\n"
    "    columns: ar\n"
    "        ? const ['الرقم', 'الكود', 'العميل', 'جهة الاتصال', 'الهاتف', 'البريد', 'الحالة']\n"
    "        : const ['ID', 'Code', 'Customer', 'Contact', 'Phone', 'Email', 'Status'],\n"
    "    rows: customers\n"
    "        .map((item) => [\n"
    "              '${item.id}',\n"
    "              item.internalCode ?? '',\n"
    "              item.name,\n"
    "              item.contactName ?? '',\n"
    "              _customerPhoneForDisplay(item.phone),\n"
    "              item.email ?? '',\n"
    "              context.scL10n.status(item.isActive ? 'active' : 'inactive'),\n"
    "            ])\n"
    "        .toList(growable: false),\n"
    "  );\n"
    "}\n\n"
    "String _customerPhoneForDisplay(String? value) {\n"
    "  final text = value?.trim() ?? '';\n"
    "  return text.replaceFirst(RegExp(r'\\s+\\+500$'), '').trim();\n"
    "}\n\n",
)
replace_once(
    path,
    "      if (customer.phone != null) customer.phone!,",
    "      if (customer.phone != null) _customerPhoneForDisplay(customer.phone),",
)
replace_once(
    path,
    "        value: customer.phone\n      ),",
    "        value: _customerPhoneForDisplay(customer.phone)\n      ),",
)

# ---------------------------------------------------------------------------
# Payments print current grid and remove obsolete five-page client cap.
# ---------------------------------------------------------------------------
path = 'mobile/lib/features/payments/payments_screen.dart'
replace_once(path, "import '../dashboard/dashboard_models.dart';\n", "import '../dashboard/dashboard_models.dart';\nimport '../reports/report_printing.dart';\n")
replace_once(
    path,
    "    return SafeContractsBackdrop(\n      child: Column(\n        children: [\n          if (_loading) const LinearProgressIndicator(minHeight: 2),\n          Expanded(",
    "    final report = _paymentsReport(context, page.payments);\n"
    "    return SafeContractsBackdrop(\n"
    "      child: Column(\n"
    "        children: [\n"
    "          if (_loading) const LinearProgressIndicator(minHeight: 2),\n"
    "          Padding(\n"
    "            padding: const EdgeInsets.fromLTRB(14, 10, 14, 0),\n"
    "            child: Row(\n"
    "              children: [\n"
    "                Expanded(\n"
    "                  child: Text(\n"
    "                    l10n.isArabic ? 'الدفعات' : 'Payments',\n"
    "                    style: Theme.of(context).textTheme.titleLarge?.copyWith(\n"
    "                          fontWeight: FontWeight.w900,\n"
    "                        ),\n"
    "                  ),\n"
    "                ),\n"
    "                GridPrintButton(\n"
    "                  report: report,\n"
    "                  busy: _loading,\n"
    "                  compact: true,\n"
    "                ),\n"
    "              ],\n"
    "            ),\n"
    "          ),\n"
    "          Expanded(",
)
replace_once(
    path,
    "            onNext: page.hasMore && page.page < 5\n                ? () => unawaited(_load(page.page + 1))\n                : null,",
    "            onNext: page.hasMore\n                ? () => unawaited(_load(page.page + 1))\n                : null,",
)
insert_before(
    path,
    "final class _PremiumPaymentCard extends StatelessWidget {\n",
    "ReportGrid _paymentsReport(\n"
    "  BuildContext context,\n"
    "  List<SafeContractsPayment> payments,\n"
    ") {\n"
    "  final ar = context.scL10n.isArabic;\n"
    "  return ReportGrid(\n"
    "    title: ar ? 'الدفعات المعروضة' : 'Visible payments',\n"
    "    fileStem: 'payments_grid',\n"
    "    columns: ar\n"
    "        ? const ['المرجع', 'العقد', 'الطرف', 'الاستحقاق', 'الأصلي', 'المدفوع', 'المتبقي', 'الاتجاه', 'الحالة']\n"
    "        : const ['Reference', 'Contract', 'Counterparty', 'Due', 'Original', 'Paid', 'Remaining', 'Direction', 'Status'],\n"
    "    rows: payments\n"
    "        .map((item) => [\n"
    "              item.reference ?? '#${item.id}',\n"
    "              item.contractNumber ?? '#${item.contractId}',\n"
    "              item.displayOwner ?? '',\n"
    "              item.dueDate,\n"
    "              item.originalAmount,\n"
    "              item.paidAmount,\n"
    "              item.remainingAmount,\n"
    "              item.financialDirection,\n"
    "              context.scL10n.status(item.status),\n"
    "            ])\n"
    "        .toList(growable: false),\n"
    "  );\n"
    "}\n\n",
)

# Payment model/repository: accept every page the bounded backend can expose.
path = 'mobile/lib/features/payments/payments.dart'
replace_once(path, "      page: _boundedInt(meta['page'], 'meta.page', 1, 5),", "      page: _boundedInt(meta['page'], 'meta.page', 1, 1000000),")
replace_once(
    path,
    "    if (page < 1 || page > 5) {\n      throw ArgumentError('Payment page must be between 1 and 5.');\n    }",
    "    if (page < 1 || page > 1000000) {\n"
    "      throw ArgumentError('Payment page is outside the supported server range.');\n"
    "    }",
)

# ---------------------------------------------------------------------------
# Shell: screenshot-inspired sidebar, reports, theme switch, no floating quick-add.
# ---------------------------------------------------------------------------
path = 'mobile/lib/features/navigation/app_shell.dart'
replace_once(path, "import '../records/mobile_quick_add_screen.dart';\n", "")
replace_once(path, "import '../profile/profile_screen.dart';\n", "import '../profile/profile_screen.dart';\nimport '../reports/reports_screen.dart';\n")
replace_once(path, "import '../ui/safecontracts_design.dart';\n", "import '../ui/safecontracts_design.dart';\nimport '../ui/theme_palette.dart';\n")
replace_once(
    path,
    "    required this.pushRegistration,\n    required this.languageCode,",
    "    required this.pushRegistration,\n    required this.themePaletteController,\n    required this.languageCode,",
)
replace_once(
    path,
    "  final MobilePushRegistration pushRegistration;\n  final String languageCode;",
    "  final MobilePushRegistration pushRegistration;\n  final ThemePaletteController themePaletteController;\n  final String languageCode;",
)
replace_once(
    path,
    "        case MobileDestination.notifications:\n          await widget.notificationsController.refreshSilently();",
    "        case MobileDestination.notifications:\n          await widget.notificationsController.refreshSilently();",
)
replace_once(
    path,
    "        case MobileDestination.export:\n          await widget.dashboardController.refreshSilently();",
    "        case MobileDestination.reports:\n          break;\n"
    "        case MobileDestination.export:\n"
    "          await widget.dashboardController.refreshSilently();",
)
replace_once(path, "    final quickAdds = availableMobileQuickAdds(widget.session);\n", "")
replace_once(
    path,
    "        title: Row(\n          children: [",
    "        actions: [\n"
    "          IconButton(\n"
    "            tooltip: l10n.isArabic ? 'تغيير لون الثيم' : 'Change theme color',\n"
    "            onPressed: () => unawaited(widget.themePaletteController.cycleAlternative()),\n"
    "            icon: const Icon(Icons.palette_outlined),\n"
    "          ),\n"
    "        ],\n"
    "        title: Row(\n"
    "          children: [",
)
replace_regex(
    path,
    r"      drawer: NavigationDrawer\(.*?\n      body: SafeContractsBackdrop\(",
    "      drawer: _AlkenzyDrawer(\n"
    "        destinations: widget.policy.destinations,\n"
    "        selected: _selected,\n"
    "        paletteController: widget.themePaletteController,\n"
    "        labelFor: (destination) => _label(l10n, destination),\n"
    "        iconFor: _icon,\n"
    "        onSelected: (destination) {\n"
    "          _selectDestination(destination);\n"
    "          Navigator.of(context).pop();\n"
    "        },\n"
    "      ),\n"
    "      body: SafeContractsBackdrop(",
    flags=re.S,
)
replace_regex(
    path,
    r"      floatingActionButton: quickAdds\.isEmpty.*?      floatingActionButtonLocation: FloatingActionButtonLocation\.centerDocked,\n",
    "      floatingActionButton: null,\n",
    flags=re.S,
)
replace_regex(
    path,
    r"  Future<void> _showQuickAdd\(List<MobileQuickAddType> actions\) async \{.*?\n  void _openContract\(int contractId\) \{",
    "  void _openContract(int contractId) {",
    flags=re.S,
)
replace_regex(
    path,
    r"final class _QuickAddFab extends StatefulWidget \{.*?\nfinal class _SafeContractsBottomNavigation extends StatelessWidget \{",
    "final class _SafeContractsBottomNavigation extends StatelessWidget {",
    flags=re.S,
)
replace_once(
    path,
    "      MobileDestination.notifications => NotificationsScreen(\n          controller: widget.notificationsController,\n          onOpenDeepLink: _openDeepLink,\n        ),\n      MobileDestination.export =>",
    "      MobileDestination.notifications => NotificationsScreen(\n"
    "          controller: widget.notificationsController,\n"
    "          onOpenDeepLink: _openDeepLink,\n"
    "        ),\n"
    "      MobileDestination.reports => ReportsScreen(\n"
    "          repository: MobileReportsRepository(apiClient),\n"
    "        ),\n"
    "      MobileDestination.export =>",
)
insert_before(
    path,
    "final class _SafeContractsBottomNavigation extends StatelessWidget {\n",
    "final class _AlkenzyDrawer extends StatelessWidget {\n"
    "  const _AlkenzyDrawer({\n"
    "    required this.destinations,\n"
    "    required this.selected,\n"
    "    required this.paletteController,\n"
    "    required this.labelFor,\n"
    "    required this.iconFor,\n"
    "    required this.onSelected,\n"
    "  });\n\n"
    "  final List<MobileDestination> destinations;\n"
    "  final MobileDestination selected;\n"
    "  final ThemePaletteController paletteController;\n"
    "  final String Function(MobileDestination destination) labelFor;\n"
    "  final IconData Function(MobileDestination destination) iconFor;\n"
    "  final ValueChanged<MobileDestination> onSelected;\n\n"
    "  @override\n"
    "  Widget build(BuildContext context) {\n"
    "    const cream = Color(0xFFF1F0DE);\n"
    "    const drawerNavy = Color(0xFF07304F);\n"
    "    final ar = context.scL10n.isArabic;\n"
    "    return Drawer(\n"
    "      width: MediaQuery.sizeOf(context).width * 0.84,\n"
    "      backgroundColor: drawerNavy,\n"
    "      surfaceTintColor: Colors.transparent,\n"
    "      child: SafeArea(\n"
    "        child: Column(\n"
    "          children: [\n"
    "            Container(\n"
    "              margin: const EdgeInsets.fromLTRB(14, 12, 14, 10),\n"
    "              padding: const EdgeInsets.fromLTRB(18, 18, 18, 18),\n"
    "              decoration: BoxDecoration(\n"
    "                color: const Color(0xFF0D496F),\n"
    "                borderRadius: BorderRadius.circular(28),\n"
    "              ),\n"
    "              child: Row(\n"
    "                children: [\n"
    "                  const SafeContractsBrandMark(size: 58, borderRadius: 16),\n"
    "                  const SizedBox(width: 14),\n"
    "                  Expanded(\n"
    "                    child: Column(\n"
    "                      crossAxisAlignment: CrossAxisAlignment.start,\n"
    "                      children: [\n"
    "                        Text(\n"
    "                          SafeContractsBrand.name,\n"
    "                          style: Theme.of(context).textTheme.headlineSmall?.copyWith(\n"
    "                                color: Colors.white,\n"
    "                                fontWeight: FontWeight.w700,\n"
    "                              ),\n"
    "                        ),\n"
    "                        Text(\n"
    "                          ar ? 'مساحة العمل التنفيذية' : 'Executive workspace',\n"
    "                          style: Theme.of(context).textTheme.bodyMedium?.copyWith(\n"
    "                                color: Colors.white60,\n"
    "                              ),\n"
    "                        ),\n"
    "                      ],\n"
    "                    ),\n"
    "                  ),\n"
    "                ],\n"
    "              ),\n"
    "            ),\n"
    "            Expanded(\n"
    "              child: ListView.builder(\n"
    "                padding: const EdgeInsets.fromLTRB(14, 2, 14, 8),\n"
    "                itemCount: destinations.length,\n"
    "                itemBuilder: (context, index) {\n"
    "                  final destination = destinations[index];\n"
    "                  final active = destination == selected;\n"
    "                  return Padding(\n"
    "                    padding: const EdgeInsets.symmetric(vertical: 3),\n"
    "                    child: Material(\n"
    "                      color: active\n"
    "                          ? const Color(0xFF536A7A).withValues(alpha: 0.72)\n"
    "                          : Colors.transparent,\n"
    "                      borderRadius: BorderRadius.circular(28),\n"
    "                      child: InkWell(\n"
    "                        borderRadius: BorderRadius.circular(28),\n"
    "                        onTap: () => onSelected(destination),\n"
    "                        child: Padding(\n"
    "                          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),\n"
    "                          child: Row(\n"
    "                            children: [\n"
    "                              Icon(iconFor(destination), color: cream, size: 27),\n"
    "                              const SizedBox(width: 18),\n"
    "                              Expanded(\n"
    "                                child: Text(\n"
    "                                  labelFor(destination),\n"
    "                                  maxLines: 1,\n"
    "                                  overflow: TextOverflow.ellipsis,\n"
    "                                  style: Theme.of(context).textTheme.titleMedium?.copyWith(\n"
    "                                        color: cream,\n"
    "                                        fontWeight: active ? FontWeight.w700 : FontWeight.w500,\n"
    "                                      ),\n"
    "                                ),\n"
    "                              ),\n"
    "                            ],\n"
    "                          ),\n"
    "                        ),\n"
    "                      ),\n"
    "                    ),\n"
    "                  );\n"
    "                },\n"
    "              ),\n"
    "            ),\n"
    "            AnimatedBuilder(\n"
    "              animation: paletteController,\n"
    "              builder: (context, child) => Container(\n"
    "                margin: const EdgeInsets.fromLTRB(14, 6, 14, 12),\n"
    "                padding: const EdgeInsets.all(12),\n"
    "                decoration: BoxDecoration(\n"
    "                  color: Colors.white.withValues(alpha: 0.07),\n"
    "                  borderRadius: BorderRadius.circular(18),\n"
    "                ),\n"
    "                child: Row(\n"
    "                  children: [\n"
    "                    const Icon(Icons.palette_outlined, color: cream),\n"
    "                    const SizedBox(width: 10),\n"
    "                    Expanded(\n"
    "                      child: Text(\n"
    "                        ar ? 'لون الثيم' : 'Theme color',\n"
    "                        style: const TextStyle(color: cream, fontWeight: FontWeight.w700),\n"
    "                      ),\n"
    "                    ),\n"
    "                    for (final palette in AlkenzyThemePalette.values)\n"
    "                      Padding(\n"
    "                        padding: const EdgeInsetsDirectional.only(start: 5),\n"
    "                        child: Tooltip(\n"
    "                          message: palette.label(ar),\n"
    "                          child: InkWell(\n"
    "                            borderRadius: BorderRadius.circular(99),\n"
    "                            onTap: () => unawaited(paletteController.setPalette(palette)),\n"
    "                            child: Container(\n"
    "                              width: 24,\n"
    "                              height: 24,\n"
    "                              decoration: BoxDecoration(\n"
    "                                color: palette.primary,\n"
    "                                shape: BoxShape.circle,\n"
    "                                border: Border.all(\n"
    "                                  color: paletteController.palette == palette ? cream : Colors.white24,\n"
    "                                  width: paletteController.palette == palette ? 2.5 : 1,\n"
    "                                ),\n"
    "                              ),\n"
    "                            ),\n"
    "                          ),\n"
    "                        ),\n"
    "                      ),\n"
    "                  ],\n"
    "                ),\n"
    "              ),\n"
    "            ),\n"
    "          ],\n"
    "        ),\n"
    "      ),\n"
    "    );\n"
    "  }\n"
    "}\n\n",
)
replace_once(path, "    return l10n.isArabic ? 'لوحة تحكم اتنين' : 'Dashboard Two';", "    return l10n.isArabic ? 'تبويب لوحة التحكم' : 'Dashboard Tab';")
replace_once(
    path,
    "    MobileDestination.notifications => 'Notifications',\n    MobileDestination.export => 'Excel export',",
    "    MobileDestination.notifications => 'Notifications',\n"
    "    MobileDestination.reports => l10n.isArabic ? 'التقارير' : 'Reports',\n"
    "    MobileDestination.export => 'Excel export',",
)
replace_once(
    path,
    "    MobileDestination.notifications => Icons.notifications_outlined,\n    MobileDestination.export => Icons.file_download_outlined,",
    "    MobileDestination.notifications => Icons.notifications_outlined,\n"
    "    MobileDestination.reports => Icons.analytics_outlined,\n"
    "    MobileDestination.export => Icons.file_download_outlined,",
)

# ---------------------------------------------------------------------------
# Android bootstrap + CI APK verification for biometrics.
# ---------------------------------------------------------------------------
path = 'scripts/bootstrap_android.sh'
replace_once(
    path,
    "    'android.permission.POST_NOTIFICATIONS',\n]",
    "    'android.permission.POST_NOTIFICATIONS',\n    'android.permission.USE_BIOMETRIC',\n]",
)
replace_once(
    path,
    "grep -Fq 'android.permission.POST_NOTIFICATIONS' \"$MANIFEST\" || {\n  echo \"FAIL: Android release manifest is missing POST_NOTIFICATIONS permission\" >&2\n  exit 1\n}\n",
    "grep -Fq 'android.permission.POST_NOTIFICATIONS' \"$MANIFEST\" || {\n"
    "  echo \"FAIL: Android release manifest is missing POST_NOTIFICATIONS permission\" >&2\n"
    "  exit 1\n"
    "}\n"
    "grep -Fq 'android.permission.USE_BIOMETRIC' \"$MANIFEST\" || {\n"
    "  echo \"FAIL: Android release manifest is missing USE_BIOMETRIC permission\" >&2\n"
    "  exit 1\n"
    "}\n",
)
replace_once(
    path,
    "grep -Fq 'safecontracts/notifications' \"$MAIN_ACTIVITY_TARGET\" || {",
    "grep -Fq 'FlutterFragmentActivity' \"$MAIN_ACTIVITY_TARGET\" || {\n"
    "  echo \"FAIL: Android release activity must use FlutterFragmentActivity for local_auth\" >&2\n"
    "  exit 1\n"
    "}\n"
    "grep -Fq 'safecontracts/notifications' \"$MAIN_ACTIVITY_TARGET\" || {",
)
replace_once(
    path,
    "echo \"Alkenzy ADV Android scaffold bootstrapped with supplied Alkenzy launcher icon, high-importance tray notifications, release signing, INTERNET, notifications, and Firebase contracts.\"",
    "echo \"Alkenzy ADV Android scaffold bootstrapped with biometrics, supplied launcher icon, high-importance notifications, release signing, networking, and Firebase contracts.\"",
)

path = '.github/workflows/quality-gates.yml'
replace_once(
    path,
    "          grep -Fq \"android.permission.POST_NOTIFICATIONS\" \"$RUNNER_TEMP/safecontracts-apk-permissions.txt\"\n",
    "          grep -Fq \"android.permission.POST_NOTIFICATIONS\" \"$RUNNER_TEMP/safecontracts-apk-permissions.txt\"\n"
    "          grep -Fq \"android.permission.USE_BIOMETRIC\" \"$RUNNER_TEMP/safecontracts-apk-permissions.txt\"\n",
)
replace_once(
    path,
    "          The APK manifest has android.permission.INTERNET and POST_NOTIFICATIONS.\n",
    "          The APK manifest has INTERNET, POST_NOTIFICATIONS, and USE_BIOMETRIC.\n"
    "          Saved sessions can be protected by device biometric authentication after explicit user opt-in.\n",
)

print('ALKENZY mobile user final patch applied successfully.')
