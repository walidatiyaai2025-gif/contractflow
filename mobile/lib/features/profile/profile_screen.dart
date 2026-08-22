import 'dart:async';

import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../help/mobile_user_guide_screen.dart';
import '../help/mobile_user_guide_translations.dart';
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
  @override
  void initState() {
    super.initState();
    unawaited(widget.controller.ensureLoaded());
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
            context.scL10n.isArabic
                ? 'تعذر فتح الرابط. حاول مرة أخرى.'
                : 'Unable to open this link. Try again.',
          ),
        ),
      );
    }
  }

  Future<void> _showPrivacyAndLegal() async {
    final l10n = context.scL10n;
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
              title: Text(l10n.isArabic
                  ? 'الخصوصية والمعلومات القانونية'
                  : 'Privacy & legal'),
              subtitle: Text(
                l10n.isArabic
                    ? 'روابط Alkenzy ADV الرسمية المنشورة من خادم النظام.'
                    : 'Official Alkenzy ADV links published by the service.',
              ),
            ),
            if (links.privacyPolicy.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.privacy_tip_outlined),
                title:
                    Text(l10n.isArabic ? 'سياسة الخصوصية' : 'Privacy policy'),
                trailing: const Icon(Icons.open_in_new_rounded),
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  unawaited(_openPublicLink(links.privacyPolicy));
                },
              ),
            if (links.accountDeletion.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.person_remove_outlined),
                title:
                    Text(l10n.isArabic ? 'طلب حذف الحساب' : 'Account deletion'),
                trailing: const Icon(Icons.open_in_new_rounded),
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  unawaited(_openPublicLink(links.accountDeletion));
                },
              ),
            if (links.support.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.support_agent_rounded),
                title: Text(l10n.isArabic ? 'الدعم الفني' : 'Support'),
                trailing: const Icon(Icons.open_in_new_rounded),
                onTap: () {
                  Navigator.of(sheetContext).pop();
                  unawaited(_openPublicLink(links.support));
                },
              ),
            if (links.terms.isNotEmpty)
              ListTile(
                leading: const Icon(Icons.description_outlined),
                title: Text(l10n.isArabic ? 'شروط الاستخدام' : 'Terms of use'),
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

  Future<void> _confirmLogout() async {
    final l10n = context.scL10n;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        title: Text(l10n.isArabic ? 'تسجيل الخروج' : 'Sign out'),
        content: Text(
          l10n.isArabic
              ? 'سيتم إلغاء تسجيل هذا الجهاز للإشعارات وإنهاء جلسة Safe Contracts على هذا الهاتف. هل تريد المتابعة؟'
              : 'This device will be unregistered from push notifications and the Safe Contracts session on this phone will be ended. Continue?',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: Text(l10n.isArabic ? 'إلغاء' : 'Cancel'),
          ),
          FilledButton.icon(
            onPressed: () => Navigator.of(dialogContext).pop(true),
            icon: const Icon(Icons.logout_rounded),
            label: Text(l10n.isArabic ? 'تسجيل الخروج' : 'Sign out'),
          ),
        ],
      ),
    );
    if (!mounted || confirmed != true) return;
    widget.onClearSession();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
          child: Card(
            child: ListTile(
              leading: const Icon(Icons.help_outline_rounded),
              title: Text(mobileGuideText(l10n, 'User Guide')),
              subtitle: Text(
                mobileGuideText(
                  l10n,
                  'Only sections available to your account are shown.',
                ),
              ),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => unawaited(_openUserGuide()),
            ),
          ),
        ),
        if (widget.config.storeLinks.hasAny)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
            child: Card(
              child: ListTile(
                leading: const Icon(Icons.shield_outlined),
                title: Text(
                  l10n.isArabic
                      ? 'الخصوصية والمعلومات القانونية'
                      : 'Privacy & legal',
                ),
                subtitle: Text(
                  l10n.isArabic
                      ? 'سياسة الخصوصية، حذف الحساب، الدعم وشروط الاستخدام.'
                      : 'Privacy policy, account deletion, support and terms.',
                ),
                trailing: const Icon(Icons.chevron_right_rounded),
                onTap: () => unawaited(_showPrivacyAndLegal()),
              ),
            ),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 10, 16, 0),
          child: Card(
            child: ListTile(
              leading: Icon(
                Icons.logout_rounded,
                color: Theme.of(context).colorScheme.error,
              ),
              title: Text(
                l10n.isArabic ? 'تسجيل الخروج' : 'Sign out',
                style: TextStyle(
                  color: Theme.of(context).colorScheme.error,
                  fontWeight: FontWeight.w800,
                ),
              ),
              subtitle: Text(
                l10n.isArabic
                    ? 'إنهاء الجلسة وإلغاء تسجيل هذا الجهاز من الإشعارات.'
                    : 'End the session and unregister this device from push notifications.',
              ),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => unawaited(_confirmLogout()),
            ),
          ),
        ),
        Expanded(
          child: ModernProfileContent(
            session: widget.session,
            config: widget.config,
            controller: widget.controller,
            pushRegistration: widget.pushRegistration,
            languageCode: widget.languageCode,
            onLanguageChanged: widget.onLanguageChanged,
            onClearSession: widget.onClearSession,
          ),
        ),
      ],
    );
  }
}
