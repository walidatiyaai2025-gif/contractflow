import '../../core/api/api_client.dart';

final class CustomerRecord {
  const CustomerRecord({
    required this.id,
    required this.name,
    required this.isActive,
    this.internalCode,
    this.contactName,
    this.email,
    this.phone,
  });

  final int id;
  final String name;
  final bool isActive;
  final String? internalCode;
  final String? contactName;
  final String? email;
  final String? phone;

  factory CustomerRecord.fromData(Object? value) {
    final data = apiObjectMap(value, 'customer');
    return CustomerRecord(
      id: recordInt(data['id'], 'customer.id'),
      name: recordString(data['name'], 'Customer'),
      isActive: recordBool(data['is_active']),
      internalCode: recordNullableString(data['internal_code']),
      contactName: recordNullableString(data['contact_name']),
      email: recordNullableString(data['email']),
      phone: recordNullableString(data['phone']),
    );
  }
}

final class ContractRecord {
  const ContractRecord({
    required this.id,
    required this.contractNumber,
    required this.customerId,
    required this.status,
    required this.baseValue,
    required this.isArchived,
    this.customerName,
    this.accountantUserId,
    this.startDate,
    this.endDate,
  });

  final int id;
  final String contractNumber;
  final int customerId;
  final String? customerName;
  final int? accountantUserId;
  final String status;
  final String? startDate;
  final String? endDate;
  final String baseValue;
  final bool isArchived;

  factory ContractRecord.fromData(Object? value) {
    final data = apiObjectMap(value, 'contract');
    return ContractRecord(
      id: recordInt(data['id'], 'contract.id'),
      contractNumber: recordString(data['contract_number'], 'Contract'),
      customerId: recordInt(data['customer_id'], 'contract.customer_id'),
      customerName: recordNullableString(data['customer_name']),
      accountantUserId: recordNullableInt(data['accountant_user_id']),
      status: recordString(data['status'], 'unknown'),
      startDate: recordNullableString(data['start_date']),
      endDate: recordNullableString(data['end_date']),
      baseValue: recordString(data['base_value'], '0.0000'),
      isArchived: recordBool(data['is_archived']),
    );
  }
}

final class PaymentRecord {
  const PaymentRecord({
    required this.id,
    required this.contractId,
    required this.sequenceNo,
    required this.dueDate,
    required this.originalAmount,
    required this.paidAmount,
    required this.remainingAmount,
    required this.status,
    required this.contractIsArchived,
    this.contractNumber,
    this.customerId,
    this.customerName,
    this.accountantUserId,
    this.reference,
    this.expectedPaymentDate,
  });

  final int id;
  final int contractId;
  final String? contractNumber;
  final int? customerId;
  final String? customerName;
  final int? accountantUserId;
  final int sequenceNo;
  final String? reference;
  final String dueDate;
  final String? expectedPaymentDate;
  final String originalAmount;
  final String paidAmount;
  final String remainingAmount;
  final String status;
  final bool contractIsArchived;

  factory PaymentRecord.fromData(Object? value) {
    final data = apiObjectMap(value, 'payment');
    return PaymentRecord(
      id: recordInt(data['id'], 'payment.id'),
      contractId: recordInt(data['contract_id'], 'payment.contract_id'),
      contractNumber: recordNullableString(data['contract_number']),
      customerId: recordNullableInt(data['customer_id']),
      customerName: recordNullableString(data['customer_name']),
      accountantUserId: recordNullableInt(data['accountant_user_id']),
      sequenceNo: recordInt(data['sequence_no'], 'payment.sequence_no'),
      reference: recordNullableString(data['reference']),
      dueDate: recordString(data['due_date'], ''),
      expectedPaymentDate: recordNullableString(data['expected_payment_date']),
      originalAmount: recordString(data['original_amount'], '0.0000'),
      paidAmount: recordString(data['paid_amount'], '0.0000'),
      remainingAmount: recordString(data['remaining_amount'], '0.0000'),
      status: recordString(data['status'], 'unknown'),
      contractIsArchived: recordBool(data['contract_is_archived']),
    );
  }
}

final class PaymentMethodOption {
  const PaymentMethodOption({
    required this.id,
    required this.code,
    required this.name,
    required this.displayOrder,
  });

  final int id;
  final String code;
  final String name;
  final int displayOrder;

  factory PaymentMethodOption.fromData(Object? value) {
    final data = apiObjectMap(value, 'payment_method');
    return PaymentMethodOption(
      id: recordInt(data['id'], 'payment_method.id'),
      code: recordString(data['code'], ''),
      name: recordString(data['name'], 'Payment method'),
      displayOrder:
          recordInt(data['display_order'], 'payment_method.display_order'),
    );
  }
}

