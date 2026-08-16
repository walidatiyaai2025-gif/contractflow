import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_models.dart';
import 'followups.dart';

final class FollowUpsScreen extends StatefulWidget {
  const FollowUpsScreen({
    required this.repository,
    required this.pageSize,
    required this.filters,
    required this.currency,
    required this.canManage,
    this.refreshRevision = 0,
    super.key,
  });

  final FollowUpsRepository repository;
  final int pageSize;
  final DashboardFilters filters;
  final MobileCurrencyConfig currency;
  final bool canManage;
  final int refreshRevision;

  @override
  State<FollowUpsScreen> createState() => _FollowUpsScreenState();
}

final class _FollowUpsScreenState extends State<FollowUpsScreen> {
  bool _loading = true;
  String? _error;
  FollowUpQueuePage? _page;
  int _pageNumber = 1;

  @override
  void initState() {
    super.initState();
    unawaited(_load(1));
  }

  @override
  void didUpdateWidget(covariant FollowUpsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.filters != widget.filters ||
        oldWidget.pageSize != widget.pageSize) {
      unawaited(_load(1));
    } else if (oldWidget.refreshRevision != widget.refreshRevision) {
      unawaited(_load(_pageNumber, background: true));
    }
  }

  Future<void> _load(int page, {bool background = false}) async {
    final keepVisible = background && _page != null;
    if (!keepVisible) {
      setState(() {
        _loading = true;
        _error = null;
        _pageNumber = page;
      });
    }
    try {
      final result = await widget.repository.loadQueue(
        page: page,
        perPage: widget.pageSize,
        filters: widget.filters,
      );
      if (!mounted) return;
      setState(() {
        _page = result;
        _pageNumber = page;
        _error = null;
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) return;
      if (keepVisible) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  Future<void> _open(FollowUpQueueItem item) async {
    final l10n = context.scL10n;
    await Navigator.of(context).push<void>(
      MaterialPageRoute<void>(
        builder: (_) => FollowUpHistoryScreen(
          repository: widget.repository,
          paymentId: item.paymentId,
          title: item.reference ?? l10n.paymentNumber(item.paymentId),
          pageSize: widget.pageSize,
          canManage: widget.canManage,
        ),
      ),
    );
    if (mounted) unawaited(_load(_pageNumber));
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    if (_loading) return const Center(child: CircularProgressIndicator());
    if (_error != null) {
      return _ErrorState(
        message: l10n.rawMessage(_error!),
        onRetry: () => _load(_pageNumber),
      );
    }
    final page = _page;
    if (page == null || page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _load(1),
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: <Widget>[
            const SizedBox(height: 180),
            Center(
              child: Text(
                  l10n.t('No follow-up items match the authorized filters.')),
            ),
          ],
        ),
      );
    }

    return Column(
      children: [
        Expanded(
          child: RefreshIndicator(
            onRefresh: () => _load(_pageNumber),
            child: ListView.separated(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: const EdgeInsets.all(16),
              itemCount: page.items.length,
              separatorBuilder: (_, __) => const SizedBox(height: 8),
              itemBuilder: (context, index) {
                final item = page.items[index];
                return Card(
                  child: ListTile(
                    title: Text(
                        item.reference ?? l10n.paymentNumber(item.paymentId)),
                    subtitle: Text(
                      '${l10n.t('Due')} ${item.dueDate} · ${l10n.status(item.paymentStatus)}\n'
                      '${l10n.t('Remaining')} ${l10n.money(item.remainingAmount, widget.currency)} · '
                      '${l10n.t('Follow-up')} ${l10n.status(item.followUpState ?? 'none')}',
                    ),
                    isThreeLine: true,
                    trailing: const Icon(Icons.chevron_right),
                    onTap: () => unawaited(_open(item)),
                  ),
                );
              },
            ),
          ),
        ),
        SafeArea(
          top: false,
          child: Row(
            children: [
              IconButton(
                tooltip: l10n.t('Previous page'),
                onPressed: page.page > 1
                    ? () => unawaited(_load(page.page - 1))
                    : null,
                icon: const Icon(Icons.chevron_left),
              ),
              Expanded(
                child: Text(
                  l10n.pageNumber(page.page),
                  textAlign: TextAlign.center,
                ),
              ),
              IconButton(
                tooltip: l10n.t('Next page'),
                onPressed: page.hasMore && page.page < 5
                    ? () => unawaited(_load(page.page + 1))
                    : null,
                icon: const Icon(Icons.chevron_right),
              ),
            ],
          ),
        ),
      ],
    );
  }
}

