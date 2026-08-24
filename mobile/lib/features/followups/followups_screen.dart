import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../dashboard/dashboard_models.dart';
import '../ui/safecontracts_design.dart';
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
    if (_loading && _page == null) {
      return const _FollowUpsLoading();
    }
    if (_error != null && _page == null) {
      return _FollowUpState(
        icon: Icons.cloud_off_outlined,
        title: l10n.isArabic
            ? 'تعذر تحميل المتابعات'
            : 'Unable to load follow-ups',
        message: l10n.rawMessage(_error!),
        actionLabel: l10n.t('Retry'),
        onAction: () => unawaited(_load(_pageNumber)),
      );
    }
    final page = _page;
    if (page == null || page.items.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _load(1),
        color: SafeContractsVisual.navy,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 56, 16, 24),
          children: [
            _FollowUpState(
              icon: Icons.task_alt_rounded,
              title: l10n.isArabic
                  ? 'لا توجد متابعات معلقة'
                  : 'Nothing needs follow-up',
              message: l10n.t(
                'No follow-up items match the authorized filters.',
              ),
              actionLabel: l10n.t('Refresh'),
              onAction: () => unawaited(_load(1)),
              embedded: true,
            ),
          ],
        ),
      );
    }

    return SafeContractsBackdrop(
      child: Column(
        children: [
          if (_loading) const LinearProgressIndicator(minHeight: 2),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _load(_pageNumber),
              color: SafeContractsVisual.navy,
              child: ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(14, 12, 14, 18),
                itemCount: page.items.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, index) {
                  final item = page.items[index];
                  return _FollowUpCard(
                    item: item,
                    currency: widget.currency,
                    onTap: () => unawaited(_open(item)),
                  );
                },
              ),
            ),
          ),
          _FollowUpPaging(
            page: page,
            loading: _loading,
            onPrevious:
                page.page > 1 ? () => unawaited(_load(page.page - 1)) : null,
            onNext: page.hasMore && page.page < 5
                ? () => unawaited(_load(page.page + 1))
                : null,
          ),
        ],
      ),
    );
  }
}

final class _FollowUpCard extends StatelessWidget {
  const _FollowUpCard({
    required this.item,
    required this.currency,
    required this.onTap,
  });

