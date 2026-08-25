import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/auth/mobile_token_store.dart';
import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../ui/safecontracts_components.dart';
import '../ui/safecontracts_design.dart';
import '../ui/safecontracts_form.dart';
import '../ui/safecontracts_splash.dart';
import '../ui/safecontracts_tokens.dart';
import 'biometric_auth.dart';
import 'mobile_auth.dart';

final class SafeContractsLoginScreen extends StatefulWidget {
  const SafeContractsLoginScreen({
    required this.controller,
    required this.onAuthenticated,
    this.languageCode = 'en',
    this.onLanguageChanged,
    this.onBack,
    super.key,
  });

  final MobileLoginController controller;
  final String languageCode;
  final ValueChanged<String>? onLanguageChanged;
  final VoidCallback? onBack;
  final Future<void> Function() onAuthenticated;

  @override
  State<SafeContractsLoginScreen> createState() =>
      _SafeContractsLoginScreenState();
}

final class _SafeContractsLoginScreenState
    extends State<SafeContractsLoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _username = TextEditingController();
  final _password = TextEditingController();
  final MobileBiometricAuth _biometricAuth = MobileBiometricAuth();
  bool _obscurePassword = true;
  bool _bootstrapping = false;
  bool _biometricAvailable = false;
  bool _biometricCredentialAvailable = false;
  bool _biometricBusy = false;

  @override
  void initState() {
    super.initState();
    unawaited(_refreshBiometricAvailability());
  }

  Future<void> _refreshBiometricAvailability() async {
    final store = widget.controller.repository.tokenStore;
    if (store is! SecureMobileTokenStore) return;
    final available = await _biometricAuth.isAvailable();
    final remembered = available && await store.hasPersistentToken();
    if (mounted) {
      setState(() {
        _biometricAvailable = available;
        _biometricCredentialAvailable = remembered;
      });
    }
  }

  Future<void> _submitBiometric() async {
    if (_biometricBusy || _bootstrapping) return;
    final store = widget.controller.repository.tokenStore;
    if (store is! SecureMobileTokenStore) return;
    final isArabic = context.scL10n.isArabic;
    final hasCredential = await store.hasPersistentToken();
    if (!hasCredential) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            isArabic
                ? 'سجّل الدخول بكلمة المرور مرة واحدة، ثم فعّل البصمة عندما يطلب التطبيق ذلك.'
                : 'Sign in with your password once, then enable fingerprint when the app asks.',
          ),
        ),
      );
      return;
    }
    setState(() => _biometricBusy = true);
    try {
      final authenticated = await _biometricAuth.authenticate(
        isArabic: isArabic,
      );
      if (!authenticated || !mounted) return;
      store.unlockPersistent();
      setState(() => _bootstrapping = true);
      await widget.onAuthenticated();
    } finally {
      if (mounted) {
        setState(() {
          _biometricBusy = false;
          _bootstrapping = false;
        });
      }
    }
  }

  Future<void> _offerBiometricEnrollment() async {
    final store = widget.controller.repository.tokenStore;
    if (store is! SecureMobileTokenStore) return;
    if (!await _biometricAuth.isAvailable()) return;
    if (await store.hasPersistentToken()) {
      if (mounted) {
        setState(() {
          _biometricAvailable = true;
          _biometricCredentialAvailable = true;
        });
      }
      return;
    }
    if (!mounted) return;
    final ar = context.scL10n.isArabic;
    final enable = await showDialog<bool>(
          context: context,
          barrierDismissible: false,
          builder: (dialogContext) => AlertDialog(
            icon: const Icon(Icons.fingerprint_rounded, size: 42),
            title: Text(
                ar ? 'تفعيل الدخول بالبصمة؟' : 'Enable fingerprint sign-in?'),
            content: Text(
              ar
                  ? 'سيتم حفظ رمز الجلسة الآمن فقط لاستخدامه مع البصمة. لن يتم حفظ كلمة المرور ولن يتم تشغيل خيار تذكرني.'
                  : 'Only the secure session token will be saved for biometric use. Your password will not be stored and Remember me will stay off.',
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: Text(ar ? 'ليس الآن' : 'Not now'),
              ),
              FilledButton.icon(
                onPressed: () => Navigator.of(dialogContext).pop(true),
                icon: const Icon(Icons.fingerprint_rounded),
                label: Text(ar ? 'تفعيل البصمة' : 'Enable fingerprint'),
              ),
            ],
          ),
        ) ??
        false;
    if (!enable || !mounted) return;
    final authenticated = await _biometricAuth.authenticate(isArabic: ar);
    if (!authenticated || !mounted) return;
    await store.persistCurrentForBiometric();
    if (mounted) {
      setState(() {
        _biometricAvailable = true;
        _biometricCredentialAvailable = true;
      });
    }
  }

  @override
  void dispose() {
    _username.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (_bootstrapping || !_formKey.currentState!.validate()) return;
    setState(() => _bootstrapping = true);
    try {
      final success = await widget.controller.submit(
        username: _username.text,
        password: _password.text,
      );
      if (!success || !mounted) return;
      _password.clear();
      await _offerBiometricEnrollment();
      if (!mounted) return;
      await widget.onAuthenticated();
      unawaited(_refreshBiometricAvailability());
    } finally {
      if (mounted) setState(() => _bootstrapping = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (_bootstrapping) {
      return _BlockingBootstrapSplash(
        label: l10n.t('Loading'),
        environmentLabel:
            l10n.isArabic ? 'تجهيز مساحة العمل' : 'Preparing workspace',
      );
    }

    final selectedLanguage = widget.languageCode == 'ar' ? 'ar' : 'en';
    return Scaffold(
      backgroundColor: SafeContractsVisual.navyDeep,
      body: Stack(
        fit: StackFit.expand,
        children: [
          const _LoginBackground(),
          SafeArea(
            child: LayoutBuilder(
              builder: (context, constraints) {
                final horizontal = constraints.maxWidth <= 360
                    ? SafeContractsSpacing.screenNarrow
                    : SafeContractsSpacing.screen;
                return SingleChildScrollView(
                  padding: EdgeInsets.fromLTRB(
                    horizontal,
                    SafeContractsSpacing.sm,
                    horizontal,
                    SafeContractsSpacing.xl,
                  ),
                  child: Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 470),
                      child: AnimatedBuilder(
                        animation: widget.controller,
                        builder: (context, child) {
                          final submitting = widget.controller.state ==
                              MobileLoginState.submitting;
                          return Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              _LoginTopBar(
                                selectedLanguage: selectedLanguage,
                                submitting: submitting,
                                onBack: widget.onBack,
                                onLanguageChanged: widget.onLanguageChanged,
                              ),
                              const SizedBox(height: SafeContractsSpacing.xl),
                              const _LoginBrandHero(),
                              const SizedBox(height: SafeContractsSpacing.xl),
                              SafeContractsCard(
                                accent: SafeContractsVisual.roseGold,
                                padding: const EdgeInsets.all(
                                  SafeContractsSpacing.lg,
                                ),
                                child: Form(
                                  key: _formKey,
                                  child: AutofillGroup(
                                    child: Column(
                                      crossAxisAlignment:
                                          CrossAxisAlignment.stretch,
                                      children: [
                                        Text(
                                          l10n.isArabic
                                              ? 'تسجيل الدخول'
                                              : 'Sign in',
                                          style: Theme.of(context)
                                              .textTheme
                                              .headlineSmall
                                              ?.copyWith(
                                                color: SafeContractsVisual
                                                    .navyDeep,
                                                fontWeight: FontWeight.w900,
                                              ),
                                        ),
                                        const SizedBox(
                                          height: SafeContractsSpacing.xxs,
                                        ),
                                        Text(
                                          l10n.t(
                                            'Sign in with your WordPress username and password',
                                          ),
                                          style: Theme.of(context)
                                              .textTheme
                                              .bodySmall
                                              ?.copyWith(
                                                color:
                                                    SafeContractsVisual.muted,
                                                height: 1.45,
                                              ),
                                        ),
                                        const SizedBox(
                                          height: SafeContractsSpacing.lg,
                                        ),
                                        SafeContractsTextField(
                                          controller: _username,
                                          enabled: !submitting,
                                          autofillHints: const <String>[
                                            AutofillHints.username,
                                            AutofillHints.email,
                                          ],
                                          textInputAction: TextInputAction.next,
                                          autocorrect: false,
                                          enableSuggestions: false,
                                          label: l10n.t('Username'),
                                          icon: Icons.person_outline_rounded,
                                          validator: (value) => value == null ||
                                                  value.trim().isEmpty
                                              ? l10n.t('Enter your username.')
                                              : null,
                                        ),
                                        const SizedBox(
                                          height: SafeContractsSpacing.sm,
                                        ),
                                        SafeContractsPasswordField(
                                          controller: _password,
                                          enabled: !submitting,
                                          obscureText: _obscurePassword,
                                          label: l10n.t('Password'),
                                          onToggleVisibility: () =>
                                              setState(() {
                                            _obscurePassword =
                                                !_obscurePassword;
                                          }),
                                          onSubmitted: submitting
                                              ? null
                                              : (_) => unawaited(_submit()),
                                          validator: (value) =>
                                              value == null || value.isEmpty
                                                  ? l10n.t(
                                                      'Enter your password.',
                                                    )
                                                  : null,
                                        ),
                                        const SizedBox(
                                          height: SafeContractsSpacing.xxs,
                                        ),
                                        CheckboxListTile(
                                          value: widget.controller.rememberMe,
                                          onChanged: submitting
                                              ? null
                                              : (value) => widget.controller
                                                  .setRememberMe(
                                                      value ?? false),
                                          contentPadding: EdgeInsets.zero,
                                          dense: true,
                                          controlAffinity:
                                              ListTileControlAffinity.leading,
                                          title: Text(
                                            l10n.t('Remember me'),
                                            style: const TextStyle(
                                              fontWeight: FontWeight.w800,
                                            ),
                                          ),
                                          subtitle: Text(
                                            l10n.t(
                                              'Keep me signed in on this device. Your password is never stored.',
                                            ),
                                            style: const TextStyle(
                                              color: SafeContractsVisual.muted,
                                            ),
                                          ),
                                        ),
                                        if (widget.controller.errorMessage !=
                                            null) ...[
                                          const SizedBox(
                                            height: SafeContractsSpacing.xs,
                                          ),
                                          _AuthenticationError(
                                            message: l10n.rawMessage(
                                              widget.controller.errorMessage!,
                                            ),
                                          ),
                                        ],
                                        const SizedBox(
                                          height: SafeContractsSpacing.md,
                                        ),
                                        SafeContractsButton(
                                          key: const Key('loginSubmit'),
                                          label: l10n.t(
                                            submitting
                                                ? 'Signing in…'
                                                : 'Sign in',
                                          ),
                                          icon: Icons.login_rounded,
                                          loading: submitting,
                                          onPressed: submitting
                                              ? null
                                              : () => unawaited(_submit()),
                                          variant: SafeContractsButtonVariant
                                              .primary,
                                        ),
                                        if (_biometricAvailable) ...[
                                          const SizedBox(
                                            height: SafeContractsSpacing.md,
                                          ),
                                          Row(
                                            children: [
                                              const Expanded(child: Divider()),
                                              Padding(
                                                padding:
                                                    const EdgeInsets.symmetric(
                                                  horizontal:
                                                      SafeContractsSpacing.sm,
                                                ),
                                                child: Text(
                                                  l10n.isArabic ? 'أو' : 'OR',
                                                  style: const TextStyle(
                                                    color: SafeContractsVisual
                                                        .muted,
                                                    fontWeight: FontWeight.w800,
                                                  ),
                                                ),
                                              ),
                                              const Expanded(child: Divider()),
                                            ],
                                          ),
                                          const SizedBox(
                                            height: SafeContractsSpacing.md,
                                          ),
                                          OutlinedButton.icon(
                                            key: const Key('biometricLogin'),
                                            onPressed:
                                                submitting || _biometricBusy
                                                    ? null
                                                    : () => unawaited(
                                                          _submitBiometric(),
                                                        ),
                                            icon: _biometricBusy
                                                ? const SizedBox.square(
                                                    dimension: 18,
                                                    child:
                                                        CircularProgressIndicator(
                                                      strokeWidth: 2,
                                                    ),
                                                  )
                                                : const Icon(
                                                    Icons.fingerprint_rounded,
                                                  ),
                                            label: Text(
                                              l10n.isArabic
                                                  ? 'الدخول بالبصمة'
                                                  : 'Sign in with fingerprint',
                                            ),
                                          ),
                                          if (!_biometricCredentialAvailable)
                                            Padding(
                                              padding: const EdgeInsets.only(
                                                top: SafeContractsSpacing.xs,
                                              ),
                                              child: Text(
                                                l10n.isArabic
                                                    ? 'بعد أول دخول بكلمة المرور سيطلب منك التطبيق تفعيل البصمة.'
                                                    : 'After your first password sign-in, the app will ask you to enable fingerprint.',
                                                textAlign: TextAlign.center,
                                                style: const TextStyle(
                                                  color:
                                                      SafeContractsVisual.muted,
                                                  fontSize: 11,
                                                ),
                                              ),
                                            ),
                                        ],
                                      ],
                                    ),
                                  ),
                                ),
                              ),
                              const SizedBox(height: SafeContractsSpacing.md),
                              Row(
                                mainAxisAlignment: MainAxisAlignment.center,
                                children: [
                                  const Icon(
                                    Icons.lock_outline_rounded,
                                    size: SafeContractsIconSizes.xs,
                                    color: SafeContractsVisual.champagne,
                                  ),
                                  const SizedBox(
                                    width: SafeContractsSpacing.xs,
                                  ),
                                  Flexible(
                                    child: Text(
                                      l10n.isArabic
                                          ? 'دخول آمن للمستخدمين المصرح لهم فقط'
                                          : 'Secure access for authorized users only',
                                      textAlign: TextAlign.center,
                                      style: TextStyle(
                                        color: Colors.white.withValues(
                                          alpha: 0.62,
                                        ),
                                        fontSize: 12,
                                      ),
                                    ),
                                  ),
                                ],
                              ),
                            ],
                          );
                        },
                      ),
                    ),
                  ),
                );
              },
            ),
          ),
        ],
      ),
    );
  }
}

