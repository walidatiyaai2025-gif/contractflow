import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_transport.dart';

enum MobileFailureKind {
  unauthorized,
  forbidden,
  validation,
  network,
  invalidPayload,
  server,
}

MobileFailureKind classifyMobileFailure(Object error) {
  if (error is SafeContractsApiException) {
    if (error.statusCode == 401) {
      return MobileFailureKind.unauthorized;
    }
    if (error.statusCode == 403) {
      return MobileFailureKind.forbidden;
    }
    if (error.statusCode == 400 || error.statusCode == 422) {
      return MobileFailureKind.validation;
    }
    return MobileFailureKind.server;
  }
  if (error is SafeContractsTransportException) {
    return MobileFailureKind.network;
  }
  if (error is FormatException || error is ArgumentError) {
    return MobileFailureKind.invalidPayload;
  }
  return MobileFailureKind.server;
}

enum MobileStateKind { loading, empty, error, offline, forbidden }

bool mobileStateAllowsRetry(MobileStateKind kind) {
  return kind != MobileStateKind.loading && kind != MobileStateKind.forbidden;
}

final class SafeContractsStateView extends StatelessWidget {
  const SafeContractsStateView({
    required this.kind,
    required this.message,
    this.onRetry,
    super.key,
  });

  final MobileStateKind kind;
  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    if (kind == MobileStateKind.loading) {
      return Semantics(
        container: true,
        liveRegion: true,
        label: message,
        child: const Center(
          child: CircularProgressIndicator(semanticsLabel: 'Loading'),
        ),
      );
    }

    final icon = switch (kind) {
      MobileStateKind.empty => Icons.inbox_outlined,
      MobileStateKind.offline => Icons.cloud_off_outlined,
      MobileStateKind.forbidden => Icons.lock_outline,
      MobileStateKind.error => Icons.error_outline,
      MobileStateKind.loading => Icons.hourglass_empty,
    };
    final retry = mobileStateAllowsRetry(kind) ? onRetry : null;
    final isUrgent = kind == MobileStateKind.error ||
        kind == MobileStateKind.offline ||
        kind == MobileStateKind.forbidden;

    return Semantics(
      container: true,
      liveRegion: isUrgent,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ExcludeSemantics(child: Icon(icon, size: 44)),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              if (retry != null) ...[
                const SizedBox(height: 16),
                FilledButton.tonal(
                  onPressed: retry,
                  child: const Text('Retry'),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
