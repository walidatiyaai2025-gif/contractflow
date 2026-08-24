import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
import '../ui/safecontracts_design.dart';
import 'payments.dart';

final class CollectionEntryDialog extends StatefulWidget {
  const CollectionEntryDialog({
    required this.repository,
    required this.payment,
    required this.currency,
    super.key,
  });

  final PaymentsRepository repository;
  final SafeContractsPayment payment;
  final MobileCurrencyConfig currency;

  @override
  State<CollectionEntryDialog> createState() => _CollectionEntryDialogState();
}

final class _CollectionEntryDialogState extends State<CollectionEntryDialog> {
  final _amount = TextEditingController();
  final _date = TextEditingController();
  final _reference = TextEditingController();
  bool _loadingMethods = true;
  bool _submitting = false;
  String? _error;
  List<PaymentMethodOption> _methods = const <PaymentMethodOption>[];
  int? _methodId;

  @override
  void initState() {
    super.initState();
    final now = DateTime.now();
    _date.text = '${now.year.toString().padLeft(4, '0')}-'
        '${now.month.toString().padLeft(2, '0')}-'
        '${now.day.toString().padLeft(2, '0')}';
    unawaited(_loadMethods());
  }

  Future<void> _loadMethods() async {
    setState(() {
      _loadingMethods = true;
      _error = null;
    });
    try {
      final methods = await widget.repository.paymentMethods();
      if (!mounted) return;
      setState(() {
        _methods = methods;
        _methodId = methods.isEmpty ? null : methods.first.id;
        _loadingMethods = false;
        if (methods.isEmpty) {
          _error = 'No active payment methods are available.';
        }
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _loadingMethods = false;
        _error = 'Payment methods: $error';
      });
    }
  }

  Future<void> _submit() async {
    if (widget.payment.isPayable) {
      setState(() {
        _error = 'Supplier payable entries cannot be recorded as customer collections.';
      });
      return;
    }
    final amount = _amount.text.trim();
    if (!_validPositiveMoney(amount)) {
      setState(() {
        _error = 'Enter a positive amount with up to 4 decimal places.';
      });
      return;
    }
    final date = _date.text.trim();
    if (!_validDate(date)) {
      setState(() => _error = 'Collection date must be valid YYYY-MM-DD.');
      return;
    }
    final methodId = _methodId;
    if (methodId == null) {
      setState(() => _error = 'Choose an active payment method.');
      return;
    }
    final reference = _reference.text.trim();
    if (reference.length > 191) {
      setState(() => _error = 'Reference cannot exceed 191 characters.');
      return;
    }

    setState(() {
      _submitting = true;
      _error = null;
    });
    try {
      final receipt = await widget.repository.recordCollection(
        paymentId: widget.payment.id,
        amount: amount,
        collectionDate: date,
        paymentMethodId: methodId,
        reference: reference,
      );
      if (!mounted) return;
      Navigator.pop(context, receipt);
    } on SafeContractsApiException catch (error) {
      if (!mounted) return;
      final prefix = switch (error.statusCode) {
        422 => 'Validation',
        403 => 'Forbidden',
        409 => 'Conflict',
        _ => 'Error',
      };
      setState(() {
        _submitting = false;
        _error = '$prefix: ${error.message}';
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _submitting = false;
        _error = error.toString();
      });
    }
  }

