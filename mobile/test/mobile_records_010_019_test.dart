import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
import 'package:safecontracts_mobile/features/records/mobile_records_repository.dart';

import 'fake_api_transport.dart';

void main() {
  late AppEnvironment environment;

  setUp(() {
    environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'https://example.test/wp-json/safecontracts/v1/',
    );
  });

  test('SC-P9-010 customer list/detail stays bounded and server backed', () async {
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/customers/7')) {
        return _ok(_customer());
      }
      return _ok(<Object?>[_customer()]);
    });
    final repo = _repository(environment, transport);

    final rows = await repo.customers(pageSize: 500);
    final detail = await repo.customer(7);

    expect(rows.single.name, 'Acme');
    expect(detail.internalCode, 'C-7');
    expect(transport.requests.first.uri.queryParameters['per_page'], '100');
    expect(transport.requests.first.uri.queryParameters['sort'], 'name');
  });

  test('SC-P9-011..013 contract list detail and light edit are server backed',
      () async {
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/contracts/11/light')) {
        return _ok(<String, Object?>{'id': 11, 'updated': true});
      }
      if (uri.path.endsWith('/contracts/11')) {
        return _ok(_contract());
      }
      return _ok(<Object?>[_contract()]);
    });
    final repo = _repository(environment, transport);

    final list = await repo.contracts(
      const DashboardFilters(customerId: 7),
      pageSize: 25,
    );
    final detail = await repo.contract(11);
    await repo.editContractLight(
      11,
      contractNumber: 'SC-11A',
      updateDates: true,
      startDate: '2026-01-01',
      endDate: '2026-12-31',
    );

    expect(list.single.status, 'active');
    expect(detail.baseValue, '100.0000');
    final edit = transport.requests.last;
    expect(edit.method, 'PATCH');
    final body = jsonDecode(edit.body!) as Map<String, dynamic>;
    expect(body['contract_number'], 'SC-11A');
    expect(body['start_date'], '2026-01-01');
    expect(body.containsKey('base_value'), isFalse);
  });

  test('SC-P9-014..016 payment list detail/edit preserve server balances',
      () async {
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/payments/21/expected-date')) {
        return _ok(<String, Object?>{'id': 21, 'updated': true});
      }
      if (uri.path.endsWith('/payments/21')) {
        return _ok(_payment());
      }
      return _ok(<Object?>[_payment()]);
    });
    final repo = _repository(environment, transport);

    final list = await repo.payments(
      const DashboardFilters(contractId: 11, status: 'overdue'),
      pageSize: 25,
    );
    final detail = await repo.payment(21);
    await repo.updateExpectedPaymentDate(21, '2026-08-20');

    expect(list.single.remainingAmount, '40.0000');
    expect(detail.paidAmount, '60.0000');
    expect(detail.dueDate, '2026-08-10');
    expect(detail.expectedPaymentDate, '2026-08-18');
    final edit = transport.requests.last;
    expect(edit.method, 'PATCH');
    expect(
      (jsonDecode(edit.body!) as Map<String, dynamic>)['expected_payment_date'],
      '2026-08-20',
    );
  });

  test('SC-P9-017..018 collection entry uses active server payment methods',
      () async {
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/reference-data')) {
        return _ok(<String, Object?>{
          'payment_methods': <Object?>[
            <String, Object?>{
              'id': 1,
              'code': 'cash',
              'name': 'Cash',
              'display_order': 10,
            },
          ],
        });
      }
      return _created(<String, Object?>{
        'id': 31,
        'payment_id': 21,
        'recorded': true,
      });
    });
    final repo = _repository(environment, transport);

    final methods = await repo.paymentMethods();
    final receipt = await repo.recordCollection(
      paymentId: 21,
      amount: '10.0000',
      collectionDate: '2026-08-15',
      paymentMethodId: methods.single.id,
      reference: 'MOBILE-1',
    );

    expect(methods.single.name, 'Cash');
    expect(receipt.id, 31);
    final request = transport.requests.last;
    expect(request.method, 'POST');
    final body = jsonDecode(request.body!) as Map<String, dynamic>;
    expect(body['payment_method_id'], 1);
    expect(body['payment_id'], 21);
    expect(body.containsKey('remaining_amount'), isFalse);
  });

  test('SC-P9-019 follow-up queue/history and mutation stay server authoritative',
      () async {
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/payments/21/followups/record')) {
        return _created(<String, Object?>{
          'id': 502,
          'payment_id': 21,
          'operation': 'promise',
          'recorded': true,
        });
      }
      if (uri.path.endsWith('/payments/21/followups')) {
        return _ok(<Object?>[
          <String, Object?>{
            'id': 501,
            'payment_id': 21,
            'state': 'contacted',
            'note': 'Called customer',
            'promised_date': null,
            'deferred_until': null,
            'created_by': 42,
            'created_at': '2026-08-15 10:00:00',
          },
        ]);
      }
      return _ok(<Object?>[
        <String, Object?>{
          'payment_id': 21,
          'contract_id': 11,
          'customer_id': 7,
          'accountant_user_id': 42,
          'contract_status': 'active',
          'reference': 'P-1',
          'due_date': '2026-08-10',
          'expected_payment_date': '2026-08-18',
          'remaining_amount': '40.0000',
          'status': 'overdue',
          'followup_state': 'contacted',
        },
      ]);
    });
    final repo = _repository(environment, transport);

    final queue = await repo.followUps(
      const DashboardFilters(customerId: 7, status: 'overdue'),
      pageSize: 25,
    );
    final history = await repo.followUpHistory(21, pageSize: 25);
    final receipt = await repo.recordFollowUp(
      paymentId: 21,
      operation: 'promise',
      note: 'Customer committed',
      promisedDate: '2026-08-20',
    );

    expect(queue.single.remainingAmount, '40.0000');
    expect(history.single.note, 'Called customer');
    expect(receipt.id, 502);
    final request = transport.requests.last;
    expect(request.method, 'POST');
    final body = jsonDecode(request.body!) as Map<String, dynamic>;
    expect(body['operation'], 'promise');
    expect(body['promised_date'], '2026-08-20');
    expect(body.containsKey('status'), isFalse);
    expect(body.containsKey('remaining_amount'), isFalse);
  });
}

