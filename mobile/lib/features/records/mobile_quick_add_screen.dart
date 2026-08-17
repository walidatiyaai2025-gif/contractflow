import 'dart:async';
import 'dart:convert';

import 'package:flutter/material.dart';

import '../../core/api/api_client.dart';
import '../../core/api/api_transport.dart';
import '../../core/localization/safecontracts_localizations.dart';
import '../contracts/contracts.dart';
import '../customers/customers.dart';
import '../session/session_controller.dart';
import 'mobile_quick_add_flow.dart' as legacy;

export 'mobile_quick_add_flow.dart' hide MobileQuickAddScreen;

/// Keeps the existing create-only flow, but adds a bounded paginated reference
/// gate whenever the authorized customer/contract scope extends beyond page 1.
///
/// The legacy flow deliberately requests a single page of up to 100 references.
/// This wrapper preserves that flow for the common case, while making pages 2-5
/// reachable without widening backend authority or changing create endpoints.
final class MobileQuickAddScreen extends StatefulWidget {
  const MobileQuickAddScreen({
    required this.client,
    required this.session,
    required this.type,
    super.key,
  });

  final SafeContractsApiClient client;
  final SafeContractsSession session;
  final legacy.MobileQuickAddType type;

  @override
  State<MobileQuickAddScreen> createState() => _MobileQuickAddScreenState();
}

final class _MobileQuickAddScreenState extends State<MobileQuickAddScreen> {
  static const _pageSize = 100;
  static const _maxPage = 5;

  bool _loading = true;
  String? _error;
  CustomerPage? _customerPage;
  ContractPage? _contractPage;
  SafeContractsApiClient? _flowClient;

  @override
  void initState() {
    super.initState();
    if (widget.type == legacy.MobileQuickAddType.customer) {
      _flowClient = widget.client;
      _loading = false;
    } else {
      unawaited(_prepareReferences());
    }
  }

  Future<void> _prepareReferences() async {
    if (widget.type == legacy.MobileQuickAddType.contract) {
      await _loadCustomerPage(1, initial: true);
      return;
    }
    await _loadContractPage(1, initial: true);
  }

