/// Google AdMob Android unit IDs supplied by the Alkenzy ADV owner.
///
/// The WordPress/dashboard-only "Alkenzy ADV Main Banner" unit
/// ca-app-pub-3218037275900725/8818395498 is intentionally NOT used here.
enum AlkenzyBannerSlot {
  homeFinancial,
  homeActivity,
  contractsList,
  contractDetails,
  customersList,
  customersDetail,
  suppliersList,
}

abstract final class AlkenzyAdUnits {
  static const appId = 'ca-app-pub-3218037275900725~7401372044';
  static const appOpen = 'ca-app-pub-3218037275900725/6129806290';
  static const interstitialMain = 'ca-app-pub-3218037275900725/4638303214';
  static const interstitialReports = 'ca-app-pub-3218037275900725/9633937038';

  // Two distinct customer IDs were supplied with the SAME AdMob label.
  // Keep both: the second is used in the customer details surface.
  static const Map<AlkenzyBannerSlot, String> banners = {
    AlkenzyBannerSlot.homeFinancial:
        'ca-app-pub-3218037275900725/1295000750',
    AlkenzyBannerSlot.homeActivity:
        'ca-app-pub-3218037275900725/2203711564',
    AlkenzyBannerSlot.contractsList:
        'ca-app-pub-3218037275900725/8512427056',
    AlkenzyBannerSlot.contractDetails:
        'ca-app-pub-3218037275900725/3865323776',
    AlkenzyBannerSlot.customersList:
        'ca-app-pub-3218037275900725/3882916907',
    AlkenzyBannerSlot.customersDetail:
        'ca-app-pub-3218037275900725/6979853539',
    AlkenzyBannerSlot.suppliersList:
        'ca-app-pub-3218037275900725/5666771860',
  };

  static const testBanner = 'ca-app-pub-3940256099942544/6300978111';
  static const testInterstitial = 'ca-app-pub-3940256099942544/1033173712';
  static const rewarded = 'ca-app-pub-3218037275900725/1456680066';
  static const testRewarded = 'ca-app-pub-3940256099942544/5224354917';
  static String rewardedUnit({required bool testMode}) =>
      testMode ? testRewarded : rewarded;

  static const testAppOpen = 'ca-app-pub-3940256099942544/9257395921';

  static String banner(AlkenzyBannerSlot slot, {required bool testMode}) =>
      testMode ? testBanner : banners[slot]!;

  static String interstitial({required bool reports, required bool testMode}) =>
      testMode
          ? testInterstitial
          : reports
              ? interstitialReports
              : interstitialMain;

  static String appOpenUnit({required bool testMode}) =>
      testMode ? testAppOpen : appOpen;
}
