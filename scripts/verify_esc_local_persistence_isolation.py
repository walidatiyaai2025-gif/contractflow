#!/usr/bin/env python3
"""Fail closed if ESC mobile local persistence loses Enterprise namespace isolation.

Enterprise Safe Contracts currently persists only its bearer token through the audited
Flutter secure-storage module. New preference/database/file-cache persistence must not
silently enter the mobile client: it requires an explicit Enterprise namespace and a
reviewed isolation contract before this gate may be intentionally extended.
"""

from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
TOKEN_STORE = Path("mobile/lib/core/auth/mobile_token_store.dart")
ESC_SECURE_STORAGE_KEY = "enterprise_safecontracts.mobile.bearer_token"
SECURE_STORAGE_IMPORT = (
    "package:flutter_secure_storage/flutter_secure_storage.dart"
)

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
        re.search(
            rf"(?m)^  {re.escape(dependency)}:\s*[^\n]*$",
            pubspec,
        )
        is not None
    )


def validate_root(root: Path) -> None:
    root = root.resolve()
    pubspec_path = root / "mobile/pubspec.yaml"
    token_store_path = root / TOKEN_STORE
    lib_root = root / "mobile/lib"

    pubspec = read(pubspec_path)
    token_store = read(token_store_path)
    if not lib_root.is_dir():
        fail("mobile/lib is missing")

    if not _dependency_declared(pubspec, "flutter_secure_storage"):
        fail("ESC pubspec must retain flutter_secure_storage for the audited token store")

    for dependency in FORBIDDEN_DEPENDENCIES:
        if _dependency_declared(pubspec, dependency):
            fail(
                f"unreviewed local persistence dependency is forbidden on ESC: {dependency}; "
                "introduce an explicit Enterprise namespace/isolation contract first"
            )

    required_token_markers = (
        f"import '{SECURE_STORAGE_IMPORT}';",
        "final class SecureMobileTokenStore implements MobileTokenStore",
        "const FlutterSecureStorage()",
        f"static const _key = '{ESC_SECURE_STORAGE_KEY}';",
        "_storage.read(key: _key)",
        "_storage.write(key: _key, value: normalized)",
        "_storage.delete(key: _key)",
    )
    for marker in required_token_markers:
        if marker not in token_store:
            fail(f"ESC secure token store is missing isolation marker: {marker}")

    forbidden_token_keys = (
        "safecontracts.mobile.bearer_token",
        "safecontracts_mobile.bearer_token",
        "mobile.bearer_token",
    )
    for value in forbidden_token_keys:
        if value != ESC_SECURE_STORAGE_KEY and f"'{value}'" in token_store:
            fail(f"ESC secure token store contains a generic/inherited key: {value}")

    dart_files = sorted(lib_root.rglob("*.dart"))
    if not dart_files:
        fail("mobile/lib contains no Dart production sources")

    secure_storage_users: list[Path] = []
    for path in dart_files:
        relative = path.relative_to(root)
        text = path.read_text(encoding="utf-8")

        if SECURE_STORAGE_IMPORT in text or "FlutterSecureStorage" in text:
            secure_storage_users.append(relative)
            if relative != TOKEN_STORE:
                fail(
                    "direct flutter_secure_storage use is allowed only in the audited "
                    f"ESC token store; found in {relative.as_posix()}"
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
            fail(
                "direct dart:io file/directory persistence is forbidden until an "
                "explicit ESC namespaced file/cache contract is reviewed: "
                f"{relative.as_posix()}"
            )

    if secure_storage_users != [TOKEN_STORE]:
        fail(
            "ESC secure storage must have exactly one audited production owner: "
            f"{TOKEN_STORE.as_posix()}"
        )


def main() -> int:
    try:
        validate_root(ROOT)
    except PersistenceIsolationError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1

    print(
        "ESC local persistence isolation passed: secure bearer-token storage is "
        "Enterprise-namespaced and unreviewed preference/database/file persistence "
        "is absent"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
