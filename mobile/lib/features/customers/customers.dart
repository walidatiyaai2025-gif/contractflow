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
      scope: _optionalText(meta['scope']),
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
    if (id <= 0) {
      throw ArgumentError('Customer ID must be positive.');
    }
    final envelope = await client.get('customers/$id');
    return SafeContractsCustomer.fromData(envelope.data);
  }
}

final class CustomersController extends ChangeNotifier {
  CustomersController({
    required this.repository,
    required int pageSize,
    required this.canAccess,
  }) : pageSize = pageSize.clamp(1, 100).toInt();

  final CustomersRepository repository;
  final int pageSize;
  final bool canAccess;

  CustomersLoadState state = CustomersLoadState.idle;
  CustomerDetailLoadState detailState = CustomerDetailLoadState.idle;
  CustomerPage? currentPage;
  String order = 'asc';
  String? errorMessage;
  String? detailErrorMessage;
  int? selectedCustomerId;
  SafeContractsCustomer? selectedCustomer;

  Future<void> ensureLoaded() async {
    if (state == CustomersLoadState.idle) {
      await loadPage(1);
    }
  }

  Future<void> loadPage(int page) async {
    if (!canAccess) {
      currentPage = null;
      errorMessage = 'Customer access is not authorized for this session.';
      state = CustomersLoadState.error;
      notifyListeners();
      return;
    }
    if (page < 1 || page > 5) {
      return;
    }

    state = CustomersLoadState.loading;
    errorMessage = null;
    notifyListeners();

    try {
      currentPage = await repository.loadPage(
        page: page,
        perPage: pageSize,
        order: order,
      );
      state = CustomersLoadState.ready;
    } on SafeContractsApiException catch (error) {
      errorMessage = error.message;
      state = CustomersLoadState.error;
    } on Object catch (error) {
      errorMessage = error.toString();
      state = CustomersLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refresh() async {
    await loadPage(currentPage?.page ?? 1);
  }

  Future<void> previousPage() async {
    final page = currentPage?.page ?? 1;
    if (page > 1) {
      await loadPage(page - 1);
    }
  }

  Future<void> nextPage() async {
    final value = currentPage;
    if (value != null && value.hasMore && value.page < 5) {
      await loadPage(value.page + 1);
    }
  }

  Future<void> setOrder(String nextOrder) async {
    if (nextOrder != 'asc' && nextOrder != 'desc') {
      return;
    }
    if (order == nextOrder && currentPage != null) {
      return;
    }
    order = nextOrder;
    await loadPage(1);
  }

  Future<void> openCustomer(int id) async {
    if (!canAccess || id <= 0) {
      selectedCustomerId = id > 0 ? id : null;
      selectedCustomer = null;
      detailErrorMessage = 'Customer access is not authorized.';
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
      if (selectedCustomerId != id) {
        return;
      }
      selectedCustomer = customer;
      detailState = CustomerDetailLoadState.ready;
    } on SafeContractsApiException catch (error) {
      if (selectedCustomerId != id) {
        return;
      }
      detailErrorMessage = error.message;
      detailState = CustomerDetailLoadState.error;
    } on Object catch (error) {
      if (selectedCustomerId != id) {
        return;
      }
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
}

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    int value => value,
    String value => int.tryParse(value),
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
    int value => value,
    String value => int.tryParse(value),
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
  if (value == null) {
    return null;
  }
  if (value is! String) {
    throw const FormatException(
        'Customer text field must be a string or null.');
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true => true,
    false => false,
    1 => true,
    0 => false,
    '1' => true,
    '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
