import 'package:flutter/material.dart';

import '../config/mobile_config.dart';
import '../notifications/push_registration.dart';
import '../session/session_controller.dart';
import '../ui/mobile_layout.dart';
import '../ui/safecontracts_design.dart';
import 'profile.dart';
import 'profile_devices_section.dart';
import 'profile_identity_sections.dart';
import 'profile_push_section.dart';

final class ModernProfileContent extends StatelessWidget {
  const ModernProfileContent({
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
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: controller,
      builder: (context, child) {
        return SafeContractsAdaptiveBody(
          child: ListView(
            padding: const EdgeInsets.only(bottom: 28),
            children: [
              TweenAnimationBuilder<double>(
                tween: Tween<double>(begin: 0, end: 1),
                duration: const Duration(milliseconds: 420),
                curve: Curves.easeOutCubic,
                builder: (context, value, child) {
                  return Opacity(
                    opacity: value,
                    child: Transform.translate(
                      offset: Offset(0, 16 * (1 - value)),
                      child: child,
                    ),
                  );
                },
                child: ProfileHero(session: session),
              ),
              const SizedBox(height: 24),
              const ProfileSectionTitle(
                englishTitle: 'Preferences',
                arabicTitle: 'التفضيلات',
                englishSubtitle: 'Tune the app to the way you work.',
                arabicSubtitle: 'اضبط التطبيق بالطريقة الأنسب لاستخدامك.',
              ),
              const SizedBox(height: 12),
              ProfilePreferences(
                config: config,
                languageCode: languageCode,
                onLanguageChanged: onLanguageChanged,
              ),
              const SizedBox(height: 24),
              const ProfileSectionTitle(
                englishTitle: 'Account & support',
                arabicTitle: 'الحساب والدعم',
                englishSubtitle: 'Your identity and mobile configuration.',
                arabicSubtitle: 'بيانات هويتك وإعدادات تطبيق الموبايل.',
              ),
              const SizedBox(height: 12),
              ProfileAccount(session: session, config: config),
              const SizedBox(height: 24),
              const ProfileSectionTitle(
                englishTitle: 'Notifications & devices',
                arabicTitle: 'الإشعارات والأجهزة',
                englishSubtitle: 'Keep this device connected to SafeContracts.',
                arabicSubtitle: 'تابع اتصال هذا الجهاز والأجهزة المسجلة.',
              ),
              const SizedBox(height: 12),
              ProfilePushSection(
                config: config,
                controller: controller,
                pushRegistration: pushRegistration,
              ),
              const SizedBox(height: 12),
              SafeContractsSurface(
                child: ProfileDevicesSection(controller: controller),
              ),
              const SizedBox(height: 24),
              ProfileLocalSession(onClearSession: onClearSession),
            ],
          ),
        );
      },
    );
  }
}
