import 'dart:io';

import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/localization/mobile_locale_controller.dart';
import 'package:safecontracts_mobile/core/localization/safecontracts_localizations.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';

final class _MemoryLocaleStore implements MobileLocaleStore {
  String? value;

  @override
  Future<String?> readLanguageCode() async => value;

  @override
  Future<void> writeLanguageCode(String languageCode) async {
    value = languageCode;
  }
}

void main() {
  test('SC-MOBILE-051 Arabic and English localization is deterministic', () {
    const ar = SafeContractsLocalizations(Locale('ar'));
    const en = SafeContractsLocalizations(Locale('en'));

    expect(ar.t('Dashboard'), 'لوحة التحكم');
    expect(ar.t('Contracts'), 'العقود');
    expect(ar.status('overdue'), 'متأخر');
    expect(ar.status('partially_paid'), 'مدفوع جزئياً');
    expect(en.t('Dashboard'), 'Dashboard');
    expect(en.status('overdue'), 'Overdue');
    expect(
      SafeContractsLocalizations.supportedLocales
          .map((locale) => locale.languageCode),
      containsAll(<String>['ar', 'en']),
    );
  });

  test('SC-MOBILE-052 language preference persists and currency is formatted',
      () async {
    final store = _MemoryLocaleStore();
    final controller = MobileLocaleController(store: store);
    expect(controller.languageCode, 'en');

    await controller.setLanguageCode('ar');
    expect(controller.languageCode, 'ar');
    expect(store.value, 'ar');

    final restored = MobileLocaleController(store: store);
    await restored.load();
    expect(restored.languageCode, 'ar');

    final config = SafeContractsMobileConfig.fromData(<String, Object?>{
      'support_text': '',
      'default_page_size': 25,
      'currency': <String, Object?>{
        'code': 'kwd',
        'symbol': 'د.ك',
      },
      'features': <String, Object?>{},
    });
    expect(config.currency.code, 'KWD');
    expect(config.currency.symbol, 'د.ك');
    expect(config.currency.displayToken, 'د.ك');

    const ar = SafeContractsLocalizations(Locale('ar'));
    const en = SafeContractsLocalizations(Locale('en'));
    expect(ar.money('100.0000', config.currency), '100.0000 د.ك');
    expect(en.money('100.0000', config.currency), 'د.ك 100.0000');

    controller.dispose();
    restored.dispose();
  });

  test('SC-MOBILE-053 dashboard KPI cards use responsive adjacent grid', () {
    final source = File('lib/features/dashboard/dashboard_screen.dart')
        .readAsStringSync();

    expect(source, contains('GridView.builder'));
    expect(source, contains('constraints.maxWidth >= 1100'));
    expect(source, contains('constraints.maxWidth >= 700'));
    expect(source, contains('? 5'));
    expect(source, contains('? 3'));
    expect(source, contains(': 2'));
    expect(source, contains('NeverScrollableScrollPhysics'));
    expect(source, contains('l10n.money'));
  });
}
