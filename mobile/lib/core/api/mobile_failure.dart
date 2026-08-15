import 'api_client.dart';
import 'io_api_transport.dart';

enum MobileFailureKind {
  authentication,
  forbidden,
  validation,
  network,
  server,
  invalidResponse,
  unknown,
}

final class MobileFailure {
  const MobileFailure({required this.kind, required this.message});

  final MobileFailureKind kind;
  final String message;

  bool get retryable => switch (kind) {
        MobileFailureKind.network || MobileFailureKind.server => true,
        _ => false,
      };

  static MobileFailure from(Object error) {
    if (error is SafeContractsApiException) {
      final kind = switch (error.statusCode) {
        401 => MobileFailureKind.authentication,
        403 => MobileFailureKind.forbidden,
        400 || 409 || 422 => MobileFailureKind.validation,
        >= 500 => MobileFailureKind.server,
        _ => MobileFailureKind.unknown,
      };
      return MobileFailure(kind: kind, message: error.message);
    }
    if (error is SafeContractsTransportException) {
      return MobileFailure(
        kind: MobileFailureKind.network,
        message: error.message,
      );
    }
    if (error is FormatException) {
      return MobileFailure(
        kind: MobileFailureKind.invalidResponse,
        message: error.message,
      );
    }
    return MobileFailure(
      kind: MobileFailureKind.unknown,
      message: error.toString(),
    );
  }
}
