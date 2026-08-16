#!/usr/bin/env python3
"""Publish/check isolated Enterprise Safe Contracts verified artifacts."""

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
PLUGIN_DIR = ROOT / "Last verified Enterprise Plugin"
APK_DIR = ROOT / "Last verified Enterprise apk"
SAFE_PLUGIN_DIR = ROOT / "Last verified Plugin"
SAFE_APK_DIR = ROOT / "Last verified apk"
PLUGIN_NAME = "EnterpriseSafeContracts-latest.zip"
APK_NAME = "EnterpriseSafeContracts-latest.apk"
PROVENANCE_NAME = "VERIFIED.json"
PRODUCT = "Enterprise Safe Contracts"
BRANCH = "enterprise-safecontracts"
PUBLIC_URL = "https://esc.50sols.com/"
APPLICATION_ID = "com.safecontracts.enterprise"
SOURCE_SHA_RE = re.compile(r"^[0-9a-f]{40}$", re.IGNORECASE)
HTTPS_RE = re.compile(r"^https://[^\s]+$", re.IGNORECASE)


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


def evidence(value: str, label: str) -> str:
    normalized = value.strip()
    if len(normalized) < 3:
        fail(f"{label} evidence reference is required")
    return normalized


def source_sha(value: str) -> str:
    normalized = value.strip().lower()
    if SOURCE_SHA_RE.fullmatch(normalized) is None:
        fail("source SHA must be a full 40-character Git commit SHA")
    return normalized


def quality(run_id: str, passed: bool) -> str:
    if not passed:
        fail("refusing publication: --quality-gates-passed is mandatory")
    normalized = str(run_id).strip()
    if not normalized.isdigit() or int(normalized) <= 0:
        fail("quality run ID must be a positive GitHub Actions run ID")
    return normalized


def validate_container(path: Path, extension: str, kind: str) -> None:
    if not path.is_file() or path.stat().st_size == 0:
        fail(f"{kind} is missing or empty: {path}")
    if path.suffix.lower() != extension:
        fail(f"{kind} must use {extension}")
    try:
        with zipfile.ZipFile(path) as archive:
            names = set(archive.namelist())
            corrupt = archive.testzip()
    except zipfile.BadZipFile as exc:
        raise PolicyError(f"{kind} is not a valid ZIP container") from exc
    if corrupt is not None:
        fail(f"{kind} contains corrupt member: {corrupt}")
    if extension == ".apk" and "AndroidManifest.xml" not in names:
        fail("APK does not contain AndroidManifest.xml")


def clear_generated(directory: Path, extension: str) -> None:
    directory.mkdir(parents=True, exist_ok=True)
    for path in directory.iterdir():
        if path.is_file() and (
            path.suffix.lower() == extension
            or path.name.endswith(extension + ".sha256")
            or path.name == PROVENANCE_NAME
        ):
            path.unlink()


def write_provenance(directory: Path, filename: str, kind: str, sha: str, run_id: str, extra: dict[str, object]) -> None:
    artifact = directory / filename
    checksum = digest(artifact)
    (directory / f"{filename}.sha256").write_text(f"{checksum}  {filename}\n", encoding="utf-8")
    payload: dict[str, object] = {
        "verified": True,
        "product": PRODUCT,
        "product_line": "esc",
        "branch": BRANCH,
        "public_url": PUBLIC_URL,
        "kind": kind,
        "filename": filename,
        "source_sha": sha,
        "quality_run_id": run_id,
        "quality_conclusion": "success",
        "published_utc": now_utc(),
        "size_bytes": artifact.stat().st_size,
        "sha256": checksum,
    }
    payload.update(extra)
    (directory / PROVENANCE_NAME).write_text(json.dumps(payload, indent=2, sort_keys=True) + "\n", encoding="utf-8")


def validate_cross_product_isolation() -> int:
    checks = 0
    for directory in (SAFE_PLUGIN_DIR, SAFE_APK_DIR):
        if directory.exists():
            for path in directory.iterdir():
                if path.is_file() and path.name.startswith("EnterpriseSafeContracts"):
                    fail(f"ESC artifact leaked into Safe Contract slot: {path.relative_to(ROOT)}")
                checks += 1
    for directory in (PLUGIN_DIR, APK_DIR):
        if not directory.is_dir():
            fail(f"missing Enterprise artifact directory: {directory.relative_to(ROOT)}")
        for path in directory.iterdir():
            if path.is_file() and path.name.startswith("SafeContracts-latest"):
                fail(f"Safe Contract artifact leaked into ESC slot: {path.relative_to(ROOT)}")
            checks += 1
    return checks


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
    actual = digest(artifact)
    if sidecar.read_text(encoding="utf-8").strip() != f"{actual}  {filename}":
        fail(f"retained {kind} checksum mismatch")
    try:
        payload = json.loads(provenance.read_text(encoding="utf-8"))
    except json.JSONDecodeError as exc:
        raise PolicyError(f"retained {kind} provenance is invalid JSON") from exc
    required = {
        "verified": True,
        "product": PRODUCT,
        "product_line": "esc",
        "branch": BRANCH,
        "public_url": PUBLIC_URL,
        "kind": kind,
        "filename": filename,
        "quality_conclusion": "success",
        "size_bytes": artifact.stat().st_size,
        "sha256": actual,
    }
    for key, expected in required.items():
        if payload.get(key) != expected:
            fail(f"retained {kind} provenance mismatch for {key}")
    source_sha(str(payload.get("source_sha", "")))
    run_id = str(payload.get("quality_run_id", ""))
    if not run_id.isdigit() or int(run_id) <= 0:
        fail(f"retained {kind} has invalid quality run id")
    if not str(payload.get("published_utc", "")).endswith("Z"):
        fail(f"retained {kind} published_utc must be UTC")
    validate_container(artifact, extension, kind)
    if kind == "android-apk":
        if payload.get("application_id") != APPLICATION_ID:
            fail("ESC APK provenance must declare the production ESC application id")
        for flag in ("signing_verified", "identity_verified", "firebase_identity_verified"):
            if payload.get(flag) is not True:
                fail(f"ESC APK provenance must confirm {flag}")
        api_url = str(payload.get("api_base_url", ""))
        if HTTPS_RE.fullmatch(api_url) is None:
            fail("ESC APK provenance must contain an HTTPS production API URL")
        for key in ("device_evidence", "uat_evidence", "coexistence_evidence", "firebase_evidence"):
            evidence(str(payload.get(key, "")), key)
    return 12


