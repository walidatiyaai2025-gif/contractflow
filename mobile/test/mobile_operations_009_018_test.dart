import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
import 'package:safecontracts_mobile/features/operations/operations_repository.dart';

import 'fake_api_transport.dart';

void main() {
  test('SC-P9-009 Excel export uses current server filters and decodes workbook', () async {
    final transport = FakeApiTransport(_handler);
    final repository = MobileOperationsRepository(_client(transport));

    final export = await repository.exportExcel(
      const DashboardFilters(customerId: 7, contractId: 11, status: 'overdue'),
    );

    expect(export.filename, 'safecontracts.xlsx');
    expect(export.bytes, <int>[80, 75, 3, 4]);
    expect(export.rowCounts['payments'], 2);
    final request = transport.requests.single;
    expect(request.uri.path, endsWith('/reports/excel'));
    expect(request.uri.queryParameters['customer_id'], '7');
    expect(request.uri.queryParameters['contract_id'], '11');
    expect(request.uri.queryParameters['status'], 'overdue');
  });

  test('SC-P9-010 customers list and detail preserve safe server fields', () async {
    final repository = MobileOperationsRepository(_client(FakeApiTransport(_handler)));

    final rows = await repository.customers(pageSize: 25);
    final detail = await repository.customer(7);

    expect(rows.single.name, 'Acme');
    expect(rows.single.internalCode, 'C7');
    expect(detail.email, 'ops@example.test');
    expect(detail.phone, '123');
  });

  test('SC-P9-011 contracts list propagates authorized dashboard filters', () async {
    final transport = FakeApiTransport(_handler);
    final repository = MobileOperationsRepository(_client(transport));

    final rows = await repository.contracts(
      const DashboardFilters(customerId: 7, status: 'active'),
      pageSize: 25,
    );

    expect(rows.single.contractNumber, 'SC-11');
    final request = transport.requests.single;
    expect(request.uri.queryParameters['customer_id'], '7');
    expect(request.uri.queryParameters['status'], 'active');
    expect(request.uri.queryParameters['sort'], 'id');
    expect(request.uri.queryParameters.containsKey('due_from'), isFalse);
  });

  test('SC-P9-012 contract detail keeps backend status and monetary strings', () async {
    final repository = MobileOperationsRepository(_client(FakeApiTransport(_handler)));

    final contract = await repository.contract(11);

    expect(contract.status, 'active');
    expect(contract.baseValue, '1000.1250');
    expect(contract.startDate, '2026-01-01');
    expect(contract.endDate, '2026-12-31');
  });

  test('SC-P9-013 contract light edits POST only supported fields', () async {
    final transport = FakeApiTransport(_handler);
    final repository = MobileOperationsRepository(_client(transport));

    await repository.editContractNumber(11, ' SC-11A ');
    await repository.editContractDates(11, '2026-01-01', '2026-12-31');

    expect(transport.requests, hasLength(2));
    expect(transport.requests.first.method, 'POST');
    expect(transport.requests.first.headers['Content-Type'], contains('application/json'));
    expect(
      jsonDecode(transport.requests.first.body!) as Map<String, dynamic>,
      <String, dynamic>{'contract_number': 'SC-11A'},
    );
    expect(
      jsonDecode(transport.requests.last.body!) as Map<String, dynamic>,
      <String, dynamic>{
        'start_date': '2026-01-01',
        'end_date': '2026-12-31',
      },
    );
  });

  test('SC-P9-014 payments list carries status and due filters server-side', () async {
    final transport = FakeApiTransport(_handler);
    final repository = MobileOperationsRepository(_client(transport));

    final rows = await repository.payments(
      const DashboardFilters(
        customerId: 7,
        contractId: 11,
        status: 'overdue',
        dueFrom: '2026-08-01',
        dueTo: '2026-08-31',
      ),
      pageSize: 25,
    );

    expect(rows.single.remainingAmount, '40.0000');
    final query = transport.requests.single.uri.queryParameters;
    expect(query['contract_id'], '11');
    expect(query['status'], 'overdue');
    expect(query['due_from'], '2026-08-01');
    expect(query['due_to'], '2026-08-31');
    expect(query['sort'], 'due_date');
  });

  test('SC-P9-015 payment detail keeps due and expected dates separate', () async {
    final repository = MobileOperationsRepository(_client(FakeApiTransport(_handler)));

    final payment = await repository.payment(21);

    expect(payment.dueDate, '2026-08-10');
    expect(payment.expectedPaymentDate, '2026-08-20');
    expect(payment.paidAmount, '60.0000');
    expect(payment.remainingAmount, '40.0000');
  });

  test('SC-P9-016 payment light edit only sends expected payment date', () async {
    final transport = FakeApiTransport(_handler);
    final repository = MobileOperationsRepository(_client(transport));

    await repository.updateExpectedPaymentDate(21, '2026-08-25');

    final request = transport.requests.single;
    expect(request.method, 'POST');
    expect(request.uri.path, endsWith('/payments/21/light-edit'));
    expect(
      jsonDecode(request.body!) as Map<String, dynamic>,
      <String, dynamic>{'expected_payment_date': '2026-08-25'},
    );
  });

  test('SC-P9-017 collection entry posts method and leaves proof optional', () async {
    final transport = FakeApiTransport(_handler);
    final repository = MobileOperationsRepository(_client(transport));

    final id = await repository.recordCollection(
      paymentId: 21,
      amount: '40.0000',
      collectionDate: '2026-08-15',
      paymentMethodId: 1,
      reference: 'COL-21',
    );

    expect(id, 31);
    final body = jsonDecode(transport.requests.single.body!) as Map<String, dynamic>;
    expect(body['payment_id'], 21);
    expect(body['payment_method_id'], 1);
    expect(body['amount'], '40.0000');
    expect(body.containsKey('proof_media_id'), isFalse);
  });

  test('SC-P9-018 payment-method lookup keeps active server master data only', () async {
    final repository = MobileOperationsRepository(_client(FakeApiTransport(_handler)));

    final methods = await repository.paymentMethods();

    expect(methods, hasLength(1));
    expect(methods.single.id, 1);
    expect(methods.single.code, 'cash');
    expect(methods.single.name, 'Cash');
  });
}

