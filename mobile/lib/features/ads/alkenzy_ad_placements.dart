import 'dart:async';

import 'package:flutter/material.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart' as gma;

import 'alkenzy_ad_units.dart';
import 'mobile_ads.dart';

/// In-content banner used by authenticated pages and the public landing slider.
/// Each placement has its own lifecycle and never wraps the auth/session root.
class AlkenzyInlineBanner extends StatefulWidget {
  const AlkenzyInlineBanner({required this.slot, super.key});

  final AlkenzyBannerSlot slot;

  @override
  State<AlkenzyInlineBanner> createState() => _AlkenzyInlineBannerState();
}

class _AlkenzyInlineBannerState extends State<AlkenzyInlineBanner> {
  final SafeContractsMobileAds _ads = SafeContractsMobileAds.instance;
  gma.BannerAd? _banner;
  bool _loading = false;
  int _revision = 0;
  String? _requestedUnit;

  @override
  void initState() {
    super.initState();
    _ads.addListener(_onAdsChanged);
    WidgetsBinding.instance.addPostFrameCallback((_) => _onAdsChanged());
  }

  @override
  void didUpdateWidget(covariant AlkenzyInlineBanner oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.slot != widget.slot) {
      _clear();
      _onAdsChanged();
    }
  }

  void _clear() {
    _revision++;
    final previous = _banner;
    _banner = null;
    _requestedUnit = null;
    _loading = false;
    // AdWidget must be detached from the tree before its native ad is disposed.
    if (mounted) { setState(() {}); }
    if (previous != null) {
      WidgetsBinding.instance.addPostFrameCallback((_) => previous.dispose());
    }
  }

  void _onAdsChanged() {
    if (!mounted) { return; }
    if (!_ads.canUseGooglePlacements) {
      if (_banner != null || _requestedUnit != null) { _clear(); }
      return;
    }
    final unit = AlkenzyAdUnits.banner(widget.slot, testMode: _ads.testMode);
    if (unit != _requestedUnit) { _clear(); }
    if (!_loading && _banner == null) { unawaited(_load(unit)); }
  }

  Future<void> _load(String unit) async {
    _loading = true;
    _requestedUnit = unit;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (mounted && _loading) { setState(() {}); }
    });
    final revision = ++_revision;
    try {
      if (!await _ads.ensureGooglePlacementsReady() ||
          !mounted ||
          !_ads.canUseGooglePlacements ||
          revision != _revision) {
        if (revision == _revision) { _loading = false; }
        return;
      }
      final banner = gma.BannerAd(
        adUnitId: unit,
        request: const gma.AdRequest(),
        size: gma.AdSize.banner,
        listener: gma.BannerAdListener(
          onAdLoaded: (ad) {
            if (!mounted || revision != _revision ||
                !_ads.canUseGooglePlacements) {
              ad.dispose();
              return;
            }
            setState(() {
              _banner = ad as gma.BannerAd;
              _loading = false;
            });
          },
          onAdFailedToLoad: (ad, error) {
            ad.dispose();
            if (mounted && revision == _revision) {
              setState(() { _loading = false; });
            }
          },
        ),
      );
      await banner.load();
    } on Object {
      if (mounted && revision == _revision) {
        setState(() { _loading = false; });
      }
    }
  }

  @override
  void dispose() {
    _ads.removeListener(_onAdsChanged);
    _revision++;
    _banner?.dispose();
    _banner = null;
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (!_ads.canUseGooglePlacements) { return const SizedBox.shrink(); }
    final ad = _banner;
    // Reserve space as soon as the request starts: do not shift action buttons
    // when the ad creative arrives and cause accidental taps.
    if (ad == null && !_loading) { return const SizedBox.shrink(); }
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 12),
      child: Center(
        child: SizedBox(
          width: 320,
          height: 50,
          child: ad == null ? const SizedBox.shrink() : gma.AdWidget(ad: ad),
        ),
      ),
    );
  }
}

/// Full-screen ads are never timer-triggered. Only use at a natural break
/// initiated by the user, and only after enough navigation has occurred.
class AlkenzyFullScreenAds {
  AlkenzyFullScreenAds._();
  static final instance = AlkenzyFullScreenAds._();

  final SafeContractsMobileAds _ads = SafeContractsMobileAds.instance;
  static const _minInterstitialGap = Duration(minutes: 3);
  static const _minAppOpenGap = Duration(minutes: 15);
  static const _appOpenTtl = Duration(hours: 4);

  gma.InterstitialAd? _main;
  gma.InterstitialAd? _reports;
  gma.AppOpenAd? _appOpen;
  DateTime? _appOpenLoadedAt;
  DateTime? _lastFullScreenAt;
  DateTime? _lastAppOpenAt;
  bool _preparing = false;
  bool _mainLoading = false;
  bool _reportsLoading = false;
  bool _appOpenLoading = false;
  bool _showing = false;
  int _naturalActions = 0;
  int _interstitialsShown = 0;

  void recordNavigation() => _naturalActions++;

