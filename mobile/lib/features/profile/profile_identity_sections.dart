import 'package:flutter/material.dart';

import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
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
    return SafeContractsSurface(
      padding: const EdgeInsets.all(20),
      child: Row(
        children: [
          Stack(
            clipBehavior: Clip.none,
            children: [
              const SafeContractsBrandMark(size: 72, borderRadius: 22),
              PositionedDirectional(
                end: -2,
                bottom: -2,
                child: Container(
                  width: 22,
                  height: 22,
                  decoration: BoxDecoration(
                    color: SafeContractsVisual.green,
                    shape: BoxShape.circle,
                    border: Border.all(
                      color: SafeContractsVisual.surface,
                      width: 3,
                    ),
                  ),
                  child: const Icon(
                    Icons.check_rounded,
                    size: 12,
                    color: Colors.white,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  profileCopy(context, 'My profile', 'ملفي الشخصي'),
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                        color: SafeContractsVisual.ink,
                        fontWeight: FontWeight.w900,
                      ),
                ),
                const SizedBox(height: 3),
                Text(
                  profileCopy(
                    context,
                    'User #${session.userId}',
                    'المستخدم #${session.userId}',
                  ),
                  style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                        color: SafeContractsVisual.muted,
                        fontWeight: FontWeight.w600,
                      ),
                ),
                const SizedBox(height: 10),
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    ProfilePill(
                      icon: Icons.shield_outlined,
                      text: profileScopeLabel(context, session.scope),
                    ),
                    ProfilePill(
                      icon: Icons.circle,
                      text: profileCopy(context, 'Active session', 'جلسة نشطة'),
                      background: SafeContractsVisual.greenSoft,
                      foreground: SafeContractsVisual.green,
                    ),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

final class ProfilePreferences extends StatelessWidget {
  const ProfilePreferences({
    required this.config,
    required this.languageCode,
    required this.onLanguageChanged,
    super.key,
  });

  final SafeContractsMobileConfig config;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final code = config.currency.code.isEmpty
        ? l10n.t('Not configured')
        : config.currency.code;
    final symbol = config.currency.symbol.isEmpty
        ? l10n.t('Not configured')
        : config.currency.symbol;

    return SafeContractsSurface(
      child: Column(
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const ProfileTileIcon(Icons.translate_rounded),
              const SizedBox(width: 12),
              Expanded(
                child: DropdownButtonFormField<String>(
                  key: ValueKey(languageCode),
                  initialValue: languageCode,
                  decoration: InputDecoration(labelText: l10n.t('Language')),
                  items: [
                    DropdownMenuItem(
                      value: 'ar',
                      child: Text(l10n.t('Arabic')),
                    ),
                    DropdownMenuItem(
                      value: 'en',
                      child: Text(l10n.t('English')),
                    ),
                  ],
                  onChanged: (value) {
                    if (value != null) onLanguageChanged(value);
                  },
                ),
              ),
            ],
          ),
          const Divider(height: 28),
          ProfileInfoRow(
            icon: Icons.currency_exchange_rounded,
            label: l10n.t('Currency'),
            value: '$code  •  $symbol',
          ),
        ],
      ),
    );
  }
}

final class ProfileAccount extends StatelessWidget {
  const ProfileAccount({
    required this.session,
    required this.config,
    super.key,
  });

  final SafeContractsSession session;
  final SafeContractsMobileConfig config;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return SafeContractsSurface(
      child: Column(
        children: [
          ProfileInfoRow(
            icon: Icons.badge_outlined,
            label: l10n.t('User ID'),
            value: '${session.userId}',
          ),
          const Divider(height: 24),
          ProfileInfoRow(
            icon: Icons.data_object_rounded,
            label: l10n.t('Data scope'),
            value: profileScopeLabel(context, session.scope),
          ),
          const Divider(height: 24),
          ProfileInfoRow(
            icon: Icons.view_list_outlined,
            label: l10n.t('Default page size'),
            value: '${config.defaultPageSize}',
          ),
          if (config.supportText.isNotEmpty) ...[
            const Divider(height: 24),
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                const ProfileTileIcon(
                  Icons.support_agent_rounded,
                  background: SafeContractsVisual.amberSoft,
                  foreground: SafeContractsVisual.amber,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    config.supportText,
                    style: Theme.of(context)
                        .textTheme
                        .bodyMedium
                        ?.copyWith(color: SafeContractsVisual.ink),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

final class ProfileLocalSession extends StatelessWidget {
  const ProfileLocalSession({required this.onClearSession, super.key});

  final VoidCallback onClearSession;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return SafeContractsSurface(
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const ProfileTileIcon(
            Icons.phonelink_erase_rounded,
            background: SafeContractsVisual.redSoft,
            foreground: SafeContractsVisual.red,
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  profileCopy(context, 'Local session', 'الجلسة المحلية'),
                  style: Theme.of(context)
                      .textTheme
                      .titleMedium
                      ?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 4),
                Text(
                  profileCopy(
                    context,
                    'Clear saved session data when you need to sign in again.',
                    'امسح بيانات الجلسة عند الحاجة لتسجيل الدخول من جديد.',
                  ),
                  style: Theme.of(context)
                      .textTheme
                      .bodySmall
                      ?.copyWith(color: SafeContractsVisual.muted),
                ),
                const SizedBox(height: 12),
                FilledButton.tonalIcon(
                  onPressed: onClearSession,
                  icon: const Icon(Icons.logout_rounded),
                  label: Text(l10n.t('Clear local session state')),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

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

final class ProfileInfoRow extends StatelessWidget {
  const ProfileInfoRow({
    required this.icon,
    required this.label,
    required this.value,
    super.key,
  });

  final IconData icon;
  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        ProfileTileIcon(icon),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                label,
                style: Theme.of(context)
                    .textTheme
                    .bodySmall
                    ?.copyWith(color: SafeContractsVisual.muted),
              ),
              Text(
                value,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: SafeContractsVisual.ink,
                      fontWeight: FontWeight.w800,
                    ),
              ),
            ],
          ),
        ),
      ],
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
            style: Theme.of(context)
                .textTheme
                .labelMedium
                ?.copyWith(color: foreground, fontWeight: FontWeight.w800),
          ),
        ],
      ),
    );
  }
}

String profileCopy(BuildContext context, String english, String arabic) {
  return context.scL10n.isArabic ? arabic : english;
}

String profileScopeLabel(BuildContext context, SafeContractsDataScope scope) {
  final arabic = context.scL10n.isArabic;
  return switch (scope) {
    SafeContractsDataScope.all => arabic ? 'نطاق كامل' : 'Full scope',
    SafeContractsDataScope.assigned =>
      arabic ? 'السجلات المسندة' : 'Assigned records',
    SafeContractsDataScope.none =>
      arabic ? 'بدون نطاق بيانات' : 'No data scope',
  };
}
