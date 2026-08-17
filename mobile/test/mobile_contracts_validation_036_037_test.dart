import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/features/contracts/contracts.dart';

import 'support/safecontracts_test_harness.dart';

void main() {
  group('SC-P9-036 contracts list validation', () {
    test(
      'keeps opaque server status while requiring exact money/date fields',
      () {
        final contract = SafeContractsContract.fromData(_contractData());

        expect(contract.status, 'server_custom_status');
        expect(contract.baseValue, '1000.5000');
        expect(contract.startDate, '2026-01-01');
        expect(contract.endDate, '2026-12-31');

        expect(
          () => SafeContractsContract.fromData(<String, Object?>{
            ..._contractData(),
            'base_value': 1000.5,
          }),
          throwsFormatException,
        );
        expect(
          () => SafeContractsContract.fromData(<String, Object?>{
            ..._contractData(),
            'start_date': '2026-02-31',
          }),
          throwsFormatException,
        );
      },
    );

    test('rejects duplicate IDs and invalid scope metadata', () {
      expect(
        () => ContractPage.fromEnvelope(
          ApiEnvelope(
            data: <Object?>[_contractData(), _contractData()],
            meta: _pageMeta(),
          ),
        ),
        throwsFormatException,
      );
      expect(
        () => ContractPage.fromEnvelope(
          ApiEnvelope(
            data: <Object?>[_contractData()],
            meta: <String, Object?>{..._pageMeta(), 'scope': 'admin'},
          ),
        ),
        throwsFormatException,
      );
    });

    test('invalid filter is rejected before any request', () async {
      final harness = SafeContractsTestHarness(
        (uri) => SafeContractsTestHarness.ok(<Object?>[], meta: _pageMeta()),
      );
      final repository = ContractsRepository(harness.client);

      await expectLater(
        repository.loadPage(
          page: 1,
          perPage: 25,
          filters: const ContractsFilters(status: 'server_custom_status'),
          sort: ContractSortOption.newest,
        ),
        throwsArgumentError,
      );
      expect(harness.transport.requests, isEmpty);
    });
  });

  group('SC-P9-037 contract details validation', () {
    test('invalid local ID never performs direct-object request', () async {
      final harness = SafeContractsTestHarness(
        (uri) => SafeContractsTestHarness.ok(_contractData()),
      );
      final controller = ContractsController(
        repository: ContractsRepository(harness.client),
        pageSize: 25,
        canAccess: true,
        canEditContract: false,
      );

      await controller.openContract(0);

      expect(controller.detailState, ContractDetailLoadState.error);
      expect(controller.selectedContract, isNull);
      expect(harness.transport.requests, isEmpty);
      controller.dispose();
    });

    test(
      'fresh direct read preserves server values and maps 403/404',
      () async {
        final harness = SafeContractsTestHarness((uri) {
          if (uri.path.endsWith('/contracts/70')) {
            return SafeContractsTestHarness.ok(_contractData());
          }
          if (uri.path.endsWith('/contracts/71')) {
            return SafeContractsTestHarness.error(404, 'not_found', 'Missing');
          }
          if (uri.path.endsWith('/contracts/72')) {
            return SafeContractsTestHarness.error(
              403,
              'forbidden',
              'Forbidden',
            );
          }
          return SafeContractsTestHarness.error(500, 'server_error', 'Failed');
        });
        final controller = ContractsController(
          repository: ContractsRepository(harness.client),
          pageSize: 25,
          canAccess: true,
          canEditContract: true,
        );

        await controller.openContract(70);
        expect(controller.detailState, ContractDetailLoadState.ready);
        expect(controller.selectedContract?.status, 'server_custom_status');
        expect(controller.selectedContract?.baseValue, '1000.5000');
        expect(
          harness.transport.requests.last.uri.path,
          endsWith('/contracts/70'),
        );

        await controller.openContract(71);
        expect(controller.detailState, ContractDetailLoadState.notFound);
        expect(controller.selectedContract, isNull);

        await controller.openContract(72);
        expect(controller.detailState, ContractDetailLoadState.forbidden);
        expect(controller.selectedContract, isNull);
        controller.dispose();
      },
    );
  });
}

Map<String, Object?> _contractData() {
  return <String, Object?>{
    'id': 70,
    'contract_number': 'SC-70',
    'customer_id': 7,
    'customer_name': 'Alpha Customer',
    'accountant_user_id': 42,
    'status': 'server_custom_status',
    'start_date': '2026-01-01',
    'end_date': '2026-12-31',
    'base_value': '1000.5000',
    'is_archived': '0',
    'private_note': 'must-not-be-modeled',
  };
}

Map<String, Object?> _pageMeta() {
  return <String, Object?>{
    'api_version': 'v1',
    'scope': 'assigned',
    'page': 1,
    'per_page': 25,
    'sort': 'id',
    'order': 'desc',
    'bounded_window': 500,
    'has_more': false,
  };
}
