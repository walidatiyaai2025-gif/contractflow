#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import shutil
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

from verify_esc_local_persistence_isolation import (  # noqa: E402
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


def fixture_root() -> tuple[tempfile.TemporaryDirectory[str], Path]:
    temp = tempfile.TemporaryDirectory()
    root = Path(temp.name)
    token = root / "mobile/lib/core/auth/mobile_token_store.dart"
    token.parent.mkdir(parents=True, exist_ok=True)
    token.write_text(SAFE_TOKEN_STORE, encoding="utf-8")
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
            with self.assertRaisesRegex(
                PersistenceIsolationError,
                "shared_preferences",
            ):
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

    def test_direct_secure_storage_outside_token_store_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            file = root / "mobile/lib/features/contracts/secret_cache.dart"
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text(
                "import 'package:flutter_secure_storage/flutter_secure_storage.dart';\n"
                "const storage = FlutterSecureStorage();\n",
                encoding="utf-8",
            )
            with self.assertRaisesRegex(
                PersistenceIsolationError,
                "only in the audited ESC token store",
            ):
                validate_root(root)
        finally:
            temp.cleanup()

    def test_direct_file_persistence_fails_closed(self) -> None:
        temp, root = fixture_root()
        try:
            file = root / "mobile/lib/features/contracts/file_cache.dart"
            file.parent.mkdir(parents=True, exist_ok=True)
            file.write_text(
                "import 'dart:io';\n"
                "Future<void> save() async => File('contracts.cache').writeAsString('x');\n",
                encoding="utf-8",
            )
            with self.assertRaisesRegex(
                PersistenceIsolationError,
                "direct dart:io",
            ):
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
            with self.assertRaisesRegex(
                PersistenceIsolationError,
                "missing isolation marker",
            ):
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
