import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/contracts/contracts.dart';
import 'package:safecontracts_mobile/features/finance/finance.dart';
import 'package:safecontracts_mobile/features/payments/payments.dart';

void main() {
  group('ALK-MOBILE #607 production counterparty compatibility', () {
    test('supplier contract accepts nullable legacy customer bridge', () {
      final contract = SafeContractsContract.fromData(<String, Object?>{
        'id': '4101',
        'contract_number': 'SUP-CON-4101',
        'customer_id': null,
        'customer_name': null,
        'supplier_id': '3101',
        'supplier_name': 'Production Supplier',
        'counterparty_type': 'supplier',
        'counterparty_id': '3101',
        'counterparty_name': 'Production Supplier',
        'financial_direction': 'payable',
        'currency_code': 'KWD',
        'accountant_user_id': '42',
        'status': 'active',
        'start_date': null,
        'end_date': null,
        'base_value': '1000.0000',
        'is_archived': '0',
      });

      expect(contract.id, 4101);
      expect(contract.customerId, isNull);
      expect(contract.customerName, isNull);
      expect(contract.isSupplier, isTrue);
      expect(contract.counterpartyId, 3101);
      expect(contract.displayCounterparty, 'Production Supplier');
      expect(contract.financialDirection, 'payable');
      expect(contract.currencyCode, 'KWD');
    });

    test('supplier contract accepts empty legacy customer bridge', () {
      final contract = SafeContractsContract.fromData(<String, Object?>{
        'id': 4102,
        'contract_number': 'SUP-CON-4102',
        'customer_id': '',
        'customer_name': null,
        'counterparty_type': 'supplier',
        'counterparty_id': 3102,
        'counterparty_name': 'Legacy Projection Supplier',
        'financial_direction': 'payable',
        'currency_code': 'USD',
        'accountant_user_id': null,
        'status': 'draft',
        'start_date': null,
        'end_date': null,
        'base_value': null,
        'is_archived': false,
      });

      expect(contract.customerId, isNull);
      expect(contract.counterpartyType, 'supplier');
      expect(contract.counterpartyId, 3102);
    });

    test('customer contract keeps legacy and counterparty identity aligned',
        () {
      final contract = SafeContractsContract.fromData(<String, Object?>{
        'id': 4201,
        'contract_number': 'CUS-CON-4201',
        'customer_id': 2101,
        'customer_name': 'Production Customer',
        'counterparty_type': 'customer',
        'counterparty_id': 2101,
        'counterparty_name': 'Production Customer',
        'financial_direction': 'receivable',
        'currency_code': 'KWD',
        'accountant_user_id': 42,
        'status': 'active',
        'start_date': '2026-01-01',
        'end_date': '2026-12-31',
        'base_value': '500.0000',
        'is_archived': 0,
      });

      expect(contract.customerId, 2101);
      expect(contract.isCustomer, isTrue);
      expect(contract.counterpartyId, 2101);
      expect(contract.financialDirection, 'receivable');
    });

    test('supplier AP payment accepts nullable customer projection', () {
      final payment = SafeContractsPayment.fromData(<String, Object?>{
        'id': 8101,
        'contract_id': 4101,
        'contract_number': 'SUP-CON-4101',
        'customer_id': null,
        'customer_name': null,
        'accountant_user_id': 42,
        'sequence_no': 1,
        'reference': 'AP-001',
        'due_date': '2026-09-30',
        'expected_payment_date': null,
        'original_amount': '100.0000',
        'paid_amount': '0.0000',
        'remaining_amount': '100.0000',
        'status': 'upcoming',
        'contract_is_archived': 0,
      });

      expect(payment.customerId, isNull);
      expect(payment.contractId, 4101);
      expect(payment.remainingAmount, '100.0000');
    });

    test('supplier finance obligation uses counterparty identity only', () {
      final obligation = FinanceObligation.fromData(<String, Object?>{
        'id': 8101,
        'contract_id': 4101,
        'contract_number': 'SUP-CON-4101',
        'counterparty_type': 'supplier',
        'counterparty_id': 3101,
        'counterparty_name': 'Production Supplier',
        'financial_direction': 'payable',
        'currency_code': 'KWD',
        'sequence_no': 1,
        'reference': 'AP-001',
        'due_date': '2026-09-30',
        'expected_payment_date': null,
        'original_amount': '100.0000',
        'settled_amount': '0.0000',
        'remaining_amount': '100.0000',
        'status': 'upcoming',
        'aging_bucket': 'current',
      });

      expect(obligation.counterpartyType, 'supplier');
      expect(obligation.counterpartyId, 3101);
      expect(obligation.direction, 'payable');
      expect(obligation.currencyCode, 'KWD');
    });
  });
}
