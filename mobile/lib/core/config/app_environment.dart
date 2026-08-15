enum AppEnvironmentName { local, staging, production }

final class AppEnvironment {
  AppEnvironment._({
    required this.name,
    required this.apiBaseUri,
  });

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
    if (uri.userInfo.isNotEmpty) {
      throw FormatException(
        'SC_API_BASE_URL must not contain embedded credentials.',
      );
    }
    if (uri.hasQuery || uri.fragment.isNotEmpty) {
      throw FormatException(
        'SC_API_BASE_URL must not contain a query string or fragment.',
      );
    }
    if (_containsTraversal(uri.pathSegments)) {
      throw FormatException('SC_API_BASE_URL must not contain path traversal.');
    }

    final normalizedPath = uri.path.endsWith('/') ? uri.path : '${uri.path}/';
    return AppEnvironment._(
      name: environmentName,
      apiBaseUri: uri.replace(path: normalizedPath),
    );
  }

  Uri endpoint(String relativePath) {
    final rawPath = relativePath.trim();
    if (rawPath.isEmpty || rawPath.contains('\\')) {
      throw FormatException('SafeContracts API path is invalid.');
    }

    final parsed = Uri.tryParse(rawPath);
    if (parsed == null ||
        parsed.hasScheme ||
        parsed.hasAuthority ||
        parsed.host.isNotEmpty ||
        parsed.userInfo.isNotEmpty ||
        parsed.hasQuery ||
        parsed.fragment.isNotEmpty) {
      throw FormatException('SafeContracts API path must be relative.');
    }
    if (_containsTraversal(parsed.pathSegments)) {
      throw FormatException('SafeContracts API path must not traverse upward.');
    }

    final cleanPath = parsed.path.replaceFirst(RegExp(r'^/+'), '');
    if (cleanPath.isEmpty) {
      throw FormatException('SafeContracts API path is empty.');
    }
    final endpoint = apiBaseUri.resolve(cleanPath);
    if (endpoint.scheme != apiBaseUri.scheme ||
        endpoint.host != apiBaseUri.host ||
        endpoint.port != apiBaseUri.port ||
        !endpoint.path.startsWith(apiBaseUri.path)) {
      throw FormatException('SafeContracts API endpoint escaped its base URL.');
    }
    return endpoint;
  }
}

bool _containsTraversal(List<String> segments) {
  return segments.any((segment) => segment == '.' || segment == '..');
}
