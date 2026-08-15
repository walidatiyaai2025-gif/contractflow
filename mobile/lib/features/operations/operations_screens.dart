import 'dart:async';
import 'dart:io';

import 'package:flutter/material.dart';

import '../dashboard/dashboard_models.dart';
import '../session/session_controller.dart';
import 'operations_models.dart';
import 'operations_repository.dart';

final class CustomersScreen extends StatefulWidget {
  const CustomersScreen({
    required this.repository,
    required this.pageSize,
    super.key,
  });

  final MobileOperationsRepository repository;
  final int pageSize;

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

final class _CustomersScreenState extends State<CustomersScreen> {
  late Future<List<CustomerRecord>> _rows;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _rows = widget.repository.customers(pageSize: widget.pageSize);
  }

  @override
  Widget build(BuildContext context) {
    return _ListFrame<CustomerRecord>(
      future: _rows,
      emptyText: 'No customers are available in your current scope.',
      onRefresh: () async {
        setState(_reload);
        await _rows;
      },
      itemBuilder: (context, customer) => ListTile(
        leading: const Icon(Icons.business_outlined),
        title: Text(customer.name),
        subtitle: Text(
          <String>[
            if (customer.internalCode != null) customer.internalCode!,
            if (customer.contactName != null) customer.contactName!,
          ].join(' • '),
        ),
        trailing: Icon(customer.isActive ? Icons.check_circle_outline : Icons.block),
        onTap: () => unawaited(_showCustomer(context, customer.id)),
      ),
    );
  }

  Future<void> _showCustomer(BuildContext context, int id) async {
    try {
      final customer = await widget.repository.customer(id);
      if (!context.mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(customer.name),
          content: _KeyValueList(
            values: <String, String?>{
              'Internal code': customer.internalCode,
              'Contact': customer.contactName,
              'Email': customer.email,
              'Phone': customer.phone,
              'State': customer.isActive ? 'Active' : 'Inactive',
            },
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(),
              child: const Text('Close'),
            ),
          ],
        ),
      );
    } on Object catch (error) {
      if (!context.mounted) return;
      _showError(context, error);
    }
  }
}

final class ContractsScreen extends StatefulWidget {
  const ContractsScreen({
    required this.repository,
    required this.filters,
    required this.session,
    required this.pageSize,
    super.key,
  });

  final MobileOperationsRepository repository;
  final DashboardFilters filters;
  final SafeContractsSession session;
  final int pageSize;

  @override
  State<ContractsScreen> createState() => _ContractsScreenState();
}

