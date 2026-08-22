import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart';

import 'mobile_ads_config.dart';

final class SafeContractsMobileAds extends ChangeNotifier {
  SafeContractsMobileAds._();

  static final SafeContractsMobileAds instance = SafeContractsMobileAds._();

  MobileAdvertisingConfig _config = const MobileAdvertisingConfig.defaults();
  BannerAd? _banner;
  bool _bannerLoaded = false;
  bool _consentRequested = false;
  bool _privacyOptionsRequired = false;
  bool _sdkInitialized = false;
  int _generation = 0;

  BannerAd? get banner => _bannerLoaded ? _banner : null;
  bool get privacyOptionsRequired => _config.enabled && _privacyOptionsRequired;

  Future<void> configure(MobileAdvertisingConfig config) async {
    _generation++;
    final generation = _generation;
    _config = config;
    _disposeBanner();
    notifyListeners();

    if (!config.canRequestBanner || kIsWeb) {
      return;
    }

    final canRequestAds = await _ensureConsent();
    if (generation != _generation ||
        !canRequestAds ||
        !_config.canRequestBanner) {
      return;
    }

    if (!_sdkInitialized) {
      await MobileAds.instance.initialize();
      _sdkInitialized = true;
    }
    if (generation != _generation || !_config.canRequestBanner) {
      return;
    }

    final adUnitId = _androidBannerUnitId(_config);
    if (adUnitId.isEmpty) {
      return;
    }

    final ad = BannerAd(
      adUnitId: adUnitId,
      request: const AdRequest(),
      size: AdSize.banner,
      listener: BannerAdListener(
        onAdLoaded: (loadedAd) {
          if (generation != _generation || loadedAd != _banner) {
            loadedAd.dispose();
            return;
          }
          _bannerLoaded = true;
          notifyListeners();
        },
        onAdFailedToLoad: (failedAd, error) {
          failedAd.dispose();
          if (generation != _generation || failedAd != _banner) {
            return;
          }
          _banner = null;
          _bannerLoaded = false;
          notifyListeners();
        },
      ),
    );
    _banner = ad;
    unawaited(ad.load());
  }

  void disable() {
    _generation++;
    _config = const MobileAdvertisingConfig.defaults();
    _disposeBanner();
    _privacyOptionsRequired = false;
    notifyListeners();
  }

  Future<void> showPrivacyOptions() async {
    if (!privacyOptionsRequired) return;
    await ConsentForm.showPrivacyOptionsForm((formError) {});
    await _refreshPrivacyOptionsRequirement();
    notifyListeners();
  }

  Future<bool> _ensureConsent() async {
    if (!_consentRequested) {
      _consentRequested = true;
      final completer = Completer<void>();
      ConsentInformation.instance.requestConsentInfoUpdate(
        ConsentRequestParameters(),
        () {
          if (!completer.isCompleted) completer.complete();
        },
        (formError) {
          if (!completer.isCompleted) completer.complete();
        },
      );
      await completer.future;
      await ConsentForm.loadAndShowConsentFormIfRequired((formError) {});
    }

    await _refreshPrivacyOptionsRequirement();
    return ConsentInformation.instance.canRequestAds();
  }

  Future<void> _refreshPrivacyOptionsRequirement() async {
    final requirement =
        await ConsentInformation.instance.getPrivacyOptionsRequirementStatus();
    _privacyOptionsRequired =
        requirement == PrivacyOptionsRequirementStatus.required;
  }

  String _androidBannerUnitId(MobileAdvertisingConfig config) {
    if (defaultTargetPlatform != TargetPlatform.android) {
      return '';
    }
    return config.effectiveAndroidBannerAdUnitId;
  }

  void _disposeBanner() {
    _banner?.dispose();
    _banner = null;
    _bannerLoaded = false;
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
        final banner = ads.banner;
        final showPrivacy = ads.privacyOptionsRequired;
        if (banner == null && !showPrivacy) {
          return child ?? const SizedBox.shrink();
        }

        return Directionality(
          textDirection: TextDirection.ltr,
          child: Column(
            children: [
              Expanded(child: child ?? const SizedBox.shrink()),
              if (banner != null)
                SizedBox(
                  width: banner.size.width.toDouble(),
                  height: banner.size.height.toDouble(),
                  child: AdWidget(ad: banner),
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
