#!/usr/bin/env python3
from __future__ import annotations

from pathlib import Path
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / "scripts"))

from collect_esc_android_coexistence_uat_session import SessionError, collect_session  # noqa: E402
from validate_esc_android_coexistence_evidence import (  # noqa: E402
    ESC_APPLICATION_ID,
    REQUIRED_CHECKS,
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
        installed: set[str] | None = None,
        launched_processes: set[str] | None = None,
    ) -> None:
        self.safe_path = str(safe_path)
        self.esc_path = str(esc_path)
        self.installed = (
            {SAFE_APPLICATION_ID, ESC_APPLICATION_ID}
            if installed is None
            else installed
        )
        self.launched_processes = (
            {SAFE_APPLICATION_ID, ESC_APPLICATION_ID}
            if launched_processes is None
            else launched_processes
        )
        self.commands: list[list[str]] = []

    def __call__(self, command: list[str] | tuple[str, ...]) -> str:
        args = list(command)
        self.commands.append(args)
        if args[0] == "apkanalyzer":
            if args[-1] == self.safe_path:
                return f"{SAFE_APPLICATION_ID} 123 1.2.3"
            return f"{ESC_APPLICATION_ID} 200 2.0.0"
        if args[0] == "apksigner":
            signer = SAFE_SIGNER if args[-1] == self.safe_path else ESC_SIGNER
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
            if len(args) >= 3 and args[-3:-1] == ["pm", "path"]:
                package = args[-1]
                return (
                    f"package:/data/app/{package}/base.apk"
                    if package in self.installed
                    else ""
                )
            if "monkey" in args:
                return "Events injected: 1"
            if len(args) >= 2 and args[-2] == "pidof":
                package = args[-1]
                if package in self.launched_processes:
                    return "4321" if package == SAFE_APPLICATION_ID else "5432"
                return ""
        raise AssertionError(f"unexpected command: {args}")


class AndroidCoexistenceUatSessionTests(unittest.TestCase):
    def setUp(self) -> None:
        self.tempdir = tempfile.TemporaryDirectory()
        root = Path(self.tempdir.name)
        self.safe_apk = root / "safe.apk"
        self.esc_apk = root / "esc.apk"
        self.safe_apk.write_bytes(b"safe-contract-apk")
        self.esc_apk.write_bytes(b"enterprise-safe-contracts-apk")

    def tearDown(self) -> None:
        self.tempdir.cleanup()

    def test_session_captures_only_objective_passes_and_final_gate_rejects(self) -> None:
        runner = FakeRunner(self.safe_apk, self.esc_apk)
        record = collect_session(
            self.safe_apk,
            self.esc_apk,
            SOURCE_SHA,
            "device-01",
            "ESC QA",
            tested_at_utc="2026-08-17T17:55:00Z",
            runner=runner,
        )
        self.assertEqual(record["decision"], "PENDING")
        self.assertEqual(set(record["checks"]), set(REQUIRED_CHECKS))
        self.assertEqual(record["checks"]["dual_install"]["status"], "PASS")
        self.assertEqual(record["checks"]["independent_launch"]["status"], "PASS")
        for name in REQUIRED_CHECKS:
            if name not in {"dual_install", "independent_launch"}:
                self.assertEqual(record["checks"][name]["status"], "PENDING")
        self.assertEqual(
            record["objective_session"]["safe_contract_launch"]["pid"], "4321"
        )
        self.assertEqual(record["objective_session"]["esc_launch"]["pid"], "5432")
        with self.assertRaises(EvidenceError):
            validate_record(record, SOURCE_SHA)

    def test_missing_package_fails_closed_before_launch(self) -> None:
        runner = FakeRunner(
            self.safe_apk,
            self.esc_apk,
            installed={SAFE_APPLICATION_ID},
        )
        with self.assertRaisesRegex(SessionError, ESC_APPLICATION_ID):
            collect_session(
                self.safe_apk,
                self.esc_apk,
                SOURCE_SHA,
                "device-01",
                "ESC QA",
                runner=runner,
            )
        self.assertFalse(any("monkey" in command for command in runner.commands))

    def test_launch_without_observable_process_fails_closed(self) -> None:
        runner = FakeRunner(
            self.safe_apk,
            self.esc_apk,
            launched_processes={SAFE_APPLICATION_ID},
        )
        with self.assertRaisesRegex(SessionError, ESC_APPLICATION_ID):
            collect_session(
                self.safe_apk,
                self.esc_apk,
                SOURCE_SHA,
                "device-01",
                "ESC QA",
                runner=runner,
            )

    def test_tester_is_required(self) -> None:
        with self.assertRaisesRegex(SessionError, "tester is required"):
            collect_session(
                self.safe_apk,
                self.esc_apk,
                SOURCE_SHA,
                "device-01",
                "x",
                runner=FakeRunner(self.safe_apk, self.esc_apk),
            )

    def test_harness_uses_no_destructive_adb_operation(self) -> None:
        runner = FakeRunner(self.safe_apk, self.esc_apk)
        collect_session(
            self.safe_apk,
            self.esc_apk,
            SOURCE_SHA,
            "device-01",
            "ESC QA",
            runner=runner,
        )
        forbidden_tokens = {"install", "uninstall", "clear"}
        for command in runner.commands:
            self.assertTrue(forbidden_tokens.isdisjoint(command), command)


if __name__ == "__main__":
    unittest.main()
