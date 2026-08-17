#!/usr/bin/env python3
"""Fail closed if ESC mobile local persistence loses Enterprise namespace isolation.

Enterprise Safe Contracts currently has exactly two audited persistent secure stores
and one audited transient export-file owner. New preference/database/file-cache
persistence must not silently enter the mobile client: it requires an explicit
Enterprise namespace and a reviewed isolation contract before this gate may be
intentionally extended.
"""

from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
TOKEN_STORE = Path("mobile/lib/core/auth/mobile_token_store.dart")
LOCALE_STORE = Path("mobile/lib/core/localization/mobile_locale_controller.dart")
EXPORT_FILE = Path("mobile/lib/features/export/mobile_excel_export.dart")
ESC_SECURE_STORAGE_KEY = "enterprise_safecontracts.mobile.bearer_token"
ESC_LOCALE_STORAGE_KEY = "enterprise_safecontracts.mobile.language"
ESC_EXPORT_TEMP_DIRECTORY = "enterprise_safecontracts_exports"
SECURE_STORAGE_IMPORT = "package:flutter_secure_storage/flutter_secure_storage.dart"
AUDITED_SECURE_STORAGE_FILES = {TOKEN_STORE, LOCALE_STORE}
AUDITED_FILE_STORAGE_FILES = {EXPORT_FILE}

FORBIDDEN_DEPENDENCIES = (
    "shared_preferences",
    "hive",
    "hive_flutter",
    "sqflite",
    "sqflite_common",
    "drift",
    "isar",
    "sembast",
    "objectbox",
    "realm",
    "path_provider",
)
FORBIDDEN_PACKAGE_IMPORTS = tuple(
    f"package:{name}/" for name in FORBIDDEN_DEPENDENCIES
)
FILE_PERSISTENCE_MARKERS = (
    "File(",
    "File.",
    "Directory(",
    "Directory.",
    "RandomAccessFile",
    "FileSystemEntity",
)


class PersistenceIsolationError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise PersistenceIsolationError(message)


def read(path: Path) -> str:
    if not path.is_file():
        fail(f"required ESC persistence file is missing: {path.as_posix()}")
    return path.read_text(encoding="utf-8")


def _dependency_declared(pubspec: str, dependency: str) -> bool:
    return (
        re.search(rf"(?m)^  {re.escape(dependency)}:\s*[^\n]*$", pubspec)
        is not None
    )


def _require_markers(text: str, markers: tuple[str, ...], label: str) -> None:
    for marker in markers:
        if marker not in text:
            fail(f"{label} is missing isolation marker: {marker}")


