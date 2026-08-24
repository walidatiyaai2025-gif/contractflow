import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/contracts/contracts.dart';
import 'package:safecontracts_mobile/features/contracts/contracts_screen.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';

import 'fake_api_transport.dart';

void main() {
  test('SC-P9-011 loads bounded contract page with authoritative totals',
      () async {
    final transport = FakeApiTransport(_contractHandler);
    final controller = ContractsController(
      repository: ContractsRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
      canEditContract: false,
    );

    await controller.ensureLoaded();

    expect(controller.state, ContractsLoadState.ready);
    expect(controller.currentPage?.page, 1);
    expect(controller.currentPage?.perPage, 25);
    expect(controller.currentPage?.total, 50);
    expect(controller.currentPage?.totalPages, 2);
    expect(controller.currentPage?.sort, 'id');
    expect(controller.currentPage?.order, 'desc');
    expect(controller.currentPage?.hasMore, isTrue);
    expect(controller.currentPage?.boundedWindow, 500);
    expect(controller.currentPage?.scope, 'assigned');
    expect(controller.currentPage?.contracts.single.contractNumber, 'SC-70');

    final request = transport.requests.single;
    expect(request.uri.path, endsWith('/contracts'));
    expect(request.uri.queryParameters['page'], '1');
    expect(request.uri.queryParameters['per_page'], '25');
    expect(request.uri.queryParameters['sort'], 'id');
    expect(request.uri.queryParameters['order'], 'desc');
    controller.dispose();
  });

  test('SC-P9-011 customer status sort search and page remain server-bound',
      () async {
    final transport = FakeApiTransport(_contractHandler);
    final controller = ContractsController(
      repository: ContractsRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
      canEditContract: false,
    );

    await controller.ensureLoaded();
    await controller.selectCustomer(8);
    await controller.selectStatus('active');
    await controller.selectSearch('SC-80');
    await controller.selectSort(ContractSortOption.contractNumber);
    await controller.nextPage();

    final last = transport.requests.last.uri.queryParameters;
    expect(last['customer_id'], '8');
    expect(last['status'], 'active');
    expect(last['search'], 'SC-80');
    expect(last['sort'], 'contract_number');
    expect(last['order'], 'asc');
    expect(last['page'], '2');
    expect(controller.currentPage?.page, 2);
    controller.dispose();
  });

  test('B084 repeated Next taps cannot overlap page requests', () async {
    final transport = FakeApiTransport(_contractHandler);
    final controller = ContractsController(
      repository: ContractsRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
      canEditContract: false,
    );

    await controller.ensureLoaded();
    expect(transport.requests, hasLength(1));

    final first = controller.nextPage();
    final second = controller.nextPage();
    await Future.wait([first, second]);

    expect(transport.requests, hasLength(2));
    expect(transport.requests.last.uri.queryParameters['page'], '2');
    expect(controller.currentPage?.page, 2);
    expect(controller.currentPage?.hasMore, isFalse);
    expect(controller.pageRequestInFlight, isFalse);
    controller.dispose();
  });

  test('B084 retry reloads the requested page after a recoverable failure',
      () async {
    var attempts = 0;
    final transport = FakeApiTransport((uri) {
      attempts++;
      if (attempts == 1) {
        return _error(503, 'temporarily_unavailable', 'Try again');
      }
      return _contractHandler(uri);
    });
    final controller = ContractsController(
      repository: ContractsRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
      canEditContract: false,
    );

    await controller.ensureLoaded();
    expect(controller.state, ContractsLoadState.error);
    expect(controller.currentPage, isNull);

    await controller.refresh();
    expect(controller.state, ContractsLoadState.ready);
    expect(controller.currentPage?.page, 1);
    expect(transport.requests, hasLength(2));
    controller.dispose();
  });

  test('SC-P9-011 preserves server contract status and safe projection',
      () async {
    final controller = ContractsController(
      repository: ContractsRepository(
        _client(FakeApiTransport(_contractHandler)),
      ),
      pageSize: 25,
      canAccess: true,
      canEditContract: false,
    );

    await controller.ensureLoaded();

    final contract = controller.currentPage!.contracts.single;
    expect(contract.id, 70);
    expect(contract.contractNumber, 'SC-70');
    expect(contract.customerId, 7);
    expect(contract.customerName, 'Alpha Customer');
    expect(contract.accountantUserId, 42);
    expect(contract.status, 'server_custom_status');
    expect(contract.startDate, '2026-01-01');
    expect(contract.endDate, '2026-12-31');
    expect(contract.baseValue, '1000.5000');
    expect(contract.isArchived, isFalse);
    controller.dispose();
  });

  test('SC-P9-011 unauthorized controller fails before network access',
      () async {
    final transport = FakeApiTransport(_contractHandler);
    final controller = ContractsController(
      repository: ContractsRepository(_client(transport)),
      pageSize: 25,
      canAccess: false,
      canEditContract: false,
    );

    await controller.ensureLoaded();

    expect(controller.state, ContractsLoadState.error);
    expect(controller.currentPage, isNull);
    expect(transport.requests, isEmpty);
    controller.dispose();
  });

  testWidgets('SC-P9-011 screen lazy-loads and hands selected ID to details',
      (tester) async {
    final transport = FakeApiTransport(_contractHandler);
    final controller = ContractsController(
      repository: ContractsRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
      canEditContract: false,
    );
    int? openedContractId;

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ContractsScreen(
            controller: controller,
            customers: const <CustomerOption>[
              CustomerOption(id: 7, name: 'Alpha Customer'),
              CustomerOption(id: 8, name: 'Beta Customer'),
            ],
            onOpenContract: (id) => openedContractId = id,
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('SC-70'), findsOneWidget);
    expect(find.text('server_custom_status'), findsOneWidget);
    expect(find.text('1 / 2'), findsOneWidget);

    await tester.tap(find.text('SC-70'));
    await tester.pump();

    expect(openedContractId, 70);
    expect(transport.requests, hasLength(2));
    expect(transport.requests.first.uri.path, endsWith('/contracts'));
    expect(transport.requests.last.uri.path, endsWith('/contracts/70/media'));
    controller.dispose();
  });
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

ApiTransportResponse _contractHandler(Uri uri) {
  if (uri.path.endsWith('/contracts')) {
    final page = int.parse(uri.queryParameters['page'] ?? '1');
    return _ok(
      <Object?>[
        <String, Object?>{
          'id': page == 1 ? 70 : 80,
          'contract_number': page == 1 ? 'SC-70' : 'SC-80',
          'customer_id': page == 1 ? 7 : 8,
          'customer_name': page == 1 ? 'Alpha Customer' : 'Beta Customer',
          'accountant_user_id': 42,
          'status': 'server_custom_status',
          'start_date': '2026-01-01',
          'end_date': '2026-12-31',
          'base_value': '1000.5000',
          'is_archived': '0',
          'private_note': 'MUST NOT BE MODELED BY MOBILE',
        },
      ],
      meta: <String, Object?>{
        'api_version': 'v1',
        'scope': 'assigned',
        'page': page,
        'per_page': 25,
        'total': 50,
        'total_pages': 2,
        'sort': uri.queryParameters['sort'] ?? 'id',
        'order': uri.queryParameters['order'] ?? 'desc',
        'search': uri.queryParameters['search'] ?? '',
        'returned': 1,
        'available_in_bounded_read': 50,
        'bounded_window': 500,
        'has_more': page < 2,
      },
    );
  }
  return _error(404, 'not_found', 'Not found');
}

ApiTransportResponse _ok(
  Object? data, {
  required Map<String, Object?> meta,
}) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{'data': data, 'meta': meta}),
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