final class _BlockingBootstrapSplash extends StatelessWidget {
  const _BlockingBootstrapSplash({
    required this.label,
    required this.environmentLabel,
  });

  final String label;
  final String environmentLabel;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: SafeContractsSplash(
        label: label,
        environmentLabel: environmentLabel,
        blockBack: true,
      ),
    );
  }
}

final class _LoginTopBar extends StatelessWidget {
  const _LoginTopBar({
    required this.selectedLanguage,
    required this.submitting,
    required this.onBack,
    required this.onLanguageChanged,
  });

  final String selectedLanguage;
  final bool submitting;
  final VoidCallback? onBack;
  final ValueChanged<String>? onLanguageChanged;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        if (onBack != null)
          IconButton(
            tooltip: MaterialLocalizations.of(context).backButtonTooltip,
            onPressed: submitting ? null : onBack,
            style: IconButton.styleFrom(
              foregroundColor: Colors.white,
              disabledForegroundColor: Colors.white38,
              backgroundColor: Colors.white.withValues(alpha: 0.08),
            ),
            icon: const Icon(Icons.arrow_back_rounded),
          ),
        const Spacer(),
        SegmentedButton<String>(
          segments: const <ButtonSegment<String>>[
            ButtonSegment<String>(value: 'en', label: Text('EN')),
            ButtonSegment<String>(value: 'ar', label: Text('ع')),
          ],
          selected: <String>{selectedLanguage},
          onSelectionChanged: submitting || onLanguageChanged == null
              ? null
              : (selection) => onLanguageChanged!(selection.first),
          showSelectedIcon: false,
          style: ButtonStyle(
            foregroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? SafeContractsVisual.navyDeep
                  : Colors.white,
            ),
            backgroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? SafeContractsVisual.roseGoldSoft
                  : Colors.white.withValues(alpha: 0.08),
            ),
            side: WidgetStatePropertyAll(
              BorderSide(color: Colors.white.withValues(alpha: 0.18)),
            ),
            visualDensity: VisualDensity.compact,
          ),
        ),
      ],
    );
  }
}

