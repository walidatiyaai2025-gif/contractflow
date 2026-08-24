import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('B101 contract route opens the real four-tab premium details surface',
      () {
    final shell =
        File('lib/features/navigation/app_shell.dart').readAsStringSync();
    final details = File(
      'lib/features/contracts/premium_contract_details_screen.dart',
    ).readAsStringSync();

    expect(
        shell, contains('builder: (context) => PremiumContractDetailsScreen('));
    expect(shell, contains('contractId: contractId,'));
    expect(shell, contains('onEditContract: canOpenContractEditor'));

    expect(details, contains('DefaultTabController(\n      length: 4,'));
    expect(details, contains("Tab(text: ar ? 'الملخص' : 'Summary')"));
    expect(details, contains("Tab(text: ar ? 'الدفعات' : 'Payments')"));
    expect(details, contains("Tab(text: ar ? 'المرفقات' : 'Attachments')"));
    expect(details, contains("Tab(text: ar ? 'التفاصيل' : 'Details')"));
    expect(details, contains('body: TabBarView('));
    expect(details, contains('child: _SummaryTab(bundle: bundle)'));
    expect(details, contains('child: _PaymentsTab('));
    expect(details, contains('child: _AttachmentsTab(media: bundle.media)'));
    expect(details, contains('child: _DetailsTab(contract: bundle.contract)'));
  });

  test('B101 every visible tab is backed by real authorized data loading', () {
    final details = File(
      'lib/features/contracts/premium_contract_details_screen.dart',
    ).readAsStringSync();

    expect(
      details,
      contains('widget.repository.loadContract(widget.contractId)'),
    );
    expect(details,
        contains('ContractMediaRepository(client).load(widget.contractId)'));
    expect(details, contains('PaymentsRepository(client).loadPage('));
    expect(details, contains("'finance/summary'"));
    expect(details, contains("'contract_id': '${widget.contractId}'"));
    expect(details, contains('financeAuthorized = false;'));

    expect(RegExp(r'RefreshIndicator\(').allMatches(details).length,
        greaterThanOrEqualTo(4));
    expect(details, isNot(contains('IgnorePointer(')));
    expect(details, isNot(contains('AbsorbPointer(')));
  });
}
