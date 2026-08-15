enum AppEnvironmentName { local, staging, production }

final class AppEnvironment {
  AppEnvironment._({required this.name, required this.apiBaseUri});

  final AppEnvironmentName name;
  final Uri apiBaseUri;

  factory AppEnvironment.fromCompileTime() {
    return AppEnvironment.fromValues(
      name: const String.fromEnvironment('SC_ENV', defaultValue: 'local'),
      apiBaseUrl: const String.fromEnvironment(
        'SC_API_BASE_URL',
        defaultValue: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
      ),
    );
  }

  factory AppEnvironment.fromValues({
    required String name,
    required String apiBaseUrl,
  }) {
    final normalizedName = name.trim().toLowerCase();
    final environmentName = switch (normalizedName) {
      'local' => AppEnvironmentName.local,
      'staging' => AppEnvironmentName.staging,
      'production' => AppEnvironmentName.production,
      _ => throw FormatException(
        'Unsupported SafeContracts environment: $name',
      ),
    };

    final uri = Uri.tryParse(apiBaseUrl.trim());
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw FormatException('SC_API_BASE_URL must be an absolute URL.');
    }
    if (uri.scheme != 'http' && uri.scheme != 'https') {
      throw FormatException('SC_API_BASE_URL must use HTTP or HTTPS.');
    }
    if (environmentName == AppEnvironmentName.production &&
        uri.scheme != 'https') {
      throw FormatException('Production SafeContracts API must use HTTPS.');
    }

    final normalizedPath = uri.path.endsWith('/') ? uri.path : '${uri.path}/';
    return AppEnvironment._(
      name: environmentName,
      apiBaseUri: uri.replace(path: normalizedPath),
    );
  }

  Uri endpoint(String relativePath) {
    final cleanPath = relativePath.replaceFirst(RegExp(r'^/+'), '');
    return apiBaseUri.resolve(cleanPath);
  }
}