def validate_root(root: Path) -> None:
    root = root.resolve()
    pubspec_path = root / "mobile/pubspec.yaml"
    token_store_path = root / TOKEN_STORE
    locale_store_path = root / LOCALE_STORE
    export_file_path = root / EXPORT_FILE
    lib_root = root / "mobile/lib"

    pubspec = read(pubspec_path)
    token_store = read(token_store_path)
    locale_store = read(locale_store_path)
    export_file = read(export_file_path)
    if not lib_root.is_dir():
        fail("mobile/lib is missing")

    if not _dependency_declared(pubspec, "flutter_secure_storage"):
        fail("ESC pubspec must retain flutter_secure_storage for audited persistent stores")
    for dependency in FORBIDDEN_DEPENDENCIES:
        if _dependency_declared(pubspec, dependency):
            fail(
                f"unreviewed local persistence dependency is forbidden on ESC: {dependency}; "
                "introduce an explicit Enterprise namespace/isolation contract first"
            )

    _require_markers(
        token_store,
        (
            f"import '{SECURE_STORAGE_IMPORT}';",
            "final class SecureMobileTokenStore implements MobileTokenStore",
            "const FlutterSecureStorage()",
            f"static const _key = '{ESC_SECURE_STORAGE_KEY}';",
            "_storage.read(key: _key)",
            "_storage.write(key: _key, value: normalized)",
            "_storage.delete(key: _key)",
        ),
        "ESC secure bearer-token store",
    )
    _require_markers(
        locale_store,
        (
            f"import '{SECURE_STORAGE_IMPORT}';",
            "final class SecureMobileLocaleStore implements MobileLocaleStore",
            "const FlutterSecureStorage()",
            f"static const _key = '{ESC_LOCALE_STORAGE_KEY}';",
            "storage.read(key: _key)",
            "storage.write(key: _key, value: languageCode)",
        ),
        "ESC secure locale store",
    )
    _require_markers(
        export_file,
        (
            "import 'dart:io';",
            "final class IoExcelExportSaver implements ExcelExportSaver",
            f"static const enterpriseTempDirectoryName = '{ESC_EXPORT_TEMP_DIRECTORY}';",
            "Directory.systemTemp.path",
            "$enterpriseTempDirectoryName",
            "_safeFilename(",
            "file.writeAsBytes(export.bytes, flush: true)",
        ),
        "ESC transient Excel export store",
    )

    forbidden_legacy_markers = (
        "'safecontracts.mobile.bearer_token'",
        "'safecontracts_mobile.bearer_token'",
        "'safecontracts_mobile_language'",
        "'safecontracts.mobile.language'",
        "'safecontracts_exports'",
    )
    combined_audited = token_store + "\n" + locale_store + "\n" + export_file
    for marker in forbidden_legacy_markers:
        if marker in combined_audited:
            fail(f"ESC local persistence contains a generic/inherited namespace: {marker}")

    dart_files = sorted(lib_root.rglob("*.dart"))
    if not dart_files:
        fail("mobile/lib contains no Dart production sources")

    secure_storage_users: set[Path] = set()
    file_storage_users: set[Path] = set()
    for path in dart_files:
        relative = path.relative_to(root)
        text = path.read_text(encoding="utf-8")

        if SECURE_STORAGE_IMPORT in text or "FlutterSecureStorage" in text:
            secure_storage_users.add(relative)
            if relative not in AUDITED_SECURE_STORAGE_FILES:
                fail(
                    "direct flutter_secure_storage use is allowed only in audited ESC "
                    f"stores; found in {relative.as_posix()}"
                )

        for package_import in FORBIDDEN_PACKAGE_IMPORTS:
            if package_import in text:
                fail(
                    "unreviewed local persistence package import is forbidden: "
                    f"{package_import} in {relative.as_posix()}"
                )

        if "dart:io" in text and any(
            marker in text for marker in FILE_PERSISTENCE_MARKERS
        ):
            file_storage_users.add(relative)
            if relative not in AUDITED_FILE_STORAGE_FILES:
                fail(
                    "direct dart:io file/directory persistence is allowed only in "
                    "audited ESC transient-file owners; found in "
                    f"{relative.as_posix()}"
                )

    if secure_storage_users != AUDITED_SECURE_STORAGE_FILES:
        actual = ", ".join(sorted(path.as_posix() for path in secure_storage_users))
        expected = ", ".join(
            sorted(path.as_posix() for path in AUDITED_SECURE_STORAGE_FILES)
        )
        fail(
            "ESC secure storage production owners differ from the audited allowlist; "
            f"expected [{expected}], found [{actual}]"
        )

    if file_storage_users != AUDITED_FILE_STORAGE_FILES:
        actual = ", ".join(sorted(path.as_posix() for path in file_storage_users))
        expected = ", ".join(
            sorted(path.as_posix() for path in AUDITED_FILE_STORAGE_FILES)
        )
        fail(
            "ESC file-storage production owners differ from the audited allowlist; "
            f"expected [{expected}], found [{actual}]"
        )


def main() -> int:
    try:
        validate_root(ROOT)
    except PersistenceIsolationError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1

    print(
        "ESC local persistence isolation passed: bearer-token, locale, and transient "
        "Excel export stores are Enterprise-namespaced; unreviewed preference/database/"
        "file persistence is absent"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
