import 'package:flutter/material.dart';

import 'core/config/app_environment.dart';

class SafeContractsApp extends StatelessWidget {
  const SafeContractsApp({
    required this.environment,
    super.key,
  });

  final AppEnvironment environment;

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'SafeContracts',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF173B65),
        ),
        useMaterial3: true,
      ),
      home: _FoundationScreen(environment: environment),
    );
  }
}

class _FoundationScreen extends StatelessWidget {
  const _FoundationScreen({required this.environment});

  final AppEnvironment environment;

  @override
  Widget build(BuildContext context) {
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
                const Text(
                  'Mobile foundation ready. Business data remains server-authoritative.',
                  textAlign: TextAlign.center,
                ),
                const SizedBox(height: 12),
                Text('Environment: ${environment.name.name}'),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
