import 'dart:async';

import 'package:applovin_max/applovin_max.dart' as max;
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart' as gma;

import 'mobile_ads_config.dart';

final class SafeContractsMobileAds extends ChangeNotifier {
  SafeContractsMobileAds._();

  static final SafeContractsMobileAds instance = SafeContractsMobileAds._();

  MobileAdvertisingConfig _config = const MobileAdvertisingConfig.defaults();
  gma.BannerAd? _adMobBanner;
  bool _adMobBannerLoaded = false;
  bool _consentRequested = false;
  bool _privacyOptionsRequired = false;
  bool _adMobSdkInitialized = false;
  bool _appLovinSdkInitialized = false;
  String _appLovinInitializedKey = '';
  bool _appLovinBannerActive = false;
  int _generation = 0;

  MobileAdProvider get provider => _config.provider;
  gma.BannerAd? get adMobBanner =>
      _config.provider == MobileAdProvider.admob && _adMobBannerLoaded
          ? _adMobBanner
          : null;
  bool get appLovinBannerActive =>
      _config.provider == MobileAdProvider.applovin &&
      _appLovinSdkInitialized &&
      _appLovinBannerActive &&
      _config.canRequestBanner;
  String get appLovinBannerAdUnitId => _config.appLovinBannerAdUnitId;
  bool get privacyOptionsRequired =>
      _config.provider == MobileAdProvider.admob &&
      _config.enabled &&
      _privacyOptionsRequired;

  Future<void> configure(MobileAdvertisingConfig config) async {
    _generation++;
    final generation = _generation;
    _config = config;
    _disposeAdMobBanner();
    _appLovinBannerActive = false;
    notifyListeners();

    if (!config.canRequestBanner || kIsWeb) {
      return;
    }

    switch (config.provider) {
      case MobileAdProvider.admob:
        await _configureAdMob(config, generation);
      case MobileAdProvider.applovin:
        await _configureAppLovin(config, generation);
    }
  }

  Future<void> _configureAdMob(
    MobileAdvertisingConfig config,
    int generation,
  ) async {
    final canRequestAds = await _ensureGoogleConsent();
    if (generation != _generation ||
        !canRequestAds ||
        !_config.canRequestBanner ||
        _config.provider != MobileAdProvider.admob) {
      return;
    }

    if (!_adMobSdkInitialized) {
      await gma.MobileAds.instance.initialize();
      _adMobSdkInitialized = true;
    }
    if (generation != _generation ||
        !_config.canRequestBanner ||
        _config.provider != MobileAdProvider.admob) {
      return;
    }

    final adUnitId = _androidAdMobBannerUnitId(config);
    if (adUnitId.isEmpty) {
      return;
    }

    final ad = gma.BannerAd(
      adUnitId: adUnitId,
      request: const gma.AdRequest(),
      size: gma.AdSize.banner,
      listener: gma.BannerAdListener(
        onAdLoaded: (loadedAd) {
          if (generation != _generation || loadedAd != _adMobBanner) {
            loadedAd.dispose();
            return;
          }
          _adMobBannerLoaded = true;
          notifyListeners();
        },
        onAdFailedToLoad: (failedAd, error) {
          failedAd.dispose();
          if (generation != _generation || failedAd != _adMobBanner) {
            return;
          }
          _adMobBanner = null;
          _adMobBannerLoaded = false;
          notifyListeners();
        },
      ),
    );
    _adMobBanner = ad;
    unawaited(ad.load());
  }

  Future<void> _configureAppLovin(
    MobileAdvertisingConfig config,
    int generation,
  ) async {
    if (defaultTargetPlatform != TargetPlatform.android) {
      return;
    }

    max.AppLovinMAX.setVerboseLogging(config.testMode);
    final hasPrivacyUrl = config.privacyPolicyUrl.isNotEmpty;
    max.AppLovinMAX.setTermsAndPrivacyPolicyFlowEnabled(hasPrivacyUrl);
    if (hasPrivacyUrl) {
      max.AppLovinMAX.setPrivacyPolicyUrl(config.privacyPolicyUrl);
    }
    if (config.termsUrl.isNotEmpty) {
      max.AppLovinMAX.setTermsOfServiceUrl(config.termsUrl);
    }

    if (!_appLovinSdkInitialized) {
      final result = await max.AppLovinMAX.initialize(config.appLovinSdkKey);
      if (generation != _generation || result == null) {
        return;
      }
      _appLovinSdkInitialized = true;
      _appLovinInitializedKey = config.appLovinSdkKey;
    } else if (_appLovinInitializedKey != config.appLovinSdkKey) {
      // MAX documents SDK initialization as a one-time process. A remotely
      // changed SDK key therefore fails closed for this process and is picked
      // up after the next app start rather than attempting a second init.
      return;
    }

    if (generation != _generation ||
        _config.provider != MobileAdProvider.applovin ||
        !_config.canRequestBanner) {
      return;
    }
    _appLovinBannerActive = true;
    notifyListeners();
  }

