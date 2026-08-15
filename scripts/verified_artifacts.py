#!/usr/bin/env python3
"""Publish/check the single latest verified SafeContracts plugin ZIP and APK."""

from __future__ import annotations

import argparse
from datetime import datetime, timezone
import hashlib
import json
from pathlib import Path
import re
import shutil
import sys
import zipfile

ROOT = Path(__file__).resolve().parents[1]
PLUGIN_DIR = ROOT / "Last verified Plugin"
APK_DIR = ROOT / "Last verified apk"
PLUGIN_NAME = "SafeContracts-latest.zip"
APK_NAME = "SafeContracts-latest.apk"
PROVENANCE_NAME = "VERIFIED.json"
SOURCE_SHA_RE = re.compile(r"^[0-9a-f]{40}$", re.IGNORECASE)
FORBIDDEN_PLUGIN_MARKERS = (
    ".git/",
    ".env",
    "wp-config.php",
    "service-account",
    "service_account",
    "credentials",
    "keystore",
    ".jks",
    ".p12",
    ".pem",
    ".key",
)


class PolicyError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise PolicyError(message)


def digest(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as handle:
        for block in iter(lambda: handle.read(1024 * 1024), b""):
            h.update(block)
    return h.hexdigest()


def require_policy_files() -> int:
    required = (
        ROOT / "AGENTS.md",
        ROOT / "docs/PRODUCTION_ENVIRONMENT_BUILD.md",
        PLUGIN_DIR / "README.md",
        APK_DIR / "README.md",
    )
    missing = [str(path.relative_to(ROOT)) for path in required if not path.is_file()]
    if missing:
        fail("missing verified-artifact policy files: " + ", ".join(missing))
    return len(required)


def validate_source_sha(source_sha: str) -> str:
    normalized = source_sha.strip().lower()
    if SOURCE_SHA_RE.fullmatch(normalized) is None:
        fail("source SHA must be a full 40-character Git commit SHA")
    return normalized


def validate_plugin_zip(path: Path) -> None:
    if not path.is_file() or path.stat().st_size == 0:
        fail(f"plugin ZIP is missing or empty: {path}")
    if path.suffix.lower() != ".zip":
        fail("plugin artifact must be a .zip file")
    try:
        with zipfile.ZipFile(path) as archive:
            names = [name.replace("\\", "/") for name in archive.namelist() if name]
    except zipfile.BadZipFile as exc:
        raise PolicyError("plugin artifact is not a valid ZIP") from exc
    if not names:
        fail("plugin ZIP contains no files")
    for name in names:
        lowered = name.lower()
        if name.startswith("/") or "../" in name or name == "..":
            fail(f"plugin ZIP contains unsafe path: {name}")
        if any(marker in lowered for marker in FORBIDDEN_PLUGIN_MARKERS):
            fail(f"plugin ZIP contains forbidden secret/local marker: {name}")
    roots = {name.split("/", 1)[0] for name in names}
    if roots != {"safecontracts"}:
        fail("plugin ZIP must have exactly one installable top-level directory named safecontracts/")
    if "safecontracts/safecontracts.php" not in names:
        fail("plugin ZIP is missing safecontracts/safecontracts.php")


def validate_apk(path: Path) -> None:
    if not path.is_file() or path.stat().st_size == 0:
        fail(f"APK is missing or empty: {path}")
    if path.suffix.lower() != ".apk":
        fail("Android artifact must be a .apk file")
    try:
        with zipfile.ZipFile(path) as archive:
            names = set(archive.namelist())
    except zipfile.BadZipFile as exc:
        raise PolicyError("Android artifact is not a valid APK/ZIP container") from exc
    if "AndroidManifest.xml" not in names:
        fail("Android artifact does not contain AndroidManifest.xml")


def clear_generated(directory: Path, extension: str) -> None:
    for path in directory.iterdir():
        if path.is_file() and (
            path.suffix.lower() == extension
            or path.name.endswith(extension + ".sha256")
            or path.name == PROVENANCE_NAME
        ):
            path.unlink()


def write_provenance(
    directory: Path,
    *,
    kind: str,
    filename: str,
    source_sha: str,
    quality_run_id: str,
    published_utc: str,
) -> None:
    artifact = directory / filename
    sha256 = digest(artifact)
    sidecar = directory / f"{filename}.sha256"
    sidecar.write_text(f"{sha256}  {filename}\n", encoding="utf-8")
    payload = {
        "verified": True,
        "kind": kind,
        "filename": filename,
        "source_sha": source_sha,
        "quality_run_id": quality_run_id,
        "quality_conclusion": "success",
        "published_utc": published_utc,
        "size_bytes": artifact.stat().st_size,
        "sha256": sha256,
    }
    (directory / PROVENANCE_NAME).write_text(
        json.dumps(payload, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )


def publish(args: argparse.Namespace) -> int:
    require_policy_files()
    source_sha = validate_source_sha(args.source_sha)
    if not args.quality_gates_passed:
        fail("refusing publication: --quality-gates-passed is mandatory")
    quality_run_id = str(args.quality_run_id).strip()
    if not quality_run_id.isdigit() or int(quality_run_id) <= 0:
        fail("quality run ID must be a positive GitHub Actions run ID")
    if not (ROOT / "mobile/android").is_dir():
        fail("refusing production APK publication: mobile/android scaffold is not committed")

    plugin_source = Path(args.plugin).expanduser().resolve()
    apk_source = Path(args.apk).expanduser().resolve()
    validate_plugin_zip(plugin_source)
    validate_apk(apk_source)

    clear_generated(PLUGIN_DIR, ".zip")
    clear_generated(APK_DIR, ".apk")
    plugin_target = PLUGIN_DIR / PLUGIN_NAME
    apk_target = APK_DIR / APK_NAME
    shutil.copy2(plugin_source, plugin_target)
    shutil.copy2(apk_source, apk_target)

    published_utc = datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")
    write_provenance(
        PLUGIN_DIR,
        kind="wordpress-plugin",
        filename=PLUGIN_NAME,
        source_sha=source_sha,
        quality_run_id=quality_run_id,
        published_utc=published_utc,
    )
    write_provenance(
        APK_DIR,
        kind="android-apk",
        filename=APK_NAME,
        source_sha=source_sha,
        quality_run_id=quality_run_id,
        published_utc=published_utc,
    )
    checks = check_policy(require_artifacts=True)
    print(f"Published verified SafeContracts plugin/APK artifacts ({checks} checks).")
    return 0


def check_one(directory: Path, filename: str, extension: str, kind: str, require_artifact: bool) -> int:
    artifacts = sorted(path for path in directory.glob(f"*{extension}") if path.is_file())
    if len(artifacts) > 1:
        fail(f"{directory.name} must contain at most one {extension} artifact")
    if not artifacts:
        if require_artifact:
            fail(f"{directory.name} has no retained {extension} artifact")
        return 1
    artifact = artifacts[0]
    if artifact.name != filename:
        fail(f"retained {kind} must use stable filename {filename}")
    sidecar = directory / f"{filename}.sha256"
    provenance = directory / PROVENANCE_NAME
    if not sidecar.is_file() or not provenance.is_file():
        fail(f"retained {kind} is missing checksum/provenance metadata")

    actual_sha = digest(artifact)
    expected_sidecar = f"{actual_sha}  {filename}"
    if sidecar.read_text(encoding="utf-8").strip() != expected_sidecar:
        fail(f"retained {kind} checksum sidecar does not match the binary")
    try:
        payload = json.loads(provenance.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise PolicyError(f"retained {kind} provenance is invalid JSON") from exc
    required = {
        "verified": True,
        "kind": kind,
        "filename": filename,
        "quality_conclusion": "success",
        "size_bytes": artifact.stat().st_size,
        "sha256": actual_sha,
    }
    for key, expected in required.items():
        if payload.get(key) != expected:
            fail(f"retained {kind} provenance mismatch for {key}")
    validate_source_sha(str(payload.get("source_sha", "")))
    run_id = str(payload.get("quality_run_id", ""))
    if not run_id.isdigit() or int(run_id) <= 0:
        fail(f"retained {kind} provenance has invalid Quality Gates run ID")
    published = str(payload.get("published_utc", ""))
    if not published.endswith("Z"):
        fail(f"retained {kind} provenance must use UTC published time")
    return 10


def check_policy(require_artifacts: bool = False) -> int:
    checks = require_policy_files()
    checks += check_one(PLUGIN_DIR, PLUGIN_NAME, ".zip", "wordpress-plugin", require_artifacts)
    checks += check_one(APK_DIR, APK_NAME, ".apk", "android-apk", require_artifacts)
    return checks


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    subparsers = parser.add_subparsers(dest="command", required=True)

    check_parser = subparsers.add_parser("check", help="validate retained artifact policy and any retained binaries")
    check_parser.add_argument(
        "--require-artifacts",
        action="store_true",
        help="fail when the latest ZIP/APK have not yet been published",
    )

    publish_parser = subparsers.add_parser("publish", help="replace the retained verified plugin ZIP and APK")
    publish_parser.add_argument("--plugin", required=True)
    publish_parser.add_argument("--apk", required=True)
    publish_parser.add_argument("--source-sha", required=True)
    publish_parser.add_argument("--quality-run-id", required=True)
    publish_parser.add_argument("--quality-gates-passed", action="store_true")
    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    try:
        if args.command == "check":
            checks = check_policy(require_artifacts=args.require_artifacts)
            print(f"SafeContracts verified artifact policy passed ({checks} checks).")
            return 0
        if args.command == "publish":
            return publish(args)
        parser.error("unsupported command")
    except PolicyError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
