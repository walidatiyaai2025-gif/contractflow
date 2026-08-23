import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/branding/safe_contracts_brand.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../dashboard/dashboard_models.dart';
import '../payments/payments.dart';
import '../ui/safecontracts_design.dart';
import 'contracts.dart';

final class PremiumContractDetailsScreen extends StatefulWidget {
  const PremiumContractDetailsScreen({
    required this.repository,
    required this.contractId,
    required this.currency,
    this.onEditContract,
    super.key,
  });

  final ContractsRepository repository;
  final int contractId;
  final String currency;
  final ValueChanged<int>? onEditContract;

  @override
  State<PremiumContractDetailsScreen> createState() =>
      _PremiumContractDetailsScreenState();
}

final class _PremiumContractDetailsScreenState
    extends State<PremiumContractDetailsScreen> {
  SafeContractsContract? _contract;
  List<SafeContractsPayment> _payments = const [];
  ContractPresentation? _presentation;
  String? _error;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    if (!mounted) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final client = widget.repository.client;
      final results = await Future.wait<Object>([
        widget.repository.loadContract(widget.contractId),
        PaymentsRepository(client).loadPage(
          page: 1,
          perPage: 100,
          filters: DashboardFilters(contractId: widget.contractId),
        ),
        _loadPresentation(client),
      ]);
      if (!mounted) return;
      final paymentPage = results[1] as PaymentPage;
      setState(() {
        _contract = results[0] as SafeContractsContract;
        _payments = paymentPage.payments;
        _presentation = results[2] as ContractPresentation;
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

  Future<ContractPresentation> _loadPresentation(
    SafeContractsApiClient client,
  ) async {
    final envelope = await client.get(
      'contracts/${widget.contractId}/presentation',
    );
    return ContractPresentation.fromData(envelope.data);
  }

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return Scaffold(
      backgroundColor: SafeContractsVisual.background,
      appBar: AppBar(
        title: Text(arabic ? 'تفاصيل العقد' : 'Contract details'),
        actions: [
          if (widget.onEditContract != null)
            IconButton(
              tooltip: arabic ? 'تعديل العقد' : 'Edit contract',
              icon: const Icon(Icons.edit_outlined),
              onPressed: _contract == null
                  ? null
                  : () => widget.onEditContract!(_contract!.id),
            ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _contract == null
              ? _ErrorView(message: _error, onRetry: _load)
              : DefaultTabController(
                  length: 3,
                  child: RefreshIndicator(
                    onRefresh: _load,
                    color: SafeContractsVisual.roseGold,
                    child: NestedScrollView(
                      headerSliverBuilder: (context, innerBoxIsScrolled) => [
                        SliverToBoxAdapter(
                          child: _ContractHero(
                            contract: _contract!,
                            presentation: _presentation,
                          ),
                        ),
                        SliverPersistentHeader(
                          pinned: true,
                          delegate: _TabHeaderDelegate(
                            TabBar(
                              labelColor: SafeContractsVisual.navy,
                              unselectedLabelColor: SafeContractsVisual.muted,
                              indicatorColor: SafeContractsVisual.roseGold,
                              indicatorWeight: 3,
                              tabs: [
                                Tab(
                                  icon: const Icon(Icons.description_outlined,
                                      size: 19),
                                  text: arabic ? 'ملخص العقد' : 'Summary',
                                ),
                                Tab(
                                  icon: const Icon(Icons.payments_outlined,
                                      size: 19),
                                  text: arabic
                                      ? 'الدفعات (${_payments.length})'
                                      : 'Payments (${_payments.length})',
                                ),
                                Tab(
                                  icon: const Icon(Icons.attach_file_rounded,
                                      size: 19),
                                  text: arabic
                                      ? 'المرفقات (${_presentation?.attachments.length ?? 0})'
                                      : 'Attachments (${_presentation?.attachments.length ?? 0})',
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                      body: TabBarView(
                        children: [
                          _SummaryTab(contract: _contract!),
                          _PaymentsTab(
                            payments: _payments,
                            currency: _contract!.currencyCode == 'UNSET'
                                ? widget.currency
                                : _contract!.currencyCode,
                          ),
                          _AttachmentsTab(
                            attachments:
                                _presentation?.attachments ?? const [],
                          ),
                        ],
                      ),
                    ),
                  ),
                ),
    );
  }
}

final class ContractPresentation {
  const ContractPresentation({
    required this.contractId,
    required this.coverImageUrl,
    required this.companyLogoUrl,
    required this.attachments,
  });

  final int contractId;
  final String? coverImageUrl;
  final String? companyLogoUrl;
  final List<ContractPresentationAttachment> attachments;

  factory ContractPresentation.fromData(Object? value) {
    final data = apiObjectMap(value, 'contract.presentation');
    final raw = apiObjectList(
      data['attachments'] ?? const <Object?>[],
      'contract.presentation.attachments',
    );
    return ContractPresentation(
      contractId: _int(data['contract_id'], 'contract_id'),
      coverImageUrl: _optionalString(data['cover_image_url']),
      companyLogoUrl: _optionalString(data['company_logo_url']),
      attachments: List.unmodifiable(
        raw.map(ContractPresentationAttachment.fromData),
      ),
    );
  }
}

final class ContractPresentationAttachment {
  const ContractPresentationAttachment({
    required this.id,
    required this.mediaId,
    required this.label,
    required this.mimeType,
    required this.isImage,
    required this.url,
  });

  final int id;
  final int mediaId;
  final String label;
  final String mimeType;
  final bool isImage;
  final String url;

  factory ContractPresentationAttachment.fromData(Object? value) {
    final data = apiObjectMap(value, 'contract.attachment');
    return ContractPresentationAttachment(
      id: _int(data['id'], 'attachment.id'),
      mediaId: _int(data['media_id'], 'attachment.media_id'),
      label: _optionalString(data['label']) ?? 'Attachment',
      mimeType: _optionalString(data['mime_type']) ?? '',
      isImage: data['is_image'] == true || data['is_image'] == 1,
      url: _optionalString(data['url']) ?? '',
    );
  }
}

final class _ContractHero extends StatelessWidget {
  const _ContractHero({required this.contract, required this.presentation});
  final SafeContractsContract contract;
  final ContractPresentation? presentation;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    final imageUrl = presentation?.coverImageUrl ?? presentation?.companyLogoUrl;
    final isPayable = contract.financialDirection == 'payable';
    final accent = isPayable ? const Color(0xFFAA4137) : const Color(0xFF24704E);
    return Container(
      padding: const EdgeInsets.fromLTRB(16, 16, 16, 14),
      decoration: const BoxDecoration(
        gradient: SafeContractsVisual.premiumHeaderGradient,
        borderRadius: BorderRadius.vertical(bottom: Radius.circular(28)),
      ),
      child: Column(
        children: [
          Container(
            padding: const EdgeInsets.all(12),
            decoration: BoxDecoration(
              color: SafeContractsVisual.surface,
              borderRadius: BorderRadius.circular(22),
              boxShadow: const [
                BoxShadow(
                  color: Color(0x2D091F35),
                  blurRadius: 22,
                  offset: Offset(0, 9),
                ),
              ],
            ),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRRect(
                  borderRadius: BorderRadius.circular(16),
                  child: SizedBox(
                    width: 92,
                    height: 92,
                    child: imageUrl == null || imageUrl.isEmpty
                        ? Container(
                            color: SafeContractsVisual.backgroundRaised,
                            alignment: Alignment.center,
                            child: const SafeContractsBrandMark(
                              size: 62,
                              borderRadius: 15,
                            ),
                          )
                        : Image.network(
                            imageUrl,
                            fit: BoxFit.cover,
                            errorBuilder: (context, error, stackTrace) =>
                                Container(
                              color: SafeContractsVisual.backgroundRaised,
                              alignment: Alignment.center,
                              child: const SafeContractsBrandMark(
                                size: 62,
                                borderRadius: 15,
                              ),
                            ),
                          ),
                  ),
                ),
                const SizedBox(width: 13),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        contract.displayCounterparty,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                              color: SafeContractsVisual.navy,
                              fontWeight: FontWeight.w900,
                            ),
                      ),
                      const SizedBox(height: 5),
                      Row(
                        children: [
                          Icon(
                            contract.isSupplier
                                ? Icons.local_shipping_outlined
                                : Icons.business_outlined,
                            size: 17,
                            color: SafeContractsVisual.muted,
                          ),
                          const SizedBox(width: 5),
                          Expanded(
                            child: Text(
                              contract.contractNumber,
                              maxLines: 1,
                              overflow: TextOverflow.ellipsis,
                              style: const TextStyle(
                                  color: SafeContractsVisual.muted),
                            ),
                          ),
                        ],
                      ),
                      const SizedBox(height: 7),
                      Container(
                        padding: const EdgeInsets.symmetric(
                            horizontal: 9, vertical: 5),
                        decoration: BoxDecoration(
                          color: accent.withValues(alpha: .11),
                          borderRadius: BorderRadius.circular(99),
                        ),
                        child: Text(
                          isPayable
                              ? (arabic ? 'واجب الدفع' : 'Payable')
                              : (arabic ? 'مستحق لنا' : 'Receivable'),
                          style: TextStyle(
                            color: accent,
                            fontSize: 11,
                            fontWeight: FontWeight.w900,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(height: 12),
          Row(
            children: [
              Expanded(
                child: _HeroValue(
                  icon: Icons.flag_outlined,
                  label: arabic ? 'الحالة' : 'Status',
                  value: contract.status,
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: _HeroValue(
                  icon: Icons.account_balance_wallet_outlined,
                  label: arabic ? 'قيمة العقد' : 'Contract value',
                  value:
                      '${_money(contract.baseValue ?? '0')} ${contract.currencyCode == 'UNSET' ? '' : contract.currencyCode}',
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}

final class _HeroValue extends StatelessWidget {
  const _HeroValue({required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Container(
        padding: const EdgeInsets.all(11),
        decoration: BoxDecoration(
          color: Colors.white.withValues(alpha: .10),
          borderRadius: BorderRadius.circular(15),
          border: Border.all(color: Colors.white.withValues(alpha: .08)),
        ),
        child: Row(
          children: [
            Icon(icon, color: const Color(0xFFE2A995), size: 19),
            const SizedBox(width: 8),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label,
                      style: TextStyle(
                          color: Colors.white.withValues(alpha: .65),
                          fontSize: 10)),
                  Text(value.trim(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w900)),
                ],
              ),
            ),
          ],
        ),
      );
}

final class _SummaryTab extends StatelessWidget {
  const _SummaryTab({required this.contract});
  final SafeContractsContract contract;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return ListView(
      padding: const EdgeInsets.all(16),
      children: [
        _InfoRow(
          icon: Icons.confirmation_number_outlined,
          label: arabic ? 'رقم العقد' : 'Contract number',
          value: contract.contractNumber,
        ),
        _InfoRow(
          icon: contract.isSupplier
              ? Icons.local_shipping_outlined
              : Icons.business_outlined,
          label: arabic ? 'الطرف المقابل' : 'Counterparty',
          value: contract.displayCounterparty,
        ),
        _InfoRow(
          icon: Icons.calendar_month_outlined,
          label: arabic ? 'الفترة' : 'Period',
          value:
              '${contract.startDate ?? '—'}  →  ${contract.endDate ?? '—'}',
        ),
        _InfoRow(
          icon: Icons.currency_exchange_rounded,
          label: arabic ? 'العملة' : 'Currency',
          value: contract.currencyCode,
        ),
        _InfoRow(
          icon: Icons.swap_vert_rounded,
          label: arabic ? 'الاتجاه المالي' : 'Financial direction',
          value: contract.financialDirection,
        ),
      ],
    );
  }
}

final class _InfoRow extends StatelessWidget {
  const _InfoRow({required this.icon, required this.label, required this.value});
  final IconData icon;
  final String label;
  final String value;
  @override
  Widget build(BuildContext context) => Container(
        margin: const EdgeInsets.only(bottom: 10),
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: SafeContractsVisual.surface,
          borderRadius: BorderRadius.circular(17),
          border: Border.all(color: SafeContractsVisual.outline),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: SafeContractsVisual.roseGoldSoft,
                borderRadius: BorderRadius.circular(13),
              ),
              child: Icon(icon, color: SafeContractsVisual.navy, size: 20),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label,
                      style: const TextStyle(
                          color: SafeContractsVisual.muted, fontSize: 11)),
                  const SizedBox(height: 2),
                  Text(value,
                      style: const TextStyle(
                          color: SafeContractsVisual.ink,
                          fontWeight: FontWeight.w900)),
                ],
              ),
            ),
          ],
        ),
      );
}

final class _PaymentsTab extends StatelessWidget {
  const _PaymentsTab({required this.payments, required this.currency});
  final List<SafeContractsPayment> payments;
  final String currency;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    if (payments.isEmpty) {
      return _EmptyTab(
        icon: Icons.payments_outlined,
        text: arabic ? 'لا توجد دفعات لهذا العقد.' : 'No payments for this contract.',
      );
    }
    return ListView.separated(
      padding: const EdgeInsets.all(14),
      itemCount: payments.length,
      separatorBuilder: (_, __) => const SizedBox(height: 8),
      itemBuilder: (context, index) {
        final payment = payments[index];
        final status = _paymentStatusVisual(payment.status);
        return Container(
          padding: const EdgeInsets.all(13),
          decoration: BoxDecoration(
            color: SafeContractsVisual.surface,
            borderRadius: BorderRadius.circular(17),
            border: Border.all(color: status.$2.withValues(alpha: .28)),
          ),
          child: Row(
            children: [
              Container(
                width: 43,
                height: 43,
                decoration: BoxDecoration(
                  color: status.$2.withValues(alpha: .12),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(status.$1, color: status.$2, size: 21),
              ),
              const SizedBox(width: 11),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      arabic
                          ? 'دفعة ${payment.sequenceNo}'
                          : 'Payment ${payment.sequenceNo}',
                      style: const TextStyle(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 3),
                    Text(
                      '${payment.dueDate} · ${_money(payment.originalAmount)} $currency',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                          color: SafeContractsVisual.muted, fontSize: 12),
                    ),
                  ],
                ),
              ),
              const SizedBox(width: 8),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
                decoration: BoxDecoration(
                  color: status.$2.withValues(alpha: .12),
                  borderRadius: BorderRadius.circular(99),
                ),
                child: Text(
                  payment.status,
                  style: TextStyle(
                    color: status.$2,
                    fontSize: 10,
                    fontWeight: FontWeight.w900,
                  ),
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _AttachmentsTab extends StatelessWidget {
  const _AttachmentsTab({required this.attachments});
  final List<ContractPresentationAttachment> attachments;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    if (attachments.isEmpty) {
      return _EmptyTab(
        icon: Icons.attach_file_rounded,
        text: arabic
            ? 'لا توجد مرفقات لهذا العقد.'
            : 'No attachments for this contract.',
      );
    }
    return GridView.builder(
      padding: const EdgeInsets.all(14),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 2,
        crossAxisSpacing: 10,
        mainAxisSpacing: 10,
        childAspectRatio: .92,
      ),
      itemCount: attachments.length,
      itemBuilder: (context, index) {
        final item = attachments[index];
        return Container(
          decoration: BoxDecoration(
            color: SafeContractsVisual.surface,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: SafeContractsVisual.outline),
          ),
          clipBehavior: Clip.antiAlias,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Expanded(
                child: item.isImage && item.url.isNotEmpty
                    ? Image.network(
                        item.url,
                        fit: BoxFit.cover,
                        errorBuilder: (_, __, ___) => const _AttachmentIcon(),
                      )
                    : const _AttachmentIcon(),
              ),
              Padding(
                padding: const EdgeInsets.all(9),
                child: Row(
                  children: [
                    Icon(
                      item.isImage
                          ? Icons.image_outlined
                          : Icons.description_outlined,
                      size: 16,
                      color: SafeContractsVisual.roseGoldDark,
                    ),
                    const SizedBox(width: 6),
                    Expanded(
                      child: Text(
                        item.label,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                            fontSize: 11, fontWeight: FontWeight.w800),
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );
      },
    );
  }
}

final class _AttachmentIcon extends StatelessWidget {
  const _AttachmentIcon();
  @override
  Widget build(BuildContext context) => Container(
        color: SafeContractsVisual.backgroundRaised,
        alignment: Alignment.center,
        child: const Icon(
          Icons.insert_drive_file_outlined,
          color: SafeContractsVisual.navy,
          size: 46,
        ),
      );
}

final class _EmptyTab extends StatelessWidget {
  const _EmptyTab({required this.icon, required this.text});
  final IconData icon;
  final String text;
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            Icon(icon, size: 48, color: SafeContractsVisual.roseGoldDark),
            const SizedBox(height: 10),
            Text(text,
                textAlign: TextAlign.center,
                style: const TextStyle(color: SafeContractsVisual.muted)),
          ]),
        ),
      );
}

final class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});
  final String? message;
  final Future<void> Function() onRetry;
  @override
  Widget build(BuildContext context) => Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(mainAxisSize: MainAxisSize.min, children: [
            const Icon(Icons.error_outline_rounded,
                size: 48, color: SafeContractsVisual.red),
            const SizedBox(height: 10),
            Text(message ?? 'Unable to load contract.', textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton.icon(
              onPressed: () => onRetry(),
              icon: const Icon(Icons.refresh_rounded),
              label: Text(context.scL10n.isArabic ? 'إعادة المحاولة' : 'Retry'),
            ),
          ]),
        ),
      );
}

final class _TabHeaderDelegate extends SliverPersistentHeaderDelegate {
  _TabHeaderDelegate(this.tabBar);
  final TabBar tabBar;
  @override
  double get minExtent => tabBar.preferredSize.height;
  @override
  double get maxExtent => tabBar.preferredSize.height;
  @override
  Widget build(BuildContext context, double shrinkOffset, bool overlapsContent) =>
      Material(color: SafeContractsVisual.surface, child: tabBar);
  @override
  bool shouldRebuild(covariant _TabHeaderDelegate oldDelegate) => false;
}

(IconData, Color) _paymentStatusVisual(String status) {
  return switch (status.toLowerCase()) {
    'paid' => (Icons.check_circle_outline_rounded, const Color(0xFF2B7B53)),
    'overdue' => (Icons.error_outline_rounded, const Color(0xFFB23D34)),
    'partially_paid' => (Icons.pie_chart_outline_rounded, const Color(0xFF9A7136)),
    'due' || 'due_soon' =>
      (Icons.schedule_rounded, const Color(0xFFC07645)),
    _ => (Icons.event_outlined, const Color(0xFF315D7D)),
  };
}

String _money(String raw) {
  var value = raw.trim();
  if (value.contains('.')) {
    value = value.replaceFirst(RegExp(r'0+$'), '').replaceFirst(RegExp(r'\.$'), '');
  }
  return value;
}

String? _optionalString(Object? value) {
  if (value == null) return null;
  if (value is! String) throw const FormatException('Expected a string value.');
  final text = value.trim();
  return text.isEmpty ? null : text;
}

int _int(Object? value, String field) {
  final parsed = switch (value) {
    final int v => v,
    final String v => int.tryParse(v),
    _ => null,
  };
  if (parsed == null || parsed <= 0) throw FormatException('$field is invalid.');
  return parsed;
}