  final FollowUpQueueItem item;
  final MobileCurrencyConfig currency;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final urgency = _followUpUrgency(item);
    return Material(
      color: SafeContractsVisual.surface,
      borderRadius: BorderRadius.circular(SafeContractsVisual.compactRadius),
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: onTap,
        child: Container(
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            borderRadius:
                BorderRadius.circular(SafeContractsVisual.compactRadius),
            border: Border.all(color: urgency.color.withValues(alpha: 0.28)),
            boxShadow: const [
              BoxShadow(
                color: Color(0x165A4638),
                blurRadius: 16,
                offset: Offset(0, 6),
              ),
            ],
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: BoxDecoration(
                      color: urgency.softColor,
                      borderRadius: BorderRadius.circular(14),
                    ),
                    child: Icon(urgency.icon, color: urgency.color),
                  ),
                  const SizedBox(width: 11),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          item.reference ?? l10n.paymentNumber(item.paymentId),
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style:
                              Theme.of(context).textTheme.titleMedium?.copyWith(
                                    color: SafeContractsVisual.ink,
                                    fontWeight: FontWeight.w900,
                                  ),
                        ),
                        const SizedBox(height: 3),
                        Text(
                          l10n.contractNumber(item.contractId),
                          style:
                              Theme.of(context).textTheme.bodySmall?.copyWith(
                                    color: SafeContractsVisual.muted,
                                  ),
                        ),
                      ],
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding:
                        const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                    decoration: BoxDecoration(
                      color: urgency.softColor,
                      borderRadius: BorderRadius.circular(99),
                    ),
                    child: Text(
                      urgency.label(l10n.isArabic),
                      style: TextStyle(
                        color: urgency.color,
                        fontSize: 10.5,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 8,
                runSpacing: 7,
                children: [
                  _FollowUpMeta(
                    icon: Icons.calendar_today_outlined,
                    text: '${l10n.t('Due')} ${item.dueDate}',
                  ),
                  if (item.expectedPaymentDate != null)
                    _FollowUpMeta(
                      icon: Icons.event_available_outlined,
                      text: item.expectedPaymentDate!,
                    ),
                  _FollowUpMeta(
                    icon: Icons.flag_outlined,
                    text: l10n.status(item.followUpState ?? 'none'),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              Container(
                padding:
                    const EdgeInsets.symmetric(horizontal: 12, vertical: 10),
                decoration: BoxDecoration(
                  color: SafeContractsVisual.backgroundRaised,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Row(
                  children: [
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Text(
                            l10n.t('Remaining'),
                            style: Theme.of(context)
                                .textTheme
                                .labelSmall
                                ?.copyWith(
                                  color: SafeContractsVisual.muted,
                                ),
                          ),
                          const SizedBox(height: 2),
                          Text(
                            _displayMoney(
                              context,
                              item.remainingAmount,
                              currency,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: Theme.of(context)
                                .textTheme
                                .titleMedium
                                ?.copyWith(
                                  color: urgency.color,
                                  fontWeight: FontWeight.w900,
                                ),
                          ),
                        ],
                      ),
                    ),
                    Column(
                      crossAxisAlignment: CrossAxisAlignment.end,
                      children: [
                        Text(
                          l10n.status(item.paymentStatus),
                          style: TextStyle(
                            color: safeContractsStatusColor(item.paymentStatus),
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                        const SizedBox(height: 3),
                        Icon(
                          Directionality.of(context) == TextDirection.rtl
                              ? Icons.chevron_left_rounded
                              : Icons.chevron_right_rounded,
                          color: SafeContractsVisual.muted,
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _FollowUpMeta extends StatelessWidget {
  const _FollowUpMeta({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
        decoration: BoxDecoration(
          color: SafeContractsVisual.navySoft.withValues(alpha: 0.55),
          borderRadius: BorderRadius.circular(99),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: SafeContractsVisual.navy),
            const SizedBox(width: 5),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 175),
              child: Text(
                text,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(
                  color: SafeContractsVisual.navy,
                  fontSize: 10.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      );
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
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        backgroundColor: SafeContractsVisual.background,
        surfaceTintColor: Colors.transparent,
        title: Text(l10n.t('Follow-up')),
        actions: [
          if (widget.canManage)
            IconButton.filledTonal(
              tooltip: l10n.t('Add follow-up'),
              onPressed: () => unawaited(_record()),
              icon: const Icon(Icons.add_comment_outlined),
            ),
          const SizedBox(width: 8),
        ],
      ),
      body: _loading
          ? const _FollowUpsLoading(history: true)
          : _error != null
              ? _FollowUpState(
                  icon: Icons.error_outline_rounded,
                  title: l10n.isArabic
                      ? 'تعذر تحميل سجل المتابعة'
                      : 'Unable to load history',
                  message: l10n.rawMessage(_error!),
                  actionLabel: l10n.t('Retry'),
                  onAction: () => unawaited(_load()),
                )
              : SafeContractsBackdrop(
                  child: RefreshIndicator(
                    onRefresh: _load,
                    color: SafeContractsVisual.navy,
                    child: ListView(
                      physics: const AlwaysScrollableScrollPhysics(),
                      padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
                      children: [
                        SafeContractsPremiumHeader(
                          title: widget.title,
                          subtitle: l10n.isArabic
                              ? 'سجل المتابعة التشغيلي للدفعة'
                              : 'Operational follow-up history',
                          leading: Container(
                            width: 42,
                            height: 42,
                            decoration: BoxDecoration(
                              color: Colors.white.withValues(alpha: 0.12),
                              borderRadius: BorderRadius.circular(13),
                            ),
                            child: const Icon(
                              Icons.timeline_rounded,
                              color: Colors.white,
                            ),
                          ),
                        ),
                        const SizedBox(height: 16),
                        if (_history.isEmpty)
                          _FollowUpState(
                            icon: Icons.history_toggle_off_rounded,
                            title: l10n.t('No follow-up history yet.'),
                            message: widget.canManage
                                ? (l10n.isArabic
                                    ? 'يمكنك إضافة أول إجراء متابعة لهذه الدفعة.'
                                    : 'You can add the first follow-up action for this payment.')
                                : (l10n.isArabic
                                    ? 'لا توجد إجراءات مسجلة ضمن صلاحياتك.'
                                    : 'No authorized follow-up actions are recorded.'),
                            actionLabel: widget.canManage
                                ? l10n.t('Add follow-up')
                                : null,
                            onAction: widget.canManage
                                ? () => unawaited(_record())
                                : null,
                            embedded: true,
                          )
                        else ...[
                          SafeContractsSectionTitle(
                            title: l10n.isArabic ? 'السجل الزمني' : 'Timeline',
                            subtitle: l10n.isArabic
                                ? 'الأحدث أولاً كما يعيده الخادم'
                                : 'Newest first, as returned by the server',
                          ),
                          const SizedBox(height: 10),
                          for (var index = 0; index < _history.length; index++)
                            _HistoryCard(
                              item: _history[index],
                              isLast: index == _history.length - 1,
                            ),
                        ],
                      ],
                    ),
                  ),
                ),
      floatingActionButton: widget.canManage && !_loading && _error == null
          ? FloatingActionButton.extended(
              onPressed: () => unawaited(_record()),
              icon: const Icon(Icons.add_comment_outlined),
              label: Text(l10n.t('Add follow-up')),
            )
          : null,
    );
  }
}

final class _HistoryCard extends StatelessWidget {
  const _HistoryCard({required this.item, required this.isLast});

  final FollowUpHistoryItem item;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final visual = _historyVisual(item.state);
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        SizedBox(
          width: 34,
          child: Column(
            children: [
              Container(
                width: 28,
                height: 28,
                decoration: BoxDecoration(
                  color: visual.softColor,
                  shape: BoxShape.circle,
                  border: Border.all(color: visual.color),
                ),
                child: Icon(visual.icon, size: 15, color: visual.color),
              ),
              if (!isLast)
                Container(
                  width: 2,
                  height: 84,
                  color: SafeContractsVisual.contour,
                ),
            ],
          ),
        ),
        const SizedBox(width: 8),
        Expanded(
          child: Padding(
            padding: const EdgeInsets.only(bottom: 10),
            child: SafeContractsSurface(
              elevated: false,
              padding: const EdgeInsets.all(13),
              accent: visual.color,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          l10n.status(item.state),
                          style:
                              Theme.of(context).textTheme.titleSmall?.copyWith(
                                    color: visual.color,
                                    fontWeight: FontWeight.w900,
                                  ),
                        ),
                      ),
                      Text(
                        item.createdAt,
                        style: Theme.of(context).textTheme.labelSmall?.copyWith(
                              color: SafeContractsVisual.muted,
                            ),
                      ),
                    ],
                  ),
                  if (item.promisedDate != null) ...[
                    const SizedBox(height: 8),
                    _HistoryValue(
                      icon: Icons.handshake_outlined,
                      text: '${l10n.t('Promised:')} ${item.promisedDate}',
                    ),
                  ],
                  if (item.deferredUntil != null) ...[
                    const SizedBox(height: 8),
                    _HistoryValue(
                      icon: Icons.snooze_rounded,
                      text:
                          '${l10n.t('Deferred until:')} ${item.deferredUntil}',
                    ),
                  ],
                  if (item.note != null) ...[
                    const SizedBox(height: 8),
                    Text(item.note!),
                  ],
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

final class _HistoryValue extends StatelessWidget {
  const _HistoryValue({required this.icon, required this.text});

  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Icon(icon, size: 17, color: SafeContractsVisual.muted),
          const SizedBox(width: 7),
          Expanded(child: Text(text)),
        ],
      );
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
      backgroundColor: SafeContractsVisual.background,
      insetPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 22),
      title: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: SafeContractsVisual.navySoft,
              borderRadius: BorderRadius.circular(13),
            ),
            child: const Icon(
              Icons.add_comment_outlined,
              color: SafeContractsVisual.navy,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(child: Text(l10n.t('Operational follow-up'))),
        ],
      ),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                l10n.isArabic
                    ? 'اختر إجراءً مدعومًا فعليًا. لا يتم إنشاء إجراءات اتصال أو بريد غير مدعومة من الخادم.'
                    : 'Choose a supported server action. Unsupported call, message, or email actions are not fabricated.',
                style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: SafeContractsVisual.muted,
                    ),
              ),
              const SizedBox(height: 12),
              Wrap(
                spacing: 7,
                runSpacing: 7,
                children: [
                  for (final operation in _operations)
                    ChoiceChip(
                      label: Text(l10n.status(operation)),
                      selected: _operation == operation,
                      onSelected: (_) {
                        setState(() {
                          _operation = operation;
                          _error = null;
                        });
                      },
                    ),
                ],
              ),
              if (_needsDate) ...[
                const SizedBox(height: 12),
                TextField(
                  controller: _date,
                  decoration: InputDecoration(
                    prefixIcon: const Icon(Icons.calendar_today_outlined),
                    labelText: l10n.t(
                      _operation == 'promise'
                          ? 'Promised date YYYY-MM-DD'
                          : 'Deferred until YYYY-MM-DD',
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 12),
              TextField(
                controller: _note,
                maxLength: 5000,
                minLines: 2,
                maxLines: 5,
                decoration: InputDecoration(
                  prefixIcon: const Icon(Icons.notes_rounded),
                  labelText: l10n.t(_needsNote ? 'Note' : 'Note (optional)'),
                ),
              ),
              if (_error != null)
                Container(
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: SafeContractsVisual.redSoft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    l10n.rawMessage(_error!),
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: SafeContractsVisual.redDeep),
                  ),
                ),
            ],
          ),
        ),
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.pop(context),
          child: Text(l10n.t('Cancel')),
        ),
        FilledButton.icon(
          onPressed: _save,
          icon: const Icon(Icons.check_rounded),
          label: Text(l10n.t('Record')),
        ),
      ],
    );
  }
}

