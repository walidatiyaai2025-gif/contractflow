#!/usr/bin/env python3
"""Publish/check the latest verified SafeContracts plugin ZIP and Android APK."""

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
HTTPS_RE = re.compile(r"^https://[^\s]+$", re.IGNORECASE)
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


def now_utc() -> str:
    return datetime.now(timezone.utc).isoformat(timespec="seconds").replace("+00:00", "Z")


def require_policy_files() -> int:
    required = (
        ROOT / "AGENTS.md",
        ROOT / "docs/PRODUCTION_ENVIRONMENT_BUILD.md",
        PLUGIN_DIR / "README.md",
        APK_DIR / "README.md",
        ROOT / "scripts/package_plugin.py",
        ROOT / "scripts/bootstrap_android.sh",
        ROOT / "mobile/android-release/app-build.gradle.kts",
        ROOT / "mobile/android-release/README.md",
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


def validate_quality(quality_run_id: str, quality_gates_passed: bool) -> tuple[str, str]:
    if not quality_gates_passed:
        fail("refusing publication: --quality-gates-passed is mandatory")
    run_id = str(quality_run_id).strip()
    if not run_id.isdigit() or int(run_id) <= 0:
        fail("quality run ID must be a positive GitHub Actions run ID")
    return run_id, "success"


def validate_plugin_zip(path: Path) -> None:
    if not path.is_file() or path.stat().st_size == 0:
        fail(f"plugin ZIP is missing or empty: {path}")
    if path.suffix.lower() != ".zip":
        fail("plugin artifact must be a .zip file")
    try:
        with zipfile.ZipFile(path) as archive:
            names = [name.replace("\\", "/") for name in archive.namelist() if name and not name.endswith("/")]
            corrupt = archive.testzip()
    except zipfile.BadZipFile as exc:
        raise PolicyError("plugin artifact is not a valid ZIP") from exc
    if corrupt is not None:
        fail(f"plugin ZIP contains corrupt member: {corrupt}")
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
    for required in (
        "safecontracts/safecontracts.php",
        "safecontracts/readme.txt",
        "safecontracts/src/Plugin.php",
        "safecontracts/src/Support/Autoloader.php",
    ):
        if required not in names:
            fail(f"plugin ZIP is missing required file: {required}")


def validate_apk(path: Path) -> None:
    if not path.is_file() or path.stat().st_size == 0:
        fail(f"APK is missing or empty: {path}")
    if path.suffix.lower() != ".apk":
        fail("Android artifact must be a .apk file")
    try:
        with zipfile.ZipFile(path) as archive:
            names = set(archive.namelist())
            corrupt = archive.testzip()
    except zipfile.BadZipFile as exc:
        raise PolicyError("Android artifact is not a valid APK/ZIP container") from exc
    if corrupt is not None:
        fail(f"APK contains corrupt member: {corrupt}")
    if "AndroidManifest.xml" not in names:
        fail("Android artifact does not contain AndroidManifest.xml")


def clear_generated(directory: Path, extension: str) -> None:
    directory.mkdir(parents=True, exist_ok=True)
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
    extra: dict[str, object] | None = None,
) -> None:
    artifact = directory / filename
    sha256 = digest(artifact)
    (directory / f"{filename}.sha256").write_text(
        f"{sha256}  {filename}\n", encoding="utf-8"
    )
    payload: dict[str, object] = {
        "verified": True,
        "kind": kind,
        "filename": filename,
        "source_sha": source_sha,
        "quality_run_id": quality_run_id,
        "quality_conclusion": "success",
        "published_utc": now_utc(),
        "size_bytes": artifact.stat().st_size,
        "sha256": sha256,
    }
    if extra:
        payload.update(extra)
    (directory / PROVENANCE_NAME).write_text(
        json.dumps(payload, indent=2, sort_keys=True) + "\n", encoding="utf-8"
    )


def publish_plugin(args: argparse.Namespace) -> int:
    require_policy_files()
    source_sha = validate_source_sha(args.source_sha)
    run_id, _ = validate_quality(args.quality_run_id, args.quality_gates_passed)
    source = Path(args.plugin).expanduser().resolve()
    validate_plugin_zip(source)
    clear_generated(PLUGIN_DIR, ".zip")
    target = PLUGIN_DIR / PLUGIN_NAME
    shutil.copy2(source, target)
    write_provenance(
        PLUGIN_DIR,
        kind="wordpress-plugin",
        filename=PLUGIN_NAME,
        source_sha=source_sha,
        quality_run_id=run_id,
        extra={"package_root": "safecontracts/"},
    )
    checks = check_one(PLUGIN_DIR, PLUGIN_NAME, ".zip", "wordpress-plugin", True)
    print(f"Published verified SafeContracts plugin ZIP ({checks} checks).")
    return 0


