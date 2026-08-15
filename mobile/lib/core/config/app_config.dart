final class AppConfig {
  const AppConfig({
    required this.environment,
    required this.apiBaseUri,
  });

  factory AppConfig.fromEnvironment() {
    const String environment = String.fromEnvironment(
      'SAFECONTRACTS_ENV',
      defaultValue: 'development',
    );
    const String apiBaseUrl = String.fromEnvironment(
      'SAFECONTRACTS_API_BASE_URL',
      defaultValue: '',
    );

    return AppConfig.fromValues(
      environment: environment,
      apiBaseUrl: apiBaseUrl,
    );
  }

  factory AppConfig.fromValues({
    required String environment,
    required String apiBaseUrl,
  }) {
    final String normalizedEnvironment = environment.trim().isEmpty
        ? 'development'
        : environment.trim().toLowerCase();

    if (apiBaseUrl.trim().isEmpty) {
      return AppConfig(
        environment: normalizedEnvironment,
        apiBaseUri: null,
      );
    }

    final Uri? parsed = Uri.tryParse(apiBaseUrl.trim());
    if (parsed == null || !parsed.hasScheme || !parsed.hasAuthority) {
      throw FormatException('SAFECONTRACTS_API_BASE_URL must be an absolute URL.');
    }
    if (parsed.scheme != 'https' && parsed.scheme != 'http') {
      throw FormatException('SAFECONTRACTS_API_BASE_URL must use http or https.');
    }

    final String normalizedPath = parsed.path.endsWith('/')
        ? parsed.path
        : '${parsed.path}/';

    return AppConfig(
      environment: normalizedEnvironment,
      apiBaseUri: parsed.replace(path: normalizedPath),
    );
  }

  final String environment;
  final Uri? apiBaseUri;

  bool get isApiConfigured => apiBaseUri != null;

  Uri apiUri(String relativePath) {
    final Uri? base = apiBaseUri;
    if (base == null) {
      throw StateError('SafeContracts API base URL is not configured.');
    }

    final String clean = relativePath.startsWith('/')
        ? relativePath.substring(1)
        : relativePath;
    return base.resolve(clean);
  }
}
