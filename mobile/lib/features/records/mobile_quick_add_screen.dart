import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../session/session_controller.dart';
import 'mobile_quick_add_flow.dart' as flow;

export 'mobile_quick_add_flow.dart' hide MobileQuickAddScreen;

final class MobileQuickAddScreen extends StatelessWidget {
  const MobileQuickAddScreen({
    required this.client,
    required this.session,
    required this.type,
    super.key,
  });

  final SafeContractsApiClient client;
  final SafeContractsSession session;
  final flow.MobileQuickAddType type;

  @override
  Widget build(BuildContext context) {
    return flow.MobileQuickAddScreen(
      client: client,
      session: session,
      type: type,
    );
  }
}