  Future<void> prepare() async {
    if (_preparing || !_ads.canUseGooglePlacements) { return; }
    _preparing = true;
    try {
      if (!await _ads.ensureGooglePlacementsReady() ||
          !_ads.canUseGooglePlacements) { return; }
      _loadInterstitial(reports: false);
      _loadInterstitial(reports: true);
      _loadAppOpen();
    } on Object {
      // Ads are optional; never fail the authenticated app.
    } finally {
      _preparing = false;
    }
  }

  void _loadInterstitial({required bool reports}) {
    if (!_ads.canUseGooglePlacements) { return; }
    if (reports ? (_reports != null || _reportsLoading)
                : (_main != null || _mainLoading)) { return; }
    if (reports) { _reportsLoading = true; } else { _mainLoading = true; }
    final unit = AlkenzyAdUnits.interstitial(
      reports: reports, testMode: _ads.testMode,
    );
    gma.InterstitialAd.load(
      adUnitId: unit,
      request: const gma.AdRequest(),
      adLoadCallback: gma.InterstitialAdLoadCallback(
        onAdLoaded: (ad) {
          if (!_ads.canUseGooglePlacements) { ad.dispose(); return; }
          if (reports) { _reports = ad; _reportsLoading = false; }
          else { _main = ad; _mainLoading = false; }
        },
        onAdFailedToLoad: (_) {
          if (reports) { _reportsLoading = false; }
          else { _mainLoading = false; }
        },
      ),
    );
  }

  /// Called only BEFORE navigation after finishing the contract/report view.
  /// Never fires on login, screen load, idle timers, data entry, or payment.
  Future<void> maybeShowAtNaturalBreak({required bool reports}) async {
    if (!_ads.canUseGooglePlacements || _showing ||
        _naturalActions < 3 || _interstitialsShown >= 2) { return; }
    final now = DateTime.now();
    if (_lastFullScreenAt != null &&
        now.difference(_lastFullScreenAt!) < _minInterstitialGap) { return; }
    final ad = reports ? _reports : _main;
    if (ad == null) { unawaited(prepare()); return; }
    if (reports) { _reports = null; } else { _main = null; }
    _showing = true;
    _lastFullScreenAt = now;
    _interstitialsShown++;
    _naturalActions = 0;
    final done = Completer<void>();
    ad.fullScreenContentCallback = gma.FullScreenContentCallback(
      onAdDismissedFullScreenContent: (ad) {
        ad.dispose();
        _showing = false;
        if (!done.isCompleted) done.complete();
        unawaited(prepare());
      },
      onAdFailedToShowFullScreenContent: (ad, error) {
        ad.dispose();
        _showing = false;
        if (!done.isCompleted) done.complete();
        unawaited(prepare());
      },
    );
    try {
      await ad.show();
      await done.future;
    } on Object {
      ad.dispose();
      _showing = false;
    }
  }

  void _loadAppOpen() {
    if (!_ads.canUseGooglePlacements || _appOpenLoading || _appOpen != null) { return; }
    _appOpenLoading = true;
    gma.AppOpenAd.load(
      adUnitId: AlkenzyAdUnits.appOpenUnit(testMode: _ads.testMode),
      request: const gma.AdRequest(),
      adLoadCallback: gma.AppOpenAdLoadCallback(
        onAdLoaded: (ad) {
          _appOpenLoading = false;
          if (!_ads.canUseGooglePlacements) { ad.dispose(); return; }
          _appOpen = ad;
          _appOpenLoadedAt = DateTime.now();
        },
        onAdFailedToLoad: (_) => _appOpenLoading = false,
      ),
    );
  }

  /// Only on foreground return to the dashboard after a real background pause;
  /// never on first launch or when a modal/contract/editing page is active.
  Future<void> showAppOpenOnForeground() async {
    if (!_ads.canUseGooglePlacements || _showing || _appOpen == null) { return; }
    final now = DateTime.now();
    if (_appOpenLoadedAt == null ||
        now.difference(_appOpenLoadedAt!) >= _appOpenTtl) {
      _appOpen?.dispose();
      _appOpen = null;
      unawaited(prepare());
      return;
    }
    if (_lastAppOpenAt != null &&
        now.difference(_lastAppOpenAt!) < _minAppOpenGap) { return; }
    final ad = _appOpen!;
    _appOpen = null;
    _showing = true;
    _lastAppOpenAt = now;
    ad.fullScreenContentCallback = gma.FullScreenContentCallback(
      onAdDismissedFullScreenContent: (ad) {
        ad.dispose();
        _showing = false;
        unawaited(prepare());
      },
      onAdFailedToShowFullScreenContent: (ad, error) {
        ad.dispose();
        _showing = false;
        unawaited(prepare());
      },
    );
    try { await ad.show(); }
    on Object { ad.dispose(); _showing = false; }
  }

  void reset() {
    _naturalActions = 0;
    _interstitialsShown = 0;
    _lastFullScreenAt = null;
    _lastAppOpenAt = null;
    _main?.dispose(); _main = null;
    _reports?.dispose(); _reports = null;
    _appOpen?.dispose(); _appOpen = null;
  }
}
