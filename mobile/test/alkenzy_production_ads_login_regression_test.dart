import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/app.dart';
import 'package:safecontracts_mobile/features/ads/mobile_ads_config.dart';
import 'package:safecontracts_mobile/features/bootstrap/mobile_bootstrap_controller.dart';
import 'package:safecontracts_mobile/features/session/session_controller.dart';

void main() {
  group('Alkenzy production ads authentication regression', () {
    test('login remains visible after authenticated bootstrap failures', () {
      for (final sessionState in <SessionState>[
        SessionState.error,
        SessionState.forbidden,
        SessionState.unauthenticated,
      ]) {
        for (final bootstrapState in <MobileBootstrapState>[
          MobileBootstrapState.error,
          MobileBootstrapState.blocked,
        ]) {
          expect(
            shouldOfferMobileLogin(
              loginRequested: true,
              bootstrapState: bootstrapState,
              sessionState: sessionState,
            ),
            isTrue,
            reason: '$sessionState / $bootstrapState',
          );
        }
      }
    });

    test('cold start failures display retry, not unexpected login', () {
      expect(
        shouldOfferMobileLogin(
          loginRequested: false,
          bootstrapState: MobileBootstrapState.error,
          sessionState: SessionState.error,
        ),
        isFalse,
      );
      expect(
        shouldOfferMobileLogin(
          loginRequested: false,
          bootstrapState: MobileBootstrapState.blocked,
          sessionState: SessionState.unauthenticated,
        ),
        isTrue,
      );
    });

    test('production AdMob config remains independent of login routing', () {
      final ads = MobileAdvertisingConfig.fromData({
        'enabled': true,
        'test_mode': false,
        'banner_enabled': true,
        'provider': 'admob',
        'admob_banner_ad_unit_id':
            'ca-app-pub-3218037275900725/8818395498',
      });
      expect(ads.enabled, isTrue);
      expect(ads.testMode, isFalse);
      expect(ads.canRequestBanner, isTrue);
      expect(
        shouldOfferMobileLogin(
          loginRequested: true,
          bootstrapState: MobileBootstrapState.error,
          sessionState: SessionState.error,
        ),
        isTrue,
      );
      expect(
        shouldOfferMobileLogin(
          loginRequested: false,
          bootstrapState: MobileBootstrapState.ready,
          sessionState: SessionState.authenticated,
        ),
        isFalse,
      );
    });
  });
}