SafeContractsApiClient _client(SafeContractsTransport transport) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    ),
    transport: transport,
    headersProvider: () async => <String, String>{'X-Test-Session': 'opaque'},
  );
}

ApiTransportResponse _handler(Uri uri) {
  if (uri.path.endsWith('/reports/excel')) {
    return _ok(<String, Object?>{
      'filename': 'safecontracts.xlsx',
      'content_type': 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      'encoding': 'base64',
      'content_base64': base64Encode(<int>[80, 75, 3, 4]),
      'filters': <String, Object?>{},
      'row_counts': <String, Object?>{'contracts': 1, 'payments': 2},
    });
  }
  if (uri.path.endsWith('/customers/7')) {
    return _ok(_customer());
  }
  if (uri.path.endsWith('/customers')) {
    return _ok(<Object?>[_customer()]);
  }
  if (uri.path.endsWith('/contracts/11/light-edit')) {
    return _ok(<String, Object?>{'contract_id': 11});
  }
  if (uri.path.endsWith('/contracts/11')) {
    return _ok(_contract());
  }
  if (uri.path.endsWith('/contracts')) {
    return _ok(<Object?>[_contract()]);
  }
  if (uri.path.endsWith('/payments/21/light-edit')) {
    return _ok(<String, Object?>{
      'payment_id': 21,
      'due_date': '2026-08-10',
      'expected_payment_date': '2026-08-25',
    });
  }
  if (uri.path.endsWith('/payments/21')) {
    return _ok(_payment());
  }
  if (uri.path.endsWith('/payments')) {
    return _ok(<Object?>[_payment()]);
  }
  if (uri.path.endsWith('/collections')) {
    return _ok(<String, Object?>{'collection_id': 31});
  }
  if (uri.path.endsWith('/payment-methods')) {
    return _ok(<Object?>[
      <String, Object?>{
        'id': 1,
        'code': 'cash',
        'name': 'Cash',
        'display_order': 10,
        'is_active': true,
      },
      <String, Object?>{
        'id': 2,
        'code': 'old',
        'name': 'Old Method',
        'display_order': 20,
        'is_active': false,
      },
    ]);
  }
  return _error(404, 'not_found', 'Not found');
}

Map<String, Object?> _customer() {
  return <String, Object?>{
    'id': 7,
    'internal_code': 'C7',
    'name': 'Acme',
    'contact_name': 'Ops',
    'email': 'ops@example.test',
    'phone': '123',
    'is_active': true,
  };
}

Map<String, Object?> _contract() {
  return <String, Object?>{
    'id': 11,
    'contract_number': 'SC-11',
    'customer_id': 7,
    'customer_name': 'Acme',
    'accountant_user_id': 42,
    'status': 'active',
    'start_date': '2026-01-01',
    'end_date': '2026-12-31',
    'base_value': '1000.1250',
    'is_archived': false,
  };
}

Map<String, Object?> _payment() {
  return <String, Object?>{
    'id': 21,
    'contract_id': 11,
    'contract_number': 'SC-11',
    'customer_id': 7,
    'customer_name': 'Acme',
    'accountant_user_id': 42,
    'sequence_no': 1,
    'reference': 'P-1',
    'due_date': '2026-08-10',
    'expected_payment_date': '2026-08-20',
    'original_amount': '100.0000',
    'paid_amount': '60.0000',
    'remaining_amount': '40.0000',
    'status': 'overdue',
    'contract_is_archived': false,
  };
}

ApiTransportResponse _ok(Object? data) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': <String, Object?>{'api_version': 'v1'},
    }),
  );
}

ApiTransportResponse _error(int status, String code, String message) {
  return ApiTransportResponse(
    statusCode: status,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'code': code,
      'message': message,
      'data': <String, Object?>{'status': status},
    }),
  );
}