final class _ContractsScreenState extends State<ContractsScreen> {
  late Future<List<ContractRecord>> _rows;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _rows = widget.repository.contracts(
      widget.filters,
      pageSize: widget.pageSize,
    );
  }

  @override
  Widget build(BuildContext context) {
    return _ListFrame<ContractRecord>(
      future: _rows,
      emptyText: 'No contracts match the current dashboard filters.',
      onRefresh: () async {
        setState(_reload);
        await _rows;
      },
      itemBuilder: (context, contract) => ListTile(
        leading: const Icon(Icons.description_outlined),
        title: Text(contract.contractNumber),
        subtitle: Text(
          <String>[
            if (contract.customerName != null) contract.customerName!,
            contract.status,
            contract.baseValue,
          ].join(' • '),
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => unawaited(_showContract(context, contract.id)),
      ),
    );
  }

  Future<void> _showContract(BuildContext context, int id) async {
    try {
      var contract = await widget.repository.contract(id);
      if (!context.mounted) return;
      final canEdit = widget.session.can('safecontracts_edit_contracts') &&
          !contract.isArchived;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Text(contract.contractNumber),
            content: SingleChildScrollView(
              child: _KeyValueList(
                values: <String, String?>{
                  'Customer': contract.customerName ?? contract.customerId.toString(),
                  'Status': contract.status,
                  'Start date': contract.startDate,
                  'End date': contract.endDate,
                  'Base value': contract.baseValue,
                  'Archived': contract.isArchived ? 'Yes' : 'No',
                },
              ),
            ),
            actions: [
              if (canEdit)
                TextButton(
                  onPressed: () async {
                    final updated = await _editContract(dialogContext, contract);
                    if (updated && dialogContext.mounted) {
                      contract = await widget.repository.contract(id);
                      setDialogState(() {});
                      if (mounted) setState(_reload);
                    }
                  },
                  child: const Text('Light edit'),
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

  Future<bool> _editContract(BuildContext context, ContractRecord contract) async {
    final numberController = TextEditingController(text: contract.contractNumber);
    final startController = TextEditingController(text: contract.startDate ?? '');
    final endController = TextEditingController(text: contract.endDate ?? '');
    var mode = 'number';
    try {
      final result = await showDialog<bool>(
        context: context,
        builder: (context) => StatefulBuilder(
          builder: (context, setState) => AlertDialog(
            title: const Text('Contract light edit'),
            content: SingleChildScrollView(
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  SegmentedButton<String>(
                    segments: const [
                      ButtonSegment(value: 'number', label: Text('Number')),
                      ButtonSegment(value: 'dates', label: Text('Dates')),
                    ],
                    selected: <String>{mode},
                    onSelectionChanged: (value) {
                      setState(() => mode = value.first);
                    },
                  ),
                  const SizedBox(height: 16),
                  if (mode == 'number')
                    TextField(
                      controller: numberController,
                      decoration: const InputDecoration(labelText: 'Contract number'),
                    )
                  else ...[
                    TextField(
                      controller: startController,
                      decoration: const InputDecoration(labelText: 'Start date YYYY-MM-DD'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: endController,
                      decoration: const InputDecoration(labelText: 'End date YYYY-MM-DD'),
                    ),
                  ],
                ],
              ),
            ),
            actions: [
              TextButton(
                onPressed: () => Navigator.of(context).pop(false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () async {
                  try {
                    if (mode == 'number') {
                      if (numberController.text.trim().isEmpty) {
                        throw const FormatException('Contract number is required.');
                      }
                      await widget.repository.editContractNumber(
                        contract.id,
                        numberController.text,
                      );
                    } else {
                      if (!_isDate(startController.text) || !_isDate(endController.text)) {
                        throw const FormatException('Contract dates must use YYYY-MM-DD.');
                      }
                      await widget.repository.editContractDates(
                        contract.id,
                        startController.text,
                        endController.text,
                      );
                    }
                    if (context.mounted) Navigator.of(context).pop(true);
                  } on Object catch (error) {
                    if (context.mounted) _showError(context, error);
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
      numberController.dispose();
      startController.dispose();
      endController.dispose();
    }
  }
}

final class PaymentsScreen extends StatefulWidget {
  const PaymentsScreen({
    required this.repository,
    required this.filters,
    required this.session,
    required this.pageSize,
    super.key,
  });

  final MobileOperationsRepository repository;
  final DashboardFilters filters;
  final SafeContractsSession session;
  final int pageSize;

  @override
  State<PaymentsScreen> createState() => _PaymentsScreenState();
}

final class _PaymentsScreenState extends State<PaymentsScreen> {
  late Future<List<PaymentRecord>> _rows;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _rows = widget.repository.payments(widget.filters, pageSize: widget.pageSize);
  }

  @override
  Widget build(BuildContext context) {
    return _ListFrame<PaymentRecord>(
      future: _rows,
      emptyText: 'No payments match the current dashboard filters.',
      onRefresh: () async {
        setState(_reload);
        await _rows;
      },
      itemBuilder: (context, payment) => ListTile(
        leading: const Icon(Icons.event_note_outlined),
        title: Text(payment.reference ?? 'Payment #${payment.id}'),
        subtitle: Text(
          '${payment.dueDate} • ${payment.status} • Remaining ${payment.remainingAmount}',
        ),
        trailing: const Icon(Icons.chevron_right),
        onTap: () => unawaited(_showPayment(context, payment.id)),
      ),
    );
  }

  Future<void> _showPayment(BuildContext context, int id) async {
    try {
      var payment = await widget.repository.payment(id);
      if (!context.mounted) return;
      final canEdit = widget.session.can('safecontracts_manage_payments') &&
          !payment.contractIsArchived;
      await showDialog<void>(
        context: context,
        builder: (dialogContext) => StatefulBuilder(
          builder: (context, setDialogState) => AlertDialog(
            title: Text(payment.reference ?? 'Payment #${payment.id}'),
            content: SingleChildScrollView(
              child: _KeyValueList(
                values: <String, String?>{
                  'Contract': payment.contractNumber ?? payment.contractId.toString(),
                  'Due date': payment.dueDate,
                  'Expected date': payment.expectedPaymentDate,
                  'Original': payment.originalAmount,
                  'Paid': payment.paidAmount,
                  'Remaining': payment.remainingAmount,
                  'Status': payment.status,
                },
              ),
            ),
            actions: [
              if (canEdit)
                TextButton(
                  onPressed: () async {
                    final updated = await _editExpectedDate(dialogContext, payment);
                    if (updated && dialogContext.mounted) {
                      payment = await widget.repository.payment(id);
                      setDialogState(() {});
                      if (mounted) setState(_reload);
                    }
                  },
                  child: const Text('Edit expected date'),
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

  Future<bool> _editExpectedDate(BuildContext context, PaymentRecord payment) async {
    final controller = TextEditingController(text: payment.expectedPaymentDate ?? '');
    try {
      final result = await showDialog<bool>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Expected payment date'),
          content: TextField(
            controller: controller,
            decoration: const InputDecoration(
              labelText: 'YYYY-MM-DD',
              helperText: 'Leave empty to clear the operational expected date.',
            ),
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () async {
                final value = controller.text.trim();
                if (value.isNotEmpty && !_isDate(value)) {
                  _showError(context, const FormatException('Date must use YYYY-MM-DD.'));
                  return;
                }
                try {
                  await widget.repository.updateExpectedPaymentDate(
                    payment.id,
                    value.isEmpty ? null : value,
                  );
                  if (context.mounted) Navigator.of(context).pop(true);
                } on Object catch (error) {
                  if (context.mounted) _showError(context, error);
                }
              },
              child: const Text('Save'),
            ),
          ],
        ),
      );
      return result ?? false;
    } finally {
      controller.dispose();
    }
  }
}

final class CollectionsScreen extends StatefulWidget {
  const CollectionsScreen({
    required this.repository,
    required this.filters,
    required this.pageSize,
    required this.canRecord,
    super.key,
  });

  final MobileOperationsRepository repository;
  final DashboardFilters filters;
  final int pageSize;
  final bool canRecord;

  @override
  State<CollectionsScreen> createState() => _CollectionsScreenState();
}

final class _CollectionsScreenState extends State<CollectionsScreen> {
  late Future<List<CollectionRecord>> _rows;

  @override
  void initState() {
    super.initState();
    _reload();
  }

  void _reload() {
    _rows = widget.repository.collections(widget.filters, pageSize: widget.pageSize);
  }

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        if (widget.canRecord)
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
            child: Align(
              alignment: Alignment.centerRight,
              child: FilledButton.icon(
                onPressed: () => unawaited(_record(context)),
                icon: const Icon(Icons.add),
                label: const Text('Record collection'),
              ),
            ),
          ),
        Expanded(
          child: _ListFrame<CollectionRecord>(
            future: _rows,
            emptyText: 'No collections match the current dashboard filters.',
            onRefresh: () async {
              setState(_reload);
              await _rows;
            },
            itemBuilder: (context, collection) => ListTile(
              leading: const Icon(Icons.payments_outlined),
              title: Text(collection.reference ?? 'Collection #${collection.id}'),
              subtitle: Text(
                '${collection.collectionDate} • ${collection.paymentMethodName ?? 'Method #${collection.paymentMethodId}'}',
              ),
              trailing: Text(collection.amount),
            ),
          ),
        ),
      ],
    );
  }

  Future<void> _record(BuildContext context) async {
    try {
      final methods = await widget.repository.paymentMethods();
      if (!context.mounted) return;
      if (methods.isEmpty) {
        _showError(context, const FormatException('No active payment methods are available.'));
        return;
      }

      final paymentController = TextEditingController(
        text: widget.filters.contractId == null ? '' : '',
      );
      final amountController = TextEditingController();
      final dateController = TextEditingController(text: _today());
      final referenceController = TextEditingController();
      var selectedMethod = methods.first.id;
      try {
        final saved = await showDialog<bool>(
          context: context,
          builder: (context) => StatefulBuilder(
            builder: (context, setState) => AlertDialog(
              title: const Text('Record collection'),
              content: SingleChildScrollView(
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    TextField(
                      controller: paymentController,
                      keyboardType: TextInputType.number,
                      decoration: const InputDecoration(labelText: 'Payment ID'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: amountController,
                      keyboardType: const TextInputType.numberWithOptions(decimal: true),
                      decoration: const InputDecoration(labelText: 'Amount'),
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: dateController,
                      decoration: const InputDecoration(labelText: 'Collection date YYYY-MM-DD'),
                    ),
                    const SizedBox(height: 12),
                    DropdownButtonFormField<int>(
                      initialValue: selectedMethod,
                      isExpanded: true,
                      decoration: const InputDecoration(labelText: 'Payment method'),
                      items: methods
                          .map(
                            (method) => DropdownMenuItem<int>(
                              value: method.id,
                              child: Text(method.name),
                            ),
                          )
                          .toList(growable: false),
                      onChanged: (value) {
                        if (value != null) setState(() => selectedMethod = value);
                      },
                    ),
                    const SizedBox(height: 12),
                    TextField(
                      controller: referenceController,
                      decoration: const InputDecoration(labelText: 'Reference (optional)'),
                    ),
                  ],
                ),
              ),
              actions: [
                TextButton(
                  onPressed: () => Navigator.of(context).pop(false),
                  child: const Text('Cancel'),
                ),
                FilledButton(
                  onPressed: () async {
                    final paymentId = int.tryParse(paymentController.text.trim()) ?? 0;
                    final amount = double.tryParse(amountController.text.trim()) ?? 0;
                    if (paymentId <= 0 || amount <= 0 || !_isDate(dateController.text.trim())) {
                      _showError(
                        context,
                        const FormatException('Payment, positive amount and valid date are required.'),
                      );
                      return;
                    }
                    try {
                      await widget.repository.recordCollection(
                        paymentId: paymentId,
                        amount: amountController.text,
                        collectionDate: dateController.text,
                        paymentMethodId: selectedMethod,
                        reference: referenceController.text,
                      );
                      if (context.mounted) Navigator.of(context).pop(true);
                    } on Object catch (error) {
                      if (context.mounted) _showError(context, error);
                    }
                  },
                  child: const Text('Record'),
                ),
              ],
            ),
          ),
        );
        if (saved == true && mounted) {
          setState(_reload);
          await _rows;
        }
      } finally {
        paymentController.dispose();
        amountController.dispose();
        dateController.dispose();
        referenceController.dispose();
      }
    } on Object catch (error) {
      if (!context.mounted) return;
      _showError(context, error);
    }
  }
}

final class ExcelExportScreen extends StatefulWidget {
  const ExcelExportScreen({
    required this.repository,
    required this.filters,
    super.key,
  });

  final MobileOperationsRepository repository;
  final DashboardFilters filters;

  @override
  State<ExcelExportScreen> createState() => _ExcelExportScreenState();
}

final class _ExcelExportScreenState extends State<ExcelExportScreen> {
  bool _working = false;
  ExcelExportPayload? _payload;
  String? _savedPath;
  String? _error;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(24),
      children: [
        Text('Excel export', style: Theme.of(context).textTheme.headlineSmall),
        const SizedBox(height: 8),
        const Text(
          'The workbook is generated by the SafeContracts WordPress backend using the current authorized dashboard filters.',
        ),
        const SizedBox(height: 20),
        FilledButton.icon(
          onPressed: _working ? null : () => unawaited(_export()),
          icon: const Icon(Icons.file_download_outlined),
          label: Text(_working ? 'Generating…' : 'Generate Excel'),
        ),
        if (_working) ...[
          const SizedBox(height: 12),
          const LinearProgressIndicator(),
        ],
        if (_error != null) ...[
          const SizedBox(height: 16),
          Text(_error!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
        ],
        if (_payload != null) ...[
          const SizedBox(height: 24),
          _KeyValueList(
            values: <String, String?>{
              'Filename': _payload!.filename,
              'Content type': _payload!.contentType,
              'Size': '${_payload!.bytes.length} bytes',
              'Rows': _payload!.rowCounts.entries
                  .map((entry) => '${entry.key}: ${entry.value}')
                  .join(', '),
              'Saved file': _savedPath,
            },
          ),
        ],
      ],
    );
  }

  Future<void> _export() async {
    setState(() {
      _working = true;
      _error = null;
      _savedPath = null;
    });
    try {
      final payload = await widget.repository.exportExcel(widget.filters);
      final safeName = payload.filename.replaceAll(RegExp(r'[^A-Za-z0-9._-]'), '_');
      final file = File('${Directory.systemTemp.path}/$safeName');
      await file.writeAsBytes(payload.bytes, flush: true);
      if (!mounted) return;
      setState(() {
        _payload = payload;
        _savedPath = file.path;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() => _error = error.toString());
    } finally {
      if (mounted) setState(() => _working = false);
    }
  }
}

final class _ListFrame<T> extends StatelessWidget {
  const _ListFrame({
    required this.future,
    required this.emptyText,
    required this.onRefresh,
    required this.itemBuilder,
  });

  final Future<List<T>> future;
  final String emptyText;
  final Future<void> Function() onRefresh;
  final Widget Function(BuildContext context, T item) itemBuilder;

  @override
  Widget build(BuildContext context) {
    return FutureBuilder<List<T>>(
      future: future,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorView(message: snapshot.error.toString(), onRetry: onRefresh);
        }
        final rows = snapshot.data ?? <T>[];
        return RefreshIndicator(
          onRefresh: onRefresh,
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: rows.isEmpty ? 1 : rows.length,
            itemBuilder: (context, index) {
              if (rows.isEmpty) {
                return Padding(
                  padding: const EdgeInsets.all(32),
                  child: Center(child: Text(emptyText, textAlign: TextAlign.center)),
                );
              }
              return itemBuilder(context, rows[index]);
            },
          ),
        );
      },
    );
  }
}

final class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

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
            const Icon(Icons.error_outline, size: 48),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: () => unawaited(onRetry()),
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

final class _KeyValueList extends StatelessWidget {
  const _KeyValueList({required this.values});

  final Map<String, String?> values;

  @override
  Widget build(BuildContext context) {
    final visible = values.entries.where((entry) => entry.value != null && entry.value!.isNotEmpty);
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      mainAxisSize: MainAxisSize.min,
      children: visible
          .map(
            (entry) => Padding(
              padding: const EdgeInsets.only(bottom: 8),
              child: Text('${entry.key}: ${entry.value}'),
            ),
          )
          .toList(growable: false),
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

String _today() {
  final now = DateTime.now();
  final month = now.month.toString().padLeft(2, '0');
  final day = now.day.toString().padLeft(2, '0');
  return '${now.year}-$month-$day';
}
