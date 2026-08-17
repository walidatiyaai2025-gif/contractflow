#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

from verify_esc_local_persistence_isolation import (  # noqa: E402
    ESC_EXPORT_TEMP_DIRECTORY,
    ESC_LOCALE_STORAGE_KEY,
    ESC_SECURE_STORAGE_KEY,
    PersistenceIsolationError,
    validate_root,
)

SAFE_PUBSPEC = """name: safecontracts_mobile
publish_to: none
version: 0.1.0+1

environment:
  sdk: ">=3.6.0 <4.0.0"

dependencies:
  flutter:
    sdk: flutter
  flutter_secure_storage: ^10.3.1

dev_dependencies:
  flutter_test:
    sdk: flutter
"""

SAFE_TOKEN_STORE = f"""import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class MobileTokenStore {{
  Future<String?> read();
  Future<void> write(String token, {{bool persistent = true}});
  Future<void> clear();
}}

final class SecureMobileTokenStore implements MobileTokenStore {{
  SecureMobileTokenStore({{FlutterSecureStorage? storage}})
      : _storage = storage ?? const FlutterSecureStorage();

  static const _key = '{ESC_SECURE_STORAGE_KEY}';
  final FlutterSecureStorage _storage;

  @override
  Future<String?> read() => _storage.read(key: _key);

  @override
  Future<void> write(String token, {{bool persistent = true}}) async {{
    final normalized = token.trim();
    if (persistent) {{
      await _storage.write(key: _key, value: normalized);
    }} else {{
      await _storage.delete(key: _key);
    }}
  }}

  @override
  Future<void> clear() => _storage.delete(key: _key);
}}
"""

SAFE_LOCALE_STORE = f"""import 'package:flutter/widgets.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

abstract interface class MobileLocaleStore {{
  Future<String?> readLanguageCode();
  Future<void> writeLanguageCode(String languageCode);
}}

final class SecureMobileLocaleStore implements MobileLocaleStore {{
  const SecureMobileLocaleStore({{this.storage = const FlutterSecureStorage()}});

  static const _key = '{ESC_LOCALE_STORAGE_KEY}';
  final FlutterSecureStorage storage;

  @override
  Future<String?> readLanguageCode() => storage.read(key: _key);

  @override
  Future<void> writeLanguageCode(String languageCode) =>
      storage.write(key: _key, value: languageCode);
}}
"""

SAFE_EXPORT_STORE = f"""import 'dart:io';

abstract interface class ExcelExportSaver {{
  Future<String> save(dynamic export);
}}

final class IoExcelExportSaver implements ExcelExportSaver {{
  static const enterpriseTempDirectoryName = '{ESC_EXPORT_TEMP_DIRECTORY}';

  @override
  Future<String> save(dynamic export) async {{
    final directory = Directory(
      '${{Directory.systemTemp.path}}${{Platform.pathSeparator}}$enterpriseTempDirectoryName',
    );
    await directory.create(recursive: true);
    final safe = _safeFilename('report.xlsx');
    final file = File('${{directory.path}}${{Platform.pathSeparator}}$safe');
    await file.writeAsBytes(export.bytes, flush: true);
    return file.path;
  }}
}}

String _safeFilename(String value) => value;
"""


def fixture_root() -> tuple[tempfile.TemporaryDirectory[str], Path]:
    temp = tempfile.TemporaryDirectory()
    root = Path(temp.name)
    token = root / "mobile/lib/core/auth/mobile_token_store.dart"
    token.parent.mkdir(parents=True, exist_ok=True)
    token.write_text(SAFE_TOKEN_STORE, encoding="utf-8")
    locale = root / "mobile/lib/core/localization/mobile_locale_controller.dart"
    locale.parent.mkdir(parents=True, exist_ok=True)
    locale.write_text(SAFE_LOCALE_STORE, encoding="utf-8")
    export = root / "mobile/lib/features/export/mobile_excel_export.dart"
    export.parent.mkdir(parents=True, exist_ok=True)
    export.write_text(SAFE_EXPORT_STORE, encoding="utf-8")
    (root / "mobile/pubspec.yaml").write_text(SAFE_PUBSPEC, encoding="utf-8")
    return temp, root


