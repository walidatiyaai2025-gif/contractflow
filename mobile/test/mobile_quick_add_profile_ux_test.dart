import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/records/mobile_quick_add_screen.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';

void main() {
  test('quick add is hidden when the session has no create capability', () {
    final session = _session(<String, bool>{
      'safecontracts_edit_customers': true,
      'safecontracts_edit_contracts': true,
      'safecontracts_edit_payments': true,
    });

    expect(availableMobileQuickAdds(session), isEmpty);
  });

  test('quick add exposes only create actions granted to the session', () {
    final session = _session(<String, bool>{
      'safecontracts_create_customers': true,
      'safecontracts_create_contracts': false,
      'safecontracts_create_payments': true,
      'safecontracts_edit_contracts': true,
    });

    expect(
      availableMobileQuickAdds(session),
      <MobileQuickAddType>[
        MobileQuickAddType.customer,
        MobileQuickAddType.payment,
      ],
    );
  });

  test('all create capabilities expose the complete quick add set', () {
    final session = _session(<String, bool>{
      'safecontracts_create_customers': true,
      'safecontracts_create_contracts': true,
      'safecontracts_create_payments': true,
    });

    expect(
      availableMobileQuickAdds(session),
      <MobileQuickAddType>[
        MobileQuickAddType.customer,
        MobileQuickAddType.contract,
        MobileQuickAddType.payment,
      ],
    );
  });

  test('profile no longer owns CRUD entry points or raw capabilities', () {
    final source = <String>[
      File('lib/features/profile/profile_screen.dart').readAsStringSync(),
      File('lib/features/profile/modern_profile_content.dart')
          .readAsStringSync(),
      File('lib/features/profile/profile_identity_sections.dart')
          .readAsStringSync(),
    ].join('\n');

    expect(source, isNot(contains('MobileRecordEditorScreen')));
    expect(source, isNot(contains('mobile_record_editor_screen.dart')));
    expect(source, isNot(contains('Granted capabilities')));
    expect(source, isNot(contains('_grantedCapabilities')));
    expect(source, isNot(contains('Data management')));
    expect(source, contains('ProfileSectionTitle'));
    expect(source, contains('My profile'));
  });

  test('app shell owns the animated permission-aware quick add entry point',
      () {
    final source =
        File('lib/features/navigation/app_shell.dart').readAsStringSync();

    expect(source, contains('availableMobileQuickAdds(widget.session)'));
    expect(source, contains('floatingActionButton:'));
    expect(source, contains('_QuickAddFab'));
    expect(source, contains('_QuickAddSheet'));
    expect(source, contains('AnimatedSwitcher'));
    expect(source, contains('AnimatedScale'));
  });

  test('quick add reaches bounded reference pages beyond the first 100', () {
    final source = File('lib/features/records/mobile_quick_add_screen.dart')
        .readAsStringSync();

    expect(source, contains('static const _pageSize = 100'));
    expect(source, contains('static const _maxPage = 5'));
    expect(source, contains('result.hasMore'));
    expect(source, contains('_loadCustomerPage(_customerPage!.page + 1)'));
    expect(source, contains('_loadContractPage(_contractPage!.page + 1)'));
    expect(source, contains('_PinnedReferenceTransport'));
    expect(
        source,
        contains(
            "export 'mobile_quick_add_flow.dart' hide MobileQuickAddScreen"));
  });
}

SafeContractsSession _session(Map<String, bool> capabilities) {
  return SafeContractsSession(
    userId: 7,
    scope: SafeContractsDataScope.assigned,
    capabilities: Map<String, bool>.unmodifiable(capabilities),
  );
}
