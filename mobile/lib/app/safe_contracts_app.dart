import 'package:flutter/material.dart';

import '../core/config/app_config.dart';
import '../features/foundation/foundation_home_page.dart';

final class SafeContractsApp extends StatelessWidget {
  const SafeContractsApp({
    required this.config,
    super.key,
  });

  final AppConfig config;

  @override
  Widget build(BuildContext context) {
    const Color navy = Color(0xFF173F67);
    const Color success = Color(0xFF2DBE60);

    return MaterialApp(
      debugShowCheckedModeBanner: false,
      title: 'SafeContracts',
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(
          seedColor: navy,
          primary: navy,
          secondary: success,
        ),
        useMaterial3: true,
      ),
      home: FoundationHomePage(config: config),
    );
  }
}
