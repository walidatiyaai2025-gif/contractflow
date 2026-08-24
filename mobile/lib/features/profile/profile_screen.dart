import 'dart:async';

import 'package:flutter/material.dart';

import '../config/mobile_config.dart';
import '../help/mobile_user_guide_screen.dart';
import '../navigation/navigation_policy.dart';
import '../notifications/push_registration.dart';
import '../session/session_controller.dart';
import 'modern_profile_content.dart';
import 'profile.dart';

final class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    required this.session,
    required this.config,
    required this.controller,
    required this.pushRegistration,
    required this.languageCode,
    required this.onLanguageChanged,
    required this.onClearSession,
    super.key,
  });

  final SafeContractsSession session;
  final SafeContractsMobileConfig config;
  final ProfileController controller;
  final MobilePushRegistration pushRegistration;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;
  final VoidCallback onClearSession;

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

final class _ProfileScreenState extends State<ProfileScreen> {
  Future<void> _openUserGuide() async {
    final policy = MobileNavigationPolicy.resolve(widget.session, widget.config);
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (context) => MobileUserGuideScreen(
          destinations: policy.destinations,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return ModernProfileContent(
      session: widget.session,
      languageCode: widget.languageCode,
      onLanguageChanged: widget.onLanguageChanged,
      onLogout: widget.onClearSession,
      onUserGuide: () => unawaited(_openUserGuide()),
    );
  }
}
