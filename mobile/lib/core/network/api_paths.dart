abstract final class ApiPaths {
  static const String namespace = 'wp-json/safecontracts/v1';

  static String get health => '$namespace/health';
  static String get me => '$namespace/me';
}
