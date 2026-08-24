import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/payments/payments.dart';

void main() {
  test('B001 Flutter consumes server-authoritative payment status unchanged', () {
    final payment = SafeContractsPayment.fromData(<String, Object?>{
      'id': 7001,
      'contract_id': 501,
      'contract_number': 'SC-501',
      'customer_id': 7,
      'customer_name': 'Customer',
      'counterparty_type': 'customer',
      'counterparty_id': 7,
      'counterparty_name': 'Customer',
      'financial_direction': 'receivable',
      'currency_code': 'KWD',
      'sequence_no': 1,
      'reference': 'P-001',
      'due_date': '2020-08-01',
      'expected_payment_date': '2099-08-01',
      'original_amount': '500.0000',
      'paid_amount': '0.0000',
      'remaining_amount': '500.0000',
      'status': 'overdue',
      'contract_is_archived': false,
    });

    expect(payment.status, 'overdue');
    expect(payment.dueDate, '2020-08-01');
    expect(payment.expectedPaymentDate, '2099-08-01');
    expect(payment.remainingAmount, '500.0000');
  });
}
