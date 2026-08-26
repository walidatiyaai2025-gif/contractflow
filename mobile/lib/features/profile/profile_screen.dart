import 'dart:async';

import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:url_launcher/url_launcher.dart';

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

  bool get _isArabic => widget.languageCode.trim().toLowerCase() == 'ar';

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
            _isArabic
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

  Future<void> _openPublicLink(String url) async {
    final uri = Uri.tryParse(url);
    if (uri == null ||
        !(uri.scheme == 'https' || uri.scheme == 'http') ||
        uri.host.isEmpty) {
      return;
    }
    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            _isArabic
                ? 'تعذر فتح الرابط. حاول مرة أخرى.'
                : 'Unable to open this link. Try again.',
          ),
        ),
      );
    }
  }

  Future<void> _showPrivacyAndLegal() async {
    final links = widget.config.storeLinks;
    await showModalBottomSheet<void>(
      context: context,
      showDragHandle: true,
      builder: (sheetContext) => SafeArea(
        child: ListView(
          shrinkWrap: true,
          padding: const EdgeInsets.only(bottom: 12),
          children: [
            ListTile(
              title: Text(
                _isArabic
                    ? 'الخصوصية والمعلومات القانونية'
                    : 'Privacy & legal',
              ),
              subtitle: Text(
                _isArabic
                    ? 'روابط Alkenzy ADV الرسمية المنشورة من خادم النظام.'
                    : 'Official Alkenzy ADV links published by the service.',
              ),
            ),
            if (links.privacyPolicy.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.privacy_tip_outlined),
                title: Text(_isArabic ? 'سياسة الخصوصية' : 'Privacy policy'),
                trailing: const Icon(Icons.open_in_new_rounded),
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  unawaited(_openPublicLink(links.privacyPolicy));
                },
              ),
            if (links.accountDeletion.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.person_remove_outlined),
                title: Text(_isArabic ? 'طلب حذف الحساب' : 'Account deletion'),
                trailing: const Icon(Icons.open_in_new_rounded),
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  unawaited(_openPublicLink(links.accountDeletion));
                },
              ),
            if (links.support.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.support_agent_rounded),
                title: Text(_isArabic ? 'الدعم الفني' : 'Support'),
                trailing: const Icon(Icons.open_in_new_rounded),
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  unawaited(_openPublicLink(links.support));
                },
              ),
            if (links.terms.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.description_outlined),
                title: Text(_isArabic ? 'شروط الاستخدام' : 'Terms of use'),
                trailing: const Icon(Icons.open_in_new_rounded),
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  unawaited(_openPublicLink(links.terms));
                },
              ),
          ],
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
        onPrivacyLegal: widget.config.storeLinks.hasAny
            ? () => unawaited(_showPrivacyAndLegal())
            : null,
      ),
    );
  }
}