final class _FollowUpPaging extends StatelessWidget {
  const _FollowUpPaging({
    required this.page,
    required this.loading,
    required this.onPrevious,
    required this.onNext,
  });

  final FollowUpQueuePage page;
  final bool loading;
  final VoidCallback? onPrevious;
  final VoidCallback? onNext;

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final rtl = Directionality.of(context) == TextDirection.rtl;
    return SafeArea(
      top: false,
      minimum: const EdgeInsets.fromLTRB(14, 0, 14, 10),
      child: SafeContractsSurface(
        elevated: false,
        padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 7),
        child: Row(
          children: [
            IconButton(
              tooltip: l10n.t('Previous page'),
              onPressed: loading ? null : onPrevious,
              icon: Icon(
                rtl ? Icons.chevron_right_rounded : Icons.chevron_left_rounded,
              ),
            ),
            Expanded(
              child: Text(
                l10n.pageNumber(page.page),
                textAlign: TextAlign.center,
                style: const TextStyle(fontWeight: FontWeight.w800),
              ),
            ),
            IconButton(
              tooltip: l10n.t('Next page'),
              onPressed: loading ? null : onNext,
              icon: Icon(
                rtl ? Icons.chevron_left_rounded : Icons.chevron_right_rounded,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _FollowUpsLoading extends StatelessWidget {
  const _FollowUpsLoading({this.history = false});

  final bool history;

  @override
  Widget build(BuildContext context) => SafeContractsBackdrop(
        child: ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: history ? 4 : 5,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (context, index) => Container(
            height: history ? 96 : 132,
            decoration: BoxDecoration(
              color: SafeContractsVisual.surface,
              borderRadius:
                  BorderRadius.circular(SafeContractsVisual.compactRadius),
              border: Border.all(color: SafeContractsVisual.outline),
            ),
            child: const Center(
              child: SizedBox(
                width: 28,
                child: LinearProgressIndicator(minHeight: 2),
              ),
            ),
          ),
        ),
      );
}

final class _FollowUpState extends StatelessWidget {
  const _FollowUpState({
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
    this.embedded = false,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;
  final bool embedded;

  @override
  Widget build(BuildContext context) {
    final content = SafeContractsSurface(
      elevated: !embedded,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 54,
            height: 54,
            decoration: BoxDecoration(
              color: SafeContractsVisual.navySoft,
              borderRadius: BorderRadius.circular(17),
            ),
            child: Icon(icon, color: SafeContractsVisual.navy, size: 28),
          ),
          const SizedBox(height: 12),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 6),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 14),
            FilledButton.tonal(
              onPressed: onAction,
              child: Text(actionLabel!),
            ),
          ],
        ],
      ),
    );
    if (embedded) return content;
    return SafeContractsBackdrop(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: content,
        ),
      ),
    );
  }
}

