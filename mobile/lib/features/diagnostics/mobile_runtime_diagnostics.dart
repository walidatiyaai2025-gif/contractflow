import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

final class MobileRuntimeDiagnosticEvent {
  const MobileRuntimeDiagnosticEvent({
    required this.occurredAtUtc,
    required this.operation,
    required this.stage,
    required this.message,
    required this.context,
  });

  final String occurredAtUtc;
  final String operation;
  final String stage;
  final String message;
  final Map<String, Object?> context;

  Map<String, Object?> toJson() => <String, Object?>{
        'occurred_at_utc': occurredAtUtc,
        'operation': operation,
        'stage': stage,
        'message': message,
        'context': context,
      };
}

final class MobileRuntimeDiagnostics {
  MobileRuntimeDiagnostics._();

  static const int maxEvents = 30;
  static final List<MobileRuntimeDiagnosticEvent> _events =
      <MobileRuntimeDiagnosticEvent>[];

  static List<MobileRuntimeDiagnosticEvent> get events =>
      List<MobileRuntimeDiagnosticEvent>.unmodifiable(_events);

  static void record({
    required String operation,
    required String stage,
    required Object error,
    Map<String, Object?> context = const <String, Object?>{},
  }) {
    final cleanContext = <String, Object?>{};
    for (final entry in context.entries.take(20)) {
      final key = _bounded(entry.key, 80);
      if (key.isEmpty || _sensitive(key)) continue;
      final value = entry.value;
      cleanContext[key] = value is num || value is bool || value == null
          ? value
          : _bounded(value.toString(), 500);
    }
    _events.insert(
      0,
      MobileRuntimeDiagnosticEvent(
        occurredAtUtc: DateTime.now().toUtc().toIso8601String(),
        operation: _bounded(operation, 120),
        stage: _bounded(stage, 120),
        message: _bounded(error.toString(), 1200),
        context: Map<String, Object?>.unmodifiable(cleanContext),
      ),
    );
    if (_events.length > maxEvents) {
      _events.removeRange(maxEvents, _events.length);
    }
  }

  static void clear() => _events.clear();

  static String exportJson() => const JsonEncoder.withIndent('  ').convert(
        <String, Object?>{
          'source': 'alkenzy-mobile',
          'generated_at_utc': DateTime.now().toUtc().toIso8601String(),
          'events': _events.map((event) => event.toJson()).toList(growable: false),
        },
      );

  static bool _sensitive(String key) {
    final value = key.toLowerCase();
    return value.contains('token') ||
        value.contains('password') ||
        value.contains('authorization') ||
        value.contains('cookie') ||
        value.contains('nonce') ||
        value.contains('secret');
  }

  static String _bounded(String value, int max) {
    final normalized = value.trim();
    if (normalized.length <= max) return normalized;
    return '${normalized.substring(0, max)}…';
  }
}

final class MobileDiagnosticsCard extends StatelessWidget {
  const MobileDiagnosticsCard({required this.isArabic, super.key});

  final bool isArabic;

  @override
  Widget build(BuildContext context) {
    final events = MobileRuntimeDiagnostics.events;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.phone_android_rounded),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    isArabic ? 'أخطاء تطبيق الموبايل' : 'Mobile runtime failures',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                ),
                IconButton(
                  tooltip: isArabic ? 'نسخ' : 'Copy',
                  onPressed: events.isEmpty
                      ? null
                      : () async {
                          await Clipboard.setData(
                            ClipboardData(text: MobileRuntimeDiagnostics.exportJson()),
                          );
                          if (!context.mounted) return;
                          ScaffoldMessenger.of(context).showSnackBar(
                            SnackBar(
                              content: Text(
                                isArabic
                                    ? 'تم نسخ أخطاء الموبايل.'
                                    : 'Mobile diagnostics copied.',
                              ),
                            ),
                          );
                        },
                  icon: const Icon(Icons.copy_all_outlined),
                ),
              ],
            ),
            const SizedBox(height: 8),
            if (events.isEmpty)
              Text(
                isArabic
                    ? 'لا توجد أخطاء محلية مسجلة في هذه الجلسة.'
                    : 'No local mobile failures recorded in this session.',
              )
            else
              for (final event in events)
                ExpansionTile(
                  tilePadding: EdgeInsets.zero,
                  childrenPadding: const EdgeInsets.only(bottom: 10),
                  title: Text(
                    event.operation,
                    style: const TextStyle(fontWeight: FontWeight.w800),
                  ),
                  subtitle: Text('${event.stage} • ${event.occurredAtUtc}'),
                  children: [
                    Align(
                      alignment: AlignmentDirectional.centerStart,
                      child: SelectableText(event.message),
                    ),
                    if (event.context.isNotEmpty) ...[
                      const SizedBox(height: 8),
                      Align(
                        alignment: AlignmentDirectional.centerStart,
                        child: SelectableText(
                          const JsonEncoder.withIndent('  ').convert(event.context),
                        ),
                      ),
                    ],
                  ],
                ),
          ],
        ),
      ),
    );
  }
}
