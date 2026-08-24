import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../session/session_controller.dart';
import '../ui/safecontracts_design.dart';

final class ProfileSectionTitle extends StatelessWidget {
  const ProfileSectionTitle({
    required this.englishTitle,
    required this.arabicTitle,
    required this.englishSubtitle,
    required this.arabicSubtitle,
    super.key,
  });

  final String englishTitle;
  final String arabicTitle;
  final String englishSubtitle;
  final String arabicSubtitle;

  @override
  Widget build(BuildContext context) {
    return SafeContractsSectionTitle(
      title: profileCopy(context, englishTitle, arabicTitle),
      subtitle: profileCopy(context, englishSubtitle, arabicSubtitle),
    );
  }
}

final class ProfileHero extends StatelessWidget {
  const ProfileHero({required this.session, super.key});

  final SafeContractsSession session;

  @override
  Widget build(BuildContext context) {
    final name = _profileName(context, session);
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
        boxShadow: const [
          BoxShadow(
            color: Color(0x22092944),
            blurRadius: 16,
            offset: Offset(0, 6),
          ),
        ],
      ),
      child: Row(
        children: [
          _ProfileAvatar(session: session),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  profileCopy(context, 'My profile', 'ملفي الشخصي'),
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: Colors.white.withValues(alpha: 0.68),
                        fontWeight: FontWeight.w700,
                      ),
                ),
                const SizedBox(height: 2),
                Text(
                  name,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: Colors.white,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 4),
                Text(
                  profileAccountDescription(context, session.scope),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: Colors.white.withValues(alpha: 0.78),
                        height: 1.3,
                      ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class _ProfileAvatar extends StatelessWidget {
  const _ProfileAvatar({required this.session});

  final SafeContractsSession session;

  @override
  Widget build(BuildContext context) {
    final avatarUrl = session.avatarUrl;
    return Container(
      width: 58,
      height: 58,
      padding: const EdgeInsets.all(3),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: Colors.white.withValues(alpha: 0.14),
        border: Border.all(
          color: Colors.white.withValues(alpha: 0.22),
        ),
      ),
      child: ClipOval(
        child: Stack(
          fit: StackFit.expand,
          children: [
            const ColoredBox(
              color: SafeContractsVisual.surface,
              child: Center(
                child: SafeContractsBrandMark(size: 42, borderRadius: 12),
              ),
            ),
            if (avatarUrl != null)
              Image.network(
                avatarUrl,
                fit: BoxFit.cover,
                errorBuilder: (context, error, stackTrace) =>
                    const SizedBox.shrink(),
              ),
          ],
        ),
      ),
    );
  }
}

final class ProfileLanguageControl extends StatelessWidget {
  const ProfileLanguageControl({
    required this.languageCode,
    required this.onLanguageChanged,
    super.key,
  });

  final String languageCode;
  final ValueChanged<String> onLanguageChanged;

