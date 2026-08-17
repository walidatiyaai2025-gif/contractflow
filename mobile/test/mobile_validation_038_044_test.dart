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

  test(
    'SC-P9-038 contract light edit remains field and capability bounded',
    () async {
      final transport = FakeApiTransport(
        (uri) => _ok(<String, Object?>{'id': 11, 'updated': true}),
      );
      final denied = ContractEditController(
        client: _client(environment, transport),
        canEdit: false,
      );
      expect(
        await denied.save(
          contractId: 11,
          contractNumber: 'SC-11',
          updateDates: false,
        ),
        isFalse,
      );
      expect(transport.requests, isEmpty);
      denied.dispose();

      final allowed = ContractEditController(
        client: _client(environment, transport),
        canEdit: true,
      );
      expect(
        await allowed.save(
          contractId: 11,
          contractNumber: 'SC-11A',
          updateDates: true,
          startDate: '2026-01-01',
          endDate: '2026-12-31',
        ),
        isTrue,
      );
      final body =
          jsonDecode(transport.requests.single.body!) as Map<String, dynamic>;
      expect(
        body.keys,
        containsAll(<String>['contract_number', 'start_date', 'end_date']),
      );
      expect(body.containsKey('base_value'), isFalse);
      expect(body.containsKey('status'), isFalse);
      allowed.dispose();
    },
  );

  test(
    'SC-P9-039 payment list stays bounded filtered and deterministic',
    () async {
      final transport = FakeApiTransport(
        (uri) => _ok(<Object?>[
          _payment(),
        ], meta: _pageMeta(sort: 'due_date', order: 'asc')),
      );
      final repository = PaymentsRepository(_client(environment, transport));

      final page = await repository.loadPage(
        page: 1,
        perPage: 25,
        filters: const DashboardFilters(
          customerId: 7,
          contractId: 11,
          status: 'overdue',
        ),
      );

      expect(page.payments.single.remainingAmount, '40.0000');
      final query = transport.requests.single.uri.queryParameters;
      expect(query['customer_id'], '7');
      expect(query['contract_id'], '11');
      expect(query['status'], 'overdue');
      expect(query['sort'], 'due_date');
      expect(query['order'], 'asc');
      expect(query['page'], '1');
    },
  );

  test(
    'SC-P9-040 payment detail preserves server dates money and status',
    () async {
      final transport = FakeApiTransport((uri) => _ok(_payment()));
      final repository = PaymentsRepository(_client(environment, transport));

      final payment = await repository.loadPayment(21);

      expect(payment.dueDate, '2026-08-10');
      expect(payment.expectedPaymentDate, '2026-08-18');
      expect(payment.originalAmount, '100.0000');
      expect(payment.paidAmount, '60.0000');
      expect(payment.remainingAmount, '40.0000');
      expect(payment.status, 'overdue');
    },
  );

  test(
    'SC-P9-041 expected-date edit cannot submit contractual due date',
    () async {
      final transport = FakeApiTransport(
        (uri) => _ok(<String, Object?>{'id': 21, 'updated': true}),
      );
      final repository = PaymentsRepository(_client(environment, transport));

      await repository.updateExpectedPaymentDate(21, '2026-08-20');

      final request = transport.requests.single;
      expect(request.method, 'PATCH');
      expect(request.uri.path, endsWith('/payments/21/expected-date'));
      final body = jsonDecode(request.body!) as Map<String, dynamic>;
      expect(body, <String, Object?>{'expected_payment_date': '2026-08-20'});
      expect(body.containsKey('due_date'), isFalse);
      expect(body.containsKey('remaining_amount'), isFalse);
    },
  );

  test(
    'SC-P9-042 collection entry sends input only and no financial authority',
    () async {
      final transport = FakeApiTransport(
        (uri) => _created(<String, Object?>{
          'id': 31,
          'payment_id': 21,
          'recorded': true,
        }),
      );
      final repository = PaymentsRepository(_client(environment, transport));

      final receipt = await repository.recordCollection(
        paymentId: 21,
        amount: '10.1250',
        collectionDate: '2026-08-15',
        paymentMethodId: 4,
        reference: 'MOBILE-1',
        proofMediaId: 99,
      );

      expect(receipt.id, 31);
      final body =
          jsonDecode(transport.requests.single.body!) as Map<String, dynamic>;
      expect(body['amount'], '10.1250');
      expect(body['payment_method_id'], 4);
      expect(body['proof_media_id'], 99);
      expect(body.containsKey('remaining_amount'), isFalse);
      expect(body.containsKey('paid_amount'), isFalse);
      expect(body.containsKey('status'), isFalse);
    },
  );

  test(
    'SC-P9-043 payment methods are server supplied and duplicate-safe',
    () async {
      final transport = FakeApiTransport(
        (uri) => _ok(<String, Object?>{
          'payment_methods': <Object?>[
            <String, Object?>{
              'id': 4,
              'code': 'bank',
              'name': 'Bank transfer',
              'display_order': 10,
            },
            <String, Object?>{
              'id': 7,
              'code': 'cash',
              'name': 'Cash',
              'display_order': 20,
            },
          ],
        }),
      );
      final repository = PaymentsRepository(_client(environment, transport));

      final methods = await repository.paymentMethods();

      expect(methods.map((value) => value.id), <int>[4, 7]);
      expect(methods.map((value) => value.name), <String>[
        'Bank transfer',
        'Cash',
      ]);
      expect(transport.requests.single.uri.path, endsWith('/reference-data'));
    },
  );

  test(
    'SC-P9-044 follow-up workflow keeps operational state off balances',
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
              'created_at': '2026-08-15 10:00:00',
            },
          ], meta: _pageMeta(sort: 'created_at', order: 'desc'));
        }
        return _ok(<Object?>[
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
        ], meta: _pageMeta(sort: 'due_date', order: 'asc'));
      });
      final repository = FollowUpsRepository(_client(environment, transport));

      final queue = await repository.loadQueue(
        page: 1,
        perPage: 25,
        filters: const DashboardFilters(status: 'overdue'),
      );
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
      final body =
          jsonDecode(transport.requests.last.body!) as Map<String, dynamic>;
      expect(body['operation'], 'promise');
      expect(body['promised_date'], '2026-08-20');
      expect(body.containsKey('status'), isFalse);
      expect(body.containsKey('remaining_amount'), isFalse);
      expect(body.containsKey('due_date'), isFalse);
    },
  );
}

SafeContractsApiClient _client(
  AppEnvironment environment,
  SafeContractsTransport transport,
) {
  return SafeContractsApiClient(environment: environment, transport: transport);
}

Map<String, Object?> _pageMeta({required String sort, required String order}) {
  return <String, Object?>{
    'api_version': 'v1',
    'page': 1,
    'per_page': 25,
    'has_more': false,
    'sort': sort,
    'order': order,
  };
}

ApiTransportResponse _ok(Object? data, {Map<String, Object?>? meta}) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': meta ?? <String, Object?>{'api_version': 'v1'},
    }),
  );
}

ApiTransportResponse _created(Object? data) {
  return ApiTransportResponse(
    statusCode: 201,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': <String, Object?>{'api_version': 'v1'},
    }),
  );
}

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