  @override
  void dispose() {
    _amount.dispose();
    _date.dispose();
    _reference.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final token = widget.currency.displayToken;
    final payment = widget.payment;
    final owner = payment.displayOwner ?? l10n.t('Customer');
    final contract =
        payment.contractNumber ?? l10n.contractNumber(payment.contractId);
    final blocked = payment.isPayable;

    return AlertDialog(
      backgroundColor: SafeContractsVisual.background,
      insetPadding: const EdgeInsets.symmetric(horizontal: 14, vertical: 22),
      contentPadding: const EdgeInsets.fromLTRB(14, 14, 14, 0),
      actionsPadding: const EdgeInsets.fromLTRB(14, 6, 14, 14),
      titlePadding: const EdgeInsets.fromLTRB(18, 18, 18, 0),
      title: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: BoxDecoration(
              color: blocked
                  ? SafeContractsVisual.roseGoldSoft
                  : SafeContractsVisual.greenSoft,
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(
              blocked ? Icons.local_shipping_outlined : Icons.add_card_outlined,
              color: blocked
                  ? SafeContractsVisual.roseGoldDark
                  : SafeContractsVisual.greenDeep,
            ),
          ),
          const SizedBox(width: 11),
          Expanded(
            child: Text(
              blocked
                  ? (l10n.isArabic ? 'دفعة مورد' : 'Supplier payable')
                  : (l10n.isArabic
                      ? 'تسجيل تحصيل عميل'
                      : 'Record customer collection'),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
      content: SizedBox(
        width: 440,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              SafeContractsSurface(
                elevated: false,
                padding: const EdgeInsets.all(13),
                accent: blocked
                    ? SafeContractsVisual.roseGold
                    : SafeContractsVisual.green,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      payment.reference ?? l10n.paymentNumber(payment.id),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w900,
                          ),
                    ),
                    const SizedBox(height: 8),
                    _CollectionContextRow(
                      icon: Icons.business_outlined,
                      label: l10n.t('Customer'),
                      value: owner,
                    ),
                    _CollectionContextRow(
                      icon: Icons.folder_copy_outlined,
                      label: l10n.t('Contract'),
                      value: contract,
                    ),
                    _CollectionContextRow(
                      icon: Icons.account_balance_wallet_outlined,
                      label: l10n.t('Remaining'),
                      value: _displayMoney(
                        context,
                        payment.remainingAmount,
                        widget.currency,
                      ),
                      strong: true,
                    ),
                  ],
                ),
              ),
              if (blocked) ...[
                const SizedBox(height: 12),
                Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: SafeContractsVisual.roseGoldSoft,
                    borderRadius: BorderRadius.circular(14),
                    border: Border.all(
                      color: SafeContractsVisual.roseGold.withValues(alpha: 0.45),
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.info_outline_rounded,
                        color: SafeContractsVisual.roseGoldDark,
                      ),
                      const SizedBox(width: 9),
                      Expanded(
                        child: Text(
                          l10n.isArabic
                              ? 'هذه الدفعة واجبة الدفع لمورد. مسار التحصيل مخصص للمبالغ المستحقة من العملاء فقط.'
                              : 'This payment is payable to a supplier. Collection is reserved for customer receivables.',
                        ),
                      ),
                    ],
                  ),
                ),
              ] else ...[
                const SizedBox(height: 12),
                TextField(
                  controller: _amount,
                  enabled: !_submitting,
                  keyboardType:
                      const TextInputType.numberWithOptions(decimal: true),
                  decoration: InputDecoration(
                    labelText: token.isEmpty
                        ? l10n.t('Amount')
                        : '${l10n.t('Amount')} ($token)',
                    prefixIcon: const Icon(Icons.payments_outlined),
                    helperText: l10n.isArabic
                        ? 'لا يمكن أن يتجاوز التحصيل الرصيد المتبقي؛ الخادم يتحقق من القيمة.'
                        : 'The server validates the amount against the remaining balance.',
                  ),
                ),
                const SizedBox(height: 6),
                TextField(
                  controller: _date,
                  enabled: !_submitting,
                  decoration: InputDecoration(
                    labelText: l10n.t('Collection date YYYY-MM-DD'),
                    prefixIcon: const Icon(Icons.calendar_today_outlined),
                  ),
                ),
                const SizedBox(height: 6),
                if (_loadingMethods)
                  Container(
                    padding: const EdgeInsets.all(13),
                    decoration: BoxDecoration(
                      color: SafeContractsVisual.surface,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(color: SafeContractsVisual.outline),
                    ),
                    child: Row(
                      children: [
                        const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        ),
                        const SizedBox(width: 10),
                        Expanded(
                          child: Text(
                            l10n.isArabic
                                ? 'جارٍ تحميل طرق الدفع المسموح بها…'
                                : 'Loading authorized payment methods…',
                          ),
                        ),
                      ],
                    ),
                  )
                else if (_methods.isNotEmpty)
                  DropdownButtonFormField<int>(
                    initialValue: _methodId,
                    isExpanded: true,
                    decoration: InputDecoration(
                      labelText: l10n.t('Payment method'),
                      prefixIcon: const Icon(Icons.credit_card_outlined),
                    ),
                    items: _methods
                        .map(
                          (method) => DropdownMenuItem<int>(
                            value: method.id,
                            child: Text(
                              method.name,
                              overflow: TextOverflow.ellipsis,
                            ),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: _submitting
                        ? null
                        : (value) => setState(() => _methodId = value),
                  ),
                const SizedBox(height: 6),
                TextField(
                  controller: _reference,
                  enabled: !_submitting,
                  maxLength: 191,
                  decoration: InputDecoration(
                    labelText: l10n.t('Reference (optional)'),
                    prefixIcon: const Icon(Icons.tag_outlined),
                  ),
                ),
              ],
              if (_error != null) ...[
                const SizedBox(height: 8),
                Container(
                  padding: const EdgeInsets.all(11),
                  decoration: BoxDecoration(
                    color: SafeContractsVisual.redSoft,
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      const Icon(
                        Icons.error_outline_rounded,
                        color: SafeContractsVisual.redDeep,
                        size: 20,
                      ),
                      const SizedBox(width: 8),
                      Expanded(
                        child: Text(
                          l10n.rawMessage(_error!),
                          style: const TextStyle(
                            color: SafeContractsVisual.redDeep,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
              ],
              if (!blocked) ...[
                const SizedBox(height: 10),
                Text(
                  l10n.t(
                    'The server validates scope, payment balance, settlement status and audit history. Mobile performs input-shape checks only.',
                  ),
                  style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: SafeContractsVisual.muted,
                      ),
                ),
              ],
            ],
          ),
        ),
      ),
      actions: [
        if (!blocked && !_loadingMethods && _methods.isEmpty)
          TextButton(
            onPressed: _submitting ? null : () => unawaited(_loadMethods()),
            child: Text(l10n.t('Retry methods')),
          ),
        TextButton(
          onPressed: _submitting ? null : () => Navigator.pop(context),
          child: Text(l10n.t(blocked ? 'Close' : 'Cancel')),
        ),
        if (!blocked)
          FilledButton.icon(
            onPressed: _submitting || _loadingMethods || _methods.isEmpty
                ? null
                : () => unawaited(_submit()),
            icon: _submitting
                ? const SizedBox.square(
                    dimension: 17,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Icon(Icons.check_rounded),
            label: Text(
              _submitting
                  ? (l10n.isArabic ? 'جارٍ التسجيل…' : 'Recording…')
                  : (l10n.isArabic ? 'تسجيل التحصيل' : 'Record collection'),
            ),
          ),
      ],
    );
  }
}

