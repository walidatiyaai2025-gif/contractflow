const Set<String> supportedNotificationSoundKeys = <String>{
  'default',
  'banknote_counter',
  'cashier_ka_ching',
  'coin_drop',
};

String notificationSoundKeyFromData(Map<String, String> data) {
  final candidate = (data['sound_key'] ?? 'default').trim().toLowerCase();
  return supportedNotificationSoundKeys.contains(candidate) ? candidate : 'default';
}
