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

  final AppEnvironment environment;
  final SafeContractsTransport transport;
  final ApiHeadersProvider headersProvider;

  Future<ApiEnvelope> get(
    String path, {
    Map<String, String> query = const <String, String>{},
  }) {
    return _request('GET', path, query: query);
  }

  Future<ApiEnvelope> post(
    String path, {
    Map<String, Object?> body = const <String, Object?>{},
    Map<String, String> query = const <String, String>{},
  }) {
    return _request('POST', path, query: query, jsonBody: body);
  }

  Future<ApiEnvelope> _request(
    String method,
    String path, {
    Map<String, String> query = const <String, String>{},
    Map<String, Object?>? jsonBody,
  }) async {
    final baseUri = environment.endpoint(path);
    final uri = baseUri.replace(
      queryParameters: query.isEmpty
          ? baseUri.queryParameters
          : <String, String>{...baseUri.queryParameters, ...query},
    );
    final sessionHeaders = await headersProvider();
    final response = await transport.send(
      uri: uri,
      method: method,
      headers: <String, String>{
        'Accept': 'application/json',
        if (jsonBody != null) 'Content-Type': 'application/json; charset=utf-8',
        ...sessionHeaders,
      },
      body: jsonBody == null ? null : jsonEncode(jsonBody),
    );

    final root = _decodeObject(response.body);
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
    final meta = metaValue == null
        ? <String, Object?>{}
        : _objectMap(metaValue, 'meta');
    return ApiEnvelope(data: root['data'], meta: meta);
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

String _string(Object? value, String fallback) {
  return value is String && value.trim().isNotEmpty ? value : fallback;
}