final class FollowUpHistoryScreen extends StatefulWidget {
  const FollowUpHistoryScreen({
    required this.repository,
    required this.paymentId,
    required this.title,
    required this.pageSize,
    required this.canManage,
    super.key,
  });

  final FollowUpsRepository repository;
  final int paymentId;
  final String title;
  final int pageSize;
  final bool canManage;

  @override
  State<FollowUpHistoryScreen> createState() => _FollowUpHistoryScreenState();
}

final class _FollowUpHistoryScreenState extends State<FollowUpHistoryScreen> {
  bool _loading = true;
  String? _error;
  List<FollowUpHistoryItem> _history = const <FollowUpHistoryItem>[];

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
      final history = await widget.repository.loadHistory(
        widget.paymentId,
        perPage: widget.pageSize,
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

  Future<void> _record() async {
    final l10n = context.scL10n;
    final value = await showDialog<_FollowUpInput>(
      context: context,
      builder: (_) => const _FollowUpDialog(),
    );
    if (value == null) return;
    try {
      final receipt = await widget.repository.record(
        paymentId: widget.paymentId,
        operation: value.operation,
        note: value.note,
        promisedDate: value.promisedDate,
        deferredUntil: value.deferredUntil,
      );
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(l10n.followUpRecorded(receipt.id))),
      );
      await _load();
    } on SafeContractsApiException catch (error) {
      if (!mounted) return;
      final prefix = switch (error.statusCode) {
        422 => l10n.t('Validation'),
        403 => l10n.t('Forbidden'),
        409 => l10n.t('Conflict'),
        _ => l10n.t('Error'),
      };
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$prefix: ${l10n.rawMessage(error.message)}')),
      );
    } on Object catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error.toString())),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    return Scaffold(
      appBar: AppBar(
        title: Text('${l10n.t('Follow-up')} · ${widget.title}'),
        actions: [
          if (widget.canManage)
            IconButton(
              tooltip: l10n.t('Add follow-up'),
              onPressed: () => unawaited(_record()),
              icon: const Icon(Icons.add_comment_outlined),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _ErrorState(message: l10n.rawMessage(_error!), onRetry: _load)
              : _history.isEmpty
                  ? Center(child: Text(l10n.t('No follow-up history yet.')))
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
                                    l10n.status(item.state),
                                    style:
                                        Theme.of(context).textTheme.titleMedium,
                                  ),
                                  Text(item.createdAt),
                                  if (item.promisedDate != null)
                                    Text(
                                        '${l10n.t('Promised:')} ${item.promisedDate}'),
                                  if (item.deferredUntil != null)
                                    Text(
                                        '${l10n.t('Deferred until:')} ${item.deferredUntil}'),
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

final class _FollowUpInput {
  const _FollowUpInput({
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
    'escalate'
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
    if (note != null && note.length > 5000) {
      setState(() => _error = 'Follow-up note cannot exceed 5000 characters.');
      return;
    }
    Navigator.pop(
      context,
      _FollowUpInput(
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
    final l10n = context.scL10n;
    return AlertDialog(
      title: Text(l10n.t('Operational follow-up')),
      content: SingleChildScrollView(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            DropdownButtonFormField<String>(
              initialValue: _operation,
              isExpanded: true,
              decoration: InputDecoration(labelText: l10n.t('Action')),
              items: _operations
                  .map(
                    (operation) => DropdownMenuItem<String>(
                      value: operation,
                      child: Text(l10n.status(operation)),
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
                  labelText: l10n.t(
                    _operation == 'promise'
                        ? 'Promised date YYYY-MM-DD'
                        : 'Deferred until YYYY-MM-DD',
                  ),
                ),
              ),
            TextField(
              controller: _note,
              maxLength: 5000,
              minLines: 2,
              maxLines: 5,
              decoration: InputDecoration(
                labelText: l10n.t(_needsNote ? 'Note' : 'Note (optional)'),
              ),
            ),
            if (_error != null)
              Text(l10n.rawMessage(_error!), textAlign: TextAlign.center),
          ],
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(l10n.t('Cancel')),
        ),
        FilledButton(onPressed: _save, child: Text(l10n.t('Record'))),
      ],
    );
  }
}

final class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 12),
              FilledButton.tonal(
                onPressed: () => unawaited(onRetry()),
                child: Text(context.scL10n.t('Retry')),
              ),
            ],
          ),
        ),
      );
}

String? _nullable(String value) {
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

bool _validDate(String value) {
  final normalized = value.trim();
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(normalized);
  if (match == null) return false;
  final parsed = DateTime.tryParse(normalized);
  return parsed != null &&
      parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}
