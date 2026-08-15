import 'dart:async';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import 'operations_models.dart';
import 'operations_repository.dart';

final class FollowUpScreen extends StatefulWidget {
  const FollowUpScreen({
    required this.repository,
    required this.filters,
    required this.pageSize,
    required this.canManage,
    super.key,
  });

  final MobileOperationsRepository repository;
  final DashboardFilters filters;
  final int pageSize;
  final bool canManage;

  @override
  State<FollowUpScreen> createState() => _FollowUpScreenState();
}

final class _FollowUpScreenState extends State<FollowUpScreen> {
  late Future<List<FollowUpQueueRecord>> _queue;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _queue = widget.repository.followUpQueue(
      widget.filters,
      pageSize: widget.pageSize,
    );
  }

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<FollowUpQueueRecord>>(
      future: _queue,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(
            message: snapshot.error.toString(),
            onRetry: () {
              setState(_reload);
            },
          );
        }
        final rows = snapshot.data ?? const <FollowUpQueueRecord>[];
        return RefreshIndicator(
          onRefresh: () async {
            setState(_reload);
            await _queue;
          },
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: rows.isEmpty ? 1 : rows.length,
            itemBuilder: (context, index) {
              if (rows.isEmpty) {
                return const Padding(
                  padding: EdgeInsets.all(32),
                  child: Center(
                    child: Text('No payments currently need follow-up.'),
                  ),
                );
              }
              final row = rows[index];
              return ListTile(
                leading: const Icon(Icons.follow_the_signs_outlined),
                title: Text(row.reference ?? 'Payment #${row.paymentId}'),
                subtitle: Text(
                  '${row.dueDate} • ${row.paymentStatus} • ${row.followUpState} • Remaining ${row.remainingAmount}',
                ),
                trailing: const Icon(Icons.chevron_right),
                onTap: () => unawaited(_openPayment(context, row)),
              );
            },
          ),
        );
      },
    );
  }

  Future<void> _openPayment(
    BuildContext context,
    FollowUpQueueRecord row,
  ) async {
    try {
      var history = await widget.repository.followUpHistory(
        row.paymentId,
        pageSize: widget.pageSize,
      );
      if (!context.mounted) return;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Text(row.reference ?? 'Payment #${row.paymentId}'),
            content: SizedBox(
              width: 520,
              child: SingleChildScrollView(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text('Due: ${row.dueDate}'),
                    if (row.expectedPaymentDate != null)
                      Text('Expected: ${row.expectedPaymentDate}'),
                    Text('Remaining: ${row.remainingAmount}'),
                    const SizedBox(height: 16),
                    Text(
                      'History',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),
                    const SizedBox(height: 8),
                    if (history.isEmpty)
                      const Text('No follow-up entries yet.')
                    else
                      ...history.map(
                        (entry) => Card(
                          child: ListTile(
                            title: Text(entry.state),
                            subtitle: Text(
                              <String>[
                                entry.createdAt,
                                if (entry.note != null) entry.note!,
                                if (entry.promisedDate != null)
                                  'Promise: ${entry.promisedDate}',
                                if (entry.deferredUntil != null)
                                  'Deferred: ${entry.deferredUntil}',
                              ].join('\n'),
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
            actions: [
              if (widget.canManage)
                TextButton(
                  onPressed: () async {
                    final changed = await _recordAction(dialogContext, row.paymentId);
                    if (!changed || !dialogContext.mounted) return;
                    history = await widget.repository.followUpHistory(
                      row.paymentId,
                      pageSize: widget.pageSize,
                    );
                    setDialogState(() {});
                    if (mounted) setState(_reload);
                  },
                  child: const Text('Add follow-up'),
                ),
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(),
                child: const Text('Close'),
              ),
            ],
          ),
        ),
      );
    } on Object catch (error) {
      if (!context.mounted) return;
      _showError(context, error);
    }
  }

  Future<bool> _recordAction(BuildContext context, int paymentId) async {
    final noteController = TextEditingController();
    final dateController = TextEditingController();
    var action = 'note';
    try {
      final result = await showDialog<bool>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setState) => AlertDialog(
            title: const Text('Follow-up action'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  DropdownButtonFormField<String>(
                    initialValue: action,
                    isExpanded: true,
                    decoration: const InputDecoration(labelText: 'Action'),
                    items: const [
                      DropdownMenuItem(value: 'note', child: Text('Contacted / note')),
                      DropdownMenuItem(value: 'promise', child: Text('Promised to pay')),
                      DropdownMenuItem(value: 'issue', child: Text('Issue')),
                      DropdownMenuItem(value: 'defer', child: Text('Defer')),
                      DropdownMenuItem(value: 'escalate', child: Text('Needs escalation')),
                    ],
                    onChanged: (value) {
                      if (value != null) setState(() => action = value);
                    },
                  ),
                  const SizedBox(height: 12),
                  TextField(
                    controller: noteController,
                    maxLines: 3,
                    decoration: const InputDecoration(labelText: 'Note'),
                  ),
                  if (action == 'promise' || action == 'defer') ...[
                    const SizedBox(height: 12),
                    TextField(
                      controller: dateController,
                      decoration: const InputDecoration(labelText: 'Date YYYY-MM-DD'),
                    ),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(dialogContext).pop(false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () async {
                  final note = noteController.text.trim();
                  final date = dateController.text.trim();
                  final requiresNote = action == 'note' || action == 'issue' || action == 'escalate';
                  final requiresDate = action == 'promise' || action == 'defer';
                  if (requiresNote && note.isEmpty) {
                    _showError(dialogContext, const FormatException('A note is required for this action.'));
                    return;
                  }
                  if (requiresDate && !_isDate(date)) {
                    _showError(dialogContext, const FormatException('A valid YYYY-MM-DD date is required.'));
                    return;
                  }
                  try {
                    await widget.repository.recordFollowUp(
                      paymentId: paymentId,
                      action: action,
                      note: note.isEmpty ? null : note,
                      date: date.isEmpty ? null : date,
                    );
                    if (dialogContext.mounted) {
                      Navigator.of(dialogContext).pop(true);
                    }
                  } on Object catch (error) {
                    if (dialogContext.mounted) _showError(dialogContext, error);
                  }
                },
                child: const Text('Save'),
              ),
            ],
          ),
        ),
      );
      return result ?? false;
    } finally {
      noteController.dispose();
      dateController.dispose();
    }
  }
}

final class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Icon(Icons.error_outline, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(onPressed: onRetry, child: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text(error.toString())),
  );
}

bool _isDate(String value) {
  final text = value.trim();
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(text);
  if (match == null) return false;
  final parsed = DateTime.tryParse(text);
  return parsed != null &&
      parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}
