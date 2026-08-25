import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';

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
  final ImagePicker _imagePicker = ImagePicker();

  Future<void> _changeAvatar() async {
    final picked = await _imagePicker.pickImage(
      source: ImageSource.gallery,
      maxWidth: 1200,
      maxHeight: 1200,
      imageQuality: 86,
      requestFullMetadata: false,
    );
    if (picked == null || !mounted) return;
    final bytes = await picked.readAsBytes();
    if (!mounted) return;
    final lower = picked.name.toLowerCase();
    final mimeType = lower.endsWith('.png')
        ? 'image/png'
        : lower.endsWith('.webp')
            ? 'image/webp'
            : 'image/jpeg';
    try {
      await widget.controller.uploadAvatar(bytes: bytes, mimeType: mimeType);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            widget.languageCode == 'ar'
                ? 'تم تحديث الصورة الشخصية.'
                : 'Profile photo updated.',
          ),
        ),
      );
    } on Object catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  Future<void> _openUserGuide() async {
    final policy =
        MobileNavigationPolicy.resolve(widget.session, widget.config);
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
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) => ModernProfileContent(
        session: widget.session,
        languageCode: widget.languageCode,
        onLanguageChanged: widget.onLanguageChanged,
        onLogout: widget.onClearSession,
        onUserGuide: () => unawaited(_openUserGuide()),
        avatarUrl:
            widget.controller.avatarUrlOverride ?? widget.session.avatarUrl,
        avatarUploading: widget.controller.avatarUploadInFlight,
        onAvatarUpload: () => unawaited(_changeAvatar()),
      ),
    );
  }
}
