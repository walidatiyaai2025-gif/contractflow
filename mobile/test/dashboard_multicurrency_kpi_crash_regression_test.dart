import 'package:flutter/widgets.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/core/localization/safecontracts_localizations.dart';
import 'package:safecontracts_mobile/features/config/mobile_config.dart';
import 'package:safecontracts_mobile/features/dashboard/dashboard_models.dart';

void main() {
  group('dashboard multi-currency KPI contract', () {
    test('null legacy totals are unavailable instead of crashing', () {
      final kpis = DashboardKpis.fromData(<String, Object?>{
        'contract_count': '500',
        'currency_group_count': '3',
        'currency_code': 'USD',
        'scheduled_total': null,
        'remaining_total': null,
        'overdue_exposure': null,
        'collected_total': null,
      });

      expect(kpis.contractCount, 500);
      expect(kpis.currencyGroupCount, 3);
      expect(kpis.isMultiCurrency, isTrue);
      expect(kpis.scheduledTotal, '—');
      expect(kpis.remainingTotal, '—');
      expect(kpis.overdueExposure, '—');
      expect(kpis.collectedTotal, '—');

      const currency = MobileCurrencyConfig.defaults();
      expect(
        const SafeContractsLocalizations(Locale('en'))
            .money(kpis.scheduledTotal, currency),
        '—',
      );
      expect(
        const SafeContractsLocalizations(Locale('ar'))
            .money(kpis.remainingTotal, currency),
        '—',
      );
    });

    test('single-currency dashboard still rejects a missing total', () {
      expect(
        () => DashboardKpis.fromData(<String, Object?>{
          'contract_count': '1',
          'currency_group_count': '1',
          'currency_code': 'KWD',
          'scheduled_total': null,
          'remaining_total': '5.0000',
          'overdue_exposure': '0.0000',
          'collected_total': '5.0000',
        }),
        throwsFormatException,
      );
    });

    test('normal single-currency totals keep their exact server values', () {
      final kpis = DashboardKpis.fromData(<String, Object?>{
        'contract_count': '2',
        'currency_group_count': '1',
        'currency_code': 'KWD',
        'scheduled_total': '12.5000',
        'remaining_total': '8.0000',
        'overdue_exposure': '1.2500',
        'collected_total': '4.5000',
      });

      expect(kpis.isMultiCurrency, isFalse);
      expect(kpis.scheduledTotal, '12.5000');
      expect(kpis.remainingTotal, '8.0000');
      expect(kpis.overdueExposure, '1.2500');
      expect(kpis.collectedTotal, '4.5000');
    });
  });
}
