import 'dart:async';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import 'mobile_records.dart';
import 'mobile_records_repository.dart';

final class FollowUpsScreen extends StatefulWidget {
  const FollowUpsScreen({
    required this.repository,
    required this.pageSize,
    required this.filters,
    required this.canManage,
    super.key,
  });

  final MobileRecordsRepository repository;
  final int pageSize;
  final DashboardFilters filters;
  final bool canManage;

  @override
  State<FollowUpsScreen> createState() => _FollowUpsScreenState();
}

final class _FollowUpsScreenState extends State<FollowUpsScreen> {
  bool _loading = true;
  String? _error;
  List<FollowUpQueueRecord> _rows = const <FollowUpQueueRecord>[];

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  @override
  void didUpdateWidget(covariant FollowUpsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.filters != widget.filters) {
      unawaited(_load());
    }
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final rows = await widget.repository.followUps(
        widget.filters,
        pageSize: widget.pageSize,
      );
      if (!mounted) return;
      setState(() {
        _rows = rows;
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

  @override
  Widget build(BuildContext context) {
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) return _FollowUpError(message: _error!, onRetry: _load);
    if (_rows.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const <Widget>[
            SizedBox(height: 180),
            Center(
              child: Text('No follow-up items match the authorized filters.'),
            ),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: _rows.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final row = _rows[index];
          return Card(
            child: ListTile(
              title: Text(row.reference ?? 'Payment #${row.paymentId}'),
              subtitle: Text(
                'Due ${row.dueDate} · ${row.paymentStatus}\n'
                'Remaining ${row.remainingAmount} · Follow-up ${row.followUpState ?? 'none'}',
              ),
              isThreeLine: true,
              trailing: const Icon(Icons.chevron_right),
              onTap: () async {
                await Navigator.of(context).push<void>(
                  MaterialPageRoute<void>(
                    builder: (_) => FollowUpHistoryScreen(
                      repository: widget.repository,
                      pageSize: widget.pageSize,
                      paymentId: row.paymentId,
                      title: row.reference ?? 'Payment #${row.paymentId}',
                      canManage: widget.canManage,
                    ),
                  ),
                );
                if (mounted) unawaited(_load());
              },
            ),
          );
        },
      ),
    );
  }
}

final class FollowUpHistoryScreen extends StatefulWidget {
  const FollowUpHistoryScreen({
    required this.repository,
    required this.pageSize,
    required this.paymentId,
    required this.title,
    required this.canManage,
    super.key,
  });

  final MobileRecordsRepository repository;
  final int pageSize;
  final int paymentId;
  final String title;
  final bool canManage;

  @override
  State<FollowUpHistoryScreen> createState() => _FollowUpHistoryScreenState();
}

final class _FollowUpHistoryScreenState extends State<FollowUpHistoryScreen> {
  bool _loading = true;
  String? _error;
  List<FollowUpHistoryRecord> _history = const <FollowUpHistoryRecord>[];

  @override
  void initState() {
    super.initState();
    unawaited(_load());
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final history = await widget.repository.followUpHistory(
        widget.paymentId,
        pageSize: widget.pageSize,
      );
      if (!mounted) return;
      setState(() {
        _history = history;
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

  Future<void> _add() async {
    final value = await showDialog<_FollowUpValue>(
      context: context,
      builder: (_) => const _FollowUpDialog(),
    );
    if (value == null) return;

    try {
      final receipt = await widget.repository.recordFollowUp(
        paymentId: widget.paymentId,
        operation: value.operation,
        note: value.note,
        promisedDate: value.promisedDate,
        deferredUntil: value.deferredUntil,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('Follow-up #${receipt.id} recorded.')),
      );
      await _load();
    } on Object catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: Text('Follow-up · ${widget.title}'),
        actions: [
          if (widget.canManage)
            IconButton(
              tooltip: 'Add follow-up',
              onPressed: () => unawaited(_add()),
              icon: const Icon(Icons.add_comment_outlined),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _FollowUpError(message: _error!, onRetry: _load)
              : _history.isEmpty
                  ? const Center(child: Text('No follow-up history yet.'))
                  : RefreshIndicator(
                      onRefresh: _load,
                      child: ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.all(16),
                        itemCount: _history.length,
                        separatorBuilder: (_, __) => const SizedBox(height: 8),
                        itemBuilder: (context, index) {
                          final item = _history[index];
                          return Card(
                            child: Padding(
                              padding: const EdgeInsets.all(16),
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    item.state,
                                    style:
                                        Theme.of(context).textTheme.titleMedium,
                                  ),
                                  Text(item.createdAt),
                                  if (item.promisedDate != null)
                                    Text('Promised: ${item.promisedDate}'),
                                  if (item.deferredUntil != null)
                                    Text(
                                      'Deferred until: ${item.deferredUntil}',
                                    ),
                                  if (item.note != null) ...[
                                    const SizedBox(height: 8),
                                    Text(item.note!),
                                  ],
                                ],
                              ),
                            ),
                          );
                        },
                      ),
                    ),
    );
  }
}

