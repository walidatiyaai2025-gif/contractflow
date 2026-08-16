import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/localization/runtime_translation_overrides.dart';
import 'package:safecontracts_mobile/core/localization/safecontracts_localizations.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';

void main() {
  tearDown(SafeContractsRuntimeTranslations.clear);

  test('dashboard overrides win over bundled Arabic and English defaults', () {
    SafeContractsRuntimeTranslations.replace(<String, Map<String, String>>{
      'en': <String, String>{'Dashboard': 'Operations Home'},
      'ar': <String, String>{'Dashboard': 'لوحة العمليات'},
    });

    const english = SafeContractsLocalizations(Locale('en'));
    const arabic = SafeContractsLocalizations(Locale('ar'));

    expect(english.t('Dashboard'), 'Operations Home');
    expect(arabic.t('Dashboard'), 'لوحة العمليات');
    expect(arabic.t('Customers'), 'العملاء');
  });

  test('dynamic templates remain editable and preserve replacement tokens', () {
    SafeContractsRuntimeTranslations.replace(<String, Map<String, String>>{
      'en': <String, String>{},
      'ar': <String, String>{'Payment #{id}': 'دفعة رقم {id}'},
    });

    const arabic = SafeContractsLocalizations(Locale('ar'));
    expect(arabic.paymentNumber(77), 'دفعة رقم 77');
  });

  test('mobile config parses bounded translation overrides', () {
    final config = SafeContractsMobileConfig.fromData(<String, Object?>{
      'support_text': '',
      'default_page_size': 25,
      'currency': <String, Object?>{'code': 'KWD', 'symbol': 'د.ك'},
      'features': <String, Object?>{
        'excel_export': true,
        'push_notifications': true,
        'collection_entry': true,
      },
      'translation_overrides': <String, Object?>{
        'en': <String, Object?>{
          'Customers': 'Accounts',
          'bad-number': 123,
          '': 'blank source',
        },
        'ar': <String, Object?>{
          'Customers': 'الحسابات',
          'blank-value': '',
          'bad-nul': 'قيمة\u0000مرفوضة',
        },
      },
    });

    expect(config.translationOverrides['en'], <String, String>{
      'Customers': 'Accounts',
    });
    expect(config.translationOverrides['ar'], <String, String>{
      'Customers': 'الحسابات',
    });
  });

  test('clear restores bundled fallback behavior', () {
    SafeContractsRuntimeTranslations.replace(<String, Map<String, String>>{
      'en': <String, String>{'Dashboard': 'Custom'},
      'ar': <String, String>{'Dashboard': 'مخصص'},
    });
    SafeContractsRuntimeTranslations.clear();

    const english = SafeContractsLocalizations(Locale('en'));
    const arabic = SafeContractsLocalizations(Locale('ar'));
    expect(english.t('Dashboard'), 'Dashboard');
    expect(arabic.t('Dashboard'), 'لوحة التحكم');
  });
}
