import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';
import 'package:safecontracts_mobile/features/customers/customers.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_controller.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_repository.dart';
import 'package:safecontracts_mobile/features/export/mobile_excel_export.dart';
import 'package:safecontracts_mobile/features/navigation/navigation_policy.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';

import 'fake_api_transport.dart';

void main() {
  group('SC-P9-026 app architecture & API client validation', () {
    test('API environment cannot embed credentials or escape its v1 base', () {
      expect(
        () => AppEnvironment.fromValues(
          name: 'production',
          apiBaseUrl: 'https://user:pass@contracts.example.test/wp-json/safecontracts/v1/',
        ),
        throwsFormatException,
      );
      final environment = _environment();
      expect(
        () => environment.endpoint('https://evil.example.test/session'),
        throwsFormatException,
      );
      expect(
        () => environment.endpoint('//evil.example.test/session'),
        throwsFormatException,
      );
      expect(() => environment.endpoint('../session'), throwsFormatException);
    });

    test(
      'typed JSON mutations preserve canonical headers and body rules',
      () async {
        final transport = FakeApiTransport(
          (uri) => _ok(<String, Object?>{'updated': true}),
        );
        final client = _client(
          transport,
          headersProvider: () async => <String, String>{
            'Accept': 'text/plain',
            'X-SafeContracts-Session': 'opaque-test-session',
          },
        );
        await client.patch(
          'payments/21',
          body: <String, Object?>{'expected_payment_date': '2026-08-25'},
        );
        final request = transport.requests.single;
        expect(request.method, 'PATCH');
        expect(request.headers['Accept'], 'application/json');
        expect(
          request.headers['Content-Type'],
          'application/json; charset=utf-8',
        );
        expect(
          request.headers['X-SafeContracts-Session'],
          'opaque-test-session',
        );
        expect(
          jsonDecode(request.body!)['expected_payment_date'],
          '2026-08-25',
        );
        await expectLater(
          client.request('DELETE', 'payments/21', body: <String, Object?>{}),
          throwsFormatException,
        );
        expect(transport.requests, hasLength(1));
      },
    );

    test('invalid error envelopes and API versions fail closed', () async {
      final htmlTransport = FakeApiTransport(
        (uri) => const ApiTransportResponse(
          statusCode: 502,
          headers: <String, String>{'content-type': 'text/html'},
          body: '<html>upstream failure</html>',
        ),
      );
      await expectLater(
        _client(htmlTransport).get('session'),
        throwsA(
          isA<SafeContractsApiException>()
              .having((error) => error.statusCode, 'status', 502)
              .having(
                (error) => error.code,
                'code',
                'safecontracts_invalid_error_response',
              ),
        ),
      );
      final v2Transport = FakeApiTransport(
        (uri) => _ok(
          <String, Object?>{'ok': true},
          meta: <String, Object?>{'api_version': 'v2'},
        ),
      );
      await expectLater(
        _client(v2Transport).get('health'),
        throwsFormatException,
      );
    });
  });

  group('SC-P9-027 authentication/session validation', () {
    test(
      'authenticated=false cannot create a local authenticated session',
      () async {
        final controller = SessionController(
          _client(
            FakeApiTransport(
              (uri) => _ok(<String, Object?>{
                ..._sessionData(),
                'authenticated': false,
              }),
            ),
          ),
        );
        await controller.bootstrap();
        expect(controller.state, SessionState.error);
        expect(controller.session, isNull);
        controller.dispose();
      },
    );

    test('capability names and reset remain fail closed', () async {
      expect(
        () => SafeContractsSession.fromData(<String, Object?>{
          ..._sessionData(),
          'capabilities': <String, Object?>{'administrator': true},
        }),
        throwsFormatException,
      );
      final controller = SessionController(
        _client(FakeApiTransport((uri) => _ok(_sessionData()))),
      );
      await controller.bootstrap();
      expect(controller.state, SessionState.authenticated);
      controller.reset();
      expect(controller.state, SessionState.unauthenticated);
      expect(controller.session, isNull);
      expect(controller.errorMessage, isNull);
      controller.dispose();
    });
  });

  test('SC-P9-028 dynamic configuration uses safe disabled fallbacks', () {
    final config = SafeContractsMobileConfig.fromData(<String, Object?>{
      'support_text': List<String>.filled(501, 'x').join(),
      'default_page_size': 'not-a-number',
      'features': 'malformed',
      'firebase_service_account': 'server-secret-must-be-ignored',
      'access_token': 'must-be-ignored',
    });
    expect(config.supportText, isEmpty);
    expect(config.defaultPageSize, 25);
    expect(config.features.excelExport, isFalse);
    expect(config.features.pushNotifications, isFalse);
    expect(config.features.collectionEntry, isFalse);
  });

  test('SC-P9-029 navigation requires scope, capability and feature gates', () {
    const config = SafeContractsMobileConfig(
      supportText: '',
      defaultPageSize: 25,
      features: MobileFeatureFlags(
        excelExport: true,
        pushNotifications: true,
        collectionEntry: true,
      ),
    );
    const session = SafeContractsSession(
      userId: 42,
      scope: SafeContractsDataScope.none,
      capabilities: <String, bool>{
        'safecontracts_access': true,
        'safecontracts_view_reports': true,
        'safecontracts_export_reports': true,
        'safecontracts_manage_collections': true,
        'safecontracts_manage_followups': true,
      },
    );
    final policy = MobileNavigationPolicy.resolve(session, config);
    expect(policy.destinations, <MobileDestination>[MobileDestination.profile]);
    expect(policy.canEnterCollection, isFalse);
    expect(policy.canManageFollowUps, isFalse);
  });

  group('SC-P9-030 dashboard KPI validation', () {
    test('exact fixed-point strings remain server authoritative', () {
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
    });

    test('numeric or negative KPI payloads are rejected', () {
      expect(
        () => DashboardKpis.fromData(<String, Object?>{
          'contract_count': 1,
          'scheduled_total': 100.5,
          'remaining_total': '0.0000',
          'overdue_exposure': '0.0000',
          'collected_total': '100.5000',
        }),
        throwsFormatException,
      );
      expect(
        () => DashboardKpis.fromData(<String, Object?>{
          'contract_count': '-1',
          'scheduled_total': '0.0000',
          'remaining_total': '0.0000',
          'overdue_exposure': '0.0000',
          'collected_total': '0.0000',
        }),
        throwsFormatException,
      );
    });
  });

  group('SC-P9-031 customer dropdown validation', () {
    test('duplicate or non-positive customer options are rejected', () {
      expect(
        () => DashboardOverview.fromData(
          _dashboardData(
            customers: <Object?>[
              <String, Object?>{'id': 7, 'name': 'Alpha'},
              <String, Object?>{'id': 7, 'name': 'Duplicate'},
            ],
          ),
        ),
        throwsFormatException,
      );
      expect(
        () =>
            CustomerOption.fromData(<String, Object?>{'id': 0, 'name': 'Bad'}),
        throwsFormatException,
      );
    });

    test(
      'unknown customer selection is rejected before network access',
      () async {
        final transport = FakeApiTransport(_dashboardHandler);
        final controller = DashboardController(
          repository: DashboardRepository(_client(transport)),
          config: const SafeContractsMobileConfig.defaults(),
        );
        await expectLater(controller.selectCustomer(999), throwsArgumentError);
        expect(transport.requests, isEmpty);
        controller.dispose();
      },
    );
  });

  group('SC-P9-032 dependent contract dropdown validation', () {
    test('server contract option must match selected customer', () async {
      final transport = FakeApiTransport((uri) {
        if (uri.path.endsWith('/filters/contracts')) {
          return _ok(<Object?>[
            <String, Object?>{
              'id': 70,
              'contract_number': 'SC-70',
              'customer_id': 7,
            },
          ]);
        }
        return _error(404, 'not_found', 'Not found');
      });
      await expectLater(
        DashboardRepository(_client(transport)).loadContractOptions(8),
        throwsFormatException,
      );
    });

    test(
      'changing customer clears stale dependent options immediately',
      () async {
        final transport = FakeApiTransport(_dashboardHandler);
        final controller = DashboardController(
          repository: DashboardRepository(_client(transport)),
          config: const SafeContractsMobileConfig.defaults(),
        );
        await controller.load();
        expect(controller.availableContracts.single.id, 70);
        final pending = controller.selectCustomer(8);
        expect(controller.availableContracts, isEmpty);
        expect(controller.overview, isNull);
        expect(controller.lists, isNull);
        await pending;
        expect(controller.state, DashboardLoadState.ready);
        expect(controller.availableContracts.single.id, 80);
        controller.dispose();
      },
    );
  });

  group('SC-P9-033 dashboard filtered lists validation', () {
    test('invalid filters fail before any request', () async {
      final transport = FakeApiTransport(_dashboardHandler);
      final repository = DashboardRepository(_client(transport));
      await expectLater(
        repository.loadOverview(const DashboardFilters(customerId: 0)),
        throwsArgumentError,
      );
      await expectLater(
        repository.loadLists(
          const DashboardFilters(dueFrom: '2026-09-01', dueTo: '2026-08-01'),
          pageSize: 25,
        ),
        throwsArgumentError,
      );
      expect(transport.requests, isEmpty);
    });

    test(
      'numeric financial list payloads are rejected instead of recomputed',
      () async {
        final transport = FakeApiTransport((uri) {
          if (uri.path.endsWith('/contracts')) {
            return _ok(<Object?>[]);
          }
          if (uri.path.endsWith('/payments')) {
            return _ok(<Object?>[
              <String, Object?>{
                'id': 21,
                'reference': 'P-1',
                'status': 'overdue',
                'due_date': '2026-08-10',
                'remaining_amount': 40,
                'original_amount': '100.0000',
              },
            ]);
          }
          if (uri.path.endsWith('/collections') ||
              uri.path.endsWith('/followups')) {
            return _ok(<Object?>[]);
          }
          return _error(404, 'not_found', 'Not found');
        });
        await expectLater(
          DashboardRepository(_client(transport))
              .loadLists(const DashboardFilters(), pageSize: 25),
          throwsFormatException,
        );
      },
    );
  });

  group('SC-P9-034 mobile Excel export validation', () {
    test('requires XLSX signature and strips extra metadata', () {
      expect(
        () => MobileExcelExport.fromData(
          _exportData(bytes: const <int>[0x50, 0x4b, 0x01, 0x02, 0x03]),
        ),
        throwsFormatException,
      );
      final export = MobileExcelExport.fromData(
        _exportData(
          filters: <String, Object?>{
            'customer_id': 7,
            'accountant_user_id': 999,
            'storage_path': '/private/report.xlsx',
            'access_token': 'secret',
          },
        ),
      );
      expect(export.filters['customer_id'], 7);
      expect(export.filters.containsKey('accountant_user_id'), isFalse);
      expect(export.filters.containsKey('storage_path'), isFalse);
      expect(export.filters.containsKey('access_token'), isFalse);
    });

    test(
      'controller distinguishes forbidden, validation and network failures',
      () async {
        final forbidden = MobileExcelExportController(
          repository: MobileExcelExportRepository(
            _client(
              FakeApiTransport((uri) => _error(403, 'forbidden', 'No access')),
            ),
          ),
          filtersProvider: () => const DashboardFilters(),
          canExport: true,
          saver: _MemorySaver(),
        );
        await forbidden.downloadCurrentFilters();
        expect(forbidden.failureKind, ExcelExportFailureKind.forbidden);
        forbidden.dispose();

        final validation = MobileExcelExportController(
          repository: MobileExcelExportRepository(
            _client(
              FakeApiTransport((uri) => _error(422, 'invalid', 'Bad filter')),
            ),
          ),
          filtersProvider: () => const DashboardFilters(),
          canExport: true,
          saver: _MemorySaver(),
        );
        await validation.downloadCurrentFilters();
        expect(validation.failureKind, ExcelExportFailureKind.validation);
        validation.dispose();

        final network = MobileExcelExportController(
          repository: MobileExcelExportRepository(
            _client(const _FailingTransport()),
          ),
          filtersProvider: () => const DashboardFilters(),
          canExport: true,
          saver: _MemorySaver(),
        );
        await network.downloadCurrentFilters();
        expect(network.failureKind, ExcelExportFailureKind.network);
        network.dispose();
      },
    );
  });

  group('SC-P9-035 customers screen validation', () {
    test('customer page rejects duplicate rows and invalid scope metadata', () {
      expect(
        () => CustomerPage.fromEnvelope(
          _customerEnvelope(<Object?>[
            _customerData(7, 'Alpha'),
            _customerData(7, 'Duplicate'),
          ]),
        ),
        throwsFormatException,
      );
      expect(
        () => CustomerPage.fromEnvelope(
          _customerEnvelope(<Object?>[
            _customerData(7, 'Alpha'),
          ], scope: 'admin'),
        ),
        throwsFormatException,
      );
    });

    test(
      'invalid detail ID fails locally and private fields stay unmodeled',
      () async {
        final transport = FakeApiTransport(
          (uri) => _ok(<String, Object?>{
            ..._customerData(7, 'Alpha'),
            'notes': 'PRIVATE NOTE',
            'private_secret': 'must-not-be-modeled',
          }),
        );
        final controller = CustomersController(
          repository: CustomersRepository(_client(transport)),
          pageSize: 25,
          canAccess: true,
        );
        await controller.openCustomer(0);
        expect(controller.detailState, CustomerDetailLoadState.error);
        expect(controller.detailErrorMessage, contains('invalid'));
        expect(transport.requests, isEmpty);
        await controller.openCustomer(7);
        expect(controller.selectedCustomer?.id, 7);
        expect(controller.selectedCustomer?.name, 'Alpha');
        expect(transport.requests, hasLength(1));
        controller.dispose();
      },
    );
  });
}