final class _FollowUpValue {
  const _FollowUpValue({
    required this.operation,
    this.note,
    this.promisedDate,
    this.deferredUntil,
  });

  final String operation;
  final String? note;
  final String? promisedDate;
  final String? deferredUntil;
}

final class _FollowUpDialog extends StatefulWidget {
  const _FollowUpDialog();

  @override
  State<_FollowUpDialog> createState() => _FollowUpDialogState();
}

final class _FollowUpDialogState extends State<_FollowUpDialog> {
  static const _operations = <String>[
    'note',
    'promise',
    'issue',
    'defer',
    'escalate',
  ];

  String _operation = 'note';
  final _note = TextEditingController();
  final _date = TextEditingController();
  String? _error;

  bool get _needsDate => _operation == 'promise' || _operation == 'defer';
  bool get _needsNote =>
      _operation == 'note' || _operation == 'issue' || _operation == 'escalate';

  void _save() {
    final note = _nullable(_note.text);
    final date = _nullable(_date.text);
    if (_needsNote && note == null) {
      setState(() => _error = 'A note is required for this follow-up action.');
      return;
    }
    if (_needsDate && (date == null || !_validDate(date))) {
      setState(() => _error = 'A valid YYYY-MM-DD date is required.');
      return;
    }
    Navigator.pop(
      context,
      _FollowUpValue(
        operation: _operation,
        note: note,
        promisedDate: _operation == 'promise' ? date : null,
        deferredUntil: _operation == 'defer' ? date : null,
      ),
    );
  }

  @override
  void dispose() {
    _note.dispose();
    _date.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AlertDialog(
      title: const Text('Operational follow-up'),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _operation,
              decoration: const InputDecoration(labelText: 'Action'),
              items: _operations
                  .map(
                    (operation) => DropdownMenuItem<String>(
                      value: operation,
                      child: Text(operation),
                    ),
                  )
                  .toList(growable: false),
              onChanged: (value) {
                if (value != null) {
                  setState(() {
                    _operation = value;
                    _error = null;
                  });
                }
              },
            ),
            if (_needsDate)
              TextField(
                controller: _date,
                decoration: InputDecoration(
                  labelText: _operation == 'promise'
                      ? 'Promised date YYYY-MM-DD'
                      : 'Deferred until YYYY-MM-DD',
                ),
              ),
            TextField(
              controller: _note,
              maxLength: 5000,
              minLines: 2,
              maxLines: 5,
              decoration: InputDecoration(
                labelText: _needsNote ? 'Note' : 'Note (optional)',
              ),
            ),
            if (_error != null)
              Text(
                _error!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: const Text('Cancel'),
        ),
        FilledButton(onPressed: _save, child: const Text('Record')),
      ],
    );
  }
}

String? _nullable(String value) {
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

bool _validDate(String value) {
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(value);
  if (match == null) return false;
  final parsed = DateTime.tryParse(value);
  if (parsed == null) return false;
  return parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}

final class _FollowUpError extends StatelessWidget {
  const _FollowUpError({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton.tonal(
              onPressed: () => unawaited(onRetry()),
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}
