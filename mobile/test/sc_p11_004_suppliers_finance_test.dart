import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';
import 'package:safecontracts_mobile/features/contracts/contracts.dart';
import 'package:safecontracts_mobile/features/finance/finance.dart';
import 'package:safecontracts_mobile/features/navigation/navigation_policy.dart';
import 'package:safecontracts_mobile/features/records/mobile_quick_add_screen.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';

void main() {
  test('suppliers navigation requires the explicit supplier view capability',
      () {
    final withoutSupplier = MobileNavigationPolicy.resolve(
      _session(<String, bool>{'safecontracts_access': true}),
      const SafeContractsMobileConfig.defaults(),
    );
    final withSupplier = MobileNavigationPolicy.resolve(
      _session(<String, bool>{
        'safecontracts_access': true,
        'safecontracts_view_suppliers': true,
      }),
      const SafeContractsMobileConfig.defaults(),
    );

    expect(
      withoutSupplier.destinations,
      isNot(contains(MobileDestination.suppliers)),
    );
    expect(withSupplier.destinations, contains(MobileDestination.suppliers));
  });

  test('finance navigation accepts AP or AR independently', () {
    final none = MobileNavigationPolicy.resolve(
      _session(<String, bool>{'safecontracts_access': true}),
      const SafeContractsMobileConfig.defaults(),
    );
    final ap = MobileNavigationPolicy.resolve(
      _session(<String, bool>{
        'safecontracts_access': true,
        'safecontracts_view_payables': true,
      }),
      const SafeContractsMobileConfig.defaults(),
    );
    final ar = MobileNavigationPolicy.resolve(
      _session(<String, bool>{
        'safecontracts_access': true,
        'safecontracts_view_receivables': true,
      }),
      const SafeContractsMobileConfig.defaults(),
    );

    expect(none.destinations, isNot(contains(MobileDestination.finance)));
    expect(ap.destinations, contains(MobileDestination.finance));
    expect(ar.destinations, contains(MobileDestination.finance));
  });

  test('supplier quick add is exposed only by supplier create permission', () {
    expect(
      availableMobileQuickAdds(
        _session(<String, bool>{'safecontracts_create_customers': true}),
      ),
      isNot(contains(MobileQuickAddType.supplier)),
    );
    expect(
      availableMobileQuickAdds(
        _session(<String, bool>{'safecontracts_create_suppliers': true}),
      ),
      contains(MobileQuickAddType.supplier),
    );
  });

  test('supplier contract does not fabricate a legacy customer', () {
    final contract = SafeContractsContract.fromData(<String, Object?>{
      'id': 901,
      'contract_number': 'SUP-901',
      'customer_id': null,
      'customer_name': null,
      'counterparty_type': 'supplier',
      'counterparty_id': 77,
      'counterparty_name': 'Kuwait Supply Co',
      'financial_direction': 'payable',
      'currency_code': 'KWD',
      'accountant_user_id': 42,
      'status': 'active',
      'start_date': '2026-08-01',
      'end_date': null,
      'base_value': '2500.0000',
      'is_archived': false,
    });

    expect(contract.customerId, isNull);
    expect(contract.customerName, isNull);
    expect(contract.isSupplier, isTrue);
    expect(contract.counterpartyId, 77);
    expect(contract.displayCounterparty, 'Kuwait Supply Co');
    expect(contract.financialDirection, 'payable');
    expect(contract.currencyCode, 'KWD');
  });

  test('contract parser rejects a counterparty direction conflict', () {
    expect(
      () => SafeContractsContract.fromData(<String, Object?>{
        'id': 902,
        'contract_number': 'SUP-902',
        'counterparty_type': 'supplier',
        'counterparty_id': 78,
        'counterparty_name': 'Wrong Direction Supplier',
        'financial_direction': 'receivable',
        'currency_code': 'KWD',
        'accountant_user_id': null,
        'status': 'active',
        'start_date': null,
        'end_date': null,
        'base_value': null,
        'is_archived': false,
      }),
      throwsFormatException,
    );
  });

  test('finance obligation rejects counterparty direction conflict', () {
    expect(
      () => FinanceObligation.fromData(<String, Object?>{
        'id': 501,
        'contract_id': 902,
        'contract_number': 'SUP-902',
        'counterparty_type': 'supplier',
        'counterparty_id': 78,
        'counterparty_name': 'Wrong Direction Supplier',
        'financial_direction': 'receivable',
        'currency_code': 'KWD',
        'sequence_no': 1,
        'reference': 'INV-1',
        'due_date': '2026-08-31',
        'expected_payment_date': null,
        'original_amount': '100.0000',
        'settled_amount': '0.0000',
        'remaining_amount': '100.0000',
        'status': 'due',
        'aging_bucket': 'current',
      }),
      throwsFormatException,
    );
  });
}

SafeContractsSession _session(Map<String, bool> capabilities) {
  return SafeContractsSession(
    userId: 7,
    scope: SafeContractsDataScope.assigned,
    capabilities: Map<String, bool>.unmodifiable(capabilities),
  );
}
