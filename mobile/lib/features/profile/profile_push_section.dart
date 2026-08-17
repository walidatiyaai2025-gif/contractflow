import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../notifications/push_registration.dart';
import '../ui/safecontracts_design.dart';
import 'profile.dart';
import 'profile_identity_sections.dart';

final class ProfilePushSection extends StatelessWidget {
  const ProfilePushSection({
    required this.config,
    required this.controller,
    required this.pushRegistration,
    super.key,
  });

  final SafeContractsMobileConfig config;
  final ProfileController controller;
  final MobilePushRegistration pushRegistration;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (!config.features.pushNotifications) {
      return SafeContractsSurface(
        child: const _PushMessage(
          icon: Icons.notifications_off_outlined,
          englishTitle: 'Push notifications are disabled.',
          arabicTitle: 'الإشعارات غير مفعلة حاليًا.',
          englishSubtitle: 'Enable them from the mobile configuration.',
          arabicSubtitle: 'يمكن تفعيلها من إعدادات تطبيق الموبايل.',
        ),
      );
    }

    return ValueListenableBuilder<MobilePushRegistrationSnapshot>(
      valueListenable: pushRegistration.status,
      builder: (context, status, child) {
        final registered = status.backendRegistered;
        return AnimatedSwitcher(
          duration: const Duration(milliseconds: 240),
          child: SafeContractsSurface(
            key: ValueKey('${status.backendState}-${status.permission}'),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                _PushMessage(
                  icon: registered
                      ? Icons.notifications_active_rounded
                      : Icons.sync_problem_rounded,
                  englishTitle: registered
                      ? 'Device registered with SafeContracts'
                      : 'Device registration is not complete',
                  arabicTitle: registered
                      ? 'الجهاز متصل بـ SafeContracts'
                      : 'تسجيل الجهاز غير مكتمل',
                  englishSubtitle: registered
                      ? 'Push delivery is connected for this device.'
                      : 'Review the status or retry registration.',
                  arabicSubtitle: registered
                      ? 'استقبال الإشعارات متصل على هذا الجهاز.'
                      : 'راجع الحالة أو أعد محاولة التسجيل.',
                  healthy: registered,
                ),
                const SizedBox(height: 14),
                _KeyValue(
                  label: l10n.t('Notification permission'),
                  value: l10n.t(_permissionLabel(status.permission)),
                ),
                const SizedBox(height: 6),
                _KeyValue(
                  label: l10n.t('FCM token acquired'),
                  value: l10n.yesNo(status.tokenAcquired),
                ),
                const SizedBox(height: 6),
                _KeyValue(
                  label: l10n.t('Backend registration'),
                  value: l10n.t(_backendLabel(status.backendState)),
                ),
                if (status.errorCode != null) ...[
                  const SizedBox(height: 6),
                  _KeyValue(
                    label: l10n.t('Diagnostic code'),
                    value: status.errorCode!,
                  ),
                ],
                const SizedBox(height: 14),
                FilledButton.tonalIcon(
                  onPressed:
                      status.backendState == MobilePushBackendState.registering
                          ? null
                          : () => unawaited(_retry()),
                  icon: const Icon(Icons.refresh_rounded),
                  label: Text(l10n.t('Retry device registration')),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _retry() async {
    await pushRegistration.refreshTokenAndRetry();
    if (pushRegistration.status.value.backendRegistered) {
      await controller.load();
    }
  }
}

final class _PushMessage extends StatelessWidget {
  const _PushMessage({
    required this.icon,
    required this.englishTitle,
    required this.arabicTitle,
    this.englishSubtitle,
    this.arabicSubtitle,
    this.healthy = false,
  });

  final IconData icon;
  final String englishTitle;
  final String arabicTitle;
  final String? englishSubtitle;
  final String? arabicSubtitle;
  final bool healthy;

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        ProfileTileIcon(
          icon,
          background: healthy
              ? SafeContractsVisual.greenSoft
              : SafeContractsVisual.amberSoft,
          foreground:
              healthy ? SafeContractsVisual.green : SafeContractsVisual.amber,
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                profileCopy(context, englishTitle, arabicTitle),
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
              ),
              if (englishSubtitle != null && arabicSubtitle != null) ...[
                const SizedBox(height: 3),
                Text(
                  profileCopy(context, englishSubtitle!, arabicSubtitle!),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                ),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

final class _KeyValue extends StatelessWidget {
  const _KeyValue({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
        ),
        const SizedBox(width: 12),
        Text(
          value,
          style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: SafeContractsVisual.ink,
                fontWeight: FontWeight.w800,
              ),
        ),
      ],
    );
  }
}

String _permissionLabel(MobilePushPermissionState permission) {
  return switch (permission) {
    MobilePushPermissionState.authorized => 'Allowed',
    MobilePushPermissionState.provisional => 'Provisional',
    MobilePushPermissionState.denied => 'Denied',
    MobilePushPermissionState.unknown => 'Unknown',
  };
}

String _backendLabel(MobilePushBackendState state) {
  return switch (state) {
    MobilePushBackendState.idle => 'Not started',
    MobilePushBackendState.registering => 'Registering…',
    MobilePushBackendState.registered => 'Registered',
    MobilePushBackendState.error => 'Failed',
  };
}
