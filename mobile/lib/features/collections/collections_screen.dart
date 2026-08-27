import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/localization/safecontracts_localizations.dart';
import '../dashboard/dashboard_models.dart';
import '../ui/safecontracts_design.dart';
import 'collections.dart';

final class CollectionsScreen extends StatefulWidget {
  const CollectionsScreen({
    required this.repository,
    required this.pageSize,
    required this.filters,
    this.refreshRevision = 0,
    super.key,
  });

  final CollectionsRepository repository;
  final int pageSize;
  final DashboardFilters filters;
  final int refreshRevision;

  @override
  State<CollectionsScreen> createState() => _CollectionsScreenState();
}

final class _CollectionsScreenState extends State<CollectionsScreen> {
  final ScrollController _scrollController = ScrollController();
  CollectionPage? _page;
  bool _loading = true;
  bool _requestInFlight = false;
  String? _error;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_loadNextOnScroll);
    unawaited(_load(1));
  }

  @override
  void didUpdateWidget(covariant CollectionsScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.filters != widget.filters ||
        oldWidget.pageSize != widget.pageSize) {
      unawaited(_load(1));
    } else if (oldWidget.refreshRevision != widget.refreshRevision) {
      unawaited(_load(1, background: _page != null));
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _loadNextOnScroll() {
    final page = _page;
    if (page == null || !page.hasMore || _requestInFlight) return;
    if (!_scrollController.hasClients ||
        _scrollController.position.extentAfter > 360) {
      return;
    }
    unawaited(_load(page.page + 1, background: true));
  }

  Future<void> _load(int page, {bool background = false}) async {
    if (_requestInFlight) return;
    _requestInFlight = true;
    final keepVisible = background && _page != null;
    if (!keepVisible && mounted) {
      setState(() {
        _loading = true;
        _error = null;
      });
    }
    try {
      final result = await widget.repository.loadPage(
        page: page,
        perPage: widget.pageSize,
        filters: widget.filters,
      );
      if (!mounted) return;
      setState(() {
        if (page > 1 && _page != null) {
          final merged = <int, SafeContractsCollection>{
            for (final item in _page!.collections) item.id: item,
            for (final item in result.collections) item.id: item,
          };
          _page = CollectionPage(
            collections:
                List<SafeContractsCollection>.unmodifiable(merged.values),
            page: result.page,
            perPage: result.perPage,
            hasMore: result.hasMore,
            sort: result.sort,
            order: result.order,
          );
        } else {
          _page = result;
        }
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
    } finally {
      _requestInFlight = false;
    }
  }

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    if (_loading && _page == null) {
      return const _CollectionsLoading();
    }
    if (_error != null && _page == null) {
      return _CollectionsState(
        icon: Icons.cloud_off_outlined,
        title: ar ? 'تعذر تحميل التحصيلات' : 'Unable to load collections',
        message: context.scL10n.rawMessage(_error!),
        actionLabel: context.scL10n.t('Retry'),
        onAction: () => unawaited(_load(1)),
      );
    }

    final page = _page;
    if (page == null || page.collections.isEmpty) {
      return RefreshIndicator(
        onRefresh: () => _load(1),
        color: SafeContractsVisual.navy,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(16, 56, 16, 24),
          children: [
            _CollectionsState(
              icon: Icons.payments_outlined,
              title: ar ? 'لا توجد تحصيلات أو مدفوعات' : 'No settlements',
              message: ar
                  ? 'لا توجد حركات سداد فعلية مطابقة للفلاتر الحالية.'
                  : 'No actual settlement ledger entries match the current filters.',
              actionLabel: context.scL10n.t('Refresh'),
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
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 10, 14, 4),
            child: SafeContractsPremiumHeader(
              compact: true,
              leading: Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: SafeContractsVisual.greenSoft,
                  borderRadius: BorderRadius.circular(13),
                ),
                child: const Icon(
                  Icons.account_balance_wallet_outlined,
                  color: SafeContractsVisual.greenDeep,
                ),
              ),
              title: ar ? 'التحصيلات والمدفوعات الفعلية' : 'Settlement ledger',
              subtitle: ar
                  ? 'القيم أدناه من سجل التحصيلات الفعلي وليست من لقطة الدفعة المجدولة.'
                  : 'Amounts below come from the actual settlement ledger, not scheduled-payment snapshots.',
              trailing: IconButton.filledTonal(
                tooltip: context.scL10n.t('Refresh'),
                onPressed: _requestInFlight ? null : () => unawaited(_load(1)),
                icon: const Icon(Icons.refresh_rounded),
              ),
            ),
          ),
          if (_loading) const LinearProgressIndicator(minHeight: 2),
          Expanded(
            child: RefreshIndicator(
              onRefresh: () => _load(1),
              color: SafeContractsVisual.navy,
              child: ListView.separated(
                controller: _scrollController,
                physics: const AlwaysScrollableScrollPhysics(),
                padding: const EdgeInsets.fromLTRB(14, 8, 14, 20),
                itemCount: page.collections.length,
                separatorBuilder: (_, __) => const SizedBox(height: 10),
                itemBuilder: (context, index) =>
                    _CollectionCard(collection: page.collections[index]),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

final class _CollectionCard extends StatelessWidget {
  const _CollectionCard({required this.collection});

  final SafeContractsCollection collection;

  @override
  Widget build(BuildContext context) {
    final ar = context.scL10n.isArabic;
    final payable = collection.isPayable;
    final accent = payable
        ? SafeContractsVisual.roseGoldDark
        : SafeContractsVisual.greenDeep;
    final soft = payable
        ? SafeContractsVisual.roseGoldSoft
        : SafeContractsVisual.greenSoft;
    final amountLabel = payable
        ? (ar ? 'المبلغ المدفوع' : 'Paid amount')
        : (ar ? 'المبلغ المحصل' : 'Collected amount');
    final owner = collection.counterpartyName ??
        collection.contractNumber ??
        (ar ? 'الطرف غير مسمى' : 'Unnamed counterparty');

    return SafeContractsSurface(
      padding: const EdgeInsets.all(14),
      accent: accent,
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
                  color: soft,
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(
                  payable ? Icons.north_east_rounded : Icons.south_west_rounded,
                  color: accent,
                ),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      owner,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            color: SafeContractsVisual.ink,
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      collection.contractNumber ??
                          (ar
                              ? 'العقد #${collection.contractId}'
                              : 'Contract #${collection.contractId}'),
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                            color: SafeContractsVisual.muted,
                          ),
                    ),
                  ],
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 5),
                decoration: BoxDecoration(
                  color: soft,
                  borderRadius: BorderRadius.circular(99),
                ),
                child: Text(
                  payable
                      ? (ar ? 'مدفوع للمورد' : 'Supplier payment')
                      : (ar ? 'محصل من العميل' : 'Customer collection'),
                  style: TextStyle(
                    color: accent,
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 12),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: SafeContractsVisual.backgroundRaised,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  amountLabel,
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                ),
                const SizedBox(height: 2),
                Text(
                  '${_money(collection.amount)} ${collection.currencyCode}',
                  textDirection: TextDirection.ltr,
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: accent,
                        fontWeight: FontWeight.w900,
                      ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 10),
          Wrap(
            spacing: 8,
            runSpacing: 7,
            children: [
              _CollectionMeta(
                icon: Icons.calendar_today_outlined,
                value: collection.collectionDate,
              ),
              if (collection.paymentMethodName != null)
                _CollectionMeta(
                  icon: Icons.account_balance_outlined,
                  value: collection.paymentMethodName!,
                ),
              _CollectionMeta(
                icon: Icons.receipt_long_outlined,
                value: collection.paymentReference ??
                    (ar
                        ? 'دفعة #${collection.paymentId}'
                        : 'Payment #${collection.paymentId}'),
              ),
              if (collection.reference != null)
                _CollectionMeta(
                  icon: Icons.tag_rounded,
                  value: collection.reference!,
                ),
            ],
          ),
        ],
      ),
    );
  }

  String _money(String raw) {
    final value = double.tryParse(raw);
    if (value == null) return raw;
    final fixed = value.toStringAsFixed(4);
    return fixed.replaceFirst(RegExp(r'\.?0+$'), '');
  }
}

