#!/usr/bin/env python3
"""Raise AppLovin MAX Flutter plugin compileSdk after Flutter bootstrap."""

from __future__ import annotations

import json
from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
MOBILE = ROOT / "mobile"
PLUGIN_INDEX = MOBILE / ".flutter-plugins-dependencies"


def main() -> int:
    try:
        if not PLUGIN_INDEX.is_file():
            raise RuntimeError("Flutter plugin dependency index is missing after bootstrap")
        data = json.loads(PLUGIN_INDEX.read_text(encoding="utf-8"))
        android_plugins = data.get("plugins", {}).get("android", [])
        plugin = next((item for item in android_plugins if item.get("name") == "applovin_max"), None)
        if not isinstance(plugin, dict) or not plugin.get("path"):
            raise RuntimeError("applovin_max Android plugin path is unavailable")

        android_dir = Path(str(plugin["path"])) / "android"
        build_file = next((p for p in (android_dir / "build.gradle", android_dir / "build.gradle.kts") if p.is_file()), None)
        if build_file is None:
            raise RuntimeError(f"applovin_max Android build file is missing under {android_dir}")

        text = build_file.read_text(encoding="utf-8")
        patterns = [
            re.compile(r"(?m)(compileSdkVersion\s+)(\d+)"),
            re.compile(r"(?m)(compileSdk\s*=\s*)(\d+)"),
            re.compile(r"(?m)(compileSdk\s+)(\d+)"),
        ]
        found = False
        for pattern in patterns:
            if pattern.search(text):
                found = True
                text = pattern.sub(r"\g<1>36", text)
        if not found:
            raise RuntimeError(f"unable to locate applovin_max compileSdk declaration in {build_file}")

        build_file.write_text(text, encoding="utf-8")
        values = [int(value) for value in re.findall(r"compileSdk(?:Version)?(?:\s*=)?\s+(\d+)", text)]
        if not values or min(values) < 34:
            raise RuntimeError(f"applovin_max compileSdk is still below AndroidX floor: {values}")
        print(f"Patched AppLovin MAX compileSdk to {min(values)} via {build_file}")
        return 0
    except (OSError, ValueError, KeyError, json.JSONDecodeError, RuntimeError) as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
