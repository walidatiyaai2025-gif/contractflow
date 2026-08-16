import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import 'mobile_auth.dart';

final class SafeContractsLoginScreen extends StatefulWidget {
  const SafeContractsLoginScreen({
    required this.controller,
    required this.onAuthenticated,
    this.languageCode = 'en',
    this.onLanguageChanged,
    super.key,
  });

  final MobileLoginController controller;
  final String languageCode;
  final ValueChanged<String>? onLanguageChanged;
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
    final scheme = Theme.of(context).colorScheme;
    if (_bootstrapping) {
      return _BlockingBootstrapSplash(
        label: l10n.t('Loading'),
        scheme: scheme,
      );
    }

    final selectedLanguage = widget.languageCode == 'ar' ? 'ar' : 'en';
    return Scaffold(
      body: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            colors: <Color>[
              scheme.primaryContainer.withValues(alpha: 0.72),
              scheme.surface,
              scheme.surface,
            ],
          ),
        ),
        child: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 28),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 480),
                child: AnimatedBuilder(
                  animation: widget.controller,
                  builder: (context, child) {
                    final submitting =
                        widget.controller.state == MobileLoginState.submitting;
                    return Column(
                      crossAxisAlignment: CrossAxisAlignment.stretch,
                      children: [
                        Align(
                          alignment: AlignmentDirectional.centerEnd,
                          child: SegmentedButton<String>(
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
                                : (selection) =>
                                    widget.onLanguageChanged!(selection.first),
                            showSelectedIcon: false,
                            style: const ButtonStyle(
                              visualDensity: VisualDensity.compact,
                            ),
                          ),
                        ),
                        const SizedBox(height: 22),
                        Container(
                          width: 74,
                          height: 74,
                          alignment: Alignment.center,
                          decoration: BoxDecoration(
                            color: scheme.primary,
                            borderRadius: BorderRadius.circular(22),
                            boxShadow: <BoxShadow>[
                              BoxShadow(
                                color: scheme.primary.withValues(alpha: 0.22),
                                blurRadius: 28,
                                offset: const Offset(0, 12),
                              ),
                            ],
                          ),
                          child: Icon(
                            Icons.shield_outlined,
                            size: 38,
                            color: scheme.onPrimary,
                          ),
                        ),
                        const SizedBox(height: 18),
                        Text(
                          l10n.t('SafeContracts'),
                          style: Theme.of(context)
                              .textTheme
                              .headlineMedium
                              ?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                        ),
                        const SizedBox(height: 6),
                        Text(
                          l10n.t(
                              'Sign in with your WordPress username and password'),
                          style:
                              Theme.of(context).textTheme.bodyLarge?.copyWith(
                                    color: scheme.onSurfaceVariant,
                                  ),
                        ),
                        const SizedBox(height: 24),
                        Card(
                          child: Padding(
                            padding: const EdgeInsets.all(22),
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
                                    const SizedBox(height: 16),
                                    TextFormField(
                                      controller: _password,
                                      enabled: !submitting,
                                      obscureText: _obscurePassword,
                                      autofillHints: const <String>[
                                        AutofillHints.password
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
                                    const SizedBox(height: 8),
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
                                            color: scheme.errorContainer,
                                            borderRadius:
                                                BorderRadius.circular(14),
                                          ),
                                          child: Text(
                                            l10n.rawMessage(
                                              widget.controller.errorMessage!,
                                            ),
                                            style: TextStyle(
                                              color: scheme.onErrorContainer,
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
                                                  strokeWidth: 2),
                                            )
                                          : const Icon(Icons.login),
                                      label: Text(
                                        l10n.t(submitting
                                            ? 'Signing in…'
                                            : 'Sign in'),
                                      ),
                                    ),
                                  ],
                                ),
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
      ),
    );
  }
}

final class _BlockingBootstrapSplash extends StatelessWidget {
  const _BlockingBootstrapSplash({
    required this.label,
    required this.scheme,
  });

  final String label;
  final ColorScheme scheme;

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: false,
      child: Scaffold(
        body: DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: <Color>[
                scheme.primaryContainer.withValues(alpha: 0.78),
                scheme.surface,
              ],
            ),
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
                      width: 82,
                      height: 82,
                      alignment: Alignment.center,
                      decoration: BoxDecoration(
                        color: scheme.primary,
                        borderRadius: BorderRadius.circular(26),
                      ),
                      child: Icon(
                        Icons.shield_outlined,
                        size: 42,
                        color: scheme.onPrimary,
                      ),
                    ),
                    const SizedBox(height: 22),
                    Text(
                      'SafeContracts',
                      style:
                          Theme.of(context).textTheme.headlineSmall?.copyWith(
                                fontWeight: FontWeight.w800,
                              ),
                    ),
                    const SizedBox(height: 18),
                    const SizedBox.square(
                      dimension: 34,
                      child: CircularProgressIndicator(strokeWidth: 3),
                    ),
                    const SizedBox(height: 14),
                    Text(label),
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
