import 'dart:async';

import 'package:flutter/material.dart';

import 'core/api/api_client.dart';
import 'core/api/io_api_transport.dart';
import 'core/auth/mobile_token_store.dart';
import 'core/config/app_environment.dart';
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

  @override
  void initState() {
    super.initState();
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
    if (config == null || !config.features.pushNotifications) {
      return;
    }
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

  @override
  void dispose() {
    unawaited(_pushRegistration.dispose());
    _loginController.dispose();
    _bootstrap.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SafeContracts',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF173B65)),
        useMaterial3: true,
      ),
      builder: (context, child) => SafeContractsDirectionScope(
        languageCode: widget.languageCode,
        child: child ?? const SizedBox.shrink(),
      ),
      home: _BootstrapView(
        environment: widget.environment,
        controller: _bootstrap,
        loginController: _loginController,
        pushRegistration: _pushRegistration,
        onAuthenticated: _afterAuthenticated,
        onReady: _startPushIfNeeded,
        onLogout: _logout,
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
    required this.onAuthenticated,
    required this.onReady,
    required this.onLogout,
  });

  final AppEnvironment environment;
  final MobileBootstrapController controller;
  final MobileLoginController loginController;
  final MobilePushRegistration pushRegistration;
  final Future<void> Function() onAuthenticated;
  final Future<void> Function() onReady;
  final Future<void> Function() onLogout;

  @override
  Widget build(BuildContext context) {
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
              usingConfigDefaults: controller.usingConfigDefaults,
              onClearSession: () => unawaited(onLogout()),
            );
          }
        }

        if (controller.sessionController?.state ==
            SessionState.unauthenticated) {
          return SafeContractsLoginScreen(
            controller: loginController,
            onAuthenticated: onAuthenticated,
          );
        }

        return Scaffold(
          body: SafeArea(
            child: Center(
              child: Padding(
                padding: const EdgeInsets.all(24),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(Icons.fact_check_outlined, size: 56),
                    const SizedBox(height: 16),
                    Text(
                      'SafeContracts',
                      style: Theme.of(context).textTheme.headlineMedium,
                    ),
                    const SizedBox(height: 8),
                    Text('Environment: ${environment.name.name}'),
                    const SizedBox(height: 16),
                    if (controller.state == MobileBootstrapState.idle ||
                        controller.state == MobileBootstrapState.loading)
                      const CircularProgressIndicator()
                    else ...[
                      Text(
                        controller.message ??
                            'SafeContracts mobile is unavailable.',
                        textAlign: TextAlign.center,
                      ),
                      const SizedBox(height: 12),
                      FilledButton(
                        onPressed: () => unawaited(controller.bootstrap()),
                        child: const Text('Retry session'),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        );
      },
    );
  }
}
