import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/api/api_client.dart';
import '../session/session_controller.dart';

const _manageSystemCapability = 'safecontracts_manage_system';

final class MobileRuntimeInspectorScreen extends StatefulWidget {
  const MobileRuntimeInspectorScreen({
    required this.client,
    required this.session,
    required this.languageCode,
    super.key,
  });

  final SafeContractsApiClient client;
  final SafeContractsSession session;
  final String languageCode;

  @override
  State<MobileRuntimeInspectorScreen> createState() =>
      _MobileRuntimeInspectorScreenState();
}

final class _MobileRuntimeInspectorScreenState
    extends State<MobileRuntimeInspectorScreen> {
  bool _loading = true;
  String? _error;
  RuntimeInspectorSnapshot? _snapshot;

  bool get _ar => widget.languageCode.trim().toLowerCase() == 'ar';
  bool get _isAdmin => widget.session.can(_manageSystemCapability);

  @override
  void initState() {
    super.initState();
    if (_isAdmin) {
      _load();
    } else {
      _loading = false;
    }
  }

  Future<void> _load() async {
    if (!_isAdmin) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final envelope = await widget.client.get('diagnostics/runtime');
      final snapshot = RuntimeInspectorSnapshot.fromEnvelope(envelope);
      if (!mounted) return;
      setState(() {
        _snapshot = snapshot;
        _loading = false;
      });
    } on SafeContractsApiException catch (error) {
      if (!mounted) return;
      setState(() {
        _error = '${error.code} (${error.statusCode}): ${error.message}';
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  Future<void> _copySnapshot() async {
    final snapshot = _snapshot;
    if (snapshot == null) return;
    await Clipboard.setData(
      ClipboardData(
        text: const JsonEncoder.withIndent('  ').convert(snapshot.raw),
      ),
    );
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(_ar ? 'تم نسخ بيانات التشخيص.' : 'Diagnostics copied.'),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text(_ar ? 'فاحص التشغيل' : 'Runtime Inspector'),
        actions: [
          if (_isAdmin)
            IconButton(
              tooltip: _ar ? 'تحديث' : 'Refresh',
              onPressed: _loading ? null : _load,
              icon: const Icon(Icons.refresh_rounded),
            ),
          if (_snapshot != null)
            IconButton(
              tooltip: _ar ? 'نسخ التشخيص' : 'Copy diagnostics',
              onPressed: _copySnapshot,
              icon: const Icon(Icons.copy_all_outlined),
            ),
        ],
      ),
      body: !_isAdmin
          ? _ForbiddenState(isArabic: _ar)
          : _loading
              ? const Center(child: CircularProgressIndicator())
              : _error != null
                  ? _ErrorState(
                      isArabic: _ar,
                      message: _error!,
                      onRetry: _load,
                    )
                  : _InspectorBody(
                      snapshot: _snapshot!,
                      isArabic: _ar,
                      onRefresh: _load,
                    ),
    );
  }
}

final class RuntimeInspectorSnapshot {
  const RuntimeInspectorSnapshot({
    required this.environment,
    required this.events,
    required this.retentionLimit,
    required this.raw,
  });

  final Map<String, Object?> environment;
  final List<RuntimeInspectorEvent> events;
  final int retentionLimit;
  final Map<String, Object?> raw;

  factory RuntimeInspectorSnapshot.fromEnvelope(ApiEnvelope envelope) {
    final data = apiObjectMap(envelope.data, 'runtime_inspector.data');
    final environment = apiObjectMap(
      data['environment'],
      'runtime_inspector.environment',
    );
    final eventValues = apiObjectList(
      data['events'],
      'runtime_inspector.events',
    );
    if (eventValues.length > 50) {
      throw const FormatException('Runtime diagnostics exceed retention bound.');
    }
    final events = eventValues
        .map(RuntimeInspectorEvent.fromData)
        .toList(growable: false);
    final retentionLimit = _intValue(data['retention_limit'], fallback: 50);
    return RuntimeInspectorSnapshot(
      environment: Map<String, Object?>.unmodifiable(environment),
      events: List<RuntimeInspectorEvent>.unmodifiable(events),
      retentionLimit: retentionLimit.clamp(1, 50).toInt(),
      raw: Map<String, Object?>.unmodifiable(data),
    );
  }
}

final class RuntimeInspectorEvent {
  const RuntimeInspectorEvent({
    required this.id,
    required this.occurredAtUtc,
    required this.operation,
    required this.stage,
    required this.classification,
    required this.rootCause,
    required this.exceptionClass,
    required this.message,
    required this.dbError,
    required this.source,
    required this.context,
    required this.environment,
    required this.raw,
  });

  final String id;
  final String occurredAtUtc;
  final String operation;
  final String stage;
  final String classification;
  final String rootCause;
  final String exceptionClass;
  final String message;
  final String dbError;
  final Map<String, Object?> source;
  final Map<String, Object?> context;
  final Map<String, Object?> environment;
  final Map<String, Object?> raw;

  factory RuntimeInspectorEvent.fromData(Object? value) {
    final data = apiObjectMap(value, 'runtime_inspector.event');
    return RuntimeInspectorEvent(
      id: _text(data['id']),
      occurredAtUtc: _text(data['occurred_at_utc']),
      operation: _text(data['operation']),
      stage: _text(data['stage']),
      classification: _text(data['classification']),
      rootCause: _text(data['root_cause']),
      exceptionClass: _text(data['exception_class']),
      message: _text(data['message']),
      dbError: _text(data['db_error']),
      source: _optionalMap(data['source']),
      context: _optionalMap(data['context']),
      environment: _optionalMap(data['environment']),
      raw: Map<String, Object?>.unmodifiable(data),
    );
  }
}

final class _InspectorBody extends StatelessWidget {
  const _InspectorBody({
    required this.snapshot,
    required this.isArabic,
    required this.onRefresh,
  });

  final RuntimeInspectorSnapshot snapshot;
  final bool isArabic;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    return RefreshIndicator(
      onRefresh: onRefresh,
      child: ListView(
        padding: const EdgeInsets.all(14),
        children: [
          _EnvironmentCard(
            environment: snapshot.environment,
            eventCount: snapshot.events.length,
            isArabic: isArabic,
          ),
          const SizedBox(height: 12),
          if (snapshot.events.isEmpty)
            Padding(
              padding: const EdgeInsets.symmetric(vertical: 56),
              child: Center(
                child: Text(
                  isArabic
                      ? 'لا توجد أخطاء تشغيل مسجلة حتى الآن.'
                      : 'No runtime failures recorded yet.',
                  textAlign: TextAlign.center,
                ),
              ),
            )
          else
            for (final event in snapshot.events) ...[
              _EventCard(event: event, isArabic: isArabic),
              const SizedBox(height: 10),
            ],
        ],
      ),
    );
  }
}

