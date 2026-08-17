#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

from collect_esc_android_coexistence_preflight import (  # noqa: E402
    PreflightError,
    collect_preflight,
    parse_signer,
)
from validate_esc_android_coexistence_evidence import (  # noqa: E402
    ESC_APPLICATION_ID,
    SAFE_APPLICATION_ID,
    EvidenceError,
    validate_record,
)

SOURCE_SHA = "a" * 40
SAFE_SIGNER = "1" * 64
ESC_SIGNER = "2" * 64


class FakeRunner:
    def __init__(
        self,
        safe_path: Path,
        esc_path: Path,
        *,
        esc_application_id: str = ESC_APPLICATION_ID,
        esc_signer: str = ESC_SIGNER,
        installed: set[str] | None = None,
    ) -> None:
        self.safe_path = str(safe_path)
        self.esc_path = str(esc_path)
        self.esc_application_id = esc_application_id
        self.esc_signer = esc_signer
        self.installed = (
            {SAFE_APPLICATION_ID, ESC_APPLICATION_ID}
            if installed is None
            else installed
        )

    def __call__(self, command: list[str] | tuple[str, ...]) -> str:
        args = list(command)
        if args[0] == "apkanalyzer":
            if args[-1] == self.safe_path:
                return f"{SAFE_APPLICATION_ID} 123 1.2.3"
            return f"{self.esc_application_id} 200 2.0.0"
        if args[0] == "apksigner":
            signer = SAFE_SIGNER if args[-1] == self.safe_path else self.esc_signer
            return f"Signer #1 certificate SHA-256 digest: {signer}"
        if args[0] == "adb":
            if args[-2:] == ["getprop", "ro.product.manufacturer"]:
                return "Google"
            if args[-2:] == ["getprop", "ro.product.model"]:
                return "Pixel 9"
            if args[-2:] == ["getprop", "ro.build.version.release"]:
                return "15"
            if args[-2:] == ["getprop", "ro.build.version.sdk"]:
                return "35"
            if args[-3:-1] == ["pm", "path"]:
                package = args[-1]
                return (
                    f"package:/data/app/{package}/base.apk"
                    if package in self.installed
                    else ""
                )
        raise AssertionError(f"unexpected command: {args}")


class AndroidCoexistencePreflightTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tempdir = tempfile.TemporaryDirectory()
        root = Path(self.tempdir.name)
        self.safe_apk = root / "safe.apk"
        self.esc_apk = root / "esc.apk"
        self.safe_apk.write_bytes(b"safe-contract-apk")
        self.esc_apk.write_bytes(b"enterprise-safe-contracts-apk")

    def tearDown(self) -> None:
        self.tempdir.cleanup()

    def test_apk_checks_pass_but_final_uat_remains_pending(self) -> None:
        record = collect_preflight(
            self.safe_apk,
            self.esc_apk,
            SOURCE_SHA,
            runner=FakeRunner(self.safe_apk, self.esc_apk),
        )
        self.assertEqual(record["status"], "PENDING_REAL_DEVICE_UAT")
        self.assertIsNone(record["device"])
        self.assertNotIn("decision", record)
        with self.assertRaises(EvidenceError):
            validate_record(record, SOURCE_SHA)

    def test_wrong_esc_application_id_fails_closed(self) -> None:
        runner = FakeRunner(
            self.safe_apk,
            self.esc_apk,
            esc_application_id="com.safecontracts.wrong",
        )
        with self.assertRaisesRegex(PreflightError, "ESC application id must be"):
            collect_preflight(
                self.safe_apk, self.esc_apk, SOURCE_SHA, runner=runner
            )

    def test_shared_signer_fails_closed(self) -> None:
        runner = FakeRunner(
            self.safe_apk, self.esc_apk, esc_signer=SAFE_SIGNER
        )
        with self.assertRaisesRegex(PreflightError, "signing certificate"):
            collect_preflight(
                self.safe_apk, self.esc_apk, SOURCE_SHA, runner=runner
            )

    def test_identical_apk_fails_closed(self) -> None:
        self.esc_apk.write_bytes(self.safe_apk.read_bytes())
        with self.assertRaisesRegex(PreflightError, "APK SHA-256"):
            collect_preflight(
                self.safe_apk,
                self.esc_apk,
                SOURCE_SHA,
                runner=FakeRunner(self.safe_apk, self.esc_apk),
            )

    def test_malformed_source_sha_fails_before_tooling(self) -> None:
        with self.assertRaisesRegex(PreflightError, "40-character Git SHA"):
            collect_preflight(
                self.safe_apk,
                self.esc_apk,
                "short",
                runner=FakeRunner(self.safe_apk, self.esc_apk),
            )

    def test_selected_device_requires_both_packages(self) -> None:
        runner = FakeRunner(
            self.safe_apk,
            self.esc_apk,
            installed={SAFE_APPLICATION_ID},
        )
        with self.assertRaisesRegex(PreflightError, ESC_APPLICATION_ID):
            collect_preflight(
                self.safe_apk,
                self.esc_apk,
                SOURCE_SHA,
                device_serial="device-01",
                runner=runner,
            )

    def test_selected_device_captures_dual_install(self) -> None:
        record = collect_preflight(
            self.safe_apk,
            self.esc_apk,
            SOURCE_SHA,
            device_serial="device-01",
            runner=FakeRunner(self.safe_apk, self.esc_apk),
        )
        device = record["device"]
        assert isinstance(device, dict)
        self.assertEqual(device["reference"], "device-01")
        self.assertEqual(device["manufacturer"], "Google")
        self.assertEqual(device["model"], "Pixel 9")
        self.assertEqual(device["android_version"], "15")
        self.assertEqual(device["api_level"], "35")
        self.assertTrue(device["dual_install_observed"])

    def test_signer_digest_accepts_colon_form(self) -> None:
        digest = ":".join(["ab"] * 32)
        self.assertEqual(
            parse_signer(f"Signer #1 certificate SHA-256 digest: {digest}"),
            "ab" * 32,
        )


if __name__ == "__main__":
    unittest.main()