def check_policy(require_plugin: bool = False, require_apk: bool = False) -> int:
    checks = validate_cross_product_isolation()
    checks += check_one(PLUGIN_DIR, PLUGIN_NAME, ".zip", "wordpress-plugin", require_plugin)
    checks += check_one(APK_DIR, APK_NAME, ".apk", "android-apk", require_apk)
    return checks


def publish_plugin(args: argparse.Namespace) -> int:
    sha = source_sha(args.source_sha)
    run_id = quality(args.quality_run_id, args.quality_gates_passed)
    source = Path(args.plugin).expanduser().resolve()
    validate_container(source, ".zip", "wordpress-plugin")
    clear_generated(PLUGIN_DIR, ".zip")
    shutil.copy2(source, PLUGIN_DIR / PLUGIN_NAME)
    write_provenance(PLUGIN_DIR, PLUGIN_NAME, "wordpress-plugin", sha, run_id, {"package_line": "enterprise"})
    check_policy(require_plugin=True)
    print("Published verified Enterprise Safe Contracts plugin ZIP")
    return 0


def publish_apk(args: argparse.Namespace) -> int:
    sha = source_sha(args.source_sha)
    run_id = quality(args.quality_run_id, args.quality_gates_passed)
    if not args.signing_verified or not args.identity_verified or not args.firebase_identity_verified:
        fail("ESC APK publication requires signing, Android identity and Firebase identity verification")
    api_url = args.api_base_url.strip()
    if HTTPS_RE.fullmatch(api_url) is None:
        fail("ESC production APK API base URL must be absolute HTTPS")
    source = Path(args.apk).expanduser().resolve()
    validate_container(source, ".apk", "android-apk")
    extra = {
        "application_id": APPLICATION_ID,
        "signing_verified": True,
        "identity_verified": True,
        "firebase_identity_verified": True,
        "api_base_url": api_url,
        "device_evidence": evidence(args.device_evidence, "real-device"),
        "uat_evidence": evidence(args.uat_evidence, "UAT"),
        "coexistence_evidence": evidence(args.coexistence_evidence, "Safe Contract/ESC coexistence"),
        "firebase_evidence": evidence(args.firebase_evidence, "Firebase identity"),
    }
    clear_generated(APK_DIR, ".apk")
    shutil.copy2(source, APK_DIR / APK_NAME)
    write_provenance(APK_DIR, APK_NAME, "android-apk", sha, run_id, extra)
    check_policy(require_apk=True)
    print("Published verified Enterprise Safe Contracts production APK")
    return 0


def common(parser: argparse.ArgumentParser) -> None:
    parser.add_argument("--source-sha", required=True)
    parser.add_argument("--quality-run-id", required=True)
    parser.add_argument("--quality-gates-passed", action="store_true")


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(description=__doc__)
    sub = parser.add_subparsers(dest="command", required=True)
    check = sub.add_parser("check")
    check.add_argument("--require-plugin", action="store_true")
    check.add_argument("--require-apk", action="store_true")
    check.add_argument("--require-artifacts", action="store_true")
    plugin = sub.add_parser("publish-plugin")
    plugin.add_argument("--plugin", required=True)
    common(plugin)
    apk = sub.add_parser("publish-apk")
    apk.add_argument("--apk", required=True)
    apk.add_argument("--api-base-url", required=True)
    apk.add_argument("--signing-verified", action="store_true")
    apk.add_argument("--identity-verified", action="store_true")
    apk.add_argument("--firebase-identity-verified", action="store_true")
    apk.add_argument("--device-evidence", required=True)
    apk.add_argument("--uat-evidence", required=True)
    apk.add_argument("--coexistence-evidence", required=True)
    apk.add_argument("--firebase-evidence", required=True)
    common(apk)
    return parser


def main() -> int:
    args = build_parser().parse_args()
    try:
        if args.command == "check":
            checks = check_policy(
                require_plugin=args.require_plugin or args.require_artifacts,
                require_apk=args.require_apk or args.require_artifacts,
            )
            print(f"Enterprise verified artifact policy passed ({checks} checks)")
            return 0
        if args.command == "publish-plugin":
            return publish_plugin(args)
        if args.command == "publish-apk":
            return publish_apk(args)
    except PolicyError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1
    return 1


if __name__ == "__main__":
    raise SystemExit(main())
