import 'dart:convert';

import '../config/app_environment.dart';
import 'api_transport.dart';

final class ApiEnvelope {
  const ApiEnvelope({
    required this.data,
    required this.meta,
  });

  final Object? data;
  final Map<String, Object?> meta;
}

final class SafeContractsApiException implements Exception {
  const SafeContractsApiException({
    required this.code,
    required this.message,
    required this.statusCode,
  });

  final String code;
  final String message;
  final int statusCode;

  @override
  String toString() => '$code ($statusCode): $message';
}

typedef ApiHeadersProvider = Future<Map<String, String>> Function();

final class SafeContractsApiClient {
  SafeContractsApiClient({
    required this.environment,
    required this.transport,
    ApiHeadersProvider? headersProvider,
  }) : headersProvider = headersProvider ?? _emptyHeaders;

  static const apiVersion = 'v1';
  static const maxJsonRequestBytes = 256 * 1024;
  static const _bodyMethods = <String>{'POST', 'PUT', 'PATCH'};
  static const _supportedMethods = <String>{
    'GET',
    'POST',
    'PUT',
    'PATCH',
    'DELETE',
  };

  final AppEnvironment environment;
  final SafeContractsTransport transport;
  final ApiHeadersProvider headersProvider;

  Future<ApiEnvelope> get(
    String path, {
    Map<String, String> query = const <String, String>{},
  }) {
    return request('GET', path, query: query);
  }

  Future<ApiEnvelope> post(
    String path, {
    Map<String, String> query = const <String, String>{},
    Map<String, Object?> body = const <String, Object?>{},
  }) {
    return request('POST', path, query: query, body: body);
  }

  Future<ApiEnvelope> patch(
    String path, {
    Map<String, String> query = const <String, String>{},
    Map<String, Object?> body = const <String, Object?>{},
  }) {
    return request('PATCH', path, query: query, body: body);
  }

  Future<ApiEnvelope> request(
    String method,
    String path, {
    Map<String, String> query = const <String, String>{},
    Map<String, Object?>? body,
  }) async {
    final normalizedMethod = method.trim().toUpperCase();
    if (!_supportedMethods.contains(normalizedMethod)) {
      throw FormatException(
        'SafeContracts API method $normalizedMethod is not supported.',
      );
    }
    if (body != null && !_bodyMethods.contains(normalizedMethod)) {
      throw FormatException(
        'SafeContracts API $normalizedMethod requests must not include a body.',
      );
    }

    final baseUri = environment.endpoint(path);
    final uri = baseUri.replace(
      queryParameters: query.isEmpty
          ? baseUri.queryParameters
          : <String, String>{...baseUri.queryParameters, ...query},
    );
    final sessionHeaders = _validatedHeaders(await headersProvider());
    final encodedBody = body == null ? null : jsonEncode(body);
    if (encodedBody != null &&
        utf8.encode(encodedBody).length > maxJsonRequestBytes) {
      throw const FormatException(
          'SafeContracts API JSON request is too large.');
    }

    final response = await transport.send(
      uri: uri,
      method: normalizedMethod,
      headers: <String, String>{
        ...sessionHeaders,
        'Accept': 'application/json',
        if (encodedBody != null)
          'Content-Type': 'application/json; charset=utf-8',
      },
      body: encodedBody,
    );

    Map<String, Object?> root;
    try {
      root = _decodeObject(response.body);
    } on FormatException {
      if (response.statusCode < 200 || response.statusCode >= 300) {
        throw SafeContractsApiException(
          code: 'safecontracts_invalid_error_response',
          message: 'SafeContracts request failed.',
          statusCode: response.statusCode,
        );
      }
      rethrow;
    }

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw SafeContractsApiException(
        code: _string(root['code'], 'safecontracts_request_failed'),
        message: _string(root['message'], 'SafeContracts request failed.'),
        statusCode: response.statusCode,
      );
    }

    if (!root.containsKey('data')) {
      throw const FormatException(
        'SafeContracts API response does not contain data.',
      );
    }
    final metaValue = root['meta'];
    final meta =
        metaValue == null ? <String, Object?>{} : _objectMap(metaValue, 'meta');
    final responseVersion = meta['api_version'];
    if (responseVersion != null && responseVersion != apiVersion) {
      throw const FormatException(
          'SafeContracts API version is not supported.');
    }
    return ApiEnvelope(
      data: root['data'],
      meta: Map<String, Object?>.unmodifiable(meta),
    );
  }

  static Future<Map<String, String>> _emptyHeaders() async {
    return <String, String>{};
  }
}

Map<String, Object?> apiObjectMap(Object? value, String field) {
  return _objectMap(value, field);
}

List<Object?> apiObjectList(Object? value, String field) {
  if (value is! List<Object?>) {
    throw FormatException('$field must be a JSON array.');
  }
  return value;
}

Map<String, Object?> _decodeObject(String body) {
  if (body.trim().isEmpty) {
    throw const FormatException(
      'SafeContracts API returned an empty response.',
    );
  }
  final Object? decoded = jsonDecode(body) as Object?;
  return _objectMap(decoded, 'response');
}

Map<String, Object?> _objectMap(Object? value, String field) {
  if (value is! Map<Object?, Object?>) {
    throw FormatException('$field must be a JSON object.');
  }
  final result = <String, Object?>{};
  for (final entry in value.entries) {
    final key = entry.key;
    if (key is! String) {
      throw FormatException('$field contains a non-string key.');
    }
    result[key] = entry.value;
  }
  return result;
}

Map<String, String> _validatedHeaders(Map<String, String> headers) {
  final result = <String, String>{};
  for (final entry in headers.entries) {
    if (entry.key.trim().isEmpty ||
        entry.key.contains('\r') ||
        entry.key.contains('\n') ||
        entry.value.contains('\r') ||
        entry.value.contains('\n')) {
      throw const FormatException('SafeContracts API header is invalid.');
    }
    result[entry.key] = entry.value;
  }
  return result;
}

String _string(Object? value, String fallback) {
  return value is String && value.trim().isNotEmpty ? value : fallback;
}
