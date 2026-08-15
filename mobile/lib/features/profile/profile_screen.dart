import 'dart:async';

import 'package:flutter/material.dart';

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
    required this.onClearSession,
    super.key,
  });

  final SafeContractsSession session;
  final SafeContractsMobileConfig config;
  final ProfileController controller;
  final MobilePushRegistration pushRegistration;
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
    return AnimatedBuilder(
      animation: widget.controller,
      builder: (context, child) {
        return SafeContractsAdaptiveBody(
          child: ListView(
            children: [
              Text('Session', style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 12),
              Text('User ID: ${widget.session.userId}'),
              Text('Data scope: ${widget.session.scope.name}'),
              Text('Page size: ${widget.config.defaultPageSize}'),
              if (widget.config.supportText.isNotEmpty) ...[
                const SizedBox(height: 12),
                Text(widget.config.supportText),
              ],
              const SizedBox(height: 24),
              Text(
                'Push registration',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              _pushSection(),
              const SizedBox(height: 24),
              Text(
                'Granted capabilities',
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
                'Registered devices',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: 8),
              _deviceSection(),
              const SizedBox(height: 24),
              FilledButton.tonal(
                onPressed: widget.onClearSession,
                child: const Text('Clear local session state'),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _pushSection() {
    if (!widget.config.features.pushNotifications) {
      return const Card(
        child: ListTile(
          leading: Icon(Icons.notifications_off_outlined),
          title: Text('Push notifications are disabled by mobile configuration.'),
          subtitle: Text(
            'Enable Push notifications in SafeContracts → Mobile Configuration.',
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
                        registered
                            ? 'Device registered with SafeContracts'
                            : 'Device registration is not complete',
                        style: Theme.of(context).textTheme.titleSmall,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text('Notification permission: ${_permissionLabel(status.permission)}'),
                Text('FCM token acquired: ${status.tokenAcquired ? 'Yes' : 'No'}'),
                Text('Backend registration: ${_backendLabel(status.backendState)}'),
                if (status.errorCode != null)
                  Text('Diagnostic code: ${status.errorCode}'),
                if (status.permission == MobilePushPermissionState.denied) ...[
                  const SizedBox(height: 8),
                  const Text(
                    'Android notification permission is denied. The device can still register, but notification display remains blocked until permission is enabled.',
                  ),
                ],
                if (!registered) ...[
                  const SizedBox(height: 12),
                  FilledButton.tonalIcon(
                    onPressed: status.backendState ==
                            MobilePushBackendState.registering
                        ? null
                        : () => unawaited(_retryPushRegistration()),
                    icon: const Icon(Icons.refresh),
                    label: const Text('Retry device registration'),
                  ),
                ],
              ],
            ),
          ),
        );
      },
    );
  }

  Future<void> _retryPushRegistration() async {
    await widget.pushRegistration.retryNow();
    if (widget.pushRegistration.status.value.backendRegistered) {
      await widget.controller.load();
    }
  }

  Widget _deviceSection() {
    final controller = widget.controller;
    if (controller.state == ProfileDeviceLoadState.loading &&
        controller.snapshot == null) {
      return const SizedBox(
        height: 120,
        child: SafeContractsStateView(
          kind: MobileStateKind.loading,
          message: 'Loading device state…',
        ),
      );
    }
    if (controller.state == ProfileDeviceLoadState.error) {
      return SafeContractsStateView(
        kind: MobileStateKind.error,
        message: controller.errorMessage ?? 'Device state is unavailable.',
        onRetry: () => unawaited(controller.load()),
      );
    }
    final devices =
        controller.snapshot?.devices ?? const <SafeContractsDevice>[];
    if (devices.isEmpty) {
      return const Text('No registered devices are currently visible.');
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
                    ? 'No last-seen timestamp'
                    : 'Last seen: ${device.lastSeenAt}',
              ),
              trailing: Text(device.isActive ? 'Active' : 'Inactive'),
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
