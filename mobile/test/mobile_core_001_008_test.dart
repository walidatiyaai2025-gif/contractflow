import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_controller.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_repository.dart';
import 'package:safecontracts_mobile/features/navigation/navigation_policy.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';

import 'fake_api_transport.dart';

void main() {
  group('SC-P9-001 API client', () {
    test('builds typed v1 GET request with injected session headers', () async {
      final transport = FakeApiTransport(
        (uri) => _ok(<String, Object?>{'ok': true}),
      );
      final client = _client(
        transport,
        headersProvider: () async => <String, String>{
          'X-SafeContracts-Session': 'opaque-test-session',
        },
      );

      final response = await client.get(
        'payments',
        query: const <String, String>{'sort': 'due_date', 'order': 'asc'},
      );

      expect(
        apiObjectMap(response.data, 'data')['ok'],
        isTrue,
      );
      expect(response.meta['api_version'], 'v1');
      expect(transport.requests, hasLength(1));
      final request = transport.requests.single;
      expect(request.method, 'GET');
      expect(request.uri.path, endsWith('/safecontracts/v1/payments'));
      expect(request.uri.queryParameters['sort'], 'due_date');
      expect(request.headers['Accept'], 'application/json');
      expect(
        request.headers['X-SafeContracts-Session'],
        'opaque-test-session',
      );
    });

    test('maps WordPress REST failures without exposing a second auth store',
        () async {
      final transport = FakeApiTransport(
        (uri) => _error(
          403,
          'safecontracts_forbidden',
          'You do not have access to SafeContracts.',
        ),
      );
      final client = _client(transport);

      await expectLater(
        client.get('session'),
        throwsA(
          isA<SafeContractsApiException>()
              .having((error) => error.statusCode, 'status', 403)
              .having(
                (error) => error.code,
                'code',
                'safecontracts_forbidden',
              ),
        ),
      );
    });
  });

  group('SC-P9-002 session', () {
    test('parses server capability and accountant scope metadata', () async {
      final transport = FakeApiTransport(
        (uri) => _ok(_sessionData()),
      );
      final controller = SessionController(_client(transport));

      await controller.bootstrap();

      expect(controller.state, SessionState.authenticated);
      expect(controller.session?.userId, 42);
      expect(controller.session?.scope, SafeContractsDataScope.assigned);
      expect(controller.session?.can('safecontracts_access'), isTrue);
      expect(controller.session?.can('safecontracts_view_all'), isFalse);
      controller.dispose();
    });

    test('maps forbidden bootstrap without retaining a session', () async {
      final transport = FakeApiTransport(
        (uri) => _error(403, 'safecontracts_forbidden', 'Forbidden'),
      );
      final controller = SessionController(_client(transport));

      await controller.bootstrap();

      expect(controller.state, SessionState.forbidden);
      expect(controller.session, isNull);
      controller.dispose();
    });
  });

  test('SC-P9-003 mobile config is typed, bounded and ignores unknown fields',
      () {
    final config = SafeContractsMobileConfig.fromData(<String, Object?>{
      'support_text': 'Support desk',
      'default_page_size': 200,
      'features': <String, Object?>{
        'excel_export': true,
        'push_notifications': true,
        'collection_entry': false,
        'future_flag': true,
      },
      'firebase_service_account': 'must-be-ignored',
    });

    expect(config.supportText, 'Support desk');
    expect(config.defaultPageSize, 100);
    expect(config.features.excelExport, isTrue);
    expect(config.features.pushNotifications, isTrue);
    expect(config.features.collectionEntry, isFalse);
  });

  test('SC-P9-004 navigation derives from capabilities instead of role names',
      () {
    final session = SafeContractsSession(
      userId: 42,
      scope: SafeContractsDataScope.assigned,
      capabilities: const <String, bool>{
        'safecontracts_access': true,
        'safecontracts_view_reports': true,
        'safecontracts_export_reports': true,
        'safecontracts_manage_collections': false,
        'safecontracts_manage_followups': true,
      },
    );
    const config = SafeContractsMobileConfig(
      supportText: '',
      defaultPageSize: 25,
      features: MobileFeatureFlags(
        excelExport: true,
        pushNotifications: false,
        collectionEntry: true,
      ),
    );

    final policy = MobileNavigationPolicy.resolve(session, config);

    expect(policy.destinations, contains(MobileDestination.dashboard));
    expect(policy.destinations, contains(MobileDestination.export));
    expect(policy.canEnterCollection, isFalse);
    expect(policy.canManageFollowUps, isTrue);
  });

  test('SC-P9-005 KPI values remain exact server strings', () {
    final kpis = DashboardKpis.fromData(<String, Object?>{
      'contract_count': '3',
      'scheduled_total': '1000.1250',
      'remaining_total': '250.1000',
      'overdue_exposure': '75.0500',
      'collected_total': '750.0250',
    });

    expect(kpis.contractCount, 3);
    expect(kpis.scheduledTotal, '1000.1250');
    expect(kpis.remainingTotal, '250.1000');
    expect(kpis.overdueExposure, '75.0500');
    expect(kpis.collectedTotal, '750.0250');
  });

  group('SC-P9-006..008 dashboard selectors and lists', () {
    test('customer change resets contract and loads dependent options',
        () async {
      final transport = FakeApiTransport(_dashboardHandler);
      final repository = DashboardRepository(_client(transport));
      final controller = DashboardController(
        repository: repository,
        config: const SafeContractsMobileConfig.defaults(),
      );

      await controller.load();
      await controller.selectContract(70);
      expect(controller.filters.contractId, 70);

      await controller.selectCustomer(8);

      expect(controller.filters.customerId, 8);
      expect(controller.filters.contractId, isNull);
      expect(controller.availableContracts.map((item) => item.id), <int>[80]);
      expect(
        transport.requests.any(
          (request) =>
              request.uri.path.endsWith('/filters/contracts') &&
              request.uri.queryParameters['customer_id'] == '8',
        ),
        isTrue,
      );
      controller.dispose();
    });

    test('contract and status filters propagate to server list requests',
        () async {
      final transport = FakeApiTransport(_dashboardHandler);
      final controller = DashboardController(
        repository: DashboardRepository(_client(transport)),
        config: const SafeContractsMobileConfig.defaults(),
      );

      await controller.load();
      await controller.selectCustomer(8);
      await controller.selectContract(80);
      await controller.selectStatus('overdue');

      final paymentRequests = transport.requests.where(
        (request) => request.uri.path.endsWith('/payments'),
      );
      final lastPayment = paymentRequests.last.uri.queryParameters;
      expect(lastPayment['customer_id'], '8');
      expect(lastPayment['contract_id'], '80');
      expect(lastPayment['status'], 'overdue');
      expect(lastPayment['sort'], 'due_date');
      expect(lastPayment['per_page'], '25');
      controller.dispose();
    });

    test('filtered list models preserve server status and balances', () async {
      final repository = DashboardRepository(
        _client(FakeApiTransport(_dashboardHandler)),
      );

      final lists = await repository.loadLists(
        const DashboardFilters(customerId: 7, status: 'overdue'),
        pageSize: 25,
      );

      expect(lists.payments.single.status, 'overdue');
      expect(lists.payments.single.remainingAmount, '40.0000');
      expect(lists.collections.single.amount, '60.0000');
      expect(lists.followUps.single.date, '2026-08-10');
    });
  });
}

