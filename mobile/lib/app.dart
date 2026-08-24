import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';
import 'package:google_fonts/google_fonts.dart';

import 'core/api/api_client.dart';
import 'core/api/io_api_transport.dart';
import 'core/auth/mobile_token_store.dart';
import 'core/branding/safe_contracts_brand.dart';
import 'core/config/app_environment.dart';
import 'core/localization/mobile_locale_controller.dart';
import 'core/localization/safecontracts_localizations.dart';
import 'features/auth/login_screen.dart';
import 'features/auth/mobile_auth.dart';
import 'features/bootstrap/mobile_bootstrap_controller.dart';
import 'features/navigation/app_shell.dart';
import 'features/notifications/push_registration.dart';
import 'features/session/session_controller.dart';
import 'features/ui/mobile_layout.dart';
import 'features/ui/safecontracts_splash.dart';
import 'features/ui/safecontracts_theme.dart';
import 'features/welcome/company_welcome_screen.dart';
import 'features/welcome/mobile_landing.dart';

ThemeData _theme(String languageCode) {
  if (languageCode.trim().toLowerCase().startsWith('ar')) {
    // Keep the app boundary explicit about the approved Arabic family while
    // SafeContractsTheme remains the single owner of the complete theme.
    assert(GoogleFonts.cairo().fontFamily != null);
  }
  return SafeContractsTheme.build(languageCode);
}

class SafeContractsApp extends StatefulWidget {
  const SafeContractsApp({
    required this.environment,
    this.client,
    this.tokenStore,
    this.languageCode = 'en',
    super.key,
  });

  final AppEnvironment environment;
  final SafeContractsApiClient? client;
  final MobileTokenStore? tokenStore;
  final String languageCode;

  @override
  State<SafeContractsApp> createState() => _SafeContractsAppState();
}

final class _SafeContractsAppState extends State<SafeContractsApp> {
  late final MobileTokenStore _tokenStore;
  late final SafeContractsApiClient _client;
  late final MobileAuthRepository _authRepository;
  late final MobileLoginController _loginController;
  late final MobileBootstrapController _bootstrap;
  late final MobilePushRegistration _pushRegistration;
  late final MobileLocaleController _localeController;
  late final MobileLandingController _landingController;

  @override
  void initState() {
    super.initState();
    _localeController = MobileLocaleController(
      initialLanguageCode: widget.languageCode,
    );
    unawaited(_localeController.load());
    _tokenStore = widget.tokenStore ??
        (widget.client == null
            ? SecureMobileTokenStore()
            : MemoryMobileTokenStore());
    _client = widget.client ??
        SafeContractsApiClient(
          environment: widget.environment,
          transport: IoApiTransport(),
          headersProvider: () async {
            final token = await _tokenStore.read();
            return token == null
                ? <String, String>{}
                : <String, String>{'Authorization': 'Bearer $token'};
          },
        );
    _authRepository = MobileAuthRepository(
      client: _client,
      tokenStore: _tokenStore,
    );
    _loginController = MobileLoginController(repository: _authRepository);
    _bootstrap = MobileBootstrapController(_client);
    _pushRegistration = MobilePushRegistration(client: _client);
    _landingController = MobileLandingController(
      MobileLandingRepository(_client),
    );
    unawaited(_landingController.ensureLoaded());
    unawaited(_bootstrap.bootstrap());
  }

  Future<void> _afterAuthenticated() async {
    _loginController.resetError();
    await _bootstrap.bootstrap();
  }

  Future<void> _startPushIfNeeded() async {
    final config = _bootstrap.configController?.config;
    if (config == null || !config.features.pushNotifications) return;
    await _pushRegistration.start();
    if (_pushRegistration.status.value.backendRegistered) {
      await _bootstrap.profileController?.load();
    }
  }

  Future<void> _logout() async {
    try {
      await _pushRegistration.revokeAndStop();
    } on Object {
      // Push cleanup must not prevent authentication revocation.
    }

    try {
      await _authRepository.logout();
    } on Object {
      try {
        await _tokenStore.clear();
      } on Object {
        // The server-side logout may already have revoked the token. Protected
        // in-memory state is invalidated below even if secure storage is
        // temporarily unavailable.
      }
    } finally {
      _bootstrap.signOutLocalState();
    }
  }

