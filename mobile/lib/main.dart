import 'package:flutter/widgets.dart';

import 'app.dart';
import 'core/config/app_environment.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(SafeContractsApp(environment: AppEnvironment.fromCompileTime()));
}