final class CollectionReceipt {
  const CollectionReceipt({required this.id, required this.paymentId});

  final int id;
  final int paymentId;

  factory CollectionReceipt.fromData(Object? value) {
    final data = apiObjectMap(value, 'collection_receipt');
    return CollectionReceipt(
      id: recordInt(data['id'], 'collection_receipt.id'),
      paymentId: recordInt(data['payment_id'], 'collection_receipt.payment_id'),
    );
  }
}

final class FollowUpQueueRecord {
  const FollowUpQueueRecord({
    required this.paymentId,
    required this.contractId,
    required this.dueDate,
    required this.remainingAmount,
    required this.paymentStatus,
    this.customerId,
    this.accountantUserId,
    this.contractStatus,
    this.reference,
    this.expectedPaymentDate,
    this.followUpState,
  });

  final int paymentId;
  final int contractId;
  final int? customerId;
  final int? accountantUserId;
  final String? contractStatus;
  final String? reference;
  final String dueDate;
  final String? expectedPaymentDate;
  final String remainingAmount;
  final String paymentStatus;
  final String? followUpState;

  factory FollowUpQueueRecord.fromData(Object? value) {
    final data = apiObjectMap(value, 'followup_queue');
    return FollowUpQueueRecord(
      paymentId: recordInt(data['payment_id'], 'followup_queue.payment_id'),
      contractId: recordInt(data['contract_id'], 'followup_queue.contract_id'),
      customerId: recordNullableInt(data['customer_id']),
      accountantUserId: recordNullableInt(data['accountant_user_id']),
      contractStatus: recordNullableString(data['contract_status']),
      reference: recordNullableString(data['reference']),
      dueDate: recordString(data['due_date'], ''),
      expectedPaymentDate: recordNullableString(data['expected_payment_date']),
      remainingAmount: recordString(data['remaining_amount'], '0.0000'),
      paymentStatus: recordString(data['status'], 'unknown'),
      followUpState: recordNullableString(data['followup_state']),
    );
  }
}

final class FollowUpHistoryRecord {
  const FollowUpHistoryRecord({
    required this.id,
    required this.paymentId,
    required this.state,
    required this.createdAt,
    this.note,
    this.promisedDate,
    this.deferredUntil,
    this.createdBy,
  });

  final int id;
  final int paymentId;
  final String state;
  final String? note;
  final String? promisedDate;
  final String? deferredUntil;
  final int? createdBy;
  final String createdAt;

  factory FollowUpHistoryRecord.fromData(Object? value) {
    final data = apiObjectMap(value, 'followup_history');
    return FollowUpHistoryRecord(
      id: recordInt(data['id'], 'followup_history.id'),
      paymentId: recordInt(data['payment_id'], 'followup_history.payment_id'),
      state: recordString(data['state'], 'unknown'),
      note: recordNullableString(data['note']),
      promisedDate: recordNullableString(data['promised_date']),
      deferredUntil: recordNullableString(data['deferred_until']),
      createdBy: recordNullableInt(data['created_by']),
      createdAt: recordString(data['created_at'], ''),
    );
  }
}

final class FollowUpReceipt {
  const FollowUpReceipt({required this.id, required this.paymentId});
  final int id;
  final int paymentId;

  factory FollowUpReceipt.fromData(Object? value) {
    final data = apiObjectMap(value, 'followup_receipt');
    return FollowUpReceipt(
      id: recordInt(data['id'], 'followup_receipt.id'),
      paymentId: recordInt(data['payment_id'], 'followup_receipt.payment_id'),
    );
  }
}

int recordInt(Object? value, String field) {
  if (value is int) return value;
  if (value is String) {
    final parsed = int.tryParse(value);
    if (parsed != null) return parsed;
  }
  throw FormatException('$field must be an integer.');
}

int? recordNullableInt(Object? value) {
  if (value == null || value == '') return null;
  if (value is int) return value;
  if (value is String) return int.tryParse(value);
  return null;
}

String recordString(Object? value, String fallback) {
  if (value is String) return value;
  if (value is num) return value.toString();
  return fallback;
}

String? recordNullableString(Object? value) {
  if (value == null) return null;
  if (value is String) {
    final normalized = value.trim();
    return normalized.isEmpty ? null : normalized;
  }
  if (value is num) return value.toString();
  return null;
}

bool recordBool(Object? value) {
  if (value is bool) return value;
  if (value is int) return value != 0;
  if (value is String) {
    return value == '1' || value.toLowerCase() == 'true';
  }
  return false;
}
