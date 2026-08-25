import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

enum CustomersLoadState { idle, loading, ready, error }

enum CustomerDetailLoadState { idle, loading, ready, error }

final class SafeContractsCustomer {
  const SafeContractsCustomer({
    required this.id,
    required this.name,
    required this.internalCode,
    required this.contactName,
    required this.email,
    required this.phone,
    required this.isActive,
  });

  final int id;
  final String name;
  final String? internalCode;
  final String? contactName;
  final String? email;
  final String? phone;
  final bool isActive;

  factory SafeContractsCustomer.fromData(Object? value) {
    final data = apiObjectMap(value, 'customer');
    return SafeContractsCustomer(
      id: _positiveInt(data['id'], 'customer.id'),
      name: _requiredText(data['name'], 'customer.name'),
      internalCode: _optionalText(data['internal_code']),
      contactName: _optionalText(data['contact_name']),
      email: _optionalText(data['email']),
      phone: _optionalText(data['phone']),
      isActive: _boolish(data['is_active'], 'customer.is_active'),
    );
  }
}

final class CustomerDraft {
  const CustomerDraft({
    required this.name,
    this.internalCode,
    this.contactName,
    this.email,
    this.phone,
    this.notes,
    this.isActive = true,
  });

  final String name;
  final String? internalCode;
  final String? contactName;
  final String? email;
  final String? phone;

  /// Notes are supported by the mutation API but are not exposed by the
  /// customer read projection. Callers must therefore omit notes on edit
  /// unless the user intentionally supplied a replacement value.
  final String? notes;
  final bool isActive;

  Map<String, Object?> toPayload({required bool includeNotes}) =>
      <String, Object?>{
        'name': name.trim(),
        'internal_code': _payloadText(internalCode),
        'contact_name': _payloadText(contactName),
        'email': _payloadText(email),
        'phone': _payloadText(phone),
        'is_active': isActive,
        if (includeNotes) 'notes': _payloadText(notes),
      };
}

final class CustomerPage {
  const CustomerPage({
    required this.customers,
    required this.page,
    required this.perPage,
    required this.sort,
    required this.order,
    required this.hasMore,
    required this.boundedWindow,
    required this.scope,
  });

  final List<SafeContractsCustomer> customers;
  final int page;
  final int perPage;
  final String sort;
  final String order;
  final bool hasMore;
  final int boundedWindow;
  final String? scope;

  factory CustomerPage.fromEnvelope(ApiEnvelope envelope) {
    final values = apiObjectList(envelope.data, 'customers.data');
    final customers =
        values.map(SafeContractsCustomer.fromData).toList(growable: false);
    final meta = envelope.meta;
    final page = _boundedInt(meta['page'], 'meta.page', minimum: 1, maximum: 5);
    final perPage = _boundedInt(
      meta['per_page'],
      'meta.per_page',
      minimum: 1,
      maximum: 100,
    );
    final boundedWindow = _boundedInt(
      meta['bounded_window'],
      'meta.bounded_window',
      minimum: 1,
      maximum: 500,
    );
    if (customers.length > perPage || customers.length > boundedWindow) {
      throw const FormatException(
        'Customer page contains more rows than its bounded metadata allows.',
      );
    }
    final seen = <int>{};
    for (final customer in customers) {
      if (!seen.add(customer.id)) {
        throw const FormatException('Customer page contains a duplicate ID.');
      }
    }
    final sort = _requiredText(meta['sort'], 'meta.sort');
    if (sort != 'name' && sort != 'id') {
      throw const FormatException('Customer sort metadata is invalid.');
    }
    final order = _requiredText(meta['order'], 'meta.order').toLowerCase();
    if (order != 'asc' && order != 'desc') {
      throw const FormatException('Customer order metadata is invalid.');
    }
    return CustomerPage(
      customers: List<SafeContractsCustomer>.unmodifiable(customers),
      page: page,
      perPage: perPage,
      sort: sort,
      order: order,
      hasMore: _boolish(meta['has_more'], 'meta.has_more'),
      boundedWindow: boundedWindow,
      scope: _scope(meta['scope']),
    );
  }
}

