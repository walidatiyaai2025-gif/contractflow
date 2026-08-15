import 'dart:async';

import 'package:flutter/material.dart';

import 'mobile_records.dart';
import 'mobile_records_repository.dart';

final class CustomersScreen extends StatefulWidget {
  const CustomersScreen({
    required this.repository,
    required this.pageSize,
    super.key,
  });

  final MobileRecordsRepository repository;
  final int pageSize;

  @override
  State<CustomersScreen> createState() => _CustomersScreenState();
}

final class _CustomersScreenState extends State<CustomersScreen> {
  bool _loading = true;
  String? _error;
  List<CustomerRecord> _customers = const <CustomerRecord>[];

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
      final customers = await widget.repository.customers(
        pageSize: widget.pageSize,
      );
      if (!mounted) return;
      setState(() {
        _customers = customers;
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

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_error != null) {
      return _ErrorState(message: _error!, onRetry: _load);
    }
    if (_customers.isEmpty) {
      return RefreshIndicator(
        onRefresh: _load,
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          children: const <Widget>[
            SizedBox(height: 180),
            Center(child: Text('No authorized customers found.')),
          ],
        ),
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: _customers.length,
        separatorBuilder: (_, __) => const SizedBox(height: 8),
        itemBuilder: (context, index) {
          final customer = _customers[index];
          return Card(
            child: ListTile(
              leading: CircleAvatar(
                child: Text(
                  customer.name.isEmpty
                      ? '?'
                      : customer.name.substring(0, 1).toUpperCase(),
                ),
              ),
              title: Text(customer.name),
              subtitle: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (customer.internalCode != null)
                    Text('Code: ${customer.internalCode}'),
                  if (customer.contactName != null)
                    Text('Contact: ${customer.contactName}'),
                  if (customer.phone != null) Text(customer.phone!),
                  if (customer.email != null) Text(customer.email!),
                ],
              ),
              trailing: const Icon(Icons.chevron_right),
              onTap: () => Navigator.of(context).push<void>(
                MaterialPageRoute<void>(
                  builder: (_) => CustomerDetailScreen(
                    repository: widget.repository,
                    customerId: customer.id,
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
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
            FilledButton.tonal(
              onPressed: () => unawaited(onRetry()),
              child: const Text('Retry'),
            ),
          ],
        ),
      ),
    );
  }
}

final class CustomerDetailScreen extends StatefulWidget {
  const CustomerDetailScreen({
    required this.repository,
    required this.customerId,
    super.key,
  });

  final MobileRecordsRepository repository;
  final int customerId;

  @override
  State<CustomerDetailScreen> createState() => _CustomerDetailScreenState();
}

final class _CustomerDetailScreenState extends State<CustomerDetailScreen> {
  bool _loading = true;
  String? _error;
  CustomerRecord? _customer;

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
      final customer = await widget.repository.customer(widget.customerId);
      if (!mounted) return;
      setState(() {
        _customer = customer;
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

  @override
  Widget build(BuildContext context) {
    final customer = _customer;
    return Scaffold(
      appBar: AppBar(title: const Text('Customer details')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : _error != null
              ? _ErrorState(message: _error!, onRetry: _load)
              : customer == null
                  ? const Center(child: Text('Customer not found.'))
                  : ListView(
                      padding: const EdgeInsets.all(24),
                      children: [
                        Text(
                          customer.name,
                          style: Theme.of(context).textTheme.headlineSmall,
                        ),
                        const SizedBox(height: 16),
                        if (customer.internalCode != null)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Code'),
                            subtitle: Text(customer.internalCode!),
                          ),
                        if (customer.contactName != null)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Contact'),
                            subtitle: Text(customer.contactName!),
                          ),
                        if (customer.phone != null)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Phone'),
                            subtitle: Text(customer.phone!),
                          ),
                        if (customer.email != null)
                          ListTile(
                            contentPadding: EdgeInsets.zero,
                            title: const Text('Email'),
                            subtitle: Text(customer.email!),
                          ),
                        ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: const Text('Active'),
                          subtitle: Text(customer.isActive ? 'Yes' : 'No'),
                        ),
                      ],
                    ),
    );
  }
}
