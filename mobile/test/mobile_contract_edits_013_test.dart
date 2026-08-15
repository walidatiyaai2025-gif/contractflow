import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/contracts/contract_edit_screen.dart';
import 'package:safecontracts_mobile/features/contracts/contracts.dart';

import 'fake_api_transport.dart';

void main() {
  test('SC-P9-013 sends one edit command then refreshes direct detail', () async {
    final transport = FakeApiTransport(_successHandler);
    final controller = _controller(transport, canEdit: true);

    final ok = await controller.editContractNumber(70, ' SC-70-REV ');

    expect(ok, isTrue);
    expect(controller.editState, ContractEditState.saved);
    expect(controller.selectedContract?.contractNumber, 'SC-70-REV');
    expect(transport.requests, hasLength(2));

    final command = transport.requests.first;
    expect(command.method, 'POST');
    expect(command.uri.path, endsWith('/contracts/70/edit'));
    expect(command.headers['Content-Type'], 'application/json');
    final body = jsonDecode(command.body!) as Map<String, dynamic>;
    expect(body, <String, dynamic>{
      'operation': 'contract_number',
      'contract_number': 'SC-70-REV',
    });

    expect(transport.requests.last.method, 'GET');
    expect(transport.requests.last.uri.path, endsWith('/contracts/70'));
    controller.dispose();
  });

  test('SC-P9-013 maps validation conflict and forbidden edit errors', () async {
    for (final entry in <int, ContractEditState>{
      400: ContractEditState.validationError,
      403: ContractEditState.forbidden,
      409: ContractEditState.conflict,
    }.entries) {
      final transport = FakeApiTransport(
        (uri) => uri.path.endsWith('/edit')
            ? _error(entry.key, 'edit_error', 'Edit rejected ${entry.key}.')
            : _detail('SC-70'),
      );
      final controller = _controller(transport, canEdit: true);

      final ok = await controller.editBaseValue(70, '150');

      expect(ok, isFalse);
      expect(controller.editState, entry.value);
      expect(controller.editErrorMessage, 'Edit rejected ${entry.key}.');
      expect(transport.requests, hasLength(1));
      controller.dispose();
    }
  });

  test('SC-P9-013 edit capability fails closed before network access', () async {
    final transport = FakeApiTransport(_successHandler);
    final controller = _controller(transport, canEdit: false);

    final ok = await controller.editStatus(70, 'completed');

    expect(ok, isFalse);
    expect(controller.editState, ContractEditState.forbidden);
    expect(transport.requests, isEmpty);
    controller.dispose();
  });

  test('SC-P9-013 date command sends both dates as one transaction', () async {
    final transport = FakeApiTransport(
      (uri) {
        if (uri.path.endsWith('/contracts/70/edit')) {
          return _ok(<String, Object?>{
            'contract_id': 70,
            'operation': 'dates',
          });
        }
        if (uri.path.endsWith('/contracts/70')) {
          return _detail('SC-70');
        }
        return _error(404, 'not_found', 'Not found');
      },
    );
    final controller = _controller(transport, canEdit: true);

    final ok = await controller.editDates(70, '2026-02-01', '2026-11-30');

    expect(ok, isTrue);
    final body = jsonDecode(transport.requests.first.body!) as Map<String, dynamic>;
    expect(body, <String, dynamic>{
      'operation': 'dates',
      'start_date': '2026-02-01',
      'end_date': '2026-11-30',
    });
    controller.dispose();
  });

  testWidgets('SC-P9-013 light-edit screen saves contract number command',
      (tester) async {
    final transport = FakeApiTransport(_successHandler);
    final controller = _controller(transport, canEdit: true);
    final contract = _contract('SC-70');

    await tester.pumpWidget(
      MaterialApp(
        home: ContractEditScreen(controller: controller, contract: contract),
      ),
    );
    await tester.pumpAndSettle();

    final numberField = find.widgetWithText(TextField, 'Contract number');
    expect(numberField, findsOneWidget);
    await tester.enterText(numberField, 'SC-70-REV');
    await tester.tap(find.text('Save contract number'));
    await tester.pumpAndSettle();

    expect(find.text('Contract number saved.'), findsOneWidget);
    final body = jsonDecode(transport.requests.first.body!) as Map<String, dynamic>;
    expect(body['operation'], 'contract_number');
    expect(body['contract_number'], 'SC-70-REV');

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump();
    controller.dispose();
  });
}

ContractsController _controller(
  SafeContractsTransport transport, {
  required bool canEdit,
}) {
  return ContractsController(
    repository: ContractsRepository(_client(transport)),
    pageSize: 25,
    canAccess: true,
    canEditContract: canEdit,
  );
}

SafeContractsApiClient _client(SafeContractsTransport transport) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    ),
    transport: transport,
  );
}

ApiTransportResponse _successHandler(Uri uri) {
  if (uri.path.endsWith('/contracts/70/edit')) {
    return _ok(<String, Object?>{
      'contract_id': 70,
      'operation': 'contract_number',
    });
  }
  if (uri.path.endsWith('/contracts/70')) {
    return _detail('SC-70-REV');
  }
  return _error(404, 'not_found', 'Not found');
}

ApiTransportResponse _detail(String contractNumber) => _ok(
      <String, Object?>{
        'id': 70,
        'contract_number': contractNumber,
        'customer_id': 7,
        'customer_name': 'Alpha Customer',
        'accountant_user_id': 42,
        'status': 'active',
        'start_date': '2026-01-01',
        'end_date': '2026-12-31',
        'base_value': '100.0000',
        'is_archived': '0',
      },
    );

SafeContractsContract _contract(String contractNumber) => SafeContractsContract(
      id: 70,
      contractNumber: contractNumber,
      customerId: 7,
      customerName: 'Alpha Customer',
      accountantUserId: 42,
      status: 'active',
      startDate: '2026-01-01',
      endDate: '2026-12-31',
      baseValue: '100.0000',
      isArchived: false,
    );

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
