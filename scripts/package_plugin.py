#!/usr/bin/env python3
"""Build and validate a deterministic installable SafeContracts WordPress plugin ZIP."""

from __future__ import annotations

import argparse
import hashlib
from pathlib import Path, PurePosixPath
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
SOURCE = ROOT / "wordpress-plugin" / "safecontracts"
DEFAULT_OUTPUT = ROOT / "dist" / "SafeContracts-plugin-candidate.zip"
ZIP_EPOCH = (1980, 1, 1, 0, 0, 0)
FORBIDDEN_PARTS = {
    ".git",
    ".github",
    ".idea",
    ".vscode",
    "node_modules",
    "vendor",
    "coverage",
    "__pycache__",
}
FORBIDDEN_SUFFIXES = {".log", ".tmp", ".swp", ".jks", ".keystore", ".p12", ".pem", ".key"}
FORBIDDEN_NAMES = {".env", "wp-config.php", ".DS_Store", "Thumbs.db"}


class PackageError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise PackageError(message)


def _allowed(relative: Path) -> bool:
    if any(part in FORBIDDEN_PARTS for part in relative.parts):
        return False
    if relative.name in FORBIDDEN_NAMES or relative.name.startswith(".env."):
        return False
    if relative.suffix.lower() in FORBIDDEN_SUFFIXES:
        return False
    return True


def source_files() -> list[Path]:
    if not SOURCE.is_dir():
        fail("wordpress-plugin/safecontracts source directory is missing")
    files = [path for path in SOURCE.rglob("*") if path.is_file() and _allowed(path.relative_to(SOURCE))]
    files.sort(key=lambda path: path.relative_to(SOURCE).as_posix())
    if not files:
        fail("plugin source contains no packageable files")
    required = {"safecontracts.php", "readme.txt", "src/Plugin.php", "src/Support/Autoloader.php"}
    present = {path.relative_to(SOURCE).as_posix() for path in files}
    missing = sorted(required - present)
    if missing:
        fail("plugin source is missing required files: " + ", ".join(missing))
    entry = (SOURCE / "safecontracts.php").read_text(encoding="utf-8")
    for marker in ("Plugin Name: SafeContracts", "Requires PHP: 8.1", "SAFECONTRACTS_VERSION"):
        if marker not in entry:
            fail(f"plugin entry file is missing marker: {marker}")
    return files


def build(output: Path) -> int:
    files = source_files()
    output.parent.mkdir(parents=True, exist_ok=True)
    temporary = output.with_suffix(output.suffix + ".tmp")
    temporary.unlink(missing_ok=True)
    with zipfile.ZipFile(temporary, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
        for path in files:
            relative = path.relative_to(SOURCE).as_posix()
            name = f"safecontracts/{relative}"
            info = zipfile.ZipInfo(name, ZIP_EPOCH)
            info.compress_type = zipfile.ZIP_DEFLATED
            info.external_attr = 0o100644 << 16
            archive.writestr(info, path.read_bytes(), compress_type=zipfile.ZIP_DEFLATED, compresslevel=9)
    temporary.replace(output)
    checks = validate(output)
    sha256 = hashlib.sha256(output.read_bytes()).hexdigest()
    print(f"Built {output.relative_to(ROOT)} ({output.stat().st_size} bytes, sha256={sha256}, {checks} checks).")
    return checks


def validate(path: Path) -> int:
    if not path.is_file() or path.stat().st_size == 0:
        fail(f"plugin ZIP missing or empty: {path}")
    try:
        with zipfile.ZipFile(path) as archive:
            infos = archive.infolist()
            names = [info.filename.replace("\\", "/") for info in infos if not info.is_dir()]
            if archive.testzip() is not None:
                fail("plugin ZIP contains a corrupt member")
    except zipfile.BadZipFile as exc:
        raise PackageError("plugin package is not a valid ZIP") from exc
    if not names:
        fail("plugin ZIP contains no files")
    if names != sorted(names):
        fail("plugin ZIP file order is not deterministic")
    roots = {PurePosixPath(name).parts[0] for name in names}
    if roots != {"safecontracts"}:
        fail("plugin ZIP must contain exactly one top-level safecontracts/ directory")
    for name in names:
        pure = PurePosixPath(name)
        if pure.is_absolute() or ".." in pure.parts:
            fail(f"plugin ZIP contains unsafe path: {name}")
        relative = Path(*pure.parts[1:])
        if not _allowed(relative):
            fail(f"plugin ZIP contains forbidden local/secret file: {name}")
    required = {
        "safecontracts/safecontracts.php",
        "safecontracts/readme.txt",
        "safecontracts/src/Plugin.php",
        "safecontracts/src/Support/Autoloader.php",
    }
    missing = sorted(required - set(names))
    if missing:
        fail("plugin ZIP is missing required install files: " + ", ".join(missing))
    return len(names) + len(required) + 4


def main() -> int:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="command", required=True)
    build_parser = sub.add_parser("build")
    build_parser.add_argument("--output", default=str(DEFAULT_OUTPUT))
    check_parser = sub.add_parser("check")
    check_parser.add_argument("zip")
    args = parser.parse_args()
    try:
        if args.command == "build":
            build(Path(args.output).expanduser().resolve())
        else:
            checks = validate(Path(args.zip).expanduser().resolve())
            print(f"SafeContracts plugin package validation passed ({checks} checks).")
        return 0
    except PackageError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