  Future<void> _loadCustomerPage(int page, {bool initial = false}) async {
    if (page < 1 || page > _maxPage) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await CustomersRepository(widget.client).loadPage(
        page: page,
        perPage: _pageSize,
        order: 'asc',
      );
      if (!mounted) return;
      if (result.page == _maxPage && result.hasMore) {
        throw const FormatException(
          'Customer references exceed the supported bounded mobile window.',
        );
      }
      if (initial && !result.hasMore) {
        setState(() {
          _flowClient = widget.client;
          _customerPage = null;
          _loading = false;
        });
        return;
      }
      setState(() {
        _customerPage = result;
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

  Future<void> _loadContractPage(int page, {bool initial = false}) async {
    if (page < 1 || page > _maxPage) return;
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final result = await ContractsRepository(widget.client).loadPage(
        page: page,
        perPage: _pageSize,
        filters: const ContractsFilters(),
        sort: ContractSortOption.newest,
      );
      if (!mounted) return;
      if (result.page == _maxPage && result.hasMore) {
        throw const FormatException(
          'Contract references exceed the supported bounded mobile window.',
        );
      }
      if (initial && !result.hasMore) {
        setState(() {
          _flowClient = widget.client;
          _contractPage = null;
          _loading = false;
        });
        return;
      }
      setState(() {
        _contractPage = result;
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

  void _selectCustomer(SafeContractsCustomer customer) {
    setState(() {
      _flowClient = _clientPinnedTo(
        widget.client,
        suffix: '/customers',
        response: <String, Object?>{
          'data': <Object?>[
            <String, Object?>{
              'id': customer.id,
              'name': customer.name,
              'internal_code': customer.internalCode,
              'contact_name': customer.contactName,
              'email': customer.email,
              'phone': customer.phone,
              'is_active': customer.isActive,
            },
          ],
          'meta': <String, Object?>{
            'api_version': SafeContractsApiClient.apiVersion,
            'page': 1,
            'per_page': 1,
            'sort': 'name',
            'order': 'asc',
            'has_more': false,
            'bounded_window': 1,
          },
        },
      );
    });
  }

  void _selectContract(SafeContractsContract contract) {
    setState(() {
      _flowClient = _clientPinnedTo(
        widget.client,
        suffix: '/contracts',
        response: <String, Object?>{
          'data': <Object?>[
            <String, Object?>{
              'id': contract.id,
              'contract_number': contract.contractNumber,
              'customer_id': contract.customerId,
              'customer_name': contract.customerName,
              'accountant_user_id': contract.accountantUserId,
              'status': contract.status,
              'start_date': contract.startDate,
              'end_date': contract.endDate,
              'base_value': contract.baseValue,
              'is_archived': contract.isArchived,
            },
          ],
          'meta': <String, Object?>{
            'api_version': SafeContractsApiClient.apiVersion,
            'page': 1,
            'per_page': 1,
            'sort': 'id',
            'order': 'desc',
            'has_more': false,
            'bounded_window': 1,
          },
        },
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final flowClient = _flowClient;
    if (flowClient != null) {
      return legacy.MobileQuickAddScreen(
        client: flowClient,
        session: widget.session,
        type: widget.type,
      );
    }

    if (_loading) {
      return Scaffold(
        appBar: AppBar(
            title: Text(legacy.mobileQuickAddLabel(context, widget.type))),
        body: const Center(child: CircularProgressIndicator()),
      );
    }

    final error = _error;
    if (error != null) {
      return _ReferenceGateError(
        title: legacy.mobileQuickAddLabel(context, widget.type),
        message: error,
        onRetry: _prepareReferences,
      );
    }

    return switch (widget.type) {
      legacy.MobileQuickAddType.contract => _CustomerReferenceGate(
          page: _customerPage!,
          onPrevious: () => unawaited(
            _loadCustomerPage(_customerPage!.page - 1),
          ),
          onNext: () => unawaited(
            _loadCustomerPage(_customerPage!.page + 1),
          ),
          onSelected: _selectCustomer,
        ),
      legacy.MobileQuickAddType.payment => _ContractReferenceGate(
          page: _contractPage!,
          onPrevious: () => unawaited(
            _loadContractPage(_contractPage!.page - 1),
          ),
          onNext: () => unawaited(
            _loadContractPage(_contractPage!.page + 1),
          ),
          onSelected: _selectContract,
        ),
      legacy.MobileQuickAddType.customer => throw StateError(
          'Customer quick add must enter the existing flow directly.',
        ),
    };
  }
}

final class _CustomerReferenceGate extends StatelessWidget {
  const _CustomerReferenceGate({
    required this.page,
    required this.onPrevious,
    required this.onNext,
    required this.onSelected,
  });

  final CustomerPage page;
  final VoidCallback onPrevious;
  final VoidCallback onNext;
  final ValueChanged<SafeContractsCustomer> onSelected;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return _ReferenceGateScaffold(
      title: arabic ? 'اختر العميل' : 'Choose customer',
      subtitle: arabic
          ? 'نطاقك يحتوي أكثر من 100 عميل. اختر العميل من الصفحة الصحيحة ثم أكمل إنشاء العقد.'
          : 'Your scope contains more than 100 customers. Choose the customer from the correct page, then continue creating the contract.',
      page: page.page,
      canPrevious: page.page > 1,
      canNext: page.hasMore && page.page < _MobileQuickAddScreenState._maxPage,
      onPrevious: onPrevious,
      onNext: onNext,
      children: page.customers
          .map(
            (customer) => ListTile(
              leading: const Icon(Icons.business_outlined),
              title: Text(customer.name),
              subtitle: customer.internalCode == null
                  ? null
                  : Text(customer.internalCode!),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => onSelected(customer),
            ),
          )
          .toList(growable: false),
    );
  }
}

final class _ContractReferenceGate extends StatelessWidget {
  const _ContractReferenceGate({
    required this.page,
    required this.onPrevious,
    required this.onNext,
    required this.onSelected,
  });

  final ContractPage page;
  final VoidCallback onPrevious;
  final VoidCallback onNext;
  final ValueChanged<SafeContractsContract> onSelected;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return _ReferenceGateScaffold(
      title: arabic ? 'اختر العقد' : 'Choose contract',
      subtitle: arabic
          ? 'نطاقك يحتوي أكثر من 100 عقد. اختر العقد من الصفحة الصحيحة ثم أكمل إضافة الدفعة.'
          : 'Your scope contains more than 100 contracts. Choose the contract from the correct page, then continue adding the payment.',
      page: page.page,
      canPrevious: page.page > 1,
      canNext: page.hasMore && page.page < _MobileQuickAddScreenState._maxPage,
      onPrevious: onPrevious,
      onNext: onNext,
      children: page.contracts
          .map(
            (contract) => ListTile(
              leading: const Icon(Icons.description_outlined),
              title: Text(contract.contractNumber),
              subtitle: Text(
                contract.customerName == null
                    ? contract.status
                    : '${contract.customerName} • ${contract.status}',
              ),
              trailing: const Icon(Icons.chevron_right_rounded),
              onTap: () => onSelected(contract),
            ),
          )
          .toList(growable: false),
    );
  }
}

final class _ReferenceGateScaffold extends StatelessWidget {
  const _ReferenceGateScaffold({
    required this.title,
    required this.subtitle,
    required this.page,
    required this.canPrevious,
    required this.canNext,
    required this.onPrevious,
    required this.onNext,
    required this.children,
  });

  final String title;
  final String subtitle;
  final int page;
  final bool canPrevious;
  final bool canNext;
  final VoidCallback onPrevious;
  final VoidCallback onNext;
  final List<Widget> children;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: SafeArea(
        child: Column(
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(18, 12, 18, 8),
              child: Text(subtitle),
            ),
            Expanded(
              child: ListView.separated(
                padding: const EdgeInsets.symmetric(horizontal: 12),
                itemCount: children.length,
                separatorBuilder: (_, __) => const Divider(height: 1),
                itemBuilder: (context, index) => children[index],
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(12, 8, 12, 16),
              child: Row(
                children: [
                  OutlinedButton.icon(
                    onPressed: canPrevious ? onPrevious : null,
                    icon: const Icon(Icons.chevron_left_rounded),
                    label: Text(arabic ? 'السابق' : 'Previous'),
                  ),
                  const Spacer(),
                  Text(arabic ? 'الصفحة $page' : 'Page $page'),
                  const Spacer(),
                  OutlinedButton.icon(
                    onPressed: canNext ? onNext : null,
                    icon: const Icon(Icons.chevron_right_rounded),
                    label: Text(arabic ? 'التالي' : 'Next'),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _ReferenceGateError extends StatelessWidget {
  const _ReferenceGateError({
    required this.title,
    required this.message,
    required this.onRetry,
  });

  final String title;
  final String message;
  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    final arabic = context.scL10n.isArabic;
    return Scaffold(
      appBar: AppBar(title: Text(title)),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.cloud_off_outlined, size: 44),
              const SizedBox(height: 12),
              Text(message, textAlign: TextAlign.center),
              const SizedBox(height: 16),
              FilledButton.tonalIcon(
                onPressed: () => unawaited(onRetry()),
                icon: const Icon(Icons.refresh_rounded),
                label: Text(arabic ? 'إعادة المحاولة' : 'Retry'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

SafeContractsApiClient _clientPinnedTo(
  SafeContractsApiClient source, {
  required String suffix,
  required Map<String, Object?> response,
}) {
  return SafeContractsApiClient(
    environment: source.environment,
    transport: _PinnedReferenceTransport(
      delegate: source.transport,
      suffix: suffix,
      responseBody: jsonEncode(response),
    ),
    headersProvider: source.headersProvider,
  );
}

final class _PinnedReferenceTransport implements SafeContractsTransport {
  const _PinnedReferenceTransport({
    required this.delegate,
    required this.suffix,
    required this.responseBody,
  });

  final SafeContractsTransport delegate;
  final String suffix;
  final String responseBody;

  @override
  Future<ApiTransportResponse> send({
    required Uri uri,
    required String method,
    Map<String, String> headers = const <String, String>{},
    String? body,
  }) {
    if (method == 'GET' &&
        uri.path.endsWith(suffix) &&
        uri.queryParameters['page'] == '1' &&
        uri.queryParameters['per_page'] == '100') {
      return Future<ApiTransportResponse>.value(
        ApiTransportResponse(
          statusCode: 200,
          headers: const <String, String>{
            'content-type': 'application/json; charset=utf-8',
          },
          body: responseBody,
        ),
      );
    }
    return delegate.send(
      uri: uri,
      method: method,
      headers: headers,
      body: body,
    );
  }
}
