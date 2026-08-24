import 'package:flutter/material.dart';

import '../config/mobile_config.dart';
import 'dashboard_controller.dart';
import 'dashboard_screen.dart';

final class DashboardContextScreen extends StatelessWidget {
  const DashboardContextScreen({
    required this.controller,
    required this.currency,
    this.onOpenPayments,
    super.key,
  });

  final DashboardController controller;
  final MobileCurrencyConfig currency;
  final VoidCallback? onOpenPayments;

  @override
  Widget build(BuildContext context) {
    return DashboardScreen(
      controller: controller,
      currency: currency,
      onOpenPayments: onOpenPayments,
    );
  }
}