class EscLocalPersistenceIsolationTests(unittest.TestCase):
    def test_current_repository_passes(self) -> None:
        validate_root(ROOT)

    def test_minimal_safe_fixture_passes(self) -> None:
        temp, root = fixture_root()
        try:
            validate_root(root)
        finally:
            temp.cleanup()

    def test_shared_preferences_dependency_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            pubspec = root / "mobile/pubspec.yaml"
            pubspec.write_text(
                pubspec.read_text(encoding="utf-8")
                + "  shared_preferences: ^2.5.0\n",
                encoding="utf-8",
            )
            with self.assertRaisesRegex(PersistenceIsolationError, "shared_preferences"):
                validate_root(root)
        finally:
            temp.cleanup()

    def test_database_package_import_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            file = root / "mobile/lib/features/contracts/local_cache.dart"
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text(
                "import 'package:sqflite/sqflite.dart';\n",
                encoding="utf-8",
            )
            with self.assertRaisesRegex(PersistenceIsolationError, "sqflite"):
                validate_root(root)
        finally:
            temp.cleanup()

    def test_direct_secure_storage_outside_audited_stores_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            file = root / "mobile/lib/features/contracts/secret_cache.dart"
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text(
                "import 'package:flutter_secure_storage/flutter_secure_storage.dart';\n"
                "const storage = FlutterSecureStorage();\n",
                encoding="utf-8",
            )
            with self.assertRaisesRegex(PersistenceIsolationError, "only in audited ESC stores"):
                validate_root(root)
        finally:
            temp.cleanup()

    def test_direct_file_persistence_outside_export_owner_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            file = root / "mobile/lib/features/contracts/file_cache.dart"
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text(
                "import 'dart:io';\n"
                "Future<void> save() async => File('contracts.cache').writeAsString('x');\n",
                encoding="utf-8",
            )
            with self.assertRaisesRegex(PersistenceIsolationError, "audited ESC transient-file owners"):
                validate_root(root)
        finally:
            temp.cleanup()

    def test_token_namespace_drift_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            token = root / "mobile/lib/core/auth/mobile_token_store.dart"
            token.write_text(
                token.read_text(encoding="utf-8").replace(
                    ESC_SECURE_STORAGE_KEY,
                    "safecontracts.mobile.bearer_token",
                ),
                encoding="utf-8",
            )
            with self.assertRaisesRegex(PersistenceIsolationError, "missing isolation marker"):
                validate_root(root)
        finally:
            temp.cleanup()

    def test_locale_namespace_drift_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            locale = root / "mobile/lib/core/localization/mobile_locale_controller.dart"
            locale.write_text(
                locale.read_text(encoding="utf-8").replace(
                    ESC_LOCALE_STORAGE_KEY,
                    "safecontracts_mobile_language",
                ),
                encoding="utf-8",
            )
            with self.assertRaisesRegex(PersistenceIsolationError, "missing isolation marker"):
                validate_root(root)
        finally:
            temp.cleanup()

    def test_export_namespace_drift_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            export = root / "mobile/lib/features/export/mobile_excel_export.dart"
            export.write_text(
                export.read_text(encoding="utf-8").replace(
                    ESC_EXPORT_TEMP_DIRECTORY,
                    "safecontracts_exports",
                ),
                encoding="utf-8",
            )
            with self.assertRaisesRegex(PersistenceIsolationError, "missing isolation marker"):
                validate_root(root)
        finally:
            temp.cleanup()

    def test_path_provider_dependency_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            pubspec = root / "mobile/pubspec.yaml"
            pubspec.write_text(
                pubspec.read_text(encoding="utf-8")
                + "  path_provider: ^2.1.5\n",
                encoding="utf-8",
            )
            with self.assertRaisesRegex(PersistenceIsolationError, "path_provider"):
                validate_root(root)
        finally:
            temp.cleanup()


if __name__ == "__main__":
    unittest.main()
