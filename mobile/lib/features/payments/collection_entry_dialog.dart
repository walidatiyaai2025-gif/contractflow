import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../config/mobile_config.dart';
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
  final _proof = TextEditingController();
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
    final proofText = _proof.text.trim();
    final proofId = proofText.isEmpty ? null : int.tryParse(proofText);
    if (proofText.isNotEmpty && (proofId == null || proofId <= 0)) {
      setState(() => _error = 'Proof media ID must be a positive integer.');
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
        proofMediaId: proofId,
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
    _proof.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final l10n = context.scL10n;
    final token = widget.currency.displayToken;
    return AlertDialog(
      title: Text(
          '${l10n.t('Record collection')} · ${l10n.paymentNumber(widget.payment.id)}'),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                l10n.t(
                  'The server validates scope, payment balance, settlement status and audit history. Mobile performs input-shape checks only.',
                ),
              ),
              const SizedBox(height: 8),
              Text(
                '${l10n.t('Remaining')}: ${l10n.money(widget.payment.remainingAmount, widget.currency)}',
                style: Theme.of(context).textTheme.labelLarge,
              ),
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
                ),
              ),
              TextField(
                controller: _date,
                enabled: !_submitting,
                decoration: InputDecoration(
                  labelText: l10n.t('Collection date YYYY-MM-DD'),
                ),
              ),
              if (_loadingMethods)
                const Padding(
                  padding: EdgeInsets.all(16),
                  child: CircularProgressIndicator(),
                )
              else if (_methods.isNotEmpty)
                DropdownButtonFormField<int>(
                  initialValue: _methodId,
                  isExpanded: true,
                  decoration:
                      InputDecoration(labelText: l10n.t('Payment method')),
                  items: _methods
                      .map(
                        (method) => DropdownMenuItem<int>(
                          value: method.id,
                          child: Text(method.name,
                              overflow: TextOverflow.ellipsis),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: _submitting
                      ? null
                      : (value) => setState(() => _methodId = value),
                ),
              TextField(
                controller: _reference,
                enabled: !_submitting,
                maxLength: 191,
                decoration: InputDecoration(
                  labelText: l10n.t('Reference (optional)'),
                ),
              ),
              TextField(
                controller: _proof,
                enabled: !_submitting,
                keyboardType: TextInputType.number,
                decoration: InputDecoration(
                  labelText: l10n.t('Proof media ID (optional)'),
                ),
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(l10n.rawMessage(_error!), textAlign: TextAlign.center),
              ],
            ],
          ),
        ),
      ),
      actions: [
        if (!_loadingMethods && _methods.isEmpty)
          TextButton(
            onPressed: _submitting ? null : () => unawaited(_loadMethods()),
            child: Text(l10n.t('Retry methods')),
          ),
        TextButton(
          onPressed: _submitting ? null : () => Navigator.pop(context),
          child: Text(l10n.t('Cancel')),
        ),
        FilledButton(
          onPressed: _submitting || _loadingMethods || _methods.isEmpty
              ? null
              : () => unawaited(_submit()),
          child: _submitting
              ? const SizedBox.square(
                  dimension: 18,
                  child: CircularProgressIndicator(strokeWidth: 2),
                )
              : Text(l10n.t('Record')),
        ),
      ],
    );
  }
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
