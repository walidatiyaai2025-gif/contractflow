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
      name: const String.fromEnvironment('ESC_ENV', defaultValue: 'local'),
      apiBaseUrl: const String.fromEnvironment(
        'ESC_API_BASE_URL',
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
          'Unsupported Enterprise Safe Contracts environment: $name',
        ),
    };

    final uri = Uri.tryParse(apiBaseUrl.trim());
    if (uri == null || !uri.hasScheme || uri.host.isEmpty) {
      throw FormatException('ESC_API_BASE_URL must be an absolute URL.');
    }
    if (uri.scheme != 'http' && uri.scheme != 'https') {
      throw FormatException('ESC_API_BASE_URL must use HTTP or HTTPS.');
    }
    if (environmentName == AppEnvironmentName.production &&
        uri.scheme != 'https') {
      throw FormatException(
        'Production Enterprise Safe Contracts API must use HTTPS.',
      );
    }
    if (uri.userInfo.isNotEmpty) {
      throw FormatException(
        'ESC_API_BASE_URL must not contain embedded credentials.',
      );
    }
    if (uri.hasQuery || uri.fragment.isNotEmpty) {
      throw FormatException(
        'ESC_API_BASE_URL must not contain a query string or fragment.',
      );
    }
    if (_containsTraversal(uri.pathSegments)) {
      throw FormatException(
          'ESC_API_BASE_URL must not contain path traversal.');
    }

    final normalizedPath = uri.path.endsWith('/') ? uri.path : '${uri.path}/';
    return AppEnvironment._(
      name: environmentName,
      apiBaseUri: uri.replace(path: normalizedPath),
    );
  }

  Uri endpoint(String relativePath) {
    final parsed = _validatedRelativePath(relativePath);
    final endpoint = apiBaseUri.resolve(parsed.path);
    if (endpoint.scheme != apiBaseUri.scheme ||
        endpoint.host != apiBaseUri.host ||
        endpoint.port != apiBaseUri.port ||
        !endpoint.path.startsWith(apiBaseUri.path)) {
      throw FormatException(
        'Enterprise Safe Contracts API endpoint escaped its base URL.',
      );
    }
    return endpoint;
  }

  /// Builds WordPress' query-style REST endpoint on the exact same origin.
  ///
  /// The fallback exists only for structurally valid WordPress pretty REST
  /// bases (`.../wp-json/<namespace>/`). Scheme, host, port and REST namespace
  /// are derived from the validated ESC base URI and cannot be replaced by a
  /// response or caller-provided URL.
  Uri? wordpressQueryEndpoint(
    String relativePath, {
    Map<String, String> query = const <String, String>{},
  }) {
    final prettyEndpoint = endpoint(relativePath);
    const marker = '/wp-json/';
    final basePath = apiBaseUri.path;
    final markerIndex = basePath.indexOf(marker);
    if (markerIndex < 0 ||
        basePath.indexOf(marker, markerIndex + marker.length) >= 0) {
      return null;
    }

    final namespace = basePath.substring(markerIndex + marker.length);
    if (namespace.isEmpty || namespace == '/' || !namespace.endsWith('/')) {
      return null;
    }

    final routeStart = markerIndex + '/wp-json'.length;
    if (prettyEndpoint.path.length <= routeStart) {
      return null;
    }
    final restRoute = prettyEndpoint.path.substring(routeStart);
    if (!restRoute.startsWith('/') ||
        _containsTraversal(Uri(path: restRoute).pathSegments)) {
      return null;
    }

    final wordpressRootPath = basePath.substring(0, markerIndex + 1);
    return apiBaseUri.replace(
      path: wordpressRootPath,
      queryParameters: <String, String>{
        ...query,
        'rest_route': restRoute,
      },
      fragment: '',
    );
  }
}

Uri _validatedRelativePath(String relativePath) {
  final rawPath = relativePath.trim();
  if (rawPath.isEmpty || rawPath.contains('\\')) {
    throw FormatException('Enterprise Safe Contracts API path is invalid.');
  }

  final parsed = Uri.tryParse(rawPath);
  if (parsed == null ||
      parsed.hasScheme ||
      parsed.hasAuthority ||
      parsed.host.isNotEmpty ||
      parsed.userInfo.isNotEmpty ||
      parsed.hasQuery ||
      parsed.fragment.isNotEmpty) {
    throw FormatException(
      'Enterprise Safe Contracts API path must be relative.',
    );
  }
  if (_containsTraversal(parsed.pathSegments)) {
    throw FormatException(
      'Enterprise Safe Contracts API path must not traverse upward.',
    );
  }

  final cleanPath = parsed.path.replaceFirst(RegExp(r'^/+'), '');
  if (cleanPath.isEmpty) {
    throw FormatException('Enterprise Safe Contracts API path is empty.');
  }
  return parsed.replace(path: cleanPath);
}

bool _containsTraversal(List<String> segments) {
  return segments.any((segment) => segment == '.' || segment == '..');
}