AppEnvironment _environment() => AppEnvironment.fromValues(
  name: 'local',
  apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
);

SafeContractsApiClient _client(
  SafeContractsTransport transport, {
  ApiHeadersProvider? headersProvider,
}) => SafeContractsApiClient(
  environment: _environment(),
  transport: transport,
  headersProvider: headersProvider,
);

ApiTransportResponse _ok(
  Object? data, {
  Map<String, Object?> meta = const <String, Object?>{'api_version': 'v1'},
}) => ApiTransportResponse(
  statusCode: 200,
  headers: const <String, String>{'content-type': 'application/json'},
  body: jsonEncode(<String, Object?>{'data': data, 'meta': meta}),
);

ApiTransportResponse _error(int status, String code, String message) =>
    ApiTransportResponse(
      statusCode: status,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'code': code,
        'message': message,
        'data': <String, Object?>{'status': status},
      }),
    );

Map<String, Object?> _sessionData() => <String, Object?>{
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

Map<String, Object?> _dashboardData({
  List<Object?>? customers,
  List<Object?>? contracts,
}) => <String, Object?>{
  'filters': <String, Object?>{},
  'kpis': <String, Object?>{
    'contract_count': '1',
    'scheduled_total': '100.0000',
    'remaining_total': '40.0000',
    'overdue_exposure': '40.0000',
    'collected_total': '60.0000',
  },
  'customers':
      customers ??
      <Object?>[
        <String, Object?>{'id': 7, 'name': 'Alpha Customer'},
        <String, Object?>{'id': 8, 'name': 'Beta Customer'},
      ],
  'contracts':
      contracts ??
      <Object?>[
        <String, Object?>{
          'id': 70,
          'contract_number': 'SC-70',
          'customer_id': 7,
        },
      ],
};

ApiTransportResponse _dashboardHandler(Uri uri) {
  if (uri.path.endsWith('/filters/contracts')) {
    final customerId = uri.queryParameters['customer_id'];
    return _ok(<Object?>[
      <String, Object?>{
        'id': customerId == '8' ? 80 : 70,
        'contract_number': customerId == '8' ? 'SC-80' : 'SC-70',
        'customer_id': customerId == '8' ? 8 : 7,
      },
    ]);
  }
  if (uri.path.endsWith('/dashboard')) {
    final customerId = uri.queryParameters['customer_id'];
    return _ok(
      _dashboardData(
        contracts: <Object?>[
          <String, Object?>{
            'id': customerId == '8' ? 80 : 70,
            'contract_number': customerId == '8' ? 'SC-80' : 'SC-70',
            'customer_id': customerId == '8' ? 8 : 7,
          },
        ],
      ),
    );
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
  return _error(404, 'not_found', 'Not found');
}

const _xlsxBytes = <int>[0x50, 0x4b, 0x03, 0x04, 0x01, 0x02, 0x03];

Map<String, Object?> _exportData({
  List<int> bytes = _xlsxBytes,
  Map<String, Object?> filters = const <String, Object?>{'customer_id': 7},
}) => <String, Object?>{
  'filename': 'SafeContracts-report.xlsx',
  'content_type': MobileExcelExport.xlsxContentType,
  'encoding': 'base64',
  'content_base64': base64Encode(bytes),
  'filters': filters,
  'row_counts': <String, Object?>{
    'customers': 1,
    'contracts': 1,
    'payments': 1,
    'collections': 1,
    'followups': 1,
    'private_internal_rows': 999,
  },
};

Map<String, Object?> _customerData(int id, String name) => <String, Object?>{
  'id': id,
  'internal_code': 'C$id',
  'name': name,
  'contact_name': 'Operations',
  'email': 'ops@example.test',
  'phone': '+96555555555',
  'is_active': '1',
};

ApiEnvelope _customerEnvelope(
  List<Object?> customers, {
  String scope = 'assigned',
}) => ApiEnvelope(
  data: customers,
  meta: <String, Object?>{
    'api_version': 'v1',
    'scope': scope,
    'page': 1,
    'per_page': 25,
    'sort': 'name',
    'order': 'asc',
    'bounded_window': 500,
    'has_more': false,
  },
);

final class _MemorySaver implements ExcelExportSaver {
  @override
  Future<String> save(MobileExcelExport export) async =>
      '/memory/${export.filename}';
}

final class _FailingTransport implements SafeContractsTransport {
  const _FailingTransport();

  @override
  Future<ApiTransportResponse> send({
    required Uri uri,
    required String method,
    Map<String, String> headers = const <String, String>{},
    String? body,
  }) async {
    throw const SafeContractsTransportException('Network unavailable.');
  }
}
