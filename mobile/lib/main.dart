import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/widgets.dart';

import 'app.dart';
import 'core/config/app_environment.dart';
import 'features/ads/mobile_ads.dart';
import 'features/notifications/notification_presenter.dart';

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  await Firebase.initializeApp();
  await MobileNotificationPresenter.start();
  runApp(
    SafeContractsAdsHost(
      child: SafeContractsApp(environment: AppEnvironment.fromCompileTime()),
    ),
  );
}
