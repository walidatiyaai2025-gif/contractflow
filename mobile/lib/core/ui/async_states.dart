import 'dart:async';

import 'package:flutter/material.dart';

import '../api/mobile_failure.dart';

final class SafeLoadingState extends StatelessWidget {
  const SafeLoadingState({this.label = 'Loading…', super.key});

  final String label;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const CircularProgressIndicator(),
            const SizedBox(height: 12),
            Text(label, textAlign: TextAlign.center),
          ],
        ),
      ),
    );
  }
}

final class SafeEmptyState extends StatelessWidget {
  const SafeEmptyState({required this.message, super.key});

  final String message;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Text(message, textAlign: TextAlign.center),
      ),
    );
  }
}

final class SafeErrorState extends StatelessWidget {
  const SafeErrorState({
    required this.failure,
    this.onRetry,
    this.compact = false,
    super.key,
  });

  final MobileFailure failure;
  final FutureOr<void> Function()? onRetry;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final retry = failure.retryable && onRetry != null;
    final content = Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(_icon(failure.kind), size: compact ? 28 : 48),
        SizedBox(height: compact ? 8 : 12),
        Text(failure.message, textAlign: TextAlign.center),
        if (retry) ...[
          const SizedBox(height: 12),
          FilledButton.tonal(
            onPressed: () => unawaited(Future<void>.sync(onRetry!)),
            child: const Text('Retry'),
          ),
        ],
      ],
    );
    return Center(
      child: Padding(
        padding: EdgeInsets.all(compact ? 12 : 24),
        child: content,
      ),
    );
  }
}

IconData _icon(MobileFailureKind kind) {
  return switch (kind) {
    MobileFailureKind.authentication => Icons.login_outlined,
    MobileFailureKind.forbidden => Icons.lock_outline,
    MobileFailureKind.validation => Icons.rule_outlined,
    MobileFailureKind.network => Icons.cloud_off_outlined,
    MobileFailureKind.server => Icons.cloud_sync_outlined,
    MobileFailureKind.invalidResponse => Icons.data_object_outlined,
    MobileFailureKind.unknown => Icons.error_outline,
  };
}