final class _LoginBrandHero extends StatelessWidget {
  const _LoginBrandHero();

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.all(8),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.08),
            borderRadius: BorderRadius.circular(SafeContractsRadii.lg),
            border: Border.all(color: Colors.white.withValues(alpha: 0.12)),
          ),
          child: const SafeContractsBrandMark(size: 66, borderRadius: 18),
        ),
        const SizedBox(height: SafeContractsSpacing.md),
        Text(
          SafeContractsBrand.name,
          style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w900,
                letterSpacing: -0.7,
              ),
        ),
        const SizedBox(height: SafeContractsSpacing.xs),
        Text(
          l10n.isArabic
              ? 'العقود والمدفوعات والتحصيلات في مساحة عمل تنفيذية واحدة.'
              : 'Contracts, payments and collections in one executive workspace.',
          style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                color: Colors.white.withValues(alpha: 0.74),
                height: 1.55,
              ),
        ),
      ],
    );
  }
}

final class _AuthenticationError extends StatelessWidget {
  const _AuthenticationError({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      liveRegion: true,
      child: Container(
        padding: const EdgeInsets.all(SafeContractsSpacing.sm),
        decoration: BoxDecoration(
          color: SafeContractsVisual.redSoft,
          borderRadius: BorderRadius.circular(SafeContractsRadii.sm),
          border: Border.all(
            color: SafeContractsVisual.red.withValues(alpha: 0.28),
          ),
        ),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Icon(
              Icons.error_outline_rounded,
              color: SafeContractsVisual.redDeep,
              size: SafeContractsIconSizes.sm,
            ),
            const SizedBox(width: SafeContractsSpacing.xs),
            Expanded(
              child: Text(
                message,
                style: const TextStyle(
                  color: SafeContractsVisual.redDeep,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _LoginBackground extends StatelessWidget {
  const _LoginBackground();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          stops: <double>[0, 0.48, 0.73, 1],
          colors: <Color>[
            Color(0xFF061B2F),
            SafeContractsVisual.navyDeep,
            SafeContractsVisual.background,
            SafeContractsVisual.background,
          ],
        ),
      ),
    );
  }
}