  @override
  void dispose() {
    unawaited(_pushRegistration.dispose());
    _localeController.dispose();
    _landingController.dispose();
    _loginController.dispose();
    _bootstrap.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _localeController,
      builder: (context, child) => MaterialApp(
        title: SafeContractsBrand.name,
        debugShowCheckedModeBanner: false,
        locale: _localeController.locale,
        supportedLocales: SafeContractsLocalizations.supportedLocales,
        localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
          SafeContractsLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        theme: _theme(_localeController.languageCode),
        builder: (context, child) => SafeContractsDirectionScope(
          languageCode: _localeController.languageCode,
          child: child ?? const SizedBox.shrink(),
        ),
        home: _BootstrapView(
          environment: widget.environment,
          controller: _bootstrap,
          landingController: _landingController,
          loginController: _loginController,
          pushRegistration: _pushRegistration,
          languageCode: _localeController.languageCode,
          onLanguageChanged: _localeController.setLanguageCode,
          onAuthenticated: _afterAuthenticated,
          onReady: _startPushIfNeeded,
          onLogout: _logout,
        ),
      ),
    );
  }
}

final class _BootstrapView extends StatefulWidget {
  const _BootstrapView({
    required this.environment,
    required this.controller,
    required this.landingController,
    required this.loginController,
    required this.pushRegistration,
    required this.languageCode,
    required this.onLanguageChanged,
    required this.onAuthenticated,
    required this.onReady,
    required this.onLogout,
  });

  final AppEnvironment environment;
  final MobileBootstrapController controller;
  final MobileLandingController landingController;
  final MobileLoginController loginController;
  final MobilePushRegistration pushRegistration;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;
  final Future<void> Function() onAuthenticated;
  final Future<void> Function() onReady;
  final Future<void> Function() onLogout;

  @override
  State<_BootstrapView> createState() => _BootstrapViewState();
}

final class _BootstrapViewState extends State<_BootstrapView> {
  bool _showLogin = false;
  bool _logoutInFlight = false;

  Future<void> _logoutFromAuthenticatedShell() async {
    if (_logoutInFlight) return;
    _logoutInFlight = true;
    try {
      await widget.onLogout();
      if (!mounted) return;
      setState(() => _showLogin = true);
    } finally {
      _logoutInFlight = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        if (widget.controller.state == MobileBootstrapState.ready) {
          final session = widget.controller.sessionController?.session;
          final config = widget.controller.configController?.config;
          final policy = widget.controller.navigationPolicy;
          final dashboard = widget.controller.dashboardController;
          final customers = widget.controller.customersController;
          final suppliers = widget.controller.suppliersController;
          final contracts = widget.controller.contractsController;
          final finance = widget.controller.financeController;
          final notifications = widget.controller.notificationsController;
          final profile = widget.controller.profileController;
          final excelExport = widget.controller.excelExportController;
          if (session != null &&
              config != null &&
              policy != null &&
              dashboard != null &&
              customers != null &&
              suppliers != null &&
              contracts != null &&
              finance != null &&
              notifications != null &&
              profile != null &&
              excelExport != null) {
            unawaited(widget.onReady());
            return SafeContractsShell(
              session: session,
              config: config,
              policy: policy,
              dashboardController: dashboard,
              customersController: customers,
              suppliersController: suppliers,
              contractsController: contracts,
              financeController: finance,
              notificationsController: notifications,
              profileController: profile,
              excelExportController: excelExport,
              pushRegistration: widget.pushRegistration,
              languageCode: widget.languageCode,
              onLanguageChanged: widget.onLanguageChanged,
              usingConfigDefaults: widget.controller.usingConfigDefaults,
              onClearSession: () =>
                  unawaited(_logoutFromAuthenticatedShell()),
            );
          }
        }

        if (widget.controller.sessionController?.state ==
            SessionState.unauthenticated) {
          if (!_showLogin) {
            return AlkenzyCompanyWelcomeScreen(
              controller: widget.landingController,
              languageCode: widget.languageCode,
              onLanguageChanged: widget.onLanguageChanged,
              onSignIn: () => setState(() => _showLogin = true),
            );
          }
          return SafeContractsLoginScreen(
            controller: widget.loginController,
            languageCode: widget.languageCode,
            onLanguageChanged: widget.onLanguageChanged,
            onBack: () => setState(() => _showLogin = false),
            onAuthenticated: () async {
              await widget.onAuthenticated();
              if (mounted) setState(() => _showLogin = false);
            },
          );
        }

        final isLoading =
            widget.controller.state == MobileBootstrapState.idle ||
                widget.controller.state == MobileBootstrapState.loading;
        return SafeContractsSplash(
          label: isLoading
              ? l10n.t('Loading')
              : l10n.rawMessage(
                  widget.controller.message ??
                      'SafeContracts mobile is unavailable.',
                ),
          environmentLabel:
              '${l10n.t('Environment')}: ${widget.environment.name.name}',
          state: isLoading
              ? SafeContractsSplashState.loading
              : SafeContractsSplashState.error,
          message: isLoading
              ? null
              : l10n.rawMessage(
                  widget.controller.message ??
                      'SafeContracts mobile is unavailable.',
                ),
          retryLabel: l10n.t('Retry session'),
          onRetry:
              isLoading ? null : () => unawaited(widget.controller.bootstrap()),
        );
      },
    );
  }
}
