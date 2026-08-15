import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/contracts/contract_edit_screen.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
import 'package:safecontracts_mobile/features/followups/followups.dart';
import 'package:safecontracts_mobile/features/payments/payments.dart';

import 'fake_api_transport.dart';

void main() {
  late AppEnvironment environment;

  setUp(() {
    environment = AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'https://example.test/wp-json/safecontracts/v1/',
    );
  });

  test('SC-P9-013 contract light edit sends supported fields only', () async {
    final transport = FakeApiTransport((uri) => _ok(<String, Object?>{'id': 11, 'updated': true}));
    final controller = ContractEditController(client: _client(environment, transport), canEdit: true);

    final saved = await controller.save(
      contractId: 11,
      contractNumber: 'SC-11A',
      updateDates: true,
      startDate: '2026-01-01',
      endDate: '2026-12-31',
    );

    expect(saved, isTrue);
    expect(controller.state, ContractEditState.saved);
    final request = transport.requests.single;
    expect(request.method, 'PATCH');
    final body = jsonDecode(request.body!) as Map<String, dynamic>;
    expect(body, <String, Object?>{
      'contract_number': 'SC-11A',
      'start_date': '2026-01-01',
      'end_date': '2026-12-31',
    });
    expect(body.containsKey('base_value'), isFalse);
    controller.dispose();
  });

  test('SC-P9-013 surfaces conflict distinctly', () async {
    final transport = FakeApiTransport((uri) => _error(409, 'safecontracts_contract_light_edit_conflict', 'Archived contract cannot be edited.'));
    final controller = ContractEditController(client: _client(environment, transport), canEdit: true);

    final saved = await controller.save(contractId: 11, contractNumber: 'SC-11', updateDates: false);

    expect(saved, isFalse);
    expect(controller.state, ContractEditState.conflict);
    controller.dispose();
  });

  test('SC-P9-014..016 payment paging/detail/edit preserve server authority', () async {
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/payments/21/expected-date')) return _ok(<String, Object?>{'id': 21, 'updated': true});
      if (uri.path.endsWith('/payments/21')) return _ok(_payment());
      return _ok(
        <Object?>[_payment()],
        meta: <String, Object?>{
          'api_version': 'v1',
          'page': 1,
          'per_page': 25,
          'has_more': false,
          'sort': 'due_date',
          'order': 'asc',
        },
      );
    });
    final repository = PaymentsRepository(_client(environment, transport));

    final page = await repository.loadPage(
      page: 1,
      perPage: 25,
      filters: const DashboardFilters(customerId: 7, contractId: 11, status: 'overdue'),
    );
    final detail = await repository.loadPayment(21);
    await repository.updateExpectedPaymentDate(21, '2026-08-20');

    expect(page.payments.single.remainingAmount, '40.0000');
    expect(page.payments.single.status, 'overdue');
    expect(detail.paidAmount, '60.0000');
    expect(detail.dueDate, '2026-08-10');
    expect(detail.expectedPaymentDate, '2026-08-18');
    expect(transport.requests.first.uri.queryParameters['sort'], 'due_date');
    expect(transport.requests.first.uri.queryParameters['order'], 'asc');
    expect(transport.requests.first.uri.queryParameters['customer_id'], '7');
    expect(transport.requests.first.uri.queryParameters['contract_id'], '11');
    final edit = transport.requests.last;
    expect(edit.method, 'PATCH');
    final editBody = jsonDecode(edit.body!) as Map<String, dynamic>;
    expect(editBody, <String, Object?>{'expected_payment_date': '2026-08-20'});
  });

  test('SC-P9-017..018 collection uses active server payment-method IDs', () async {
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/reference-data')) {
        return _ok(<String, Object?>{
          'payment_methods': <Object?>[
            <String, Object?>{'id': 4, 'code': 'bank', 'name': 'Bank transfer', 'display_order': 10},
          ],
        });
      }
      return _created(<String, Object?>{'id': 31, 'payment_id': 21, 'recorded': true});
    });
    final repository = PaymentsRepository(_client(environment, transport));

    final methods = await repository.paymentMethods();
    final receipt = await repository.recordCollection(
      paymentId: 21,
      amount: '10.0000',
      collectionDate: '2026-08-15',
      paymentMethodId: methods.single.id,
      reference: 'MOBILE-1',
      proofMediaId: 99,
    );

    expect(methods.single.name, 'Bank transfer');
    expect(receipt.id, 31);
    final request = transport.requests.last;
    expect(request.method, 'POST');
    final body = jsonDecode(request.body!) as Map<String, dynamic>;
    expect(body['payment_method_id'], 4);
    expect(body['payment_id'], 21);
    expect(body['proof_media_id'], 99);
    expect(body.containsKey('remaining_amount'), isFalse);
  });

  test('SC-P9-019 follow-up queue/history/mutation do not mutate financial state', () async {
    final transport = FakeApiTransport((uri) {
      if (uri.path.endsWith('/payments/21/followups/record')) {
        return _created(<String, Object?>{'id': 502, 'payment_id': 21, 'operation': 'promise', 'recorded': true});
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
            'created_at': '2026-08-15 10:00:00',
          },
        ]);
      }
      return _ok(
        <Object?>[
          <String, Object?>{
            'payment_id': 21,
            'contract_id': 11,
            'reference': 'P-1',
            'due_date': '2026-08-10',
            'expected_payment_date': '2026-08-18',
            'remaining_amount': '40.0000',
            'status': 'overdue',
            'followup_state': 'contacted',
          },
        ],
        meta: <String, Object?>{'api_version': 'v1', 'page': 1, 'per_page': 25, 'has_more': false, 'sort': 'due_date', 'order': 'asc'},
      );
    });
    final repository = FollowUpsRepository(_client(environment, transport));

    final queue = await repository.loadQueue(page: 1, perPage: 25, filters: const DashboardFilters(status: 'overdue'));
    final history = await repository.loadHistory(21, perPage: 25);
    final receipt = await repository.record(
      paymentId: 21,
      operation: 'promise',
      note: 'Customer committed',
      promisedDate: '2026-08-20',
    );

    expect(queue.items.single.remainingAmount, '40.0000');
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

SafeContractsApiClient _client(AppEnvironment environment, SafeContractsTransport transport) {
  return SafeContractsApiClient(environment: environment, transport: transport);
}

ApiTransportResponse _ok(Object? data, {Map<String, Object?>? meta}) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{'data': data, 'meta': meta ?? <String, Object?>{'api_version': 'v1'}}),
  );
}

ApiTransportResponse _created(Object? data) => ApiTransportResponse(
      statusCode: 201,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{'data': data, 'meta': <String, Object?>{'api_version': 'v1'}}),
    );

ApiTransportResponse _error(int status, String code, String message) => ApiTransportResponse(
      statusCode: status,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{'code': code, 'message': message, 'data': <String, Object?>{'status': status}}),
    );

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
