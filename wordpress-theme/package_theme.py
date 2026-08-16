#!/usr/bin/env python3
"""Build and verify a deterministic installable Safe Contracts theme ZIP."""

from __future__ import annotations

import argparse
import hashlib
from pathlib import Path, PurePosixPath
from zipfile import ZIP_DEFLATED, ZipFile, ZipInfo

ROOT = Path(__file__).resolve().parents[1]
THEME_DIR = ROOT / "wordpress-theme" / "safecontracts-onepage"
ARCHIVE_ROOT = PurePosixPath("safecontracts-onepage")
FIXED_TIME = (2020, 1, 1, 0, 0, 0)
EXCLUDED_NAMES = {".DS_Store", "Thumbs.db"}


def iter_files() -> list[Path]:
    files: list[Path] = []
    for path in THEME_DIR.rglob("*"):
        if not path.is_file():
            continue
        if path.name in EXCLUDED_NAMES or path.name.startswith("."):
            continue
        if "__pycache__" in path.parts:
            continue
        files.append(path)
    return sorted(files, key=lambda item: item.relative_to(THEME_DIR).as_posix())


def archive_name(path: Path) -> str:
    return str(ARCHIVE_ROOT / PurePosixPath(path.relative_to(THEME_DIR).as_posix()))


def build(output: Path) -> None:
    output.parent.mkdir(parents=True, exist_ok=True)
    files = iter_files()
    if not files:
        raise SystemExit("Theme source is empty")

    with ZipFile(output, "w", compression=ZIP_DEFLATED, compresslevel=9) as archive:
        for path in files:
            info = ZipInfo(archive_name(path), FIXED_TIME)
            info.compress_type = ZIP_DEFLATED
            info.external_attr = (0o100644 & 0xFFFF) << 16
            archive.writestr(info, path.read_bytes(), compress_type=ZIP_DEFLATED, compresslevel=9)


def check(archive_path: Path) -> None:
    required = {
        "safecontracts-onepage/style.css",
        "safecontracts-onepage/functions.php",
        "safecontracts-onepage/front-page.php",
        "safecontracts-onepage/header.php",
        "safecontracts-onepage/footer.php",
        "safecontracts-onepage/theme.json",
        "safecontracts-onepage/inc/brand.php",
        "safecontracts-onepage/assets/css/theme.css",
        "safecontracts-onepage/assets/css/brand.css",
        "safecontracts-onepage/assets/js/theme.js",
        "safecontracts-onepage/assets/images/hero-devices.svg",
        "safecontracts-onepage/assets/images/handshake.svg",
    }

    with ZipFile(archive_path, "r") as archive:
        names = archive.namelist()
        if len(names) != len(set(names)):
            raise SystemExit("Theme ZIP contains duplicate entries")
        missing = sorted(required.difference(names))
        if missing:
            raise SystemExit(f"Theme ZIP missing required files: {missing}")
        if any(not name.startswith("safecontracts-onepage/") for name in names):
            raise SystemExit("Theme ZIP contains files outside the install root")
        if any("/." in name or "__pycache__" in name for name in names):
            raise SystemExit("Theme ZIP contains excluded development files")
        bad = archive.testzip()
        if bad:
            raise SystemExit(f"Theme ZIP CRC validation failed at {bad}")


def sha256(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def main() -> None:
    parser = argparse.ArgumentParser()
    sub = parser.add_subparsers(dest="command", required=True)

    build_parser = sub.add_parser("build")
    build_parser.add_argument("--output", type=Path, required=True)

    check_parser = sub.add_parser("check")
    check_parser.add_argument("archive", type=Path)

    args = parser.parse_args()
    if args.command == "build":
        build(args.output)
        check(args.output)
        print(f"{sha256(args.output)}  {args.output}")
    else:
        check(args.archive)
        print(f"OK {args.archive}")


if __name__ == "__main__":
    main()