def _require_evidence(value: str, label: str) -> str:
    normalized = value.strip()
    if len(normalized) < 3:
        fail(f"{label} evidence reference is required")
    return normalized


def publish_apk(args: argparse.Namespace) -> int:
    require_policy_files()
    source_sha = validate_source_sha(args.source_sha)
    run_id, _ = validate_quality(args.quality_run_id, args.quality_gates_passed)
    if not args.signing_verified:
        fail("refusing production APK publication: --signing-verified is mandatory")
    api_base_url = args.api_base_url.strip()
    if HTTPS_RE.fullmatch(api_base_url) is None:
        fail("production APK API base URL must be absolute HTTPS")
    device_evidence = _require_evidence(args.device_evidence, "real-device")
    uat_evidence = _require_evidence(args.uat_evidence, "UAT")
    source = Path(args.apk).expanduser().resolve()
    validate_apk(source)
    clear_generated(APK_DIR, ".apk")
    target = APK_DIR / APK_NAME
    shutil.copy2(source, target)
    write_provenance(
        APK_DIR,
        kind="android-apk",
        filename=APK_NAME,
        source_sha=source_sha,
        quality_run_id=run_id,
        extra={
            "signing_verified": True,
            "api_base_url": api_base_url,
            "device_evidence": device_evidence,
            "uat_evidence": uat_evidence,
        },
    )
    checks = check_one(APK_DIR, APK_NAME, ".apk", "android-apk", True)
    print(f"Published verified SafeContracts production APK ({checks} checks).")
    return 0


def check_one(
    directory: Path,
    filename: str,
    extension: str,
    kind: str,
    require_artifact: bool,
) -> int:
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
    if sidecar.read_text(encoding="utf-8").strip() != f"{actual_sha}  {filename}":
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
    if not str(payload.get("published_utc", "")).endswith("Z"):
        fail(f"retained {kind} provenance must use UTC published time")
    if kind == "wordpress-plugin":
        validate_plugin_zip(artifact)
        if payload.get("package_root") != "safecontracts/":
            fail("retained plugin provenance must declare package_root=safecontracts/")
    else:
        validate_apk(artifact)
        if payload.get("signing_verified") is not True:
            fail("retained APK provenance must confirm verified production signing")
        api_url = str(payload.get("api_base_url", ""))
        if HTTPS_RE.fullmatch(api_url) is None:
            fail("retained APK provenance must contain production HTTPS API URL")
        _require_evidence(str(payload.get("device_evidence", "")), "real-device")
        _require_evidence(str(payload.get("uat_evidence", "")), "UAT")
    return 12


def check_policy(require_plugin: bool = False, require_apk: bool = False) -> int:
    checks = require_policy_files()
    checks += check_one(PLUGIN_DIR, PLUGIN_NAME, ".zip", "wordpress-plugin", require_plugin)
    checks += check_one(APK_DIR, APK_NAME, ".apk", "android-apk", require_apk)
    return checks


def _common_publish(parser: argparse.ArgumentParser) -> None:
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--quality-run-id", required=True)
    parser.add_argument("--quality-gates-passed", action="store_true")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    subparsers = parser.add_subparsers(dest="command", required=True)

    check_parser = subparsers.add_parser("check")
    check_parser.add_argument("--require-plugin", action="store_true")
    check_parser.add_argument("--require-apk", action="store_true")
    check_parser.add_argument("--require-artifacts", action="store_true")

    plugin_parser = subparsers.add_parser("publish-plugin")
    plugin_parser.add_argument("--plugin", required=True)
    _common_publish(plugin_parser)

    apk_parser = subparsers.add_parser("publish-apk")
    apk_parser.add_argument("--apk", required=True)
    apk_parser.add_argument("--api-base-url", required=True)
    apk_parser.add_argument("--signing-verified", action="store_true")
    apk_parser.add_argument("--device-evidence", required=True)
    apk_parser.add_argument("--uat-evidence", required=True)
    _common_publish(apk_parser)
    return parser


def main() -> int:
    parser = build_parser()
    args = parser.parse_args()
    try:
        if args.command == "check":
            require_plugin = args.require_plugin or args.require_artifacts
            require_apk = args.require_apk or args.require_artifacts
            checks = check_policy(require_plugin=require_plugin, require_apk=require_apk)
            print(f"SafeContracts verified artifact policy passed ({checks} checks).")
            return 0
        if args.command == "publish-plugin":
            return publish_plugin(args)
        if args.command == "publish-apk":
            return publish_apk(args)
        parser.error("unsupported command")
    except PolicyError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
