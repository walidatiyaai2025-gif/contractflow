import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

enum SuppliersLoadState { idle, loading, ready, error }

enum SupplierDetailLoadState { idle, loading, ready, error }

final class SafeContractsSupplier {
  const SafeContractsSupplier({
    required this.id,
    required this.internalCode,
    required this.legalName,
    required this.tradingName,
    required this.contactName,
    required this.phone,
    required this.email,
    required this.address,
    required this.countryCode,
    required this.registrationNumber,
    required this.taxNumber,
    required this.defaultCurrency,
    required this.paymentTerms,
    required this.status,
    required this.notes,
    required this.isArchived,
  });

  final int id;
  final String? internalCode;
  final String legalName;
  final String? tradingName;
  final String? contactName;
  final String? phone;
  final String? email;
  final String? address;
  final String? countryCode;
  final String? registrationNumber;
  final String? taxNumber;
  final String? defaultCurrency;
  final String? paymentTerms;
  final String status;
  final String? notes;
  final bool isArchived;

  String get displayName => tradingName ?? legalName;

  factory SafeContractsSupplier.fromData(Object? value) {
    final data = apiObjectMap(value, 'supplier');
    final status = _requiredText(data['status'], 'supplier.status').toLowerCase();
    if (!const <String>{'active', 'inactive', 'suspended', 'archived'}
        .contains(status)) {
      throw const FormatException('supplier.status is invalid.');
    }
    final currency = _optionalText(data['default_currency'])?.toUpperCase();
    if (currency != null && !RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
      throw const FormatException('supplier.default_currency is invalid.');
    }
    final country = _optionalText(data['country_code'])?.toUpperCase();
    if (country != null && !RegExp(r'^[A-Z]{2}$').hasMatch(country)) {
      throw const FormatException('supplier.country_code is invalid.');
    }

    return SafeContractsSupplier(
      id: _positiveInt(data['id'], 'supplier.id'),
      internalCode: _optionalText(data['internal_code']),
      legalName: _requiredText(data['legal_name'], 'supplier.legal_name'),
      tradingName: _optionalText(data['trading_name']),
      contactName: _optionalText(data['contact_name']),
      phone: _optionalText(data['phone']),
      email: _optionalText(data['email']),
      address: _optionalText(data['address']),
      countryCode: country,
      registrationNumber: _optionalText(data['registration_number']),
      taxNumber: _optionalText(data['tax_number']),
      defaultCurrency: currency,
      paymentTerms: _optionalText(data['payment_terms']),
      status: status,
      notes: _optionalText(data['notes']),
      isArchived: _boolish(data['is_archived'], 'supplier.is_archived'),
    );
  }
}

final class SupplierDraft {
  const SupplierDraft({
    required this.legalName,
    this.internalCode,
    this.tradingName,
    this.contactName,
    this.phone,
    this.email,
    this.address,
    this.countryCode,
    this.registrationNumber,
    this.taxNumber,
    this.defaultCurrency,
    this.paymentTerms,
    this.status = 'active',
    this.notes,
  });

  final String legalName;
  final String? internalCode;
  final String? tradingName;
  final String? contactName;
  final String? phone;
  final String? email;
  final String? address;
  final String? countryCode;
  final String? registrationNumber;
  final String? taxNumber;
  final String? defaultCurrency;
  final String? paymentTerms;
  final String status;
  final String? notes;

  Map<String, Object?> toPayload() => <String, Object?>{
        'legal_name': legalName.trim(),
        'internal_code': _payloadText(internalCode),
        'trading_name': _payloadText(tradingName),
        'contact_name': _payloadText(contactName),
        'phone': _payloadText(phone),
        'email': _payloadText(email),
        'address': _payloadText(address),
        'country_code': _payloadText(countryCode)?.toUpperCase(),
        'registration_number': _payloadText(registrationNumber),
        'tax_number': _payloadText(taxNumber),
        'default_currency': _payloadText(defaultCurrency)?.toUpperCase(),
        'payment_terms': _payloadText(paymentTerms),
        'status': status.trim().toLowerCase(),
        'notes': _payloadText(notes),
      };
}

