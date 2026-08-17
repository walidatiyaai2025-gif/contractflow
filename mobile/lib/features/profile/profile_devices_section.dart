import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../ui/mobile_states.dart';
import '../ui/safecontracts_design.dart';
import 'profile.dart';
import 'profile_identity_sections.dart';

final class ProfileDevicesSection extends StatelessWidget {
  const ProfileDevicesSection({required this.controller, super.key});

  final ProfileController controller;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (controller.state == ProfileDeviceLoadState.loading &&
        controller.snapshot == null) {
      return SizedBox(
        height: 90,
        child: SafeContractsStateView(
          kind: MobileStateKind.loading,
          message: l10n.t('Loading device state…'),
        ),
      );
    }
    if (controller.state == ProfileDeviceLoadState.error) {
      return SafeContractsStateView(
        kind: MobileStateKind.error,
        message: l10n.rawMessage(
          controller.errorMessage ?? 'Device state is unavailable.',
        ),
        onRetry: () => unawaited(controller.load()),
      );
    }
    final devices =
        controller.snapshot?.devices ?? const <SafeContractsDevice>[];
    if (devices.isEmpty) {
      return Text(
        profileCopy(
          context,
          'No registered devices are currently visible.',
          'لا توجد أجهزة مسجلة ظاهرة حاليًا.',
        ),
        style: Theme.of(context).textTheme.bodyMedium
            ?.copyWith(color: SafeContractsVisual.muted),
      );
    }
    return Column(
      children: devices
          .map(
            (device) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading: ProfileTileIcon(_deviceIcon(device.platform)),
              title: Text(
                device.platform.toUpperCase(),
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
              subtitle: Text(
                device.lastSeenAt == null
                    ? l10n.t('No last-seen timestamp')
                    : '${l10n.t('Last seen')}: ${device.lastSeenAt}',
              ),
              trailing: ProfilePill(
                icon: Icons.circle,
                text: l10n.status(device.isActive ? 'active' : 'inactive'),
                background: device.isActive
                    ? SafeContractsVisual.greenSoft
                    : SafeContractsVisual.redSoft,
                foreground: device.isActive
                    ? SafeContractsVisual.green
                    : SafeContractsVisual.red,
              ),
            ),
          )
          .toList(growable: false),
    );
  }
}

IconData _deviceIcon(String platform) {
  return switch (platform) {
    'android' => Icons.android,
    'ios' => Icons.phone_iphone,
    'web' => Icons.language,
    _ => Icons.devices_other,
  };
}
