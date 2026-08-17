import 'package:flutter/foundation.dart';

import '../../core/api/api_client.dart';

enum ProfileDeviceLoadState { idle, loading, ready, error }

final class SafeContractsDevice {
  const SafeContractsDevice({
    required this.id,
    required this.platform,
    required this.isActive,
    required this.lastSeenAt,
    required this.createdAt,
    required this.updatedAt,
  });

  final int id;
  final String platform;
  final bool isActive;
  final String? lastSeenAt;
  final String? createdAt;
  final String? updatedAt;

  factory SafeContractsDevice.fromData(Object? value) {
    final data = apiObjectMap(value, 'device');
    final id = _positiveInt(data['id'], 'device.id');
    final platform = _platform(data['platform']);
    return SafeContractsDevice(
      id: id,
      platform: platform,
      isActive: _boolish(data['is_active'], 'device.is_active'),
      lastSeenAt: _optionalTimestamp(
        data['last_seen_at'],
        'device.last_seen_at',
      ),
      createdAt: _optionalTimestamp(data['created_at'], 'device.created_at'),
      updatedAt: _optionalTimestamp(data['updated_at'], 'device.updated_at'),
    );
  }
}

final class DevicesSnapshot {
  const DevicesSnapshot({required this.devices});

  static const maxDevices = 32;

  final List<SafeContractsDevice> devices;

  factory DevicesSnapshot.fromEnvelope(ApiEnvelope envelope) {
    if (envelope.meta['scope'] != 'current_user') {
      throw const FormatException('device scope metadata is invalid.');
    }
    final rows = apiObjectList(envelope.data, 'devices.data');
    if (rows.length > maxDevices) {
      throw const FormatException(
        'device payload exceeds the supported bound.',
      );
    }
    final devices = rows
        .map(SafeContractsDevice.fromData)
        .toList(growable: false);
    final ids = <int>{};
    for (final device in devices) {
      if (!ids.add(device.id)) {
        throw const FormatException('devices contain duplicate IDs.');
      }
    }
    return DevicesSnapshot(
      devices: List<SafeContractsDevice>.unmodifiable(devices),
    );
  }
}

final class ProfileRepository {
  ProfileRepository(this.client);

  final SafeContractsApiClient client;

  Future<DevicesSnapshot> loadDevices() async {
    final envelope = await client.get('devices');
    return DevicesSnapshot.fromEnvelope(envelope);
  }
}

final class ProfileController extends ChangeNotifier {
  ProfileController(this.repository);

  final ProfileRepository repository;

  ProfileDeviceLoadState state = ProfileDeviceLoadState.idle;
  DevicesSnapshot? snapshot;
  String? errorMessage;

  Future<void> ensureLoaded() async {
    if (state == ProfileDeviceLoadState.idle) {
      await load();
    }
  }

  Future<void> load() async {
    state = ProfileDeviceLoadState.loading;
    errorMessage = null;
    notifyListeners();
    try {
      snapshot = await repository.loadDevices();
      state = ProfileDeviceLoadState.ready;
    } on SafeContractsApiException catch (error) {
      snapshot = null;
      errorMessage = error.message;
      state = ProfileDeviceLoadState.error;
    } on Object catch (error) {
      snapshot = null;
      errorMessage = error.toString();
      state = ProfileDeviceLoadState.error;
    }
    notifyListeners();
  }
}

int _positiveInt(Object? value, String field) {
  final parsed = switch (value) {
    final int value => value,
    final String value => int.tryParse(value),
    _ => null,
  };
  if (parsed == null || parsed <= 0) {
    throw FormatException('$field must be a positive integer.');
  }
  return parsed;
}

String _platform(Object? value) {
  if (value is! String) {
    throw const FormatException('device.platform must be a string.');
  }
  final normalized = value.trim().toLowerCase();
  if (!const <String>{'android', 'ios', 'web'}.contains(normalized)) {
    throw const FormatException('device.platform is invalid.');
  }
  return normalized;
}

String? _optionalTimestamp(Object? value, String field) {
  if (value == null || value == '') {
    return null;
  }
  if (value is! String) {
    throw FormatException('$field must be a string or null.');
  }
  final normalized = value.trim();
  if (normalized.isEmpty || normalized.length > 32) {
    throw FormatException('$field is invalid.');
  }
  return normalized;
}

bool _boolish(Object? value, String field) {
  return switch (value) {
    true => true,
    false => false,
    1 => true,
    0 => false,
    '1' => true,
    '0' => false,
    _ => throw FormatException('$field must be boolean-like.'),
  };
}
