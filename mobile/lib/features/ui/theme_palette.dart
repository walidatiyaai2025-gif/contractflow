import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

enum AlkenzyThemePalette { navy, emerald, burgundy, violet, graphite }

extension AlkenzyThemePaletteX on AlkenzyThemePalette {
  String get storageValue => name;

  String label(bool ar) => switch (this) {
        AlkenzyThemePalette.navy => ar ? 'الأزرق' : 'Navy',
        AlkenzyThemePalette.emerald => ar ? 'الزمردي' : 'Emerald',
        AlkenzyThemePalette.burgundy => ar ? 'العنابي' : 'Burgundy',
        AlkenzyThemePalette.violet => ar ? 'البنفسجي' : 'Violet',
        AlkenzyThemePalette.graphite => ar ? 'الجرافيت' : 'Graphite',
      };

  Color get primary => switch (this) {
        AlkenzyThemePalette.navy => const Color(0xFF0C3B5D),
        AlkenzyThemePalette.emerald => const Color(0xFF126B58),
        AlkenzyThemePalette.burgundy => const Color(0xFF7A2942),
        AlkenzyThemePalette.violet => const Color(0xFF6242A6),
        AlkenzyThemePalette.graphite => const Color(0xFF3B424B),
      };

  Color get deep => switch (this) {
        AlkenzyThemePalette.navy => const Color(0xFF061B2F),
        AlkenzyThemePalette.emerald => const Color(0xFF063D32),
        AlkenzyThemePalette.burgundy => const Color(0xFF461426),
        AlkenzyThemePalette.violet => const Color(0xFF332060),
        AlkenzyThemePalette.graphite => const Color(0xFF20252B),
      };

  Color get soft => switch (this) {
        AlkenzyThemePalette.navy => const Color(0xFFE3EDF4),
        AlkenzyThemePalette.emerald => const Color(0xFFDDF2EC),
        AlkenzyThemePalette.burgundy => const Color(0xFFF6E3E9),
        AlkenzyThemePalette.violet => const Color(0xFFEDE7FA),
        AlkenzyThemePalette.graphite => const Color(0xFFE8EBEE),
      };

  Color get accent => switch (this) {
        AlkenzyThemePalette.navy => const Color(0xFFC98A7B),
        AlkenzyThemePalette.emerald => const Color(0xFFD29B55),
        AlkenzyThemePalette.burgundy => const Color(0xFFD3A04B),
        AlkenzyThemePalette.violet => const Color(0xFFD18B78),
        AlkenzyThemePalette.graphite => const Color(0xFFC79B56),
      };
}

final class ThemePaletteController extends ChangeNotifier {
  ThemePaletteController({FlutterSecureStorage? storage})
      : _storage = storage ?? const FlutterSecureStorage();

  static const _key = 'alkenzy_theme_palette_v1';
  final FlutterSecureStorage _storage;
  AlkenzyThemePalette _palette = AlkenzyThemePalette.navy;

  AlkenzyThemePalette get palette => _palette;

  Future<void> load() async {
    final raw = await _storage.read(key: _key);
    AlkenzyThemePalette? parsed;
    for (final value in AlkenzyThemePalette.values) {
      if (value.storageValue == raw) {
        parsed = value;
        break;
      }
    }
    if (parsed == null || parsed == _palette) return;
    _palette = parsed;
    notifyListeners();
  }

  Future<void> setPalette(AlkenzyThemePalette value) async {
    if (_palette == value) return;
    _palette = value;
    notifyListeners();
    await _storage.write(key: _key, value: value.storageValue);
  }

  Future<void> cycleAlternative() async {
    const alternatives = <AlkenzyThemePalette>[
      AlkenzyThemePalette.emerald,
      AlkenzyThemePalette.burgundy,
      AlkenzyThemePalette.violet,
      AlkenzyThemePalette.graphite,
    ];
    final index = alternatives.indexOf(_palette);
    await setPalette(alternatives[(index + 1) % alternatives.length]);
  }
}
