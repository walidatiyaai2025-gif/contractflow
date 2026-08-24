import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:local_auth/local_auth.dart';

const _biometricEnabledKey = 'alkenzy_biometric_login_enabled_v1';

final class BiometricAuthService {
  BiometricAuthService({
    LocalAuthentication? localAuthentication,
    FlutterSecureStorage? storage,
  })  : _localAuthentication = localAuthentication ?? LocalAuthentication(),
        _storage = storage ?? const FlutterSecureStorage();

  final LocalAuthentication _localAuthentication;
  final FlutterSecureStorage _storage;

  Future<bool> isEnabled() async {
    try {
      return (await _storage.read(key: _biometricEnabledKey)) == 'true';
    } on Object {
      return false;
    }
  }

  Future<void> setEnabled(bool value) => _storage.write(
        key: _biometricEnabledKey,
        value: value ? 'true' : 'false',
      );

  Future<bool> isAvailable() async {
    try {
      final supported = await _localAuthentication.isDeviceSupported();
      if (!supported) return false;
      final canCheck = await _localAuthentication.canCheckBiometrics;
      if (!canCheck) return false;
      final types = await _localAuthentication.getAvailableBiometrics();
      return types.isNotEmpty;
    } on Object {
      return false;
    }
  }

  Future<BiometricAuthResult> authenticate({
    required bool isArabic,
    bool enrollment = false,
  }) async {
    try {
      if (!await isAvailable()) {
        return BiometricAuthResult(
          success: false,
          unavailable: true,
          message: isArabic
              ? 'البصمة غير متاحة على هذا الجهاز.'
              : 'Biometric authentication is not available on this device.',
        );
      }
      final authenticated = await _localAuthentication.authenticate(
        localizedReason: isArabic
            ? (enrollment
                ? 'أكد بصمتك لتفعيل الدخول بالبصمة إلى Alkenzy ADV.'
                : 'استخدم بصمتك للدخول إلى Alkenzy ADV.')
            : (enrollment
                ? 'Confirm your fingerprint to enable biometric sign in to Alkenzy ADV.'
                : 'Use your fingerprint to sign in to Alkenzy ADV.'),
        biometricOnly: true,
        sensitiveTransaction: true,
        persistAcrossBackgrounding: true,
      );
      if (!authenticated) {
        return BiometricAuthResult(
          success: false,
          cancelled: true,
          message: isArabic
              ? 'لم يتم تأكيد البصمة.'
              : 'Fingerprint authentication was not completed.',
        );
      }
      return const BiometricAuthResult(success: true);
    } on LocalAuthException catch (error) {
      final unavailable = switch (error.code) {
        LocalAuthExceptionCode.noBiometricHardware ||
        LocalAuthExceptionCode.noBiometricsEnrolled => true,
        _ => false,
      };
      final locked = switch (error.code) {
        LocalAuthExceptionCode.temporaryLockout ||
        LocalAuthExceptionCode.biometricLockout => true,
        _ => false,
      };
      return BiometricAuthResult(
        success: false,
        unavailable: unavailable,
        locked: locked,
        message: _messageFor(error.code, isArabic),
      );
    } on Object catch (error, stackTrace) {
      debugPrint('Biometric authentication failed: $error\n$stackTrace');
      return BiometricAuthResult(
        success: false,
        message: isArabic
            ? 'تعذر استخدام البصمة الآن. يمكنك المحاولة مرة أخرى أو استخدام كلمة المرور.'
            : 'Unable to use fingerprint right now. Retry or use your password.',
      );
    }
  }

  String _messageFor(LocalAuthExceptionCode code, bool isArabic) {
    if (isArabic) {
      return switch (code) {
        LocalAuthExceptionCode.noBiometricHardware =>
          'هذا الجهاز لا يدعم التحقق بالبصمة.',
        LocalAuthExceptionCode.noBiometricsEnrolled =>
          'لا توجد بصمة مسجلة على الجهاز. أضف بصمة من إعدادات الجهاز أولاً.',
        LocalAuthExceptionCode.temporaryLockout =>
          'البصمة مقفلة مؤقتًا بعد محاولات غير ناجحة. حاول لاحقًا.',
        LocalAuthExceptionCode.biometricLockout =>
          'البصمة مقفلة على الجهاز. افتح الجهاز بالطريقة الأساسية ثم أعد المحاولة.',
        _ => 'تعذر التحقق بالبصمة. حاول مرة أخرى أو استخدم كلمة المرور.',
      };
    }
    return switch (code) {
      LocalAuthExceptionCode.noBiometricHardware =>
        'This device does not support biometric authentication.',
      LocalAuthExceptionCode.noBiometricsEnrolled =>
        'No fingerprint is enrolled. Add one in device settings first.',
      LocalAuthExceptionCode.temporaryLockout =>
        'Fingerprint is temporarily locked after unsuccessful attempts. Try again later.',
      LocalAuthExceptionCode.biometricLockout =>
        'Fingerprint is locked on this device. Unlock the device normally, then retry.',
      _ => 'Fingerprint authentication failed. Retry or use your password.',
    };
  }
}

@immutable
final class BiometricAuthResult {
  const BiometricAuthResult({
    required this.success,
    this.cancelled = false,
    this.unavailable = false,
    this.locked = false,
    this.message,
  });

  final bool success;
  final bool cancelled;
  final bool unavailable;
  final bool locked;
  final String? message;
}