final class _FollowUpVisual {
  const _FollowUpVisual({
    required this.icon,
    required this.color,
    required this.softColor,
    required this.arabicLabel,
    required this.englishLabel,
  });

  final IconData icon;
  final Color color;
  final Color softColor;
  final String arabicLabel;
  final String englishLabel;

  String label(bool isArabic) => isArabic ? arabicLabel : englishLabel;
}

_FollowUpVisual _followUpUrgency(FollowUpQueueItem item) {
  final paymentStatus = item.paymentStatus.trim().toLowerCase();
  final state = item.followUpState?.trim().toLowerCase() ?? '';
  if (paymentStatus == 'overdue' || state == 'escalate') {
    return const _FollowUpVisual(
      icon: Icons.priority_high_rounded,
      color: SafeContractsVisual.redDeep,
      softColor: SafeContractsVisual.redSoft,
      arabicLabel: 'عاجلة',
      englishLabel: 'Urgent',
    );
  }
  if (state == 'issue') {
    return const _FollowUpVisual(
      icon: Icons.report_problem_outlined,
      color: SafeContractsVisual.red,
      softColor: SafeContractsVisual.redSoft,
      arabicLabel: 'مشكلة',
      englishLabel: 'Issue',
    );
  }
  if (paymentStatus == 'due' ||
      paymentStatus == 'due_soon' ||
      state == 'promise') {
    return const _FollowUpVisual(
      icon: Icons.schedule_rounded,
      color: SafeContractsVisual.amber,
      softColor: SafeContractsVisual.amberSoft,
      arabicLabel: 'قريبة',
      englishLabel: 'Due soon',
    );
  }
  if (state == 'defer') {
    return const _FollowUpVisual(
      icon: Icons.snooze_rounded,
      color: SafeContractsVisual.roseGoldDark,
      softColor: SafeContractsVisual.roseGoldSoft,
      arabicLabel: 'مؤجلة',
      englishLabel: 'Deferred',
    );
  }
  return const _FollowUpVisual(
    icon: Icons.timeline_outlined,
    color: SafeContractsVisual.navy,
    softColor: SafeContractsVisual.navySoft,
    arabicLabel: 'متابعة',
    englishLabel: 'Follow-up',
  );
}

