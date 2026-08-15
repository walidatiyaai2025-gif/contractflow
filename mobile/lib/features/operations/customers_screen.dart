import 'dart:async';

import 'package:flutter/material.dart';

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
    return FutureBuilder<List<CustomerRecord>>(
      future: _rows,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return _ErrorState(message: snapshot.error.toString(), onRetry: _refresh);
        }
        final rows = snapshot.data ?? const <CustomerRecord>[];
        return RefreshIndicator(
          onRefresh: _refresh,
          child: ListView.builder(
            physics: const AlwaysScrollableScrollPhysics(),
            itemCount: rows.isEmpty ? 1 : rows.length,
            itemBuilder: (context, index) {
              if (rows.isEmpty) {
                return const Padding(
                  padding: EdgeInsets.all(32),
                  child: Center(child: Text('No customers are available in your current scope.')),
                );
              }
              final customer = rows[index];
              return ListTile(
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
              );
            },
          ),
        );
      },
    );
  }

  Future<void> _refresh() async {
    setState(_reload);
    await _rows;
  }

  Future<void> _showCustomer(BuildContext context, int id) async {
    try {
      final customer = await widget.repository.customer(id);
      if (!context.mounted) return;
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: Text(customer.name),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <String>[
              if (customer.internalCode != null) 'Internal code: ${customer.internalCode}',
              if (customer.contactName != null) 'Contact: ${customer.contactName}',
              if (customer.email != null) 'Email: ${customer.email}',
              if (customer.phone != null) 'Phone: ${customer.phone}',
              'State: ${customer.isActive ? 'Active' : 'Inactive'}',
            ]
                .map(
                  (value) => Padding(
                    padding: const EdgeInsets.only(bottom: 8),
                    child: Text(value),
                  ),
                )
                .toList(growable: false),
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
      if (context.mounted) _showError(context, error);
    }
  }
}

final class _ErrorState extends StatelessWidget {
  const _ErrorState({required this.message, required this.onRetry});

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

void _showError(BuildContext context, Object error) {
  ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(error.toString())));
}