final class _EnvironmentCard extends StatelessWidget {
  const _EnvironmentCard({
    required this.environment,
    required this.eventCount,
    required this.isArabic,
  });

  final Map<String, Object?> environment;
  final int eventCount;
  final bool isArabic;

  @override
  Widget build(BuildContext context) {
    final pluginVersion = _text(environment['plugin_version'], fallback: '—');
    final dbVersion = _text(environment['db_version'], fallback: '—');
    final dbLatest = _text(environment['db_latest'], fallback: '—');
    final phpVersion = _text(environment['php_version'], fallback: '—');
    final wpVersion = _text(environment['wordpress_version'], fallback: '—');
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(14),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Row(
              children: [
                const Icon(Icons.monitor_heart_outlined),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    isArabic ? 'حالة بيئة التشغيل' : 'Runtime environment',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: 10),
            _kv(isArabic ? 'إصدار البلجن' : 'Plugin', pluginVersion),
            _kv(
              isArabic ? 'قاعدة البيانات' : 'Database',
              '$dbVersion / $dbLatest',
            ),
            _kv('PHP', phpVersion),
            _kv('WordPress', wpVersion),
            _kv(
              isArabic ? 'الأخطاء المسجلة' : 'Recorded failures',
              '$eventCount',
            ),
          ],
        ),
      ),
    );
  }

  Widget _kv(String key, String value) => Padding(
        padding: const EdgeInsets.symmetric(vertical: 2),
        child: Row(
          children: [
            Expanded(child: Text(key)),
            const SizedBox(width: 12),
            Flexible(
              child: Text(
                value,
                textAlign: TextAlign.end,
                style: const TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
          ],
        ),
      );
}

final class _EventCard extends StatelessWidget {
  const _EventCard({required this.event, required this.isArabic});

  final RuntimeInspectorEvent event;
  final bool isArabic;

  Future<void> _copy(BuildContext context) async {
    await Clipboard.setData(
      ClipboardData(
        text: const JsonEncoder.withIndent('  ').convert(event.raw),
      ),
    );
    if (!context.mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(isArabic ? 'تم نسخ الخطأ.' : 'Failure copied.'),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final title = event.operation.isNotEmpty ? event.operation : 'runtime.failure';
    final cause = event.rootCause.isNotEmpty ? event.rootCause : event.message;
    return Card(
      child: ExpansionTile(
        leading: const Icon(Icons.error_outline_rounded),
        title: Text(
          title,
          style: const TextStyle(fontWeight: FontWeight.w800),
        ),
        subtitle: Text(
          [event.stage, event.classification, event.occurredAtUtc]
              .where((value) => value.isNotEmpty)
              .join(' • '),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        ),
        childrenPadding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
        children: [
          if (event.id.isNotEmpty)
            _detail('Correlation ID', event.id),
          if (cause.isNotEmpty)
            _detail(isArabic ? 'السبب' : 'Root cause', cause),
          if (event.exceptionClass.isNotEmpty)
            _detail(isArabic ? 'الاستثناء' : 'Exception', event.exceptionClass),
          if (event.dbError.isNotEmpty)
            _detail(
              isArabic ? 'خطأ قاعدة البيانات' : 'Database error',
              event.dbError,
            ),
          if (event.source.isNotEmpty)
            _detail(
              isArabic ? 'المصدر' : 'Source',
              '${_text(event.source['file'])}:${_text(event.source['line'])}',
            ),
          if (event.context.isNotEmpty)
            _detail(
              isArabic ? 'السياق' : 'Context',
              const JsonEncoder.withIndent('  ').convert(event.context),
            ),
          const SizedBox(height: 8),
          Align(
            alignment: AlignmentDirectional.centerEnd,
            child: TextButton.icon(
              onPressed: () => _copy(context),
              icon: const Icon(Icons.copy_rounded),
              label: Text(isArabic ? 'نسخ التشخيص' : 'Copy diagnostic'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _detail(String label, String value) => Padding(
        padding: const EdgeInsets.only(top: 8),
        child: Align(
          alignment: AlignmentDirectional.centerStart,
          child: SelectableText('$label\n$value'),
        ),
      );
}

final class _ErrorState extends StatelessWidget {
  const _ErrorState({
    required this.isArabic,
    required this.message,
    required this.onRetry,
  });

  final bool isArabic;
  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.warning_amber_rounded, size: 44),
              const SizedBox(height: 12),
              Text(
                isArabic
                    ? 'تعذر تحميل بيانات الفاحص.'
                    : 'Unable to load runtime diagnostics.',
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 8),
              SelectableText(message, textAlign: TextAlign.center),
              const SizedBox(height: 14),
              FilledButton.icon(
                onPressed: onRetry,
                icon: const Icon(Icons.refresh_rounded),
                label: Text(isArabic ? 'إعادة المحاولة' : 'Retry'),
              ),
            ],
          ),
        ),
      );
}

final class _ForbiddenState extends StatelessWidget {
  const _ForbiddenState({required this.isArabic});

  final bool isArabic;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Text(
            isArabic
                ? 'الفاحص متاح لمدير النظام فقط.'
                : 'Runtime Inspector is available to system administrators only.',
            textAlign: TextAlign.center,
          ),
        ),
      );
}

String _text(Object? value, {String fallback = ''}) {
  if (value == null) return fallback;
  final text = value.toString().trim();
  if (text.isEmpty) return fallback;
  return text.length <= 2000 ? text : '${text.substring(0, 2000)}…';
}

int _intValue(Object? value, {required int fallback}) {
  return switch (value) {
    final int value => value,
    final String value => int.tryParse(value) ?? fallback,
    _ => fallback,
  };
}

Map<String, Object?> _optionalMap(Object? value) {
  if (value == null) return const <String, Object?>{};
  try {
    return Map<String, Object?>.unmodifiable(
      apiObjectMap(value, 'diagnostic.map'),
    );
  } on FormatException {
    return const <String, Object?>{};
  }
}
