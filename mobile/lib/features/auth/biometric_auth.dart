import 'package:local_auth/local_auth.dart';

final class MobileBiometricAuth {
  MobileBiometricAuth({LocalAuthentication? localAuth})
      : _localAuth = localAuth ?? LocalAuthentication();

  final LocalAuthentication _localAuth;

  Future<bool> isAvailable() async {
    try {
      if (!await _localAuth.isDeviceSupported()) return false;
      if (!await _localAuth.canCheckBiometrics) return false;
      final methods = await _localAuth.getAvailableBiometrics();
      return methods.isNotEmpty;
    } on Object {
      return false;
    }
  }

  Future<bool> authenticate({required bool isArabic}) async {
    try {
      return await _localAuth.authenticate(
        localizedReason: isArabic
            ? 'استخدم بصمة الإصبع أو قفل الجهاز للدخول إلى Alkenzy ADV'
            : 'Use your biometric or device credential to unlock Alkenzy ADV',
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
