import 'package:flutter/material.dart';

import '../../core/api/mobile_failure.dart';
import '../../core/ui/async_states.dart';
import '../../core/ui/responsive.dart';
import '../config/mobile_config.dart';
import '../notifications/mobile_notifications.dart';
import '../session/session_controller.dart';

enum DeviceStatusState { idle, loading, ready, error }

final class ProfileDeviceController extends ChangeNotifier {
  ProfileDeviceController(this.repository);

  final MobileNotificationsRepository repository;

  DeviceStatusState state = DeviceStatusState.idle;
  DeviceStatus status = const DeviceStatus.empty();
  MobileFailure? failure;

  Future<void> load() async {
    state = DeviceStatusState.loading;
    failure = null;
    notifyListeners();
    try {
      status = await repository.deviceStatus();
      state = DeviceStatusState.ready;
    } on Object catch (error) {
      status = const DeviceStatus.empty();
      failure = MobileFailure.from(error);
      state = DeviceStatusState.error;
    }
    notifyListeners();
  }
}

final class ProfileScreen extends StatelessWidget {
  const ProfileScreen({
    required this.session,
    required this.config,
    required this.deviceController,
    required this.onClearSession,
    super.key,
  });

  final SafeContractsSession session;
  final SafeContractsMobileConfig config;
  final ProfileDeviceController deviceController;
  final VoidCallback onClearSession;

  @override
  Widget build(BuildContext context) {
    return SafeResponsiveBody(
      child: AnimatedBuilder(
        animation: deviceController,
        builder: (context, child) {
          final granted = session.capabilities.values.where((value) => value).length;
          return ListView(
            padding: const EdgeInsets.symmetric(vertical: 16),
            children: [
              Text('Profile', style: Theme.of(context).textTheme.headlineSmall),
              const SizedBox(height: 16),
              SafeResponsiveColumns(
                children: [
                  _InfoCard(
                    title: 'Session',
                    rows: <String, String>{
                      'User ID': session.userId.toString(),
                      'Data scope': session.scope.name,
                      'Granted capabilities': granted.toString(),
                      'Default page size': config.defaultPageSize.toString(),
                    },
                  ),
                  _DeviceCard(
                    controller: deviceController,
                    pushEnabled: config.features.pushNotifications,
                  ),
                ],
              ),
              if (config.supportText.isNotEmpty) ...[
                const SizedBox(height: 12),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(16),
                    child: Text(config.supportText),
                  ),
                ),
              ],
              const SizedBox(height: 20),
              FilledButton.tonalIcon(
                onPressed: onClearSession,
                icon: const Icon(Icons.logout_outlined),
                label: const Text('Clear local session state'),
              ),
            ],
          );
        },
      ),
    );
  }
}

final class _InfoCard extends StatelessWidget {
  const _InfoCard({required this.title, required this.rows});

  final String title;
  final Map<String, String> rows;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(title, style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            ...rows.entries.map(
              (entry) => Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Text('${entry.key}: ${entry.value}'),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _DeviceCard extends StatelessWidget {
  const _DeviceCard({required this.controller, required this.pushEnabled});

  final ProfileDeviceController controller;
  final bool pushEnabled;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text('Device', style: Theme.of(context).textTheme.titleMedium),
            const SizedBox(height: 12),
            Text('Push feature: ${pushEnabled ? 'Enabled' : 'Disabled'}'),
            if (controller.state == DeviceStatusState.loading)
              const Padding(
                padding: EdgeInsets.only(top: 12),
                child: LinearProgressIndicator(),
              )
            else if (controller.state == DeviceStatusState.error &&
                controller.failure != null)
              SafeErrorState(
                failure: controller.failure!,
                onRetry: controller.load,
                compact: true,
              )
            else ...[
              Text(
                'Active registered devices: ${controller.status.activeDeviceCount}',
              ),
              Text(
                'Platforms: ${controller.status.platforms.isEmpty ? 'None' : controller.status.platforms.join(', ')}',
              ),
            ],
          ],
        ),
      ),
    );
  }
}
