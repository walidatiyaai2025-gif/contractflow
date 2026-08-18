import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/help/mobile_user_guide_translations.dart';

void main() {
  test('ALK-PROD-001 mobile guide has complete Arabic defaults', () {
    final defaults = mobileGuideArabicDefaults();
    expect(defaults, isNotEmpty);
    for (final entry in defaults.entries) {
      expect(entry.key.trim(), isNotEmpty);
      expect(entry.value.trim(), isNotEmpty);
      expect(entry.value, isNot(entry.key));
    }

    final screen = File('lib/features/help/mobile_user_guide_screen.dart')
        .readAsStringSync();
    final referenced = RegExp(r"mobileGuideText\(\s*l10n,\s*'([^']+)'", multiLine: true)
        .allMatches(screen)
        .map((match) => match.group(1)!)
        .toSet();
    for (final source in referenced) {
      expect(
        defaults.containsKey(source),
        isTrue,
        reason: 'Missing Arabic mobile guide default for: $source',
      );
    }
  });

  test('ALK-PROD-001 mobile guide is permission-aware and reachable', () {
    final guide = File('lib/features/help/mobile_user_guide_screen.dart')
        .readAsStringSync();
    final profile =
        File('lib/features/profile/profile_screen.dart').readAsStringSync();

    expect(guide, contains('required this.destinations'));
    expect(guide, contains('destinations.map(_entryFor)'));
    expect(profile, contains('MobileNavigationPolicy.resolve'));
    expect(profile, contains('MobileUserGuideScreen'));
  });

  test('ALK-PROD-001 collection UX never requests a raw media ID', () {
    final collection = File(
      'lib/features/payments/collection_entry_dialog.dart',
    ).readAsStringSync();

    expect(collection, isNot(contains('Proof media ID')));
    expect(collection, isNot(contains('_proof')));
    expect(collection, isNot(contains('proofMediaId:')));
    expect(collection, contains('DropdownButtonFormField<int>'));
  });
}
