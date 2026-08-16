import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import 'mobile_auth.dart';

final class SafeContractsLoginScreen extends StatefulWidget {
  const SafeContractsLoginScreen({
    required this.controller,
    required this.onAuthenticated,
    super.key,
  });

  final MobileLoginController controller;
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

  @override
  void dispose() {
    _username.dispose();
    _password.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }
    final success = await widget.controller.submit(
      username: _username.text,
      password: _password.text,
    );
    if (!success || !mounted) {
      return;
    }
    _password.clear();
    await widget.onAuthenticated();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 440),
              child: Card(
                child: Padding(
                  padding: const EdgeInsets.all(24),
                  child: AnimatedBuilder(
                    animation: widget.controller,
                    builder: (context, child) {
                      final submitting = widget.controller.state ==
                          MobileLoginState.submitting;
                      return Form(
                        key: _formKey,
                        child: AutofillGroup(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.stretch,
                            children: [
                              const Icon(Icons.fact_check_outlined, size: 56),
                              const SizedBox(height: 16),
                              Text(
                                'SafeContracts',
                                textAlign: TextAlign.center,
                                style:
                                    Theme.of(context).textTheme.headlineMedium,
                              ),
                              const SizedBox(height: 8),
                              Text(
                                l10n.t(
                                  'Sign in with your WordPress username and password',
                                ),
                                textAlign: TextAlign.center,
                                style: Theme.of(context).textTheme.bodyMedium,
                              ),
                              const SizedBox(height: 24),
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
                                  labelText: l10n.t('Username'),
                                  prefixIcon: const Icon(Icons.person_outline),
                                  border: const OutlineInputBorder(),
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
                                autofillHints: const [AutofillHints.password],
                                textInputAction: TextInputAction.done,
                                onFieldSubmitted: submitting
                                    ? null
                                    : (_) => unawaited(_submit()),
                                decoration: InputDecoration(
                                  labelText: l10n.t('Password'),
                                  prefixIcon: const Icon(Icons.lock_outline),
                                  border: const OutlineInputBorder(),
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
                              if (widget.controller.errorMessage != null) ...[
                                const SizedBox(height: 16),
                                Semantics(
                                  liveRegion: true,
                                  child: Text(
                                    l10n.rawMessage(
                                      widget.controller.errorMessage!,
                                    ),
                                    textAlign: TextAlign.center,
                                    style: TextStyle(
                                      color:
                                          Theme.of(context).colorScheme.error,
                                    ),
                                  ),
                                ),
                              ],
                              const SizedBox(height: 24),
                              FilledButton.icon(
                                onPressed: submitting
                                    ? null
                                    : () => unawaited(_submit()),
                                icon: submitting
                                    ? const SizedBox.square(
                                        dimension: 18,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : const Icon(Icons.login),
                                label: Text(
                                  l10n.t(
                                    submitting ? 'Signing in…' : 'Sign in',
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
              ),
            ),
          ),
        ),
      ),
    );
  }
}
