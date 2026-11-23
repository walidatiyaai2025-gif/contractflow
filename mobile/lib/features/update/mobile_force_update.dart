import 'dart:async';

import 'package:flutter/material.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../core/api/api_client.dart';

const String alkenzyPlayPackage = 'com.safecontracts.safecontracts_mobile';
const String alkenzyPlayUrl =
    'https://play.google.com/store/apps/details?id=$alkenzyPlayPackage';

/// Only versionCode/buildNumber is compared. Version names are display-only.
final class AndroidUpdatePolicy {
  const AndroidUpdatePolicy({
    required this.enabled,
    required this.minBuild,
    required this.latestBuild,
    required this.messageAr,
    required this.messageEn,
  });

  final bool enabled;
  final int minBuild;
  final int latestBuild;
  final String messageAr;
  final String messageEn;

  bool blocks(int installedBuild) =>
      enabled &&
      minBuild > 0 &&
      latestBuild >= minBuild &&
      installedBuild > 0 &&
      installedBuild < minBuild;

  factory AndroidUpdatePolicy.fromData(Object? value) {
    final data = apiObjectMap(value, 'mobile-version');
    if (data['schema_version'] != 1 || data['platform'] != 'android') {
      throw const FormatException('Unsupported mobile update policy schema');
    }
    int build(Object? v) {
      if (v is! int || v < 0 || v > 1000000000) {
        throw const FormatException('Invalid Android update build number');
      }
      return v;
    }
    String message(Object? v) {
      if (v is! String || v.length > 300) {
        throw const FormatException('Invalid update message');
      }
      return v;
    }
    if (data['enabled'] is! bool) {
      throw const FormatException('Invalid update policy switch');
    }
    return AndroidUpdatePolicy(
      enabled: data['enabled'] as bool,
      minBuild: build(data['min_build']),
      latestBuild: build(data['latest_build']),
      messageAr: message(data['message_ar']),
      messageEn: message(data['message_en']),
    );
  }
}

enum MobileUpdateState { checking, allowed, required }

/// Public unauthenticated endpoint so the gate works before sign-in.
/// A malformed, unavailable or old WordPress endpoint must not lock out users.
final class MobileForceUpdateController extends ChangeNotifier {
  MobileForceUpdateController({
    required this.fetchPolicy,
    required this.installedBuild,
  });

  factory MobileForceUpdateController.forClient(SafeContractsApiClient client) =>
      MobileForceUpdateController(
        fetchPolicy: () async {
          final response = await client.get('mobile-version');
          return AndroidUpdatePolicy.fromData(response.data);
        },
        installedBuild: () async {
          final info = await PackageInfo.fromPlatform();
          return int.tryParse(info.buildNumber) ?? 0;
        },
      );

  final Future<AndroidUpdatePolicy> Function() fetchPolicy;
  final Future<int> Function() installedBuild;
  MobileUpdateState state = MobileUpdateState.checking;
  AndroidUpdatePolicy? policy;
  int? currentBuild;
  bool _pending = false;
  String? error;

  void allowNonProduction() {
    state = MobileUpdateState.allowed;
    notifyListeners();
  }

  Future<void> refresh() async {
    if (_pending) return;
    _pending = true;
    if (state != MobileUpdateState.required) {
      state = MobileUpdateState.checking;
      notifyListeners();
    }
    try {
      final results = await Future.wait<Object>([
        installedBuild(),
        fetchPolicy(),
      ]).timeout(const Duration(seconds: 7));
      final build = results[0] as int;
      final received = results[1] as AndroidUpdatePolicy;
      currentBuild = build;
      policy = received;
      state = received.blocks(build)
          ? MobileUpdateState.required
          : MobileUpdateState.allowed;
      error = null;
    } on Object catch (e) {
      // If a previously confirmed mandatory update exists, keep the gate on
      // during outages. Otherwise fail open to preserve login availability.
      error = e.toString();
      if (state != MobileUpdateState.required) {
        state = MobileUpdateState.allowed;
      }
    } finally {
      _pending = false;
      notifyListeners();
    }
  }