final class _CollectionContextRow extends StatelessWidget {
  const _CollectionContextRow({
    required this.icon,
    required this.label,
    required this.value,
    this.strong = false,
  });

  final IconData icon;
  final String label;
  final String value;
  final bool strong;

  @override
  Widget build(BuildContext context) => Padding(
        padding: const EdgeInsets.only(top: 7),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Icon(icon, size: 17, color: SafeContractsVisual.muted),
            const SizedBox(width: 7),
            Expanded(
              child: Text(
                '$label: $value',
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: TextStyle(
                  color: strong
                      ? SafeContractsVisual.navy
                      : SafeContractsVisual.ink,
                  fontWeight: strong ? FontWeight.w900 : FontWeight.w600,
                ),
              ),
            ),
          ],
        ),
      );
}

String _displayMoney(
  BuildContext context,
  String raw,
  MobileCurrencyConfig currency,
) {
  final formatted = context.scL10n.money(raw, currency);
  return formatted.replaceFirst(RegExp(r'\.00(?=\s|$)'), '');
}

bool _validPositiveMoney(String value) {
  final normalized = value.trim();
  if (normalized.isEmpty || normalized.length > 32) return false;
  if (!RegExp(r'^\d+(?:\.\d{1,4})?$').hasMatch(normalized)) return false;
  final digits =
      normalized.replaceAll('.', '').replaceFirst(RegExp(r'^0+'), '');
  return digits.isNotEmpty;
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
