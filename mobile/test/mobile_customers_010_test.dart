import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/customers/customers.dart';
import 'package:safecontracts_mobile/features/customers/customers_screen.dart';

import 'fake_api_transport.dart';

void main() {
  test('SC-P9-010 loads bounded customer page with server sort metadata',
      () async {
    final transport = FakeApiTransport(_customerHandler);
    final controller = CustomersController(
      repository: CustomersRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
    );

    await controller.ensureLoaded();

    expect(controller.state, CustomersLoadState.ready);
    expect(controller.currentPage?.page, 1);
    expect(controller.currentPage?.perPage, 25);
    expect(controller.currentPage?.sort, 'name');
    expect(controller.currentPage?.order, 'asc');
    expect(controller.currentPage?.hasMore, isTrue);
    expect(controller.currentPage?.boundedWindow, 500);
    expect(controller.currentPage?.scope, 'assigned');
    expect(controller.currentPage?.customers.single.name, 'Acme');

    final request = transport.requests.single;
    expect(request.uri.path, endsWith('/customers'));
    expect(request.uri.queryParameters['page'], '1');
    expect(request.uri.queryParameters['per_page'], '25');
    expect(request.uri.queryParameters['sort'], 'name');
    expect(request.uri.queryParameters['order'], 'asc');
    controller.dispose();
  });

  test('SC-P9-010 paging accumulates rows and order changes remain server-bound',
      () async {
    final transport = FakeApiTransport(_customerHandler);
    final controller = CustomersController(
      repository: CustomersRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
    );

    await controller.ensureLoaded();
    await controller.nextPage();

    expect(controller.currentPage?.page, 2);
    expect(controller.currentPage?.customers, hasLength(2));
    expect(controller.currentPage?.customers.last.name, 'Beta');
    expect(controller.currentPage?.hasMore, isFalse);

    await controller.setOrder('desc');

    expect(controller.currentPage?.page, 1);
    expect(controller.currentPage?.order, 'desc');
    expect(controller.currentPage?.customers.single.name, 'Zulu');
    final last = transport.requests.last.uri.queryParameters;
    expect(last['page'], '1');
    expect(last['sort'], 'name');
    expect(last['order'], 'desc');
    controller.dispose();
  });

  test('SC-P9-010 customer detail uses direct scoped endpoint and safe fields',
      () async {
    final transport = FakeApiTransport(_customerHandler);
    final controller = CustomersController(
      repository: CustomersRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
    );

    await controller.openCustomer(7);

    expect(controller.detailState, CustomerDetailLoadState.ready);
    expect(controller.selectedCustomer?.id, 7);
    expect(controller.selectedCustomer?.name, 'Acme');
    expect(controller.selectedCustomer?.internalCode, 'C7');
    expect(controller.selectedCustomer?.contactName, 'Operations');
    expect(controller.selectedCustomer?.email, 'ops@example.test');
    expect(controller.selectedCustomer?.phone, '+96555555555');
    expect(controller.selectedCustomer?.isActive, isTrue);
    expect(transport.requests.single.uri.path, endsWith('/customers/7'));
    controller.dispose();
  });

  test('SC-P9-010 unauthorized controller fails before network access',
      () async {
    final transport = FakeApiTransport(_customerHandler);
    final controller = CustomersController(
      repository: CustomersRepository(_client(transport)),
      pageSize: 25,
      canAccess: false,
    );

    await controller.ensureLoaded();
    await controller.openCustomer(7);

    expect(controller.state, CustomersLoadState.error);
    expect(controller.detailState, CustomerDetailLoadState.error);
    expect(transport.requests, isEmpty);
    controller.dispose();
  });

  testWidgets('SC-P9-010 screen lazy-loads list then direct customer detail',
      (tester) async {
    final transport = FakeApiTransport(_customerHandler);
    final controller = CustomersController(
      repository: CustomersRepository(_client(transport)),
      pageSize: 25,
      canAccess: true,
    );

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(body: CustomersScreen(controller: controller)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Acme'), findsOneWidget);
    expect(find.text('Active'), findsOneWidget);

    await tester.tap(find.text('Acme'));
    await tester.pumpAndSettle();

    expect(find.text('ops@example.test'), findsOneWidget);
    expect(find.text('+96555555555'), findsOneWidget);
    expect(
      transport.requests.any(
        (request) => request.uri.path.endsWith('/customers/7'),
      ),
      isTrue,
    );
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

ApiTransportResponse _customerHandler(Uri uri) {
  if (uri.path.endsWith('/customers/7')) {
    return _ok(<String, Object?>{
      'id': 7,
      'internal_code': 'C7',
      'name': 'Acme',
      'contact_name': 'Operations',
      'email': 'ops@example.test',
      'phone': '+96555555555',
      'is_active': '1',
      'notes': 'MUST NOT BE MODELED BY MOBILE',
    });
  }
  if (uri.path.endsWith('/customers')) {
    final page = int.parse(uri.queryParameters['page'] ?? '1');
    final order = uri.queryParameters['order'] ?? 'asc';
    final name = order == 'desc'
        ? 'Zulu'
        : page == 2
            ? 'Beta'
            : 'Acme';
    final id = page == 2 ? 8 : 7;
    return _ok(
      <Object?>[
        <String, Object?>{
          'id': id,
          'internal_code': id == 7 ? 'C7' : null,
          'name': name,
          'contact_name': id == 7 ? 'Operations' : '',
          'email': id == 7 ? 'ops@example.test' : '',
          'phone': id == 7 ? '+96555555555' : '',
          'is_active': '1',
          'notes': 'MUST NOT BE MODELED BY MOBILE',
        },
      ],
      meta: <String, Object?>{
        'api_version': 'v1',
        'scope': 'assigned',
        'page': page,
        'per_page': 25,
        'sort': 'name',
        'order': order,
        'returned': 1,
        'available_in_bounded_read': 2,
        'bounded_window': 500,
        'has_more': page == 1,
      },
    );
  }
  return _error(404, 'not_found', 'Not found');
}

ApiTransportResponse _ok(
  Object? data, {
  Map<String, Object?> meta = const <String, Object?>{
    'api_version': 'v1',
  },
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
