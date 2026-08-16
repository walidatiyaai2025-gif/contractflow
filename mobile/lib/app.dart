import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'core/api/api_client.dart';
import 'core/api/io_api_transport.dart';
import 'core/auth/mobile_token_store.dart';
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
      // Continue to revoke the SafeContracts mobile session.
    }
    try {
      await _authRepository.logout();
    } on Object {
      await _tokenStore.clear();
    }
    _bootstrap.signOutLocalState();
  }

  ThemeData _theme() {
    final scheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFF173B65),
      brightness: Brightness.light,
    );
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(16),
      borderSide: BorderSide(color: scheme.outlineVariant),
    );
    return ThemeData(
      colorScheme: scheme,
      useMaterial3: true,
      scaffoldBackgroundColor: const Color(0xFFF6F8FB),
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 1,
        backgroundColor: const Color(0xFFF6F8FB),
        foregroundColor: scheme.onSurface,
        titleTextStyle: TextStyle(
          color: scheme.onSurface,
          fontSize: 20,
          fontWeight: FontWeight.w700,
        ),
      ),
      cardTheme: CardThemeData(
        margin: EdgeInsets.zero,
        elevation: 0,
        color: scheme.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(20),
          side: BorderSide(color: scheme.outlineVariant.withValues(alpha: 0.62)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: scheme.surfaceContainerLowest,
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
        border: border,
        enabledBorder: border,
        focusedBorder: border.copyWith(
          borderSide: BorderSide(color: scheme.primary, width: 1.7),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(0, 50),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        backgroundColor: scheme.surface,
        indicatorColor: scheme.primaryContainer,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w700
                : FontWeight.w500,
          ),
        ),
      ),
      chipTheme: ChipThemeData(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      dividerTheme: DividerThemeData(color: scheme.outlineVariant),
    );
  }

  @override
  void dispose() {
    unawaited(_pushRegistration.dispose());
    _localeController.dispose();
    _loginController.dispose();
    _bootstrap.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _localeController,
      builder: (context, child) => MaterialApp(
        title: 'SafeContracts',
        debugShowCheckedModeBanner: false,
        locale: _localeController.locale,
        supportedLocales: SafeContractsLocalizations.supportedLocales,
        localizationsDelegates: const <LocalizationsDelegate<dynamic>>[
          SafeContractsLocalizations.delegate,
          GlobalMaterialLocalizations.delegate,
          GlobalWidgetsLocalizations.delegate,
          GlobalCupertinoLocalizations.delegate,
        ],
        theme: _theme(),
        builder: (context, child) => SafeContractsDirectionScope(
          languageCode: _localeController.languageCode,
          child: child ?? const SizedBox.shrink(),
        ),
        home: _BootstrapView(
          environment: widget.environment,
          controller: _bootstrap,
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

final class _BootstrapView extends StatelessWidget {
  const _BootstrapView({
    required this.environment,
    required this.controller,
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
  final MobileLoginController loginController;
  final MobilePushRegistration pushRegistration;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;
  final Future<void> Function() onAuthenticated;
  final Future<void> Function() onReady;
  final Future<void> Function() onLogout;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        if (controller.state == MobileBootstrapState.ready) {
          final session = controller.sessionController?.session;
          final config = controller.configController?.config;
          final policy = controller.navigationPolicy;
          final dashboard = controller.dashboardController;
          final customers = controller.customersController;
          final contracts = controller.contractsController;
          final notifications = controller.notificationsController;
          final profile = controller.profileController;
          final excelExport = controller.excelExportController;
          if (session != null &&
              config != null &&
              policy != null &&
              dashboard != null &&
              customers != null &&
              contracts != null &&
              notifications != null &&
              profile != null &&
              excelExport != null) {
            unawaited(onReady());
            return SafeContractsShell(
              session: session,
              config: config,
              policy: policy,
              dashboardController: dashboard,
              customersController: customers,
              contractsController: contracts,
              notificationsController: notifications,
              profileController: profile,
              excelExportController: excelExport,
              pushRegistration: pushRegistration,
              languageCode: languageCode,
              onLanguageChanged: onLanguageChanged,
              usingConfigDefaults: controller.usingConfigDefaults,
              onClearSession: () => unawaited(onLogout()),
            );
          }
        }

        if (controller.sessionController?.state == SessionState.unauthenticated) {
          return SafeContractsLoginScreen(
            controller: loginController,
            languageCode: languageCode,
            onLanguageChanged: onLanguageChanged,
            onAuthenticated: onAuthenticated,
          );
        }

        return Scaffold(
          body: SafeArea(
            child: Center(
              child: Card(
                margin: const EdgeInsets.all(24),
                child: Padding(
                  padding: const EdgeInsets.all(28),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(Icons.shield_outlined, size: 56),
                      const SizedBox(height: 16),
                      Text(
                        'SafeContracts',
                        style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                      const SizedBox(height: 8),
                      Text('${l10n.t('Environment')}: ${environment.name.name}'),
                      const SizedBox(height: 18),
                      if (controller.state == MobileBootstrapState.idle ||
                          controller.state == MobileBootstrapState.loading)
                        const CircularProgressIndicator()
                      else ...[
                        Text(
                          l10n.rawMessage(
                            controller.message ??
                                'SafeContracts mobile is unavailable.',
                          ),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 14),
                        FilledButton(
                          onPressed: () => unawaited(controller.bootstrap()),
                          child: Text(l10n.t('Retry session')),
                        ),
                      ],
                    ],
                  ),
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