_FollowUpVisual _historyVisual(String state) {
  final normalized = state.trim().toLowerCase();
  return switch (normalized) {
    'escalate' => const _FollowUpVisual(
        icon: Icons.priority_high_rounded,
        color: SafeContractsVisual.redDeep,
        softColor: SafeContractsVisual.redSoft,
        arabicLabel: 'تصعيد',
        englishLabel: 'Escalate',
      ),
    'issue' => const _FollowUpVisual(
        icon: Icons.report_problem_outlined,
        color: SafeContractsVisual.red,
        softColor: SafeContractsVisual.redSoft,
        arabicLabel: 'مشكلة',
        englishLabel: 'Issue',
      ),
    'promise' => const _FollowUpVisual(
        icon: Icons.handshake_outlined,
        color: SafeContractsVisual.greenDeep,
        softColor: SafeContractsVisual.greenSoft,
        arabicLabel: 'وعد',
        englishLabel: 'Promise',
      ),
    'defer' => const _FollowUpVisual(
        icon: Icons.snooze_rounded,
        color: SafeContractsVisual.roseGoldDark,
        softColor: SafeContractsVisual.roseGoldSoft,
        arabicLabel: 'تأجيل',
        englishLabel: 'Defer',
      ),
    _ => const _FollowUpVisual(
        icon: Icons.notes_rounded,
        color: SafeContractsVisual.navy,
        softColor: SafeContractsVisual.navySoft,
        arabicLabel: 'ملاحظة',
        englishLabel: 'Note',
      ),
  };
}

String _displayMoney(
  BuildContext context,
  String raw,
  MobileCurrencyConfig currency,
) {
  final formatted = context.scL10n.money(raw, currency);
  return formatted.replaceFirst(RegExp(r'\.00(?=\s|$)'), '');
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
