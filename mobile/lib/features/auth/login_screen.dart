import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../ui/alkenzy_reference_components.dart';
import '../ui/safecontracts_design.dart';
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
  bool _obscurePassword = true;
  bool _bootstrapping = false;

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
      await widget.onAuthenticated();
    } finally {
      if (mounted) setState(() => _bootstrapping = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (_bootstrapping) {
      return _BlockingBootstrapSplash(label: l10n.t('Loading'));
    }

    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        final submitting =
            widget.controller.state == MobileLoginState.submitting;
        return Scaffold(
          backgroundColor: SafeContractsVisual.background,
          body: Stack(
            fit: StackFit.expand,
            children: [
              const _LoginBackdrop(),
              SafeArea(
                child: LayoutBuilder(
                  builder: (context, constraints) {
                    final compact = constraints.maxWidth < 360;
                    return SingleChildScrollView(
                      padding: EdgeInsets.fromLTRB(
                        compact ? 14 : 20,
                        10,
                        compact ? 14 : 20,
                        24,
                      ),
                      child: ConstrainedBox(
                        constraints: BoxConstraints(
                          minHeight: constraints.maxHeight - 34,
                          maxWidth: 500,
                        ),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.stretch,
                          children: [
                            _LoginTopBar(
                              languageCode: widget.languageCode,
                              onLanguageChanged: widget.onLanguageChanged,
                              onBack: widget.onBack,
                              disabled: submitting,
                            ),
                            const SizedBox(height: 26),
                            const SafeContractsBrandMark(
                              size: 58,
                              borderRadius: 16,
                            ),
                            const SizedBox(height: 12),
                            Text(
                              SafeContractsBrand.name,
                              textAlign: TextAlign.center,
                              style: Theme.of(context)
                                  .textTheme
                                  .headlineSmall
                                  ?.copyWith(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w900,
                                  ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              l10n.isArabic
                                  ? 'مرحباً بك من جديد\nيرجى تسجيل الدخول لمتابعة أعمالك.'
                                  : 'Welcome back\nSign in to continue managing your work.',
                              textAlign: TextAlign.center,
                              style: Theme.of(context)
                                  .textTheme
                                  .bodyMedium
                                  ?.copyWith(
                                    color: Colors.white.withValues(alpha: 0.80),
                                    height: 1.55,
                                  ),
                            ),
                            const SizedBox(height: 26),
                            Container(
                              padding: const EdgeInsets.fromLTRB(18, 22, 18, 20),
                              decoration: const BoxDecoration(
                                color: SafeContractsVisual.backgroundRaised,
                                borderRadius: BorderRadius.vertical(
                                  top: Radius.circular(28),
                                  bottom: Radius.circular(28),
                                ),
                                boxShadow: AlkenzyReferenceTokens.premiumShadow,
                              ),
                              child: Form(
                                key: _formKey,
                                child: AutofillGroup(
                                  child: Column(
                                    crossAxisAlignment:
                                        CrossAxisAlignment.stretch,
                                    children: [
                                      TextFormField(
                                        controller: _username,
                                        enabled: !submitting,
                                        autofillHints: const [
                                          AutofillHints.username,
                                          AutofillHints.email,
                                        ],
                                        textInputAction: TextInputAction.next,
                                        autocorrect: false,
                                        enableSuggestions: false,
                                        decoration: InputDecoration(
                                          hintText: l10n.isArabic
                                              ? 'البريد الإلكتروني أو اسم المستخدم'
                                              : 'Email or username',
                                          prefixIcon:
                                              const Icon(Icons.person_outline),
                                        ),
                                        validator: (value) => value == null ||
                                                value.trim().isEmpty
                                            ? l10n.t('Enter your username.')
                                            : null,
                                      ),
                                      const SizedBox(height: 12),
                                      TextFormField(
                                        controller: _password,
                                        enabled: !submitting,
                                        obscureText: _obscurePassword,
                                        autofillHints: const [
                                          AutofillHints.password,
                                        ],
                                        textInputAction: TextInputAction.done,
                                        onFieldSubmitted: submitting
                                            ? null
                                            : (_) => unawaited(_submit()),
                                        decoration: InputDecoration(
                                          hintText: l10n.t('Password'),
                                          prefixIcon:
                                              const Icon(Icons.lock_outline),
                                          suffixIcon: IconButton(
                                            onPressed: submitting
                                                ? null
                                                : () => setState(() {
                                                      _obscurePassword =
                                                          !_obscurePassword;
                                                    }),
                                            icon: Icon(
                                              _obscurePassword
                                                  ? Icons.visibility_outlined
                                                  : Icons.visibility_off_outlined,
                                            ),
                                          ),
                                        ),
                                        validator: (value) =>
                                            value == null || value.isEmpty
                                                ? l10n.t('Enter your password.')
                                                : null,
                                      ),
                                      const SizedBox(height: 4),
                                      CheckboxListTile(
                                        value: widget.controller.rememberMe,
                                        onChanged: submitting
                                            ? null
                                            : (value) => widget.controller
                                                .setRememberMe(value ?? false),
                                        contentPadding: EdgeInsets.zero,
                                        dense: true,
                                        controlAffinity:
                                            ListTileControlAffinity.leading,
                                        title: Text(l10n.t('Remember me')),
                                      ),
                                      if (widget.controller.errorMessage !=
                                          null) ...[
                                        const SizedBox(height: 6),
                                        Semantics(
                                          liveRegion: true,
                                          child: AlkenzyReferenceCard(
                                            background:
                                                SafeContractsVisual.redSoft,
                                            accent: SafeContractsVisual.red,
                                            padding: const EdgeInsets.all(11),
                                            child: Text(
                                              l10n.rawMessage(
                                                widget.controller.errorMessage!,
                                              ),
                                              style: const TextStyle(
                                                color:
                                                    SafeContractsVisual.redDeep,
                                                fontWeight: FontWeight.w700,
                                              ),
                                            ),
                                          ),
                                        ),
                                      ],
                                      const SizedBox(height: 14),
                                      AlkenzyReferencePrimaryButton(
                                        label: l10n.t(
                                          submitting
                                              ? 'Signing in…'
                                              : 'Sign in',
                                        ),
                                        icon: submitting ? null : Icons.login,
                                        onPressed: submitting
                                            ? null
                                            : () => unawaited(_submit()),
                                      ),
                                      if (submitting) ...[
                                        const SizedBox(height: 10),
                                        const LinearProgressIndicator(
                                          minHeight: 2,
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                    );
                  },
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _LoginTopBar extends StatelessWidget {
  const _LoginTopBar({
    required this.languageCode,
    required this.onLanguageChanged,
    required this.onBack,
    required this.disabled,
  });

  final String languageCode;
  final ValueChanged<String>? onLanguageChanged;
  final VoidCallback? onBack;
  final bool disabled;

  @override
  Widget build(BuildContext context) {
    final selected = languageCode.toLowerCase() == 'ar' ? 'ar' : 'en';
    return Row(
      children: [
        if (onBack != null)
          IconButton(
            onPressed: disabled ? null : onBack,
            style: IconButton.styleFrom(
              foregroundColor: Colors.white,
              backgroundColor: Colors.white.withValues(alpha: 0.10),
            ),
            icon: const Icon(Icons.arrow_back_rounded),
          )
        else
          const SizedBox(width: 48),
        const Spacer(),
        SegmentedButton<String>(
          segments: const [
            ButtonSegment(value: 'ar', label: Text('ع')),
            ButtonSegment(value: 'en', label: Text('EN')),
          ],
          selected: {selected},
          showSelectedIcon: false,
          onSelectionChanged: disabled || onLanguageChanged == null
              ? null
              : (selection) => onLanguageChanged!(selection.first),
          style: ButtonStyle(
            visualDensity: VisualDensity.compact,
            foregroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? SafeContractsVisual.navyDeep
                  : Colors.white,
            ),
            backgroundColor: WidgetStateProperty.resolveWith(
              (states) => states.contains(WidgetState.selected)
                  ? SafeContractsVisual.surfaceWarm
                  : Colors.white.withValues(alpha: 0.08),
            ),
            side: WidgetStateProperty.all(
              BorderSide(color: Colors.white.withValues(alpha: 0.18)),
            ),
          ),
        ),
      ],
    );
  }
}

final class _BlockingBootstrapSplash extends StatelessWidget {
  const _BlockingBootstrapSplash({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: Scaffold(
        backgroundColor: SafeContractsVisual.navyDeep,
        body: DecoratedBox(
          decoration: const BoxDecoration(
            gradient: SafeContractsVisual.premiumHeaderGradient,
          ),
          child: SafeArea(
            child: Center(
              child: Semantics(
                liveRegion: true,
                label: label,
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.10),
                        borderRadius: BorderRadius.circular(28),
                        border: Border.all(
                          color: SafeContractsVisual.roseGold
                              .withValues(alpha: 0.50),
                        ),
                      ),
                      child: const SafeContractsBrandMark(
                        size: 88,
                        borderRadius: 24,
                      ),
                    ),
                    const SizedBox(height: 22),
                    Text(
                      SafeContractsBrand.name,
                      style:
                          Theme.of(context).textTheme.headlineSmall?.copyWith(
                                color: Colors.white,
                                fontWeight: FontWeight.w900,
                              ),
                    ),
                    const SizedBox(height: 16),
                    const SizedBox.square(
                      dimension: 28,
                      child: CircularProgressIndicator(
                        strokeWidth: 2.6,
                        color: SafeContractsVisual.roseGold,
                      ),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      label,
                      style: TextStyle(
                        color: Colors.white.withValues(alpha: 0.78),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

final class _LoginBackdrop extends StatelessWidget {
  const _LoginBackdrop();

  @override
  Widget build(BuildContext context) {
    return const DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          stops: [0, 0.53, 0.54, 1],
          colors: [
            SafeContractsVisual.navyDeep,
            SafeContractsVisual.navy,
            SafeContractsVisual.background,
            SafeContractsVisual.background,
          ],
        ),
      ),
    );
  }
}
