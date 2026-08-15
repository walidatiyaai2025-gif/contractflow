import 'dart:async';
import 'dart:convert';
import 'dart:io';

import 'api_transport.dart';

final class IoApiTransport implements SafeContractsTransport {
  IoApiTransport({
    this.timeout = const Duration(seconds: 15),
    this.maxRequestBytes = 256 * 1024,
    this.maxResponseBytes = 2 * 1024 * 1024,
  });

  final Duration timeout;
  final int maxRequestBytes;
  final int maxResponseBytes;

  @override
  Future<ApiTransportResponse> send({
    required Uri uri,
    required String method,
    Map<String, String> headers = const <String, String>{},
    String? body,
  }) async {
    final bodyBytes = body == null ? const <int>[] : utf8.encode(body);
    if (bodyBytes.length > maxRequestBytes) {
      throw const FormatException('SafeContracts API request is too large.');
    }

    final client = HttpClient()..connectionTimeout = timeout;
    try {
      final request = await client.openUrl(method, uri).timeout(timeout);
      for (final entry in headers.entries) {
        request.headers.set(entry.key, entry.value);
      }
      if (body != null) {
        request.contentLength = bodyBytes.length;
        request.add(bodyBytes);
      }

      final response = await request.close().timeout(timeout);
      final bytes = <int>[];
      await for (final chunk in response.timeout(timeout)) {
        if (bytes.length + chunk.length > maxResponseBytes) {
          throw const FormatException(
            'SafeContracts API response is too large.',
          );
        }
        bytes.addAll(chunk);
      }

      final responseHeaders = <String, String>{};
      response.headers.forEach((name, values) {
        responseHeaders[name] = values.join(',');
      });

      return ApiTransportResponse(
        statusCode: response.statusCode,
        headers: responseHeaders,
        body: utf8.decode(bytes),
      );
    } on TimeoutException {
      throw const SafeContractsTransportException(
        'SafeContracts API request timed out.',
      );
    } on HandshakeException {
      throw const SafeContractsTransportException(
        'SafeContracts API TLS handshake failed.',
      );
    } on SocketException {
      throw const SafeContractsTransportException(
        'SafeContracts API is unreachable.',
      );
    } on HttpException {
      throw const SafeContractsTransportException(
        'SafeContracts API request failed.',
      );
    } finally {
      client.close(force: true);
    }
  }
}