SafeContractsApiClient _client(
  SafeContractsTransport transport, {
  ApiHeadersProvider? headersProvider,
}) {
  return SafeContractsApiClient(
    environment: AppEnvironment.fromValues(
      name: 'local',
      apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
    ),
    transport: transport,
    headersProvider: headersProvider,
  );
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

Map<String, Object?> _sessionData() {
  return <String, Object?>{
    'authenticated': true,
    'user_id': 42,
    'scope': 'assigned',
    'capabilities': <String, Object?>{
      'safecontracts_access': true,
      'safecontracts_view_assigned': true,
      'safecontracts_view_all': false,
      'safecontracts_view_reports': true,
      'safecontracts_export_reports': true,
    },
  };
}

ApiTransportResponse _dashboardHandler(Uri uri) {
  if (uri.path.endsWith('/session')) {
    return _ok(_sessionData());
  }
  if (uri.path.endsWith('/mobile-config')) {
    return _ok(<String, Object?>{
      'support_text': 'Support desk',
      'default_page_size': 25,
      'features': <String, Object?>{
        'excel_export': true,
        'push_notifications': false,
        'collection_entry': false,
      },
    });
  }
  if (uri.path.endsWith('/filters/contracts')) {
    final customerId = uri.queryParameters['customer_id'];
    return _ok(<Object?>[
      if (customerId == '8')
        <String, Object?>{
          'id': 80,
          'contract_number': 'SC-80',
          'customer_id': 8,
        }
      else
        <String, Object?>{
          'id': 70,
          'contract_number': 'SC-70',
          'customer_id': 7,
        },
    ]);
  }
  if (uri.path.endsWith('/dashboard')) {
    final customerId = uri.queryParameters['customer_id'];
    return _ok(<String, Object?>{
      'filters': <String, Object?>{},
      'kpis': <String, Object?>{
        'contract_count': '2',
        'scheduled_total': '500.0000',
        'remaining_total': '125.0000',
        'overdue_exposure': '75.0000',
        'collected_total': '375.0000',
      },
      'customers': <Object?>[
        <String, Object?>{'id': 7, 'name': 'Alpha Customer'},
        <String, Object?>{'id': 8, 'name': 'Beta Customer'},
      ],
      'contracts': <Object?>[
        if (customerId == '8')
          <String, Object?>{
            'id': 80,
            'contract_number': 'SC-80',
            'customer_id': 8,
          }
        else
          <String, Object?>{
            'id': 70,
            'contract_number': 'SC-70',
            'customer_id': 7,
          },
      ],
    });
  }
  if (uri.path.endsWith('/payments')) {
    return _ok(<Object?>[
      <String, Object?>{
        'id': 21,
        'reference': 'P-1',
        'status': 'overdue',
        'due_date': '2026-08-10',
        'customer_name': 'Alpha Customer',
        'original_amount': '100.0000',
        'remaining_amount': '40.0000',
      },
    ]);
  }
  if (uri.path.endsWith('/collections')) {
    return _ok(<Object?>[
      <String, Object?>{
        'id': 31,
        'reference': 'COL-1',
        'payment_status': 'overdue',
        'collection_date': '2026-08-01',
        'customer_name': 'Alpha Customer',
        'amount': '60.0000',
        'remaining_amount': '40.0000',
      },
    ]);
  }
  if (uri.path.endsWith('/followups')) {
    return _ok(<Object?>[
      <String, Object?>{
        'payment_id': 21,
        'reference': 'P-1',
        'status': 'overdue',
        'followup_state': 'contacted',
        'due_date': '2026-08-10',
        'remaining_amount': '40.0000',
      },
    ]);
  }
  if (uri.path.endsWith('/contracts')) {
    return _ok(<Object?>[
      <String, Object?>{
        'id': 70,
        'contract_number': 'SC-70',
        'status': 'active',
        'customer_name': 'Alpha Customer',
        'base_value': '100.0000',
      },
    ]);
  }
  return _error(404, 'not_found', 'Not found');
}
