import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

import 'app.dart';
import 'core/config/app_environment.dart';
import 'features/ads/mobile_ads.dart';
import 'features/notifications/notification_presenter.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Render the application first. Firebase/FCM are auxiliary runtime services
  // and must never prevent the login/bootstrap UI from starting on a device
  // with an OEM-specific Firebase or notification integration failure.
  runApp(
    SafeContractsAdsHost(
      child: SafeContractsApp(environment: AppEnvironment.fromCompileTime()),
    ),
  );

  unawaited(_initializeRuntimeServices());
}

Future<void> _initializeRuntimeServices() async {
  try {
    await Firebase.initializeApp();
    await MobileNotificationPresenter.start();
  } on Object catch (error, stackTrace) {
    FlutterError.reportError(
      FlutterErrorDetails(
        exception: error,
        stack: stackTrace,
        library: 'SafeContracts runtime services',
        context: ErrorDescription('initializing Firebase/FCM after first paint'),
      ),
    );
  }
}