final class _CollectionMeta extends StatelessWidget {
  const _CollectionMeta({required this.icon, required this.value});

  final IconData icon;
  final String value;

  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.symmetric(horizontal: 9, vertical: 6),
        decoration: BoxDecoration(
          color: SafeContractsVisual.navySoft,
          borderRadius: BorderRadius.circular(99),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 14, color: SafeContractsVisual.navy),
            const SizedBox(width: 5),
            ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 190),
              child: Text(
                value,
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

final class _CollectionsLoading extends StatelessWidget {
  const _CollectionsLoading();

  @override
  Widget build(BuildContext context) => SafeContractsBackdrop(
        child: ListView.separated(
          padding: const EdgeInsets.all(16),
          itemCount: 5,
          separatorBuilder: (_, __) => const SizedBox(height: 10),
          itemBuilder: (_, __) => Container(
            height: 146,
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

final class _CollectionsState extends StatelessWidget {
  const _CollectionsState({
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
          Icon(icon, color: SafeContractsVisual.navy, size: 34),
          const SizedBox(height: 10),
          Text(
            title,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge?.copyWith(
                  fontWeight: FontWeight.w900,
                ),
          ),
          const SizedBox(height: 5),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: SafeContractsVisual.muted,
                ),
          ),
          if (actionLabel != null && onAction != null) ...[
            const SizedBox(height: 12),
            FilledButton.tonal(onPressed: onAction, child: Text(actionLabel!)),
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
