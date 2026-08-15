import 'dart:typed_data';

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
      id: readInt(data['id'], 'customer.id'),
      name: readString(data['name'], 'customer.name'),
      isActive: readBool(data['is_active']),
      internalCode: readNullableString(data['internal_code']),
      contactName: readNullableString(data['contact_name']),
      email: readNullableString(data['email']),
      phone: readNullableString(data['phone']),
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
      id: readInt(data['id'], 'contract.id'),
      contractNumber: readString(data['contract_number'], 'contract.contract_number'),
      customerId: readInt(data['customer_id'], 'contract.customer_id'),
      customerName: readNullableString(data['customer_name']),
      accountantUserId: readNullableInt(data['accountant_user_id']),
      status: readString(data['status'], 'contract.status'),
      startDate: readNullableString(data['start_date']),
      endDate: readNullableString(data['end_date']),
      baseValue: readMoney(data['base_value']),
      isArchived: readBool(data['is_archived']),
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
      id: readInt(data['id'], 'payment.id'),
      contractId: readInt(data['contract_id'], 'payment.contract_id'),
      contractNumber: readNullableString(data['contract_number']),
      customerId: readNullableInt(data['customer_id']),
      customerName: readNullableString(data['customer_name']),
      accountantUserId: readNullableInt(data['accountant_user_id']),
      sequenceNo: readInt(data['sequence_no'], 'payment.sequence_no'),
      reference: readNullableString(data['reference']),
      dueDate: readString(data['due_date'], 'payment.due_date'),
      expectedPaymentDate: readNullableString(data['expected_payment_date']),
      originalAmount: readMoney(data['original_amount']),
      paidAmount: readMoney(data['paid_amount']),
      remainingAmount: readMoney(data['remaining_amount']),
      status: readString(data['status'], 'payment.status'),
      contractIsArchived: readBool(data['contract_is_archived']),
    );
  }
}

final class CollectionRecord {
  const CollectionRecord({
    required this.id,
    required this.paymentId,
    required this.amount,
    required this.collectionDate,
    required this.paymentMethodId,
    this.paymentMethodName,
    this.reference,
    this.customerName,
    this.paymentStatus,
    this.remainingAmount,
  });

  final int id;
  final int paymentId;
  final String amount;
  final String collectionDate;
  final int paymentMethodId;
  final String? paymentMethodName;
  final String? reference;
  final String? customerName;
  final String? paymentStatus;
  final String? remainingAmount;

  factory CollectionRecord.fromData(Object? value) {
    final data = apiObjectMap(value, 'collection');
    return CollectionRecord(
      id: readInt(data['id'], 'collection.id'),
      paymentId: readInt(data['payment_id'], 'collection.payment_id'),
      amount: readMoney(data['amount']),
      collectionDate: readString(data['collection_date'], 'collection.collection_date'),
      paymentMethodId: readInt(data['payment_method_id'], 'collection.payment_method_id'),
      paymentMethodName: readNullableString(data['payment_method_name']),
      reference: readNullableString(data['reference']),
      customerName: readNullableString(data['customer_name']),
      paymentStatus: readNullableString(data['payment_status']),
      remainingAmount: readNullableString(data['remaining_amount']),
    );
  }
}

final class PaymentMethodRecord {
  const PaymentMethodRecord({
    required this.id,
    required this.code,
    required this.name,
    required this.displayOrder,
    required this.isActive,
  });

  final int id;
  final String code;
  final String name;
  final int displayOrder;
  final bool isActive;

  factory PaymentMethodRecord.fromData(Object? value) {
    final data = apiObjectMap(value, 'payment_method');
    return PaymentMethodRecord(
      id: readInt(data['id'], 'payment_method.id'),
      code: readString(data['code'], 'payment_method.code'),
      name: readString(data['name'], 'payment_method.name'),
      displayOrder: readInt(data['display_order'], 'payment_method.display_order'),
      isActive: readBool(data['is_active']),
    );
  }
}

final class ExcelExportPayload {
  const ExcelExportPayload({
    required this.filename,
    required this.contentType,
    required this.bytes,
    required this.rowCounts,
  });

  final String filename;
  final String contentType;
  final Uint8List bytes;
  final Map<String, int> rowCounts;
}

int readInt(Object? value, String field) {
  if (value is int) {
    return value;
  }
  if (value is String) {
    final parsed = int.tryParse(value);
    if (parsed != null) {
      return parsed;
    }
  }
  throw FormatException('$field must be an integer.');
}

int? readNullableInt(Object? value) {
  if (value == null || value == '') {
    return null;
  }
  return readInt(value, 'value');
}

String readString(Object? value, String field) {
  if (value is String && value.isNotEmpty) {
    return value;
  }
  if (value is num) {
    return value.toString();
  }
  throw FormatException('$field must be a non-empty string.');
}

String? readNullableString(Object? value) {
  if (value == null) {
    return null;
  }
  if (value is String) {
    return value.isEmpty ? null : value;
  }
  if (value is num) {
    return value.toString();
  }
  return null;
}

String readMoney(Object? value) {
  if (value is String && value.isNotEmpty) {
    return value;
  }
  if (value is num) {
    return value.toString();
  }
  return '0.0000';
}

bool readBool(Object? value) {
  return value == true || value == 1 || value == '1' || value == 'true';
}
