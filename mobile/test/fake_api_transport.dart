import 'package:safecontracts_mobile/core/api/api_transport.dart';

final class RecordedApiRequest {
  const RecordedApiRequest({
    required this.uri,
    required this.method,
    required this.headers,
  });

  final Uri uri;
  final String method;
  final Map<String, String> headers;
}

typedef FakeApiHandler = ApiTransportResponse Function(Uri uri);

final class FakeApiTransport implements SafeContractsTransport {
  FakeApiTransport(this.handler);

  final FakeApiHandler handler;
  final List<RecordedApiRequest> requests = <RecordedApiRequest>[];

  @override
  Future<ApiTransportResponse> send({
    required Uri uri,
    required String method,
    Map<String, String> headers = const <String, String>{},
  }) async {
    requests.add(
      RecordedApiRequest(
        uri: uri,
        method: method,
        headers: Map<String, String>.unmodifiable(headers),
      ),
    );
    return handler(uri);
  }
}