  void disable() {
    _generation++;
    _config = const MobileAdvertisingConfig.defaults();
    _disposeAdMobBanner();
    _appLovinBannerActive = false;
    _privacyOptionsRequired = false;
    notifyListeners();
  }

  Future<void> showPrivacyOptions() async {
    if (!privacyOptionsRequired) return;
    await gma.ConsentForm.showPrivacyOptionsForm((formError) {});
    await _refreshPrivacyOptionsRequirement();
    notifyListeners();
  }

  Future<bool> _ensureGoogleConsent() async {
    if (!_consentRequested) {
      _consentRequested = true;
      final completer = Completer<void>();
      gma.ConsentInformation.instance.requestConsentInfoUpdate(
        gma.ConsentRequestParameters(),
        () {
          if (!completer.isCompleted) completer.complete();
        },
        (formError) {
          if (!completer.isCompleted) completer.complete();
        },
      );
      await completer.future;
      await gma.ConsentForm.loadAndShowConsentFormIfRequired((formError) {});
    }

    await _refreshPrivacyOptionsRequirement();
    return gma.ConsentInformation.instance.canRequestAds();
  }

  Future<void> _refreshPrivacyOptionsRequirement() async {
    final requirement = await gma.ConsentInformation.instance
        .getPrivacyOptionsRequirementStatus();
    _privacyOptionsRequired =
        requirement == gma.PrivacyOptionsRequirementStatus.required;
  }

  String _androidAdMobBannerUnitId(MobileAdvertisingConfig config) {
    if (defaultTargetPlatform != TargetPlatform.android) {
      return '';
    }
    return config.effectiveAndroidAdMobBannerAdUnitId;
  }

  void _disposeAdMobBanner() {
    _adMobBanner?.dispose();
    _adMobBanner = null;
    _adMobBannerLoaded = false;
  }
}

final class SafeContractsAdsHost extends StatelessWidget {
  const SafeContractsAdsHost({
    required this.child,
    this.controller,
    super.key,
  });

  final Widget child;
  final SafeContractsMobileAds? controller;

  @override
  Widget build(BuildContext context) {
    final ads = controller ?? SafeContractsMobileAds.instance;
    return AnimatedBuilder(
      animation: ads,
      child: child,
      builder: (context, child) {
        final adMobBanner = ads.adMobBanner;
        final showAppLovin = ads.appLovinBannerActive;
        final showPrivacy = ads.privacyOptionsRequired;
        if (adMobBanner == null && !showAppLovin && !showPrivacy) {
          return child ?? const SizedBox.shrink();
        }

        return Directionality(
          textDirection: TextDirection.ltr,
          child: Column(
            children: [
              Expanded(child: child ?? const SizedBox.shrink()),
              if (adMobBanner != null)
                SizedBox(
                  width: adMobBanner.size.width.toDouble(),
                  height: adMobBanner.size.height.toDouble(),
                  child: gma.AdWidget(ad: adMobBanner),
                ),
              if (showAppLovin)
                Center(
                  child: SizedBox(
                    width: 320,
                    height: 50,
                    child: max.MaxAdView(
                      key: ValueKey(ads.appLovinBannerAdUnitId),
                      adUnitId: ads.appLovinBannerAdUnitId,
                      adFormat: max.AdFormat.banner,
                      width: 320,
                      height: 50,
                      isAdaptiveBannerEnabled: false,
                      listener: max.AdViewAdListener(
                        onAdLoadedCallback: (ad) {},
                        onAdLoadFailedCallback: (adUnitId, error) {},
                        onAdClickedCallback: (ad) {},
                        onAdExpandedCallback: (ad) {},
                        onAdCollapsedCallback: (ad) {},
                        onAdRevenuePaidCallback: (ad) {},
                      ),
                    ),
                  ),
                ),
              if (showPrivacy)
                GestureDetector(
                  behavior: HitTestBehavior.opaque,
                  onTap: () => unawaited(ads.showPrivacyOptions()),
                  child: const SizedBox(
                    height: 28,
                    child: Center(
                      child: Text(
                        'Ad privacy options',
                        style: TextStyle(
                          color: Color(0xFF42526A),
                          fontSize: 11,
                          decoration: TextDecoration.underline,
                        ),
                      ),
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}
