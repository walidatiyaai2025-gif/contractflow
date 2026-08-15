import 'dart:async';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import 'payments.dart';

final class CollectionEntryDialog extends StatefulWidget {
  const CollectionEntryDialog({
    required this.repository,
    required this.payment,
    super.key,
  });

  final PaymentsRepository repository;
  final SafeContractsPayment payment;

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
        if (methods.isEmpty) _error = 'No active payment methods are available.';
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
    final parsedAmount = double.tryParse(amount);
    if (parsedAmount == null || !parsedAmount.isFinite || parsedAmount <= 0) {
      setState(() => _error = 'Enter a positive collection amount.');
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
    return AlertDialog(
      title: Text('Record collection · Payment #${widget.payment.id}'),
      content: SizedBox(
        width: 420,
        child: SingleChildScrollView(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              TextField(
                controller: _amount,
                enabled: !_submitting,
                keyboardType: const TextInputType.numberWithOptions(decimal: true),
                decoration: const InputDecoration(labelText: 'Amount'),
              ),
              TextField(
                controller: _date,
                enabled: !_submitting,
                decoration: const InputDecoration(labelText: 'Collection date YYYY-MM-DD'),
              ),
              if (_loadingMethods)
                const Padding(padding: EdgeInsets.all(16), child: CircularProgressIndicator())
              else if (_methods.isNotEmpty)
                DropdownButtonFormField<int>(
                  initialValue: _methodId,
                  decoration: const InputDecoration(labelText: 'Payment method'),
                  items: _methods
                      .map((method) => DropdownMenuItem<int>(value: method.id, child: Text(method.name)))
                      .toList(growable: false),
                  onChanged: _submitting ? null : (value) => setState(() => _methodId = value),
                ),
              TextField(
                controller: _reference,
                enabled: !_submitting,
                maxLength: 191,
                decoration: const InputDecoration(labelText: 'Reference (optional)'),
              ),
              TextField(
                controller: _proof,
                enabled: !_submitting,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Proof media ID (optional)'),
              ),
              if (_error != null) ...[
                const SizedBox(height: 8),
                Text(_error!, textAlign: TextAlign.center),
              ],
            ],
          ),
        ),
      ),
      actions: [
        if (!_loadingMethods && _methods.isEmpty)
          TextButton(onPressed: _submitting ? null : () => unawaited(_loadMethods()), child: const Text('Retry methods')),
        TextButton(onPressed: _submitting ? null : () => Navigator.pop(context), child: const Text('Cancel')),
        FilledButton(
          onPressed: _submitting || _loadingMethods || _methods.isEmpty ? null : () => unawaited(_submit()),
          child: _submitting
              ? const SizedBox.square(dimension: 18, child: CircularProgressIndicator(strokeWidth: 2))
              : const Text('Record'),
        ),
      ],
    );
  }
}

bool _validDate(String value) {
  final match = RegExp(r'^(\d{4})-(\d{2})-(\d{2})$').firstMatch(value.trim());
  if (match == null) return false;
  final parsed = DateTime.tryParse(value.trim());
  return parsed != null &&
      parsed.year == int.parse(match.group(1)!) &&
      parsed.month == int.parse(match.group(2)!) &&
      parsed.day == int.parse(match.group(3)!);
}