final class SuppliersRepository {
  SuppliersRepository(this.client);

  final SafeContractsApiClient client;

  Future<List<SafeContractsSupplier>> search({
    String query = '',
    bool includeArchived = false,
    int limit = 100,
  }) async {
    final boundedLimit = limit.clamp(1, 200).toInt();
    final envelope = await client.get(
      'suppliers',
      query: <String, String>{
        if (query.trim().isNotEmpty) 'search': query.trim(),
        if (includeArchived) 'include_archived': '1',
        'limit': '$boundedLimit',
      },
    );
    final values = apiObjectList(envelope.data, 'suppliers.data');
    final suppliers =
        values.map(SafeContractsSupplier.fromData).toList(growable: false);
    final seen = <int>{};
    for (final supplier in suppliers) {
      if (!seen.add(supplier.id)) {
        throw const FormatException('Supplier list contains a duplicate ID.');
      }
    }
    return List<SafeContractsSupplier>.unmodifiable(suppliers);
  }

  Future<SafeContractsSupplier> loadSupplier(int id) async {
    _requireId(id);
    final envelope = await client.get('suppliers/$id');
    return SafeContractsSupplier.fromData(envelope.data);
  }

  Future<SafeContractsSupplier> create(SupplierDraft draft) async {
    _validateDraft(draft);
    final envelope = await client.post('suppliers', body: draft.toPayload());
    return SafeContractsSupplier.fromData(envelope.data);
  }

  Future<SafeContractsSupplier> update(int id, SupplierDraft draft) async {
    _requireId(id);
    _validateDraft(draft);
    final envelope = await client.patch(
      'suppliers/$id',
      body: draft.toPayload(),
    );
    return SafeContractsSupplier.fromData(envelope.data);
  }

  Future<void> archive(int id) async {
    _requireId(id);
    await client.post('suppliers/$id/archive');
  }

  void _validateDraft(SupplierDraft draft) {
    if (draft.legalName.trim().isEmpty) {
      throw ArgumentError('Supplier legal name is required.');
    }
    final currency = _payloadText(draft.defaultCurrency)?.toUpperCase();
    if (currency != null && !RegExp(r'^[A-Z]{3}$').hasMatch(currency)) {
      throw ArgumentError('Supplier currency must be a 3-letter code.');
    }
    final country = _payloadText(draft.countryCode)?.toUpperCase();
    if (country != null && !RegExp(r'^[A-Z]{2}$').hasMatch(country)) {
      throw ArgumentError('Supplier country must be a 2-letter code.');
    }
  }

  void _requireId(int id) {
    if (id <= 0) throw ArgumentError('Supplier ID must be positive.');
  }
}

final class SuppliersController extends ChangeNotifier {
  SuppliersController({
    required this.repository,
    required this.canAccess,
    required this.canCreate,
    required this.canEdit,
    required this.canArchive,
  });

  final SuppliersRepository repository;
  final bool canAccess;
  final bool canCreate;
  final bool canEdit;
  final bool canArchive;

  SuppliersLoadState state = SuppliersLoadState.idle;
  SupplierDetailLoadState detailState = SupplierDetailLoadState.idle;
  List<SafeContractsSupplier> suppliers = const <SafeContractsSupplier>[];
  SafeContractsSupplier? selectedSupplier;
  int? selectedSupplierId;
  String searchQuery = '';
  bool includeArchived = false;
  String? errorMessage;
  String? detailErrorMessage;
  bool mutationInFlight = false;

  Future<void> ensureLoaded() async {
    if (state == SuppliersLoadState.idle) await refresh();
  }

