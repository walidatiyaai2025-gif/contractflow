import 'package:flutter/foundation.dart';
import 'package:local_auth/local_auth.dart';

final class MobileBiometricAuth {
  MobileBiometricAuth({LocalAuthentication? localAuth})
      : _localAuth = localAuth ?? LocalAuthentication();

  final LocalAuthentication _localAuth;

  Future<bool> isAvailable() async {
    try {
      if (await _localAuth.canCheckBiometrics) return true;
      if (await _localAuth.isDeviceSupported()) return true;
      return false;
    } on Object {
      // Some Android vendors can fail the capability probe even though the
      // system biometric prompt is available. Keep the fingerprint entry
      // visible on Android and let authenticate() return the authoritative
      // result from the OS.
      return defaultTargetPlatform == TargetPlatform.android;
    }
  }

  Future<bool> authenticate({required bool isArabic}) async {
    try {
      return await _localAuth.authenticate(
        localizedReason: isArabic
            ? 'استخدم بصمة الإصبع للدخول إلى Alkenzy ADV'
            : 'Use your fingerprint to unlock Alkenzy ADV',
        options: const AuthenticationOptions(
          biometricOnly: true,
          stickyAuth: true,
          useErrorDialogs: true,
        ),
      );
    } on Object {
      return false;
    }
  }
}
