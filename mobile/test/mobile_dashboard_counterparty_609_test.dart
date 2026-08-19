import 'dart:convert';
import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_repository.dart';
import 'package:safecontracts_mobile/features/payments/payments.dart';

import 'fake_api_transport.dart';

void main() {
  group('ALK-MOBILE #609 dashboard counterparty compatibility', () {
    test(
        'dashboard accepts production supplier contract option with customer 0',
        () async {
      final transport = FakeApiTransport((uri) {
        if (uri.path.endsWith('/dashboard')) {
          return _ok(<String, Object?>{
            'kpis': _kpis(),
            'customers': <Object?>[
              <String, Object?>{'id': 7, 'name': 'Customer Seven'},
            ],
            'contracts': <Object?>[
              <String, Object?>{
                'id': 70,
                'contract_number': 'CUS-70',
                'customer_id': 7,
                'counterparty_type': 'customer',
                'counterparty_id': 7,
                'counterparty_name': 'Customer Seven',
              },
              <String, Object?>{
                'id': 80,
                'contract_number': 'SUP-80',
                'customer_id': 0,
                'counterparty_type': 'supplier',
                'counterparty_id': 18,
                'counterparty_name': 'Supplier Eighteen',
              },
            ],
          });
        }
        return _error(404, 'not_found', 'Not found');
      });
      final repository = DashboardRepository(_client(transport));

      final overview = await repository.loadOverview(const DashboardFilters());

      expect(overview.contracts, hasLength(2));
      final supplier = overview.contracts.last;
      expect(supplier.id, 80);
      expect(supplier.customerId, isNull);
      expect(supplier.isSupplier, isTrue);
      expect(supplier.counterpartyId, 18);
      expect(supplier.counterpartyName, 'Supplier Eighteen');
    });

    test(
        'dependent contract options accept null empty and zero supplier bridge',
        () async {
      final sentinels = <Object?>[null, '', 0, '0'];
      for (final sentinel in sentinels) {
        final transport = FakeApiTransport((uri) {
          if (uri.path.endsWith('/filters/contracts')) {
            return _ok(<Object?>[
              <String, Object?>{
                'id': 81,
                'contract_number': 'SUP-81',
                'customer_id': sentinel,
                'counterparty_type': 'supplier',
                'counterparty_id': 19,
                'counterparty_name': 'Supplier Nineteen',
              },
            ]);
          }
          return _error(404, 'not_found', 'Not found');
        });
        final repository = DashboardRepository(_client(transport));

        final options = await repository.loadContractOptions(null);

        expect(options.single.customerId, isNull);
        expect(options.single.counterpartyType, 'supplier');
        expect(options.single.counterpartyId, 19);
      }
    });

    test('customer-filtered options fail closed on supplier leakage', () async {
      final repository = DashboardRepository(
        _client(
          FakeApiTransport((uri) {
            if (uri.path.endsWith('/filters/contracts')) {
              return _ok(<Object?>[
                <String, Object?>{
                  'id': 90,
                  'contract_number': 'SUP-90',
                  'customer_id': 0,
                  'counterparty_type': 'supplier',
                  'counterparty_id': 20,
                  'counterparty_name': 'Unexpected Supplier',
                },
              ]);
            }
            return _error(404, 'not_found', 'Not found');
          }),
        ),
      );

      await expectLater(
        repository.loadContractOptions(7),
        throwsA(isA<FormatException>()),
      );
    });

    test('customer option rejects conflicting legacy and canonical identities',
        () {
      expect(
        () => ContractOption.fromData(<String, Object?>{
          'id': 91,
          'contract_number': 'CUS-91',
          'customer_id': 7,
          'counterparty_type': 'customer',
          'counterparty_id': 8,
          'counterparty_name': 'Conflicting Customer',
        }),
        throwsA(isA<FormatException>()),
      );
    });

    test('dashboard activity resolves supplier counterparty name', () {
      final record = DashboardRecord.payment(<String, Object?>{
        'id': 501,
        'reference': 'AP-501',
        'status': 'due',
        'due_date': '2026-08-25',
        'customer_name': null,
        'supplier_name': 'Supplier Display Name',
        'counterparty_name': 'Canonical Supplier Name',
        'remaining_amount': '25.0000',
        'original_amount': '25.0000',
      });

      expect(record.customerName, 'Canonical Supplier Name');
    });

    test('payment model accepts zero customer bridge and preserves supplier',
        () {
      final payment = SafeContractsPayment.fromData(<String, Object?>{
        'id': 601,
        'contract_id': 80,
        'contract_number': 'SUP-80',
        'customer_id': 0,
        'customer_name': null,
        'supplier_name': 'Supplier Eighteen',
        'counterparty_name': 'Supplier Eighteen',
        'accountant_user_id': 42,
        'sequence_no': 1,
        'reference': 'AP-601',
        'due_date': '2026-08-30',
        'expected_payment_date': null,
        'original_amount': '100.0000',
        'paid_amount': '0.0000',
        'remaining_amount': '100.0000',
        'status': 'upcoming',
        'contract_is_archived': 0,
      });

      expect(payment.customerId, isNull);
      expect(payment.counterpartyName, 'Supplier Eighteen');
      expect(payment.displayOwner, 'Supplier Eighteen');
    });

    test('source audit keeps contract and payment customer bridges optional',
        () {
      final forbidden = <RegExp>[
        RegExp(
          r"_positiveInt\(\s*data\['customer_id'\]\s*,\s*'contract\.customer_id'",
        ),
        RegExp(
          r"_positiveInt\(\s*data\['customer_id'\]\s*,\s*'payment\.customer_id'",
        ),
      ];
      final offenders = <String>[];

      for (final entity in Directory('lib').listSync(
        recursive: true,
        followLinks: false,
      )) {
        if (entity is! File || !entity.path.endsWith('.dart')) continue;
        final source = entity.readAsStringSync();
        if (forbidden.any((pattern) => pattern.hasMatch(source))) {
          offenders.add(entity.path);
        }
      }

      expect(
        offenders,
        isEmpty,
        reason:
            'Legacy Customer bridges for contract/payment payloads must remain optional for Supplier/AP records.',
      );
    });
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

Map<String, Object?> _kpis() => <String, Object?>{
      'contract_count': 1,
      'scheduled_total': '100.0000',
      'remaining_total': '75.0000',
      'overdue_exposure': '0.0000',
      'collected_total': '25.0000',
    };

ApiTransportResponse _ok(Object? data) {
  return ApiTransportResponse(
    statusCode: 200,
    headers: const <String, String>{'content-type': 'application/json'},
    body: jsonEncode(<String, Object?>{
      'data': data,
      'meta': const <String, Object?>{},
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