final class CustomersRepository {
  CustomersRepository(this.client);

  final SafeContractsApiClient client;

  Future<CustomerPage> loadPage({
    required int page,
    required int perPage,
    required String order,
  }) async {
    if (page < 1 || page > 5) {
      throw ArgumentError('Customer page must be between 1 and 5.');
    }
    if (perPage < 1 || perPage > 100) {
      throw ArgumentError('Customer page size must be between 1 and 100.');
    }
    if (order != 'asc' && order != 'desc') {
      throw ArgumentError('Customer order must be asc or desc.');
    }
    final envelope = await client.get(
      'customers',
      query: <String, String>{
        'page': '$page',
        'per_page': '$perPage',
        'sort': 'name',
        'order': order,
      },
    );
    return CustomerPage.fromEnvelope(envelope);
  }

  Future<SafeContractsCustomer> loadCustomer(int id) async {
    if (id <= 0) throw ArgumentError('Customer ID must be positive.');
    final envelope = await client.get('customers/$id');
    final customer = SafeContractsCustomer.fromData(envelope.data);
    if (customer.id != id) {
      throw const FormatException('Customer detail ID does not match request.');
    }
    return customer;
  }

  Future<SafeContractsCustomer> create(CustomerDraft draft) async {
    _validateDraft(draft);
    final envelope = await client.post(
      'mobile/customers/create',
      body: draft.toPayload(includeNotes: true),
    );
    final data = apiObjectMap(envelope.data, 'customer_create');
    final id = _positiveInt(data['id'], 'customer_create.id');
    return loadCustomer(id);
  }

  Future<SafeContractsCustomer> update(int id, CustomerDraft draft) async {
    if (id <= 0) throw ArgumentError('Customer ID must be positive.');
    _validateDraft(draft);
    await client.patch(
      'mobile/customers/$id/edit',
      // Notes are deliberately omitted because the read endpoint cannot
      // provide the current value and an edit must never erase unseen data.
      body: draft.toPayload(includeNotes: false),
    );
    return loadCustomer(id);
  }

  void _validateDraft(CustomerDraft draft) {
    final name = draft.name.trim();
    if (name.isEmpty || name.length > 191) {
      throw ArgumentError('Customer name is required and is too long.');
    }
    final email = _payloadText(draft.email);
    if (email != null && !email.contains('@')) {
      throw ArgumentError('Customer email is invalid.');
    }
  }
}

final class CustomersController extends ChangeNotifier {
  CustomersController({
    required this.repository,
    required int pageSize,
    required this.canAccess,
    this.canCreate = false,
    this.canEdit = false,
  }) : pageSize = pageSize.clamp(1, 100).toInt();

  final CustomersRepository repository;
  final int pageSize;
  final bool canAccess;
  final bool canCreate;
  final bool canEdit;

  CustomersLoadState state = CustomersLoadState.idle;
  CustomerDetailLoadState detailState = CustomerDetailLoadState.idle;
  CustomerPage? currentPage;
  String order = 'asc';
  String? errorMessage;
  String? detailErrorMessage;
  int? selectedCustomerId;
  SafeContractsCustomer? selectedCustomer;
  bool mutationInFlight = false;

  Future<void> ensureLoaded() async {
    if (state == CustomersLoadState.idle) await loadPage(1);
  }

