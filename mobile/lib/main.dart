import 'dart:async';

import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/widgets.dart';

import 'app.dart';
import 'core/config/app_environment.dart';
import 'features/notifications/notification_presenter.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();

  // Render the application first. Firebase/FCM are auxiliary runtime services
  // and must never prevent the login/bootstrap UI from starting on a device
  // with an OEM-specific Firebase or notification integration failure.
  // Ads are rendered only inside the authenticated shell footer.
  // Never wrap the app root in an ad-driven AnimatedBuilder: SDK callbacks
  // must not influence the lifecycle of the authentication/session tree.
  runApp(SafeContractsApp(environment: AppEnvironment.fromCompileTime()));

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
        context:
            ErrorDescription('initializing Firebase/FCM after first paint'),
      ),
    );
  }
}
