import 'package:flutter_test/flutter_test.dart';
import 'package:safecontracts_mobile/features/notifications/notification_sound.dart';

void main() {
  test('notification sound mapping preserves supported server keys', () {
    for (final key in supportedNotificationSoundKeys) {
      expect(
        notificationSoundKeyFromData(<String, String>{'sound_key': key}),
        key,
      );
    }
  });

  test('notification sound mapping falls back safely', () {
    expect(notificationSoundKeyFromData(const <String, String>{}), 'default');
    expect(
      notificationSoundKeyFromData(
        const <String, String>{'sound_key': 'unknown'},
      ),
      'default',
    );
  });
}