  Future<void> loadPage(int page) async {
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Customer access is not authorized for this session.';
      state = CustomersLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || (page - 1) * pageSize > 1000000) return;
    final previous = currentPage;
    state = CustomersLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      final next = await repository.loadPage(
        page: page,
        perPage: pageSize,
        order: order,
      );
      if (page > 1 && previous != null) {
        final merged = <int, SafeContractsCustomer>{
          for (final item in previous.customers) item.id: item,
          for (final item in next.customers) item.id: item,
        };
        currentPage = CustomerPage(
          customers: List<SafeContractsCustomer>.unmodifiable(merged.values),
          page: next.page,
          perPage: next.perPage,
          total: next.total,
          totalPages: next.totalPages,
          hasMore: next.hasMore,
          scope: next.scope,
        );
      } else {
        currentPage = next;
      }
      state = CustomersLoadState.ready;
    } on SafeContractsApiException catch (error) {
      currentPage = previous;
      errorMessage = error.message;
      state = CustomersLoadState.error;
    } on Object catch (error) {
      currentPage = previous;
      errorMessage = error.toString();
      state = CustomersLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refresh() => loadPage(1);

  Future<void> previousPage() async {
    final page = currentPage?.page ?? 1;
    if (page > 1) await loadPage(page - 1);
  }

  Future<void> nextPage() async {
    final page = currentPage;
    if (page != null && page.hasMore) {
      await loadPage(page.page + 1);
    }
  }

  Future<void> setOrder(String nextOrder) async {
    if (nextOrder != 'asc' && nextOrder != 'desc') return;
    if (order == nextOrder && currentPage != null) return;
    order = nextOrder;
    await loadPage(1);
  }

  Future<void> openCustomer(int id) async {
    if (!canAccess || id <= 0) {
      selectedCustomerId = id > 0 ? id : null;
      selectedCustomer = null;
      detailErrorMessage =
          'Customer access is not authorized or ID is invalid.';
      detailState = CustomerDetailLoadState.error;
      notifyListeners();
      return;
    }
    selectedCustomerId = id;
    selectedCustomer = null;
    detailErrorMessage = null;
    detailState = CustomerDetailLoadState.loading;
    notifyListeners();
    try {
      final customer = await repository.loadCustomer(id);
      if (selectedCustomerId != id) return;
      selectedCustomer = customer;
      detailState = CustomerDetailLoadState.ready;
    } on SafeContractsApiException catch (error) {
      if (selectedCustomerId != id) return;
      detailErrorMessage = error.message;
      detailState = CustomerDetailLoadState.error;
    } on Object catch (error) {
      if (selectedCustomerId != id) return;
      detailErrorMessage = error.toString();
      detailState = CustomerDetailLoadState.error;
    }
    notifyListeners();
  }

  void closeCustomer() {
    selectedCustomerId = null;
    selectedCustomer = null;
    detailErrorMessage = null;
    detailState = CustomerDetailLoadState.idle;
    notifyListeners();
  }

  Future<SafeContractsCustomer> save({
    int? id,
    required CustomerDraft draft,
  }) async {
    if (id == null && !canCreate) {
      throw StateError('Customer creation is not authorized.');
    }
    if (id != null && !canEdit) {
      throw StateError('Customer editing is not authorized.');
    }
    mutationInFlight = true;
    notifyListeners();
    try {
      final saved = id == null
          ? await repository.create(draft)
          : await repository.update(id, draft);
      await loadPage(1);
      await openCustomer(saved.id);
      return saved;
    } finally {
      mutationInFlight = false;
      notifyListeners();
    }
  }
}

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) {
    throw FormatException('$field must be a positive integer.');
  }
  return parsed;
}

int _boundedInt(
  Object? value,
  String field, {
  required int minimum,
  required int maximum,
}) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed < minimum || parsed > maximum) {
    throw FormatException('$field is outside the supported range.');
  }
  return parsed;
}

String _requiredText(Object? value, String field) {
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('$field must be a non-empty string.');
  }
  return value.trim();
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException(
        'Customer text field must be a string or null.');
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

String? _payloadText(String? value) {
  final normalized = value?.trim();
  return normalized == null || normalized.isEmpty ? null : normalized;
}

String? _scope(Object? value) {
  final normalized = _optionalText(value);
  if (normalized == null) return null;
  if (normalized != 'all' && normalized != 'assigned') {
    throw const FormatException('Customer page scope metadata is invalid.');
  }
  return normalized;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true || 1 || '1' => true,
    false || 0 || '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
