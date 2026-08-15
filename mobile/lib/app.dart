import 'dart:async';

import 'package:flutter/material.dart';

import 'core/api/api_client.dart';
import 'core/api/io_api_transport.dart';
import 'core/config/app_environment.dart';
import 'core/ui/responsive.dart';
import 'features/bootstrap/mobile_bootstrap_controller.dart';
import 'features/navigation/app_shell.dart';

class SafeContractsApp extends StatefulWidget {
  const SafeContractsApp({
    required this.environment,
    this.client,
    this.languageCode,
    super.key,
  });

  final AppEnvironment environment;
  final SafeContractsApiClient? client;
  final String? languageCode;

  @override
  State<SafeContractsApp> createState() => _SafeContractsAppState();
}

final class _SafeContractsAppState extends State<SafeContractsApp> {
  late final SafeContractsApiClient _client;
  late final MobileBootstrapController _bootstrap;

  @override
  void initState() {
    super.initState();
    _client = widget.client ??
        SafeContractsApiClient(
          environment: widget.environment,
          transport: IoApiTransport(),
        );
    _bootstrap = MobileBootstrapController(_client);
    unawaited(_bootstrap.bootstrap());
  }

  @override
  void dispose() {
    _bootstrap.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final languageCode = widget.languageCode ??
        WidgetsBinding.instance.platformDispatcher.locale.languageCode;
    return MaterialApp(
      title: 'SafeContracts',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: const Color(0xFF173B65)),
        useMaterial3: true,
      ),
      builder: (context, child) => Directionality(
        textDirection: SafeTextDirection.forLanguageCode(languageCode),
        child: child ?? const SizedBox.shrink(),
      ),
      home: _BootstrapView(
        environment: widget.environment,
        controller: _bootstrap,
      ),
    );
  }
}

final class _BootstrapView extends StatelessWidget {
  const _BootstrapView({
    required this.environment,
    required this.controller,
  });

  final AppEnvironment environment;
  final MobileBootstrapController controller;

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
          final excelExport = controller.excelExportController;
          final notifications = controller.notificationsController;
          final profileDevice = controller.profileDeviceController;
          if (session != null &&
              config != null &&
              policy != null &&
              dashboard != null &&
              customers != null &&
              excelExport != null &&
              notifications != null &&
              profileDevice != null) {
            return SafeContractsShell(
              session: session,
              config: config,
              policy: policy,
              dashboardController: dashboard,
              customersController: customers,
              excelExportController: excelExport,
              notificationsController: notifications,
              profileDeviceController: profileDevice,
              usingConfigDefaults: controller.usingConfigDefaults,
              onClearSession: controller.signOutLocalState,
            );
          }
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
