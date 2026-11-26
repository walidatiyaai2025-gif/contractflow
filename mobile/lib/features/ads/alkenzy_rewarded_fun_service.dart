import 'dart:async';

import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:google_mobile_ads/google_mobile_ads.dart' as gma;

import 'alkenzy_ad_units.dart';
import 'mobile_ads.dart';

/// Account-independent device ledger. A rewarded view grants ONE XO ticket.
/// Tickets survive app restarts; they are never granted for skipped/failed ads.
final class AlkenzyRewardedFun extends ChangeNotifier {
  AlkenzyRewardedFun({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static final instance = AlkenzyRewardedFun();
  static const cooldown = Duration(minutes: 2);
  static const _countKey = 'alkenzy.fun.rewarded_count.v1';
  static const _timeKey = 'alkenzy.fun.rewarded_at.v1';
  static const _ticketKey = 'alkenzy.fun.pending_xo.v1';

  final FlutterSecureStorage _storage;
  Future<void>? _restoring;
  bool _busy = false;
  int _count = 0;
  bool _hasTicket = false;
  DateTime? _lastRewardedAt;

  bool get busy => _busy;
  int get count => _count;
  bool get hasTicket => _hasTicket;

  Duration remaining([DateTime? time]) {
    final awarded = _lastRewardedAt;
    if (awarded == null) return Duration.zero;
    final elapsed = (time ?? DateTime.now()).difference(awarded);
    if (elapsed.isNegative) return cooldown;
    final value = cooldown - elapsed;
    return value.isNegative ? Duration.zero : value;
  }

  bool get canStart =>
      !_busy && (_hasTicket || remaining() == Duration.zero);

  Future<void> ensureLoaded() => _restoring ??= _restore();

  Future<void> _restore() async {
    try {
      final values = await Future.wait(<Future<String?>>[
        _storage.read(key: _countKey),
        _storage.read(key: _timeKey),
        _storage.read(key: _ticketKey),
      ]);
      _count = int.tryParse(values[0] ?? '') ?? 0;
      if (_count < 0) _count = 0;
      final stamp = int.tryParse(values[1] ?? '');
      _lastRewardedAt = stamp == null
          ? null
          : DateTime.fromMillisecondsSinceEpoch(stamp);
      _hasTicket = values[2] == '1';
    } on Object {
      // Storage failures do not block guest sign-in or other app features.
    } finally {
      notifyListeners();
    }
  }

  Future<void> _save() async {
    try {
      await Future.wait(<Future<void>>[
        _storage.write(key: _countKey, value: '$_count'),
        _storage.write(
          key: _timeKey,
          value: '${_lastRewardedAt?.millisecondsSinceEpoch ?? 0}',
        ),
        _storage.write(key: _ticketKey, value: _hasTicket ? '1' : '0'),
      ]);
    } on Object {
      // The in-memory ticket remains valid if device storage is unavailable.
    }
  }

  /// Consume exactly one stored entitlement when the game popup opens.
  Future<bool> takeTicket() async {
    await ensureLoaded();
    if (!_hasTicket) return false;
    _hasTicket = false;
    notifyListeners();
    await _save();
    return true;
  }

  Future<bool> watchForOneGame() async {
    await ensureLoaded();
    if (_busy || _hasTicket || remaining() > Duration.zero) return false;
    _busy = true;
    notifyListeners();
    try {
      final ads = SafeContractsMobileAds.instance;
      if (!ads.canUseGooglePlacements ||
          !await ads.ensureGooglePlacementsReady()) {
        return false;
      }

      final loaded = Completer<gma.RewardedAd?>();
      await gma.RewardedAd.load(
        adUnitId: AlkenzyAdUnits.rewardedUnit(testMode: ads.testMode),
        request: const gma.AdRequest(),
        rewardedAdLoadCallback: gma.RewardedAdLoadCallback(
          onAdLoaded: (ad) {
            if (!loaded.isCompleted) loaded.complete(ad);
          },
          onAdFailedToLoad: (error) {
            if (!loaded.isCompleted) loaded.complete(null);
          },
        ),
      );
      final ad = await loaded.future;
      if (ad == null) return false;

      final finished = Completer<void>();
      Future<void>? entitlement;
      void close(gma.AdWithoutView ad) {
        ad.dispose();
        if (!finished.isCompleted) finished.complete();
      }
      ad.fullScreenContentCallback = gma.FullScreenContentCallback(
        onAdDismissedFullScreenContent: close,
        onAdFailedToShowFullScreenContent: (ad, error) => close(ad),
      );
      try {
        await ad.show(
          onUserEarnedReward: (ad, reward) {
            // The earned callback is the ONLY path that grants a game.
            entitlement ??= _grantTicket();
          },
        );
        await finished.future;
      } on Object {
        ad.dispose();
      }
      if (entitlement != null) {
        await entitlement;
        return true;
      }
      return false;
    } on Object {
      return false;
    } finally {
      _busy = false;
      notifyListeners();
    }
  }

  Future<void> _grantTicket() async {
    if (_hasTicket) return;
    _hasTicket = true;
    _count++;
    _lastRewardedAt = DateTime.now();
    notifyListeners();
    await _save();
  }
}