MobileRecordsRepository _repository(
  AppEnvironment environment,
  FakeApiTransport transport,
) {
  return MobileRecordsRepository(
    SafeContractsApiClient(environment: environment, transport: transport),
  );
}

ApiTransportResponse _ok(Object? data) => _response(200, data);
ApiTransportResponse _created(Object? data) => _response(201, data);

ApiTransportResponse _response(int status, Object? data) {
  return ApiTransportResponse(
    statusCode: status,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': <String, Object?>{'api_version': 'v1'},
    }),
  );
}

Map<String, Object?> _customer() => <String, Object?>{
      'id': 7,
      'name': 'Acme',
      'internal_code': 'C-7',
      'contact_name': 'Ops',
      'email': 'ops@example.test',
      'phone': '123',
      'is_active': true,
    };

Map<String, Object?> _contract() => <String, Object?>{
      'id': 11,
      'contract_number': 'SC-11',
      'customer_id': 7,
      'customer_name': 'Acme',
      'accountant_user_id': 42,
      'status': 'active',
      'start_date': '2026-01-01',
      'end_date': '2026-12-31',
      'base_value': '100.0000',
      'is_archived': false,
    };

Map<String, Object?> _payment() => <String, Object?>{
      'id': 21,
      'contract_id': 11,
      'contract_number': 'SC-11',
      'customer_id': 7,
      'customer_name': 'Acme',
      'accountant_user_id': 42,
      'sequence_no': 1,
      'reference': 'P-1',
      'due_date': '2026-08-10',
      'expected_payment_date': '2026-08-18',
      'original_amount': '100.0000',
      'paid_amount': '60.0000',
      'remaining_amount': '40.0000',
      'status': 'overdue',
      'contract_is_archived': false,
    };
