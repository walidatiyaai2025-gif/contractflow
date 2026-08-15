import 'package:flutter/widgets.dart';

import 'app/safe_contracts_app.dart';
import 'core/config/app_config.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  final AppConfig config = AppConfig.fromEnvironment();
  runApp(SafeContractsApp(config: config));
}
