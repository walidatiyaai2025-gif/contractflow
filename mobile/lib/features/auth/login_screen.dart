import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
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
      if (mounted) {
        setState(() => _bootstrapping = false);
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (_bootstrapping) {
      return _BlockingBootstrapSplash(label: l10n.t('Loading'));
    }

    final selectedLanguage = widget.languageCode == 'ar' ? 'ar' : 'en';
    return Scaffold(
      backgroundColor: SafeContractsVisual.navyDeep,
      body: Stack(
        fit: StackFit.expand,
        children: [
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                stops: <double>[0, 0.48, 0.72, 1],
                colors: <Color>[
                  SafeContractsVisual.navyDeep,
                  SafeContractsVisual.navy,
                  SafeContractsVisual.background,
                  SafeContractsVisual.background,
                ],
              ),
            ),
          ),
          SafeArea(
            child: Center(
              child: SingleChildScrollView(
                padding: const EdgeInsets.fromLTRB(20, 20, 20, 28),
                child: ConstrainedBox(
                  constraints: const BoxConstraints(maxWidth: 480),
                  child: AnimatedBuilder(
                    animation: widget.controller,
                    builder: (context, child) {
                      final submitting = widget.controller.state ==
                          MobileLoginState.submitting;
                      return Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: [
                          Row(
                            children: [
                              if (widget.onBack != null)
                                IconButton(
                                  tooltip: l10n.t('Back'),
                                  onPressed: submitting ? null : widget.onBack,
                                  style: IconButton.styleFrom(
                                    foregroundColor: Colors.white,
                                    backgroundColor:
                                        Colors.white.withValues(alpha: 0.08),
                                  ),
                                  icon: const Icon(Icons.arrow_back_rounded),
                                ),
                              const Spacer(),
                              SegmentedButton<String>(
                                segments: <ButtonSegment<String>>[
                                  ButtonSegment<String>(
                                    value: 'en',
                                    label: Text(l10n.t('English')),
                                  ),
                                  ButtonSegment<String>(
                                    value: 'ar',
                                    label: Text(l10n.t('Arabic')),
                                  ),
                                ],
                                selected: <String>{selectedLanguage},
                                onSelectionChanged: submitting ||
                                        widget.onLanguageChanged == null
                                    ? null
                                    : (selection) => widget
                                        .onLanguageChanged!(selection.first),
                                showSelectedIcon: false,
                                style: ButtonStyle(
                                  foregroundColor:
                                      WidgetStateProperty.resolveWith((states) {
                                    return states.contains(WidgetState.selected)
                                        ? SafeContractsVisual.navyDeep
                                        : Colors.white;
                                  }),
                                  backgroundColor:
                                      WidgetStateProperty.resolveWith((states) {
                                    return states.contains(WidgetState.selected)
                                        ? SafeContractsVisual.roseGoldSoft
                                        : Colors.white.withValues(alpha: 0.08);
                                  }),
                                  side: WidgetStateProperty.all(
                                    BorderSide(
                                      color:
                                          Colors.white.withValues(alpha: 0.22),
                                    ),
                                  ),
                                  visualDensity: VisualDensity.compact,
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 24),
                          Align(
                            alignment: AlignmentDirectional.centerStart,
                            child: Container(
                              padding: const EdgeInsets.all(10),
                              decoration: BoxDecoration(
                                color: Colors.white.withValues(alpha: 0.10),
                                borderRadius: BorderRadius.circular(22),
                                border: Border.all(
                                  color: Colors.white.withValues(alpha: 0.16),
                                ),
                              ),
                              child: const SafeContractsBrandMark(
                                size: 76,
                                borderRadius: 18,
                              ),
                            ),
                          ),
                          const SizedBox(height: 18),
                          Text(
                            SafeContractsBrand.name,
                            style: Theme.of(context)
                                .textTheme
                                .headlineMedium
                                ?.copyWith(
                                  color: Colors.white,
                                  fontWeight: FontWeight.w900,
                                  letterSpacing: -0.7,
                                ),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            l10n.isArabic
                                ? 'إدارة العقود والمدفوعات والتحصيلات في تجربة تنفيذية واحدة.'
                                : 'Contracts, payments and collections in one executive workspace.',
                            style: Theme.of(context)
                                .textTheme
                                .bodyLarge
                                ?.copyWith(
                                  color: Colors.white.withValues(alpha: 0.76),
                                  height: 1.55,
                                ),
                          ),
                          const SizedBox(height: 28),
                          SafeContractsSurface(
                            accent: SafeContractsVisual.roseGold,
                            padding: const EdgeInsets.all(22),
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
                                          .titleLarge
                                          ?.copyWith(
                                            color: SafeContractsVisual.navy,
                                            fontWeight: FontWeight.w900,
                                          ),
                                    ),
                                    const SizedBox(height: 5),
                                    Text(
                                      l10n.t(
                                        'Sign in with your WordPress username and password',
                                      ),
                                      style: Theme.of(context)
                                          .textTheme
                                          .bodySmall
                                          ?.copyWith(
                                            color: SafeContractsVisual.muted,
                                          ),
                                    ),
                                    const SizedBox(height: 18),
                                    TextFormField(
                                      controller: _username,
                                      enabled: !submitting,
                                      autofillHints: const <String>[
                                        AutofillHints.username,
                                        AutofillHints.email,
                                      ],
                                      textInputAction: TextInputAction.next,
                                      autocorrect: false,
                                      enableSuggestions: false,
                                      decoration: InputDecoration(
                                        labelText: l10n.t('Username'),
                                        prefixIcon:
                                            const Icon(Icons.person_outline),
                                      ),
                                      validator: (value) =>
                                          value == null || value.trim().isEmpty
                                              ? l10n.t('Enter your username.')
                                              : null,
                                    ),
                                    const SizedBox(height: 14),
                                    TextFormField(
                                      controller: _password,
                                      enabled: !submitting,
                                      obscureText: _obscurePassword,
                                      autofillHints: const <String>[
                                        AutofillHints.password,
                                      ],
                                      textInputAction: TextInputAction.done,
                                      onFieldSubmitted: submitting
                                          ? null
                                          : (_) => unawaited(_submit()),
                                      decoration: InputDecoration(
                                        labelText: l10n.t('Password'),
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
                                    const SizedBox(height: 6),
                                    CheckboxListTile(
                                      value: widget.controller.rememberMe,
                                      onChanged: submitting
                                          ? null
                                          : (value) => widget.controller
                                              .setRememberMe(value ?? false),
                                      contentPadding: EdgeInsets.zero,
                                      controlAffinity:
                                          ListTileControlAffinity.leading,
                                      title: Text(l10n.t('Remember me')),
                                      subtitle: Text(
                                        l10n.t(
                                          'Keep me signed in on this device. Your password is never stored.',
                                        ),
                                      ),
                                    ),
                                    if (widget.controller.errorMessage !=
                                        null) ...[
                                      const SizedBox(height: 8),
                                      Semantics(
                                        liveRegion: true,
                                        child: Container(
                                          padding: const EdgeInsets.all(12),
                                          decoration: BoxDecoration(
                                            color: SafeContractsVisual.redSoft,
                                            borderRadius:
                                                BorderRadius.circular(14),
                                            border: Border.all(
                                              color: SafeContractsVisual.red
                                                  .withValues(alpha: 0.28),
                                            ),
                                          ),
                                          child: Text(
                                            l10n.rawMessage(
                                              widget.controller.errorMessage!,
                                            ),
                                            style: const TextStyle(
                                              color:
                                                  SafeContractsVisual.redDeep,
                                            ),
                                          ),
                                        ),
                                      ),
                                    ],
                                    const SizedBox(height: 18),
                                    FilledButton.icon(
                                      onPressed: submitting
                                          ? null
                                          : () => unawaited(_submit()),
                                      icon: submitting
                                          ? const SizedBox.square(
                                              dimension: 18,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: Colors.white,
                                              ),
                                            )
                                          : const Icon(Icons.login_rounded),
                                      label: Text(
                                        l10n.t(
                                          submitting
                                              ? 'Signing in…'
                                              : 'Sign in',
                                        ),
                                      ),
                                    ),
                                  ],
                                ),
                              ),
                            ),
                          ),
                        ],
                      );
                    },
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
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
                      padding: const EdgeInsets.all(10),
                      decoration: BoxDecoration(
                        color: Colors.white.withValues(alpha: 0.10),
                        borderRadius: BorderRadius.circular(28),
                      ),
                      child: const SafeContractsBrandMark(
                        size: 88,
                        borderRadius: 26,
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
                    const SizedBox(height: 18),
                    const SizedBox.square(
                      dimension: 34,
                      child: CircularProgressIndicator(
                        strokeWidth: 3,
                        color: SafeContractsVisual.roseGold,
                      ),
                    ),
                    const SizedBox(height: 14),
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
