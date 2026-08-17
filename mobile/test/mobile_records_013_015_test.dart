import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/contracts/contract_edit_screen.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
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

  test('SC-P9-013 sends only supported contract light-edit fields', () async {
    final transport = FakeApiTransport(
      (uri) => _ok(<String, Object?>{'id': 11, 'updated': true}),
    );
    final controller = ContractEditController(
      client: _client(environment, transport),
      canEdit: true,
    );

    final saved = await controller.save(
      contractId: 11,
      contractNumber: 'SC-11A',
      updateDates: true,
      startDate: '2026-01-01',
      endDate: '2026-12-31',
    );

    expect(saved, isTrue);
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

  test(
    'SC-P9-013 surfaces forbidden and conflict responses distinctly',
    () async {
      final forbiddenTransport = FakeApiTransport(
        (uri) => _error(
          403,
          'safecontracts_contract_light_edit_forbidden',
          'Forbidden',
        ),
      );
      final forbidden = ContractEditController(
        client: _client(environment, forbiddenTransport),
        canEdit: true,
      );
      expect(
        await forbidden.save(
          contractId: 11,
          contractNumber: 'SC-11',
          updateDates: false,
        ),
        isFalse,
      );
      expect(forbidden.state, ContractEditState.forbidden);
      forbidden.dispose();

      final conflictTransport = FakeApiTransport(
        (uri) => _error(
          409,
          'safecontracts_contract_light_edit_conflict',
          'Archived contract cannot be edited.',
        ),
      );
      final conflict = ContractEditController(
        client: _client(environment, conflictTransport),
        canEdit: true,
      );
      expect(
        await conflict.save(
          contractId: 11,
          contractNumber: 'SC-11',
          updateDates: false,
        ),
        isFalse,
      );
      expect(conflict.state, ContractEditState.conflict);
      conflict.dispose();
    },
  );

  test(
    'SC-P9-014 payment list preserves filters and deterministic paging',
    () async {
      final transport = FakeApiTransport(
        (uri) => _ok(
          <Object?>[_payment()],
          meta: <String, Object?>{
            'api_version': 'v1',
            'page': 1,
            'per_page': 25,
            'has_more': false,
            'sort': 'due_date',
            'order': 'asc',
          },
        ),
      );
      final repository = PaymentsRepository(_client(environment, transport));

      final page = await repository.loadPage(
        page: 1,
        perPage: 25,
        filters: const DashboardFilters(
          customerId: 7,
          contractId: 11,
          status: 'overdue',
          dueFrom: '2026-08-01',
          dueTo: '2026-08-31',
        ),
      );

      expect(page.payments.single.remainingAmount, '40.0000');
      expect(page.payments.single.status, 'overdue');
      final query = transport.requests.single.uri.queryParameters;
      expect(query['customer_id'], '7');
      expect(query['contract_id'], '11');
      expect(query['status'], 'overdue');
      expect(query['due_from'], '2026-08-01');
      expect(query['due_to'], '2026-08-31');
      expect(query['sort'], 'due_date');
      expect(query['order'], 'asc');
    },
  );

  test(
    'SC-P9-015 detail keeps financial and status fields server-authoritative',
    () async {
      final transport = FakeApiTransport((uri) => _ok(_payment()));
      final repository = PaymentsRepository(_client(environment, transport));

      final detail = await repository.loadPayment(21);

      expect(detail.id, 21);
      expect(detail.dueDate, '2026-08-10');
      expect(detail.expectedPaymentDate, '2026-08-18');
      expect(detail.originalAmount, '100.0000');
      expect(detail.paidAmount, '60.0000');
      expect(detail.remainingAmount, '40.0000');
      expect(detail.status, 'overdue');
      expect(transport.requests.single.uri.path, endsWith('/payments/21'));
    },
  );
}

SafeContractsApiClient _client(
  AppEnvironment environment,
  SafeContractsTransport transport,
) {
  return SafeContractsApiClient(environment: environment, transport: transport);
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
