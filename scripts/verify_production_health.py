#!/usr/bin/env python3
"""Verify the public SafeContracts production health endpoint without credentials."""

from __future__ import annotations

import json
import sys
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlparse
from urllib.request import Request, urlopen

EXPECTED_NAMESPACE_PATH = "/wp-json/safecontracts/v1/"


def fail(message: str) -> None:
    print(f"FAIL: {message}", file=sys.stderr)
    raise SystemExit(1)


def normalize_base_url(raw: str) -> str:
    value = raw.strip()
    parsed = urlparse(value)
    if parsed.scheme != "https" or not parsed.hostname:
        fail("production SafeContracts API base URL must be absolute HTTPS")
    if parsed.username or parsed.password:
        fail("production SafeContracts API base URL must not embed credentials")
    if parsed.query or parsed.fragment:
        fail("production SafeContracts API base URL must not contain query/fragment")
    if parsed.path.rstrip("/") != EXPECTED_NAMESPACE_PATH.rstrip("/"):
        fail(f"production SafeContracts API must target {EXPECTED_NAMESPACE_PATH}")
    return value if value.endswith("/") else value + "/"


def verify(base_url: str) -> int:
    base_url = normalize_base_url(base_url)
    expected = urlparse(base_url)
    health_url = urljoin(base_url, "health")
    request = Request(
        health_url,
        headers={
            "Accept": "application/json",
            "User-Agent": "SafeContracts-CI-Health/1.0",
        },
        method="GET",
    )

    try:
        with urlopen(request, timeout=30) as response:
            status = response.status
            final_url = response.geturl()
            content_type = response.headers.get("Content-Type", "")
            body = response.read(256 * 1024)
    except HTTPError as error:
        detail = error.read(2048).decode("utf-8", "replace").strip()
        fail(f"production health returned HTTP {error.code}: {detail[:500]}")
    except URLError as error:
        fail(f"production health request failed: {error.reason}")

    final = urlparse(final_url)
    if final.scheme != expected.scheme or final.hostname != expected.hostname or final.port != expected.port:
        fail("production health endpoint redirected to a different origin")
    if status != 200:
        fail(f"production health returned unexpected HTTP {status}")
    if "json" not in content_type.lower():
        fail(f"production health must return JSON, got {content_type!r}")

    try:
        payload = json.loads(body.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        fail(f"production health returned invalid JSON: {error}")

    if not isinstance(payload, dict):
        fail("production health payload must be an object")
    data = payload.get("data")
    meta = payload.get("meta")
    if not isinstance(data, dict) or not isinstance(meta, dict):
        fail("production health payload must contain data/meta objects")

    expected_values = {
        "service": "SafeContracts",
        "api_version": "v1",
        "status": "ok",
    }
    for key, expected_value in expected_values.items():
        if data.get(key) != expected_value:
            fail(f"production health data.{key} must equal {expected_value!r}")
    if meta.get("api_version") != "v1":
        fail("production health meta.api_version must equal 'v1'")

    plugin_version = data.get("plugin_version")
    if not isinstance(plugin_version, str) or not plugin_version.strip():
        fail("production health must report a non-empty plugin_version")

    print(
        "SafeContracts production health passed "
        f"(host={expected.hostname}, plugin_version={plugin_version}, api=v1)."
    )
    return 0


def main() -> int:
    if len(sys.argv) != 2:
        fail("usage: verify_production_health.py <production-api-base-url>")
    return verify(sys.argv[1])


if __name__ == "__main__":
    raise SystemExit(main())
