import 'dart:convert';

import 'package:safecontracts_mobile/core/api/api_client.dart';
import 'package:safecontracts_mobile/core/api/api_transport.dart';
import 'package:safecontracts_mobile/core/config/app_environment.dart';

import '../fake_api_transport.dart';

typedef HarnessHandler = ApiTransportResponse Function(Uri uri);

final class SafeContractsTestHarness {
  SafeContractsTestHarness(
    HarnessHandler handler, {
    ApiHeadersProvider? headersProvider,
  })  : transport = FakeApiTransport(handler),
        environment = AppEnvironment.fromValues(
          name: 'local',
          apiBaseUrl: 'http://127.0.0.1:8080/wp-json/safecontracts/v1/',
        ) {
    client = SafeContractsApiClient(
      environment: environment,
      transport: transport,
      headersProvider: headersProvider,
    );
  }

  final AppEnvironment environment;
  final FakeApiTransport transport;
  late final SafeContractsApiClient client;

  RecordedApiRequest get singleRequest => transport.requests.single;

  static ApiTransportResponse ok(
    Object? data, {
    Map<String, Object?> meta = const <String, Object?>{'api_version': 'v1'},
  }) {
    return ApiTransportResponse(
      statusCode: 200,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{'data': data, 'meta': meta}),
    );
  }

  static ApiTransportResponse error(
    int status,
    String code,
    String message,
  ) {
    return ApiTransportResponse(
      statusCode: status,
      headers: const <String, String>{'content-type': 'application/json'},
      body: jsonEncode(<String, Object?>{
        'code': code,
        'message': message,
        'data': <String, Object?>{'status': status},
      }),
    );
  }
}