  Future<void> openGooglePlay() async {
    final marketUri = Uri.parse('market://details?id=$alkenzyPlayPackage');
    try {
      if (await launchUrl(marketUri, mode: LaunchMode.externalApplication)) {
        return;
      }
    } on Object {
      // Devices without Play Store can use the HTTPS listing.
    }
    try {
      await launchUrl(
        Uri.parse(alkenzyPlayUrl),
        mode: LaunchMode.externalApplication,
      );
    } on Object {
      // The blocking surface remains available with Retry.
    }
  }
}

/// Blocking full-screen guard above Navigator; no root remount on ad callbacks.
/// Checks at app startup, when returning from Google Play and after resumes.
class MobileForceUpdateGate extends StatefulWidget {
  const MobileForceUpdateGate({
    required this.controller,
    required this.languageCode,
    required this.child,
    super.key,
  });

  final MobileForceUpdateController controller;
  final String languageCode;
  final Widget child;

  @override
  State<MobileForceUpdateGate> createState() => _MobileForceUpdateGateState();
}

class _MobileForceUpdateGateState extends State<MobileForceUpdateGate>
    with WidgetsBindingObserver {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    if (state == AppLifecycleState.resumed) {
      unawaited(widget.controller.refresh());
    }
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return ListenableBuilder(
      listenable: widget.controller,
      child: widget.child,
      builder: (context, child) {
        final state = widget.controller.state;
        if (state == MobileUpdateState.allowed) {
          return child ?? const SizedBox.shrink();
        }
        final ar = widget.languageCode.toLowerCase().startsWith('ar');
        final blocked = state == MobileUpdateState.required;
        final message = ar
            ? widget.controller.policy?.messageAr
            : widget.controller.policy?.messageEn;
        return Stack(
          children: [
            // Preserve the exact Navigator identity underneath the overlay.
            Offstage(offstage: true, child: child ?? const SizedBox.shrink()),
            Positioned.fill(
              child: Material(
                color: const Color(0xFFF4F7FA),
                child: SafeArea(
                  child: Center(
                    child: ConstrainedBox(
                      constraints: const BoxConstraints(maxWidth: 440),
                      child: SingleChildScrollView(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Icon(
                              blocked ? Icons.system_update_alt : Icons.security,
                              color: const Color(0xFF126E5E),
                              size: 60,
                            ),
                            const SizedBox(height: 24),
                            Text(
                              blocked
                                  ? (ar ? 'تحديث التطبيق مطلوب' : 'App update required')
                                  : (ar ? 'جاري التحقق من الإصدار' : 'Checking app version'),
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                                fontSize: 23,
                                color: Color(0xFF152638),
                              ),
                            ),
                            const SizedBox(height: 12),
                            Text(
                              blocked
                                  ? ((message != null && message.trim().isNotEmpty)
                                      ? message
                                      : (ar
                                          ? 'للاستمرار، حدّث Alkenzy ADV إلى أحدث إصدار من Google Play.'
                                          : 'Update Alkenzy ADV from Google Play to continue.'))
                                  : (ar
                                      ? 'نتحقق من متطلبات التحديث الآمن.'
                                      : 'Checking secure update requirements.'),
                              textAlign: TextAlign.center,
                              style: const TextStyle(fontSize: 15),
                            ),
                            const SizedBox(height: 24),
                            if (blocked) ...[
                              SizedBox(
                                width: double.infinity,
                                child: FilledButton.icon(
                                  onPressed: () =>
                                      unawaited(widget.controller.openGooglePlay()),
                                  icon: const Icon(Icons.shop_rounded),
                                  label: Text(ar ? 'التحديث من Google Play' : 'Update on Google Play'),
                                ),
                              ),
                              const SizedBox(height: 8),
                              TextButton(
                                onPressed: () =>
                                    unawaited(widget.controller.refresh()),
                                child: Text(ar ? 'التحقق بعد التحديث' : 'Check again after updating'),
                              ),
                            ] else
                              const CircularProgressIndicator(),
                          ],
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        );
      },
    );
  }
}
