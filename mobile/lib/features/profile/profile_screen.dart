import 'dart:async';

import 'package:flutter/material.dart';

import '../config/mobile_config.dart';
import '../session/session_controller.dart';
import '../ui/mobile_layout.dart';
import '../ui/mobile_states.dart';
import 'profile.dart';

final class ProfileScreen extends StatefulWidget {
  const ProfileScreen({
    required this.session,
    required this.config,
    required this.controller,
    required this.onClearSession,
    super.key,
  });

  final SafeContractsSession session;
  final SafeContractsMobileConfig config;
  final ProfileController controller;
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
