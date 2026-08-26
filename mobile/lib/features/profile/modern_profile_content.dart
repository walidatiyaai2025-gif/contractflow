import 'package:flutter/material.dart';

import '../session/session_controller.dart';
import '../ui/mobile_layout.dart';
import '../ui/safecontracts_design.dart';
import 'profile_identity_sections.dart';

final class ModernProfileContent extends StatelessWidget {
  const ModernProfileContent({
    required this.session,
    required this.languageCode,
    required this.onLanguageChanged,
    required this.onLogout,
    required this.onUserGuide,
    this.avatarUrl,
    this.avatarUploading = false,
    this.onAvatarUpload,
    this.onPrivacyLegal,
    super.key,
  });

  static const appVersion = '0.3.12+17';

  final SafeContractsSession session;
  final String languageCode;
  final ValueChanged<String> onLanguageChanged;
  final VoidCallback onLogout;
  final VoidCallback onUserGuide;
  final String? avatarUrl;
  final bool avatarUploading;
  final VoidCallback? onAvatarUpload;
  final VoidCallback? onPrivacyLegal;

  @override
  Widget build(BuildContext context) {
    final ar = languageCode.trim().toLowerCase() == 'ar';
    return SafeContractsAdaptiveBody(
      child: LayoutBuilder(
        builder: (context, constraints) {
          final content = Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ProfileHero(session: session, avatarUrlOverride: avatarUrl),
              Align(
                alignment: AlignmentDirectional.centerStart,
                child: TextButton.icon(
                  key: const Key('profileChangePhoto'),
                  onPressed: avatarUploading ? null : onAvatarUpload,
                  icon: avatarUploading
                      ? const SizedBox.square(
                          dimension: 16,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : const Icon(Icons.photo_camera_outlined),
                  label: Text(
                    ar ? 'تغيير الصورة الشخصية' : 'Change profile photo',
                  ),
                ),
              ),
              const SizedBox(height: 4),
              _EmployeeDetails(session: session, isArabic: ar),
              const SizedBox(height: 10),
              ProfileLanguageControl(
                languageCode: languageCode,
                onLanguageChanged: onLanguageChanged,
              ),
              if (onPrivacyLegal != null) ...[
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton.icon(
                    key: const Key('profilePrivacyLegal'),
                    onPressed: onPrivacyLegal,
                    icon: const Icon(Icons.shield_outlined),
                    label: Text(
                      ar ? 'الخصوصية والمعلومات القانونية' : 'Privacy & legal',
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 10),
              ProfilePrimaryActions(
                onLogout: onLogout,
                onUserGuide: onUserGuide,
              ),
              const SizedBox(height: 8),
              Text(
                ar ? 'إصدار التطبيق $appVersion' : 'App version $appVersion',
                key: const Key('profileAppVersion'),
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      color: SafeContractsVisual.muted,
                      fontWeight: FontWeight.w700,
                    ),
              ),
            ],
          );

          if (constraints.maxHeight < 560) {
            return SingleChildScrollView(
              key: const Key('profileShortDeviceScroll'),
              primary: false,
              child: content,
            );
          }
          return content;
        },
      ),
    );
  }
}

final class _EmployeeDetails extends StatelessWidget {
  const _EmployeeDetails({required this.session, required this.isArabic});

  final SafeContractsSession session;
  final bool isArabic;

  @override
  Widget build(BuildContext context) {
    final rows = <({IconData icon, String label, String value})>[
      (
        icon: Icons.badge_outlined,
        label: isArabic ? 'الاسم' : 'Name',
        value: session.displayName?.trim().isNotEmpty == true
            ? session.displayName!.trim()
            : (isArabic ? 'غير متاح' : 'Not available'),
      ),
      (
        icon: Icons.email_outlined,
        label: isArabic ? 'البريد الإلكتروني' : 'Email',
        value: session.email ?? (isArabic ? 'غير متاح' : 'Not available'),
      ),
      (
        icon: Icons.phone_outlined,
        label: isArabic ? 'رقم الهاتف' : 'Phone',
        value: session.phone ?? (isArabic ? 'غير متاح' : 'Not available'),
      ),
    ];
    return SafeContractsSurface(
      elevated: false,
      padding: const EdgeInsets.all(12),
      child: Column(
        children: [
          for (var index = 0; index < rows.length; index++) ...[
            Row(
              children: [
                Icon(
                  rows[index].icon,
                  size: 19,
                  color: SafeContractsVisual.navy,
                ),
                const SizedBox(width: 9),
                SizedBox(
                  width: 104,
                  child: Text(
                    rows[index].label,
                    style: const TextStyle(
                      color: SafeContractsVisual.muted,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
                Expanded(
                  child: Text(
                    rows[index].value,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                ),
              ],
            ),
            if (index != rows.length - 1) const Divider(height: 18),
          ],
        ],
      ),
    );
  }
}
