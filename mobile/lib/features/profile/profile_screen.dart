import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../notifications/push_registration.dart';
import '../session/session_controller.dart';
import '../ui/mobile_layout.dart';
import '../ui/mobile_states.dart';
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

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        return SafeContractsAdaptiveBody(
          child: ListView(
            children: [
              Text(
                l10n.t('Language'),
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                key: ValueKey(widget.languageCode),
                initialValue: widget.languageCode,
                decoration: InputDecoration(
                  labelText: l10n.t('Language'),
                  border: const OutlineInputBorder(),
                ),
                items: <DropdownMenuItem<String>>[
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
                  if (value != null) widget.onLanguageChanged(value);
                },
              ),
              const SizedBox(height: 24),
              Text(
                l10n.t('Currency'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              Card(
                child: ListTile(
                  leading: const Icon(Icons.currency_exchange),
                  title: Text(
                    '${l10n.t('Currency code')}: '
                    '${widget.config.currency.code.isEmpty ? l10n.t('Not configured') : widget.config.currency.code}',
                  ),
                  subtitle: Text(
                    '${l10n.t('Currency symbol')}: '
                    '${widget.config.currency.symbol.isEmpty ? l10n.t('Not configured') : widget.config.currency.symbol}',
                  ),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                l10n.t('Session'),
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: 12),
              Text('${l10n.t('User ID')}: ${widget.session.userId}'),
              Text('${l10n.t('Data scope')}: ${widget.session.scope.name}'),
              Text(
                '${l10n.t('Default page size')}: ${widget.config.defaultPageSize}',
              ),
              if (widget.config.supportText.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(widget.config.supportText),
              ],
              const SizedBox(height: 24),
              Text(
                l10n.t('Push registration'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              _pushSection(context),
              const SizedBox(height: 24),
              Text(
                l10n.t('Granted capabilities'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              ..._grantedCapabilities(widget.session).map(
                (capability) => Padding(
                  padding: const EdgeInsets.symmetric(vertical: 2),
                  child: Text('• $capability'),
                ),
              ),
              const SizedBox(height: 24),
              Text(
                l10n.t('Registered devices'),
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              _deviceSection(context),
              const SizedBox(height: 24),
              FilledButton.tonal(
                onPressed: widget.onClearSession,
                child: Text(l10n.t('Clear local session state')),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _pushSection(BuildContext context) {
    final l10n = context.scL10n;
    if (!widget.config.features.pushNotifications) {
      return Card(
        child: ListTile(
          leading: const Icon(Icons.notifications_off_outlined),
          title: Text(
            l10n.t(
              'Push notifications are disabled by mobile configuration.',
            ),
          ),
          subtitle: Text(
            l10n.t(
              'Enable Push notifications in SafeContracts → Mobile Configuration.',
            ),
          ),
        ),
      );
    }

    return ValueListenableBuilder<MobilePushRegistrationSnapshot>(
      valueListenable: widget.pushRegistration.status,
      builder: (context, status, child) {
        final registered = status.backendRegistered;
        return Card(
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(
                      registered
                          ? Icons.notifications_active_outlined
                          : Icons.sync_problem_outlined,
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: Text(
                        l10n.t(
                          registered
                              ? 'Device registered with SafeContracts'
                              : 'Device registration is not complete',
                        ),
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  '${l10n.t('Notification permission')}: '
                  '${l10n.t(_permissionLabel(status.permission))}',
                ),
                Text(
                  '${l10n.t('FCM token acquired')}: '
                  '${l10n.yesNo(status.tokenAcquired)}',
                ),
                Text(
                  '${l10n.t('Backend registration')}: '
                  '${l10n.t(_backendLabel(status.backendState))}',
                ),
                if (status.errorCode != null)
                  Text('${l10n.t('Diagnostic code')}: ${status.errorCode}'),
                if (status.permission == MobilePushPermissionState.denied) ...[
                  const SizedBox(height: 8),
                  Text(
                    l10n.t(
                      'Android notification permission is denied. The device can still register, but notification display remains blocked until permission is enabled.',
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                FilledButton.tonalIcon(
                  onPressed: status.backendState ==
                          MobilePushBackendState.registering
                      ? null
                      : () => unawaited(_retryPushRegistration()),
                  icon: const Icon(Icons.refresh),
                  label: Text(l10n.t('Retry device registration')),
                ),
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _retryPushRegistration() async {
    await widget.pushRegistration.refreshTokenAndRetry();
    if (widget.pushRegistration.status.value.backendRegistered) {
      await widget.controller.load();
    }
  }

  Widget _deviceSection(BuildContext context) {
    final l10n = context.scL10n;
    final controller = widget.controller;
    if (controller.state == ProfileDeviceLoadState.loading &&
        controller.snapshot == null) {
      return SizedBox(
        height: 120,
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
      return Text(l10n.t('No registered devices are currently visible.'));
    }
    return Column(
      children: devices
          .map(
            (device) => ListTile(
              contentPadding: EdgeInsets.zero,
              leading: Icon(_deviceIcon(device.platform)),
              title: Text(device.platform.toUpperCase()),
              subtitle: Text(
                device.lastSeenAt == null
                    ? l10n.t('No last-seen timestamp')
                    : '${l10n.t('Last seen')}: ${device.lastSeenAt}',
              ),
              trailing: Text(
                l10n.status(device.isActive ? 'active' : 'inactive'),
              ),
            ),
          )
          .toList(growable: false),
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

List<String> _grantedCapabilities(SafeContractsSession session) {
  final values = session.capabilities.entries
      .where((entry) => entry.value)
      .map((entry) => entry.key)
      .toList(growable: false)
    ..sort();
  return values;
}

IconData _deviceIcon(String platform) {
  return switch (platform) {
    'android' => Icons.android,
    'ios' => Icons.phone_iphone,
    'web' => Icons.language,
    _ => Icons.devices_other,
  };
}
