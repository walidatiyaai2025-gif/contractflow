import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

enum SafeContractsDataScope { all, assigned, none }

enum SessionState {
  idle,
  loading,
  authenticated,
  unauthenticated,
  forbidden,
  error,
}

final class SafeContractsSession {
  const SafeContractsSession({
    required this.userId,
    required this.scope,
    required this.capabilities,
    this.displayName,
    this.avatarUrl,
  });

  static const maxCapabilities = 128;

  final int userId;
  final SafeContractsDataScope scope;
  final Map<String, bool> capabilities;
  final String? displayName;
  final String? avatarUrl;

  bool can(String capability) => capabilities[capability] ?? false;

  factory SafeContractsSession.fromData(Object? value) {
    final data = apiObjectMap(value, 'session.data');
    if (data['authenticated'] != true) {
      throw const FormatException(
        'session.authenticated must be true for an authenticated session.',
      );
    }

    final userId = _positiveInt(data['user_id'], 'session.user_id');
    final scope = switch (data['scope']) {
      'all' => SafeContractsDataScope.all,
      'assigned' => SafeContractsDataScope.assigned,
      'none' => SafeContractsDataScope.none,
      _ => throw const FormatException('session.scope is invalid.'),
    };
    final rawCapabilities = apiObjectMap(
      data['capabilities'],
      'session.capabilities',
    );
    if (rawCapabilities.length > maxCapabilities) {
      throw const FormatException(
        'session.capabilities contains too many entries.',
      );
    }

    final capabilities = <String, bool>{};
    for (final entry in rawCapabilities.entries) {
      if (!_validCapabilityName(entry.key)) {
        throw const FormatException('session capability name is invalid.');
      }
      final value = entry.value;
      if (value is! bool) {
        throw FormatException(
          'session capability ${entry.key} must be boolean.',
        );
      }
      capabilities[entry.key] = value;
    }

    return SafeContractsSession(
      userId: userId,
      scope: scope,
      capabilities: Map<String, bool>.unmodifiable(capabilities),
      displayName: _optionalDisplayName(data['display_name']),
      avatarUrl: _optionalAvatarUrl(data['avatar_url']),
    );
  }
}

final class SessionController extends ChangeNotifier {
  SessionController(this.client);

  final SafeContractsApiClient client;

  SessionState state = SessionState.idle;
  SafeContractsSession? session;
  String? errorMessage;

  Future<void> bootstrap() async {
    state = SessionState.loading;
    errorMessage = null;
    notifyListeners();

    try {
      final envelope = await client.get('session');
      session = SafeContractsSession.fromData(envelope.data);
      state = SessionState.authenticated;
    } on SafeContractsApiException catch (error) {
      session = null;
      errorMessage = error.message;
      if (error.statusCode == 401) {
        state = SessionState.unauthenticated;
      } else if (error.statusCode == 403) {
        state = SessionState.forbidden;
      } else {
        state = SessionState.error;
      }
    } on Object catch (error) {
      session = null;
      errorMessage = error.toString();
      state = SessionState.error;
    }
    notifyListeners();
  }

  void reset() {
    session = null;
    errorMessage = null;
    state = SessionState.unauthenticated;
    notifyListeners();
  }
}

int _positiveInt(Object? value, String field) {
  if (value is int && value > 0) {
    return value;
  }
  if (value is String) {
    final parsed = int.tryParse(value);
    if (parsed != null && parsed > 0) {
      return parsed;
    }
  }
  throw FormatException('$field must be a positive integer.');
}

String? _optionalDisplayName(Object? value) {
  if (value == null || value == '') return null;
  if (value is! String) {
    throw const FormatException(
        'session.display_name must be a string or null.');
  }
  final normalized = value.trim();
  if (normalized.isEmpty) return null;
  if (normalized.length > 160) {
    throw const FormatException('session.display_name is too long.');
  }
  return normalized;
}

String? _optionalAvatarUrl(Object? value) {
  if (value == null || value == '') return null;
  if (value is! String) {
    throw const FormatException('session.avatar_url must be a string or null.');
  }
  final normalized = value.trim();
  if (normalized.isEmpty) return null;
  if (normalized.length > 2048) {
    throw const FormatException('session.avatar_url is too long.');
  }
  final uri = Uri.tryParse(normalized);
  if (uri == null ||
      !uri.hasAuthority ||
      (uri.scheme != 'https' && uri.scheme != 'http')) {
    throw const FormatException('session.avatar_url is invalid.');
  }
  return normalized;
}

bool _validCapabilityName(String value) {
  if (value.isEmpty || value.length > 80) {
    return false;
  }
  return RegExp(r'^safecontracts_[a-z0-9_]+$').hasMatch(value);
}
