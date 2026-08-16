import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/contracts/contract_details_screen.dart';
import 'package:safecontracts_mobile/features/contracts/contracts.dart';

import 'fake_api_transport.dart';

void main() {
  test('SC-P9-012 direct detail preserves server status and base value',
      () async {
    final transport = FakeApiTransport(_detailHandler);
    final controller = _controller(transport, canEdit: true);

    await controller.openContract(70);

    expect(controller.detailState, ContractDetailLoadState.ready);
    expect(controller.selectedContractId, 70);
    final contract = controller.selectedContract!;
    expect(contract.contractNumber, 'SC-70');
    expect(contract.status, 'server_detail_status');
    expect(contract.baseValue, '1000.5000');
    expect(contract.customerName, 'Alpha Customer');
    expect(contract.accountantUserId, 42);
    expect(contract.isArchived, isFalse);
    expect(transport.requests, hasLength(1));
    expect(transport.requests.single.uri.path, endsWith('/contracts/70'));
    controller.dispose();
  });

  test('SC-P9-012 maps direct endpoint not-found distinctly', () async {
    final controller = _controller(FakeApiTransport(_detailHandler));

    await controller.openContract(404);

    expect(controller.detailState, ContractDetailLoadState.notFound);
    expect(controller.selectedContract, isNull);
    expect(controller.detailErrorMessage, 'Contract not found.');
    controller.dispose();
  });

  test('SC-P9-012 maps forbidden direct read distinctly', () async {
    final controller = _controller(FakeApiTransport(_detailHandler));

    await controller.openContract(403);

    expect(controller.detailState, ContractDetailLoadState.forbidden);
    expect(controller.selectedContract, isNull);
    expect(controller.detailErrorMessage, 'Contract access denied.');
    controller.dispose();
  });

  test('SC-P9-012 preserves generic API errors separately', () async {
    final controller = _controller(FakeApiTransport(_detailHandler));

    await controller.openContract(500);

    expect(controller.detailState, ContractDetailLoadState.error);
    expect(controller.selectedContract, isNull);
    expect(controller.detailErrorMessage, 'Contract service unavailable.');
    controller.dispose();
  });

  test('SC-P9-012 unauthorized detail fails before network access', () async {
    final transport = FakeApiTransport(_detailHandler);
    final controller = ContractsController(
      repository: ContractsRepository(_client(transport)),
      pageSize: 25,
      canAccess: false,
      canEditContract: true,
    );

    await controller.openContract(70);

    expect(controller.detailState, ContractDetailLoadState.forbidden);
    expect(controller.selectedContract, isNull);
    expect(transport.requests, isEmpty);
    controller.dispose();
  });

  testWidgets('SC-P9-012 renders direct detail and edit action by capability',
      (tester) async {
    final transport = FakeApiTransport(_detailHandler);
    final controller = _controller(transport, canEdit: true);
    int? editContractId;

    await tester.pumpWidget(
      MaterialApp(
        home: ContractDetailsScreen(
          controller: controller,
          contractId: 70,
          onEditContract: (id) => editContractId = id,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('SC-70'), findsWidgets);
    expect(find.text('server_detail_status'), findsWidgets);
    expect(find.text('1000.50'), findsOneWidget);
    expect(find.text('Alpha Customer'), findsOneWidget);
    expect(find.text('Edit contract'), findsOneWidget);

    await tester.ensureVisible(find.text('Edit contract'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Edit contract'));
    await tester.pump();
    expect(editContractId, 70);

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump();
    controller.dispose();
  });

  testWidgets('SC-P9-012 exposes responsible accountant action for assign-only',
      (tester) async {
    final controller = _controller(
      FakeApiTransport(_detailHandler),
      canEdit: false,
    );
    int? actionContractId;

    await tester.pumpWidget(
      MaterialApp(
        home: ContractDetailsScreen(
          controller: controller,
          contractId: 70,
          onEditContract: (id) => actionContractId = id,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Edit contract'), findsNothing);
    expect(find.text('Responsible accountant'), findsOneWidget);
    expect(
      find.text('This contract is read-only for the current session.'),
      findsNothing,
    );

    await tester.ensureVisible(find.text('Responsible accountant'));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Responsible accountant'));
    await tester.pump();
    expect(actionContractId, 70);

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump();
    controller.dispose();
  });

  testWidgets('SC-P9-012 hides edit mutation action without capability',
      (tester) async {
    final controller = _controller(
      FakeApiTransport(_detailHandler),
      canEdit: false,
    );

    await tester.pumpWidget(
      MaterialApp(
        home: ContractDetailsScreen(
          controller: controller,
          contractId: 70,
          onEditContract: null,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Edit contract'), findsNothing);
    expect(find.text('Responsible accountant'), findsNothing);
    expect(
      find.text('This contract is read-only for the current session.'),
      findsOneWidget,
    );

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump();
    controller.dispose();
  });

  testWidgets('SC-P9-012 shows explicit not-found state and retry',
      (tester) async {
    final controller = _controller(FakeApiTransport(_detailHandler));

    await tester.pumpWidget(
      MaterialApp(
        home: ContractDetailsScreen(
          controller: controller,
          contractId: 404,
          onEditContract: null,
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Contract not found'), findsOneWidget);
    expect(find.text('Contract not found.'), findsOneWidget);
    expect(find.text('Retry'), findsOneWidget);

    await tester.pumpWidget(const SizedBox.shrink());
    await tester.pump();
    controller.dispose();
  });
}

ContractsController _controller(
  SafeContractsTransport transport, {
  bool canEdit = false,
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

ApiTransportResponse _detailHandler(Uri uri) {
  if (uri.path.endsWith('/contracts/70')) {
    return _ok(<String, Object?>{
      'id': 70,
      'contract_number': 'SC-70',
      'customer_id': 7,
      'customer_name': 'Alpha Customer',
      'accountant_user_id': 42,
      'status': 'server_detail_status',
      'start_date': '2026-01-01',
      'end_date': '2026-12-31',
      'base_value': '1000.5000',
      'is_archived': '0',
      'private_note': 'MUST NOT BE MODELED BY MOBILE',
    });
  }
  if (uri.path.endsWith('/contracts/404')) {
    return _error(404, 'safecontracts_not_found', 'Contract not found.');
  }
  if (uri.path.endsWith('/contracts/403')) {
    return _error(403, 'safecontracts_forbidden', 'Contract access denied.');
  }
  if (uri.path.endsWith('/contracts/500')) {
    return _error(500, 'safecontracts_error', 'Contract service unavailable.');
  }
  return _error(404, 'not_found', 'Not found');
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