  Future<void> refresh() async {
    if (!canAccess) {
      suppliers = const <SafeContractsSupplier>[];
      errorMessage = 'Supplier access is not authorized for this session.';
      state = SuppliersLoadState.error;
      notifyListeners();
      return;
    }
    state = SuppliersLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      suppliers = await repository.search(
        query: searchQuery,
        includeArchived: includeArchived && canArchive,
        limit: 200,
      );
      state = SuppliersLoadState.ready;
    } on SafeContractsApiException catch (error) {
      errorMessage = error.message;
      state = SuppliersLoadState.error;
    } on Object catch (error) {
      errorMessage = error.toString();
      state = SuppliersLoadState.error;
    }
    notifyListeners();
  }

  Future<void> refreshSilently() async {
    if (!canAccess) return;
    try {
      final next = await repository.search(
        query: searchQuery,
        includeArchived: includeArchived && canArchive,
        limit: 200,
      );
      suppliers = next;
      if (selectedSupplierId != null) {
        final match = next.where((item) => item.id == selectedSupplierId);
        if (match.isNotEmpty) selectedSupplier = match.first;
      }
      state = SuppliersLoadState.ready;
      errorMessage = null;
    } on Object {
      // Silent refresh preserves the last good Supplier snapshot.
    }
  }

  Future<void> setSearch(String value) async {
    searchQuery = value.trim();
    await refresh();
  }

  Future<void> setIncludeArchived(bool value) async {
    if (!canArchive) return;
    includeArchived = value;
    await refresh();
  }

  Future<void> openSupplier(int id) async {
    if (!canAccess || id <= 0) return;
    selectedSupplierId = id;
    selectedSupplier = null;
    detailErrorMessage = null;
    detailState = SupplierDetailLoadState.loading;
    notifyListeners();
    try {
      final value = await repository.loadSupplier(id);
      if (selectedSupplierId != id) return;
      selectedSupplier = value;
      detailState = SupplierDetailLoadState.ready;
    } on SafeContractsApiException catch (error) {
      if (selectedSupplierId != id) return;
      detailErrorMessage = error.message;
      detailState = SupplierDetailLoadState.error;
    } on Object catch (error) {
      if (selectedSupplierId != id) return;
      detailErrorMessage = error.toString();
      detailState = SupplierDetailLoadState.error;
    }
    notifyListeners();
  }

  void closeSupplier() {
    selectedSupplierId = null;
    selectedSupplier = null;
    detailErrorMessage = null;
    detailState = SupplierDetailLoadState.idle;
    notifyListeners();
  }

  Future<SafeContractsSupplier> save({
    int? id,
    required SupplierDraft draft,
  }) async {
    if (id == null && !canCreate) {
      throw StateError('Supplier creation is not authorized.');
    }
    if (id != null && !canEdit) {
      throw StateError('Supplier editing is not authorized.');
    }
    mutationInFlight = true;
    notifyListeners();
    try {
      final saved = id == null
          ? await repository.create(draft)
          : await repository.update(id, draft);
      searchQuery = '';
      await refresh();
      await openSupplier(saved.id);
      return saved;
    } finally {
      mutationInFlight = false;
      notifyListeners();
    }
  }

  Future<void> archiveSelected() async {
    final id = selectedSupplierId;
    if (!canArchive || id == null) {
      throw StateError('Supplier archiving is not authorized.');
    }
    mutationInFlight = true;
    notifyListeners();
    try {
      await repository.archive(id);
      closeSupplier();
      await refresh();
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

String _requiredText(Object? value, String field) {
  if (value is! String || value.trim().isEmpty) {
    throw FormatException('$field must be a non-empty string.');
  }
  return value.trim();
}

String? _optionalText(Object? value) {
  if (value == null) return null;
  if (value is! String) {
    throw const FormatException('Supplier text field must be a string or null.');
  }
  final normalized = value.trim();
  return normalized.isEmpty ? null : normalized;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true || 1 || '1' => true,
    false || 0 || '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}

String? _payloadText(String? value) {
  final normalized = value?.trim();
  return normalized == null || normalized.isEmpty ? null : normalized;
}