  @override
  Widget build(BuildContext context) {
    final normalized = languageCode.trim().toLowerCase() == 'ar' ? 'ar' : 'en';
    return SafeContractsSurface(
      elevated: false,
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          const _CompactIcon(Icons.translate_rounded),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  profileCopy(context, 'Language', 'اللغة'),
                  style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: SafeContractsVisual.ink,
                        fontWeight: FontWeight.w800,
                      ),
                ),
                const SizedBox(height: 7),
                SegmentedButton<String>(
                  segments: const <ButtonSegment<String>>[
                    ButtonSegment<String>(
                      value: 'ar',
                      label: Text('العربية'),
                    ),
                    ButtonSegment<String>(
                      value: 'en',
                      label: Text('English'),
                    ),
                  ],
                  selected: <String>{normalized},
                  showSelectedIcon: false,
                  style: const ButtonStyle(
                    visualDensity: VisualDensity.compact,
                    tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                  ),
                  onSelectionChanged: (selection) {
                    if (selection.isEmpty) return;
                    final next = selection.first;
                    if (next != normalized) onLanguageChanged(next);
                  },
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class ProfilePrimaryActions extends StatelessWidget {
  const ProfilePrimaryActions({
    required this.onLogout,
    required this.onUserGuide,
    super.key,
  });

  final VoidCallback onLogout;
  final VoidCallback onUserGuide;

  @override
  Widget build(BuildContext context) {
    return SafeContractsSurface(
      elevated: false,
      padding: const EdgeInsets.fromLTRB(12, 10, 12, 8),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              key: const Key('profileLogoutButton'),
              onPressed: onLogout,
              style: FilledButton.styleFrom(
                backgroundColor: SafeContractsVisual.red,
                foregroundColor: Colors.white,
                visualDensity: VisualDensity.compact,
              ),
              icon: const Icon(Icons.logout_rounded, size: 19),
              label: Text(profileCopy(context, 'Log out', 'تسجيل الخروج')),
            ),
          ),
          TextButton.icon(
            key: const Key('profileUserGuideButton'),
            onPressed: onUserGuide,
            style: TextButton.styleFrom(visualDensity: VisualDensity.compact),
            icon: const Icon(Icons.help_outline_rounded, size: 18),
            label: Text(profileCopy(context, 'User Guide', 'دليل المستخدم')),
          ),
        ],
      ),
    );
  }
}

final class _CompactIcon extends StatelessWidget {
  const _CompactIcon(this.icon);

  final IconData icon;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 36,
      height: 36,
      decoration: BoxDecoration(
        color: SafeContractsVisual.navySoft,
        borderRadius: BorderRadius.circular(11),
      ),
      child: Icon(icon, color: SafeContractsVisual.navy, size: 19),
    );
  }
}

// Compatibility primitives retained for legacy device/push widgets. They are
// no longer mounted by ProfileScreen, so the compact end-user Profile remains
// focused on identity, language, logout, and the User Guide.
final class ProfileTileIcon extends StatelessWidget {
  const ProfileTileIcon(
    this.icon, {
    this.background = SafeContractsVisual.navySoft,
    this.foreground = SafeContractsVisual.navy,
    super.key,
  });

  final IconData icon;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: 42,
      height: 42,
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(14),
      ),
      child: Icon(icon, color: foreground, size: 22),
    );
  }
}

final class ProfilePill extends StatelessWidget {
  const ProfilePill({
    required this.icon,
    required this.text,
    this.background = SafeContractsVisual.navySoft,
    this.foreground = SafeContractsVisual.navy,
    super.key,
  });

  final IconData icon;
  final String text;
  final Color background;
  final Color foreground;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
      decoration: BoxDecoration(
        color: background,
        borderRadius: BorderRadius.circular(99),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(icon, size: icon == Icons.circle ? 8 : 14, color: foreground),
          const SizedBox(width: 5),
          Text(
            text,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: foreground,
                  fontWeight: FontWeight.w800,
                ),
          ),
        ],
      ),
    );
  }
}

String _profileName(BuildContext context, SafeContractsSession session) {
  final displayName = session.displayName?.trim();
  if (displayName != null && displayName.isNotEmpty) return displayName;
  return profileCopy(
    context,
    'User #${session.userId}',
    'المستخدم #${session.userId}',
  );
}

String profileAccountDescription(
  BuildContext context,
  SafeContractsDataScope scope,
) {
  return switch (scope) {
    SafeContractsDataScope.all => profileCopy(
        context,
        'Access to all contract records.',
        'يمكنه الوصول إلى جميع سجلات العقود.',
      ),
    SafeContractsDataScope.assigned => profileCopy(
        context,
        'Access is limited to assigned records.',
        'الوصول مقتصر على السجلات المسندة إليه.',
      ),
    SafeContractsDataScope.none => profileCopy(
        context,
        'No contract records are assigned to this account.',
        'لا توجد سجلات عقود مسندة إلى هذا الحساب.',
      ),
  };
}

String profileCopy(BuildContext context, String english, String arabic) {
  return context.scL10n.isArabic ? arabic : english;
}
