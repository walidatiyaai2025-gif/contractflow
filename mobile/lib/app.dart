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
import 'features/ui/safecontracts_design.dart';
import 'features/welcome/mobile_landing.dart';
import 'features/welcome/premium_compact_welcome_screen.dart';

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
      // Continue to revoke the SafeContracts mobile session.
    }
    try {
      await _authRepository.logout();
    } on Object {
      await _tokenStore.clear();
    }
    _bootstrap.signOutLocalState();
  }

  ThemeData _theme(String languageCode) {
    final isArabic = languageCode.toLowerCase() == 'ar';
    final arabicFontFamily = isArabic ? GoogleFonts.cairo().fontFamily : null;
    const scheme = ColorScheme.light(
      primary: SafeContractsVisual.navy,
      onPrimary: Colors.white,
      primaryContainer: SafeContractsVisual.navySoft,
      onPrimaryContainer: SafeContractsVisual.navyDeep,
      secondary: SafeContractsVisual.roseGold,
      onSecondary: Colors.white,
      secondaryContainer: SafeContractsVisual.roseGoldSoft,
      onSecondaryContainer: SafeContractsVisual.ink,
      tertiary: SafeContractsVisual.green,
      onTertiary: Colors.white,
      error: SafeContractsVisual.red,
      onError: Colors.white,
      surface: SafeContractsVisual.surface,
      onSurface: SafeContractsVisual.ink,
      outline: SafeContractsVisual.outline,
      outlineVariant: Color(0xFFE6DCD2),
      surfaceContainerLowest: Color(0xFFFFFEFC),
      surfaceContainerLow: SafeContractsVisual.backgroundRaised,
      surfaceContainer: Color(0xFFF0E9E1),
      surfaceContainerHigh: Color(0xFFE8DDD1),
      surfaceContainerHighest: Color(0xFFDFD3C7),
    );
    final border = OutlineInputBorder(
      borderRadius: BorderRadius.circular(16),
      borderSide: const BorderSide(color: SafeContractsVisual.outline),
    );
    final textTheme =
        isArabic ? GoogleFonts.cairoTextTheme() : GoogleFonts.interTextTheme();

    final theme = ThemeData(
      colorScheme: scheme,
      useMaterial3: true,
      fontFamily: arabicFontFamily,
      textTheme: textTheme.apply(
        bodyColor: SafeContractsVisual.ink,
        displayColor: SafeContractsVisual.ink,
      ),
      scaffoldBackgroundColor: SafeContractsVisual.background,
      canvasColor: SafeContractsVisual.background,
      appBarTheme: AppBarTheme(
        centerTitle: false,
        elevation: 0,
        scrolledUnderElevation: 0,
        backgroundColor: SafeContractsVisual.navy,
        foregroundColor: Colors.white,
        surfaceTintColor: Colors.transparent,
        iconTheme: const IconThemeData(color: Colors.white),
        actionsIconTheme: const IconThemeData(color: Colors.white),
        titleTextStyle: TextStyle(
          color: Colors.white,
          fontSize: 19,
          fontWeight: FontWeight.w800,
          fontFamily: arabicFontFamily,
        ),
      ),
      cardTheme: CardThemeData(
        margin: EdgeInsets.zero,
        elevation: 0,
        color: SafeContractsVisual.surface,
        shadowColor: const Color(0x245A4638),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
          side: const BorderSide(color: SafeContractsVisual.outline),
        ),
      ),
      dialogTheme: DialogThemeData(
        backgroundColor: SafeContractsVisual.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(SafeContractsVisual.radius),
        ),
      ),
      bottomSheetTheme: const BottomSheetThemeData(
        backgroundColor: SafeContractsVisual.surface,
        surfaceTintColor: Colors.transparent,
        modalBackgroundColor: SafeContractsVisual.surface,
        showDragHandle: true,
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: SafeContractsVisual.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 15,
        ),
        labelStyle: const TextStyle(color: SafeContractsVisual.muted),
        hintStyle: const TextStyle(color: SafeContractsVisual.muted),
        border: border,
        enabledBorder: border,
        focusedBorder: border.copyWith(
          borderSide: const BorderSide(
            color: SafeContractsVisual.roseGold,
            width: 1.8,
          ),
        ),
        errorBorder: border.copyWith(
          borderSide: const BorderSide(color: SafeContractsVisual.red),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: SafeContractsVisual.navy,
          foregroundColor: Colors.white,
          minimumSize: const Size(0, 50),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
          textStyle: TextStyle(
            fontWeight: FontWeight.w800,
            fontFamily: arabicFontFamily,
          ),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: SafeContractsVisual.roseGold,
          foregroundColor: Colors.white,
          elevation: 0,
          minimumSize: const Size(0, 48),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: SafeContractsVisual.navy,
          side: const BorderSide(color: SafeContractsVisual.navy),
          minimumSize: const Size(0, 48),
          padding: const EdgeInsets.symmetric(horizontal: 18, vertical: 13),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(14),
          ),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: SafeContractsVisual.navy,
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      floatingActionButtonTheme: const FloatingActionButtonThemeData(
        backgroundColor: SafeContractsVisual.roseGold,
        foregroundColor: Colors.white,
        elevation: 0,
        focusElevation: 0,
        highlightElevation: 0,
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        backgroundColor: SafeContractsVisual.surface,
        indicatorColor: SafeContractsVisual.roseGoldSoft,
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            color: states.contains(WidgetState.selected)
                ? SafeContractsVisual.navy
                : SafeContractsVisual.muted,
          ),
        ),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            color: states.contains(WidgetState.selected)
                ? SafeContractsVisual.navy
                : SafeContractsVisual.muted,
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w800
                : FontWeight.w600,
            fontFamily: arabicFontFamily,
          ),
        ),
      ),
      navigationDrawerTheme: const NavigationDrawerThemeData(
        backgroundColor: SafeContractsVisual.surface,
        surfaceTintColor: Colors.transparent,
        indicatorColor: SafeContractsVisual.roseGoldSoft,
      ),
      chipTheme: ChipThemeData(
        backgroundColor: SafeContractsVisual.surface,
        selectedColor: SafeContractsVisual.roseGoldSoft,
        side: const BorderSide(color: SafeContractsVisual.outline),
        labelStyle: const TextStyle(
          color: SafeContractsVisual.ink,
          fontWeight: FontWeight.w700,
        ),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
      ),
      dividerTheme: const DividerThemeData(color: SafeContractsVisual.outline),
      snackBarTheme: SnackBarThemeData(
        backgroundColor: SafeContractsVisual.navyDeep,
        contentTextStyle: TextStyle(
          color: Colors.white,
          fontFamily: arabicFontFamily,
        ),
        behavior: SnackBarBehavior.floating,
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: SafeContractsVisual.roseGold,
        linearTrackColor: SafeContractsVisual.roseGoldSoft,
      ),
    );

    if (!isArabic) return theme;
    return theme.copyWith(
      textTheme: GoogleFonts.cairoTextTheme(theme.textTheme),
      primaryTextTheme: GoogleFonts.cairoTextTheme(theme.primaryTextTheme),
    );
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
              onClearSession: () => unawaited(widget.onLogout()),
            );
          }
        }

        if (widget.controller.sessionController?.state ==
            SessionState.unauthenticated) {
          if (!_showLogin) {
            return PremiumCompactWelcomeScreen(
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

        return Scaffold(
          body: SafeContractsBackdrop(
            child: SafeArea(
              child: Center(
                child: SafeContractsSurface(
                  margin: const EdgeInsets.all(24),
                  padding: const EdgeInsets.all(28),
                  accent: SafeContractsVisual.roseGold,
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const SafeContractsBrandMark(size: 72, borderRadius: 20),
                      const SizedBox(height: 16),
                      Text(
                        SafeContractsBrand.name,
                        style: Theme.of(context)
                            .textTheme
                            .headlineMedium
                            ?.copyWith(
                              color: SafeContractsVisual.navy,
                              fontWeight: FontWeight.w900,
                            ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        '${l10n.t('Environment')}: ${widget.environment.name.name}',
                        style:
                            const TextStyle(color: SafeContractsVisual.muted),
                      ),
                      const SizedBox(height: 18),
                      if (widget.controller.state ==
                              MobileBootstrapState.idle ||
                          widget.controller.state ==
                              MobileBootstrapState.loading)
                        const CircularProgressIndicator()
                      else ...[
                        Text(
                          l10n.rawMessage(
                            widget.controller.message ??
                                'SafeContracts mobile is unavailable.',
                          ),
                          textAlign: TextAlign.center,
                        ),
                        const SizedBox(height: 14),
                        FilledButton(
                          onPressed: () =>
                              unawaited(widget.controller.bootstrap()),
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
