import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/widgets/compact_pagination.dart';
import 'package:safecontracts_mobile/features/contracts/contracts.dart';

void main() {
  test('B084 accepts authoritative pages beyond the legacy 500-row window', () {
    final page = ContractPage.fromEnvelope(
      ApiEnvelope(
        data: <Object?>[_contractData(6)],
        meta: <String, Object?>{
          'scope': 'assigned',
          'page': 600,
          'per_page': 1,
          'total': 605,
          'total_pages': 605,
          'sort': 'id',
          'order': 'desc',
          'bounded_window': 1,
          'has_more': true,
        },
      ),
    );

    expect(page.page, 600);
    expect(page.total, 605);
    expect(page.totalPages, 605);
    expect(page.hasMore, isTrue);
  });

  test('B084 authoritative final page terminates without client inference', () {
    final page = ContractPage.fromEnvelope(
      ApiEnvelope(
        data: <Object?>[_contractData(605)],
        meta: <String, Object?>{
          'scope': 'assigned',
          'page': 605,
          'per_page': 1,
          'total': 605,
          'total_pages': 605,
          'sort': 'id',
          'order': 'desc',
          'bounded_window': 1,
          'has_more': false,
        },
      ),
    );

    expect(page.page, page.totalPages);
    expect(page.total, 605);
    expect(page.hasMore, isFalse);
  });

  testWidgets(
      'B083/B084 pagination buttons obey first, last, and loading state',
      (tester) async {
    Future<void> pump({
      required int page,
      required int totalPages,
      required bool loading,
    }) async {
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: CompactPagination(
              page: page,
              totalPages: totalPages,
              isLoading: loading,
              previousLabel: 'Previous',
              nextLabel: 'Next',
              onPrevious: () {},
              onNext: () {},
            ),
          ),
        ),
      );
    }

    await pump(page: 1, totalPages: 3, loading: false);
    expect(
        tester
            .widget<TextButton>(find.widgetWithText(TextButton, 'Previous'))
            .onPressed,
        isNull);
    expect(
        tester
            .widget<TextButton>(find.widgetWithText(TextButton, 'Next'))
            .onPressed,
        isNotNull);

    await pump(page: 3, totalPages: 3, loading: false);
    expect(
        tester
            .widget<TextButton>(find.widgetWithText(TextButton, 'Previous'))
            .onPressed,
        isNotNull);
    expect(
        tester
            .widget<TextButton>(find.widgetWithText(TextButton, 'Next'))
            .onPressed,
        isNull);

    await pump(page: 2, totalPages: 3, loading: true);
    expect(
        tester
            .widget<TextButton>(find.widgetWithText(TextButton, 'Previous'))
            .onPressed,
        isNull);
    expect(
        tester
            .widget<TextButton>(find.widgetWithText(TextButton, 'Next'))
            .onPressed,
        isNull);
  });
}

Map<String, Object?> _contractData(int id) => <String, Object?>{
      'id': id,
      'contract_number': 'SC-$id',
      'customer_id': 7,
      'customer_name': 'Customer',
      'counterparty_type': 'customer',
      'counterparty_id': 7,
      'counterparty_name': 'Customer',
      'financial_direction': 'receivable',
      'currency_code': 'KWD',
      'accountant_user_id': 42,
      'status': 'active',
      'start_date': '2026-01-01',
      'end_date': '2026-12-31',
      'base_value': '100.0000',
      'is_archived': false,
    };
