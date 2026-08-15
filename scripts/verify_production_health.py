#!/usr/bin/env python3
"""Verify and diagnose the public SafeContracts production health endpoint."""

from __future__ import annotations

import json
import re
import sys
from dataclasses import dataclass
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urljoin, urlparse, urlunparse
from urllib.request import Request, urlopen

EXPECTED_NAMESPACE_PATH = "/wp-json/safecontracts/v1/"
MAX_BODY = 256 * 1024
MAX_PREVIEW = 500


@dataclass(frozen=True)
class Probe:
    label: str
    requested_url: str
    status: int | None
    final_url: str
    content_type: str
    body: bytes
    transport_error: str = ""


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


def request(url: str, label: str) -> Probe:
    req = Request(
        url,
        headers={
            "Accept": "application/json",
            "User-Agent": "SafeContracts-CI-Health/1.0",
        },
        method="GET",
    )
    try:
        with urlopen(req, timeout=30) as response:
            return Probe(
                label=label,
                requested_url=url,
                status=response.status,
                final_url=response.geturl(),
                content_type=response.headers.get("Content-Type", ""),
                body=response.read(MAX_BODY),
            )
    except HTTPError as error:
        return Probe(
            label=label,
            requested_url=url,
            status=error.code,
            final_url=error.geturl(),
            content_type=error.headers.get("Content-Type", "") if error.headers else "",
            body=error.read(MAX_BODY),
        )
    except URLError as error:
        return Probe(
            label=label,
            requested_url=url,
            status=None,
            final_url=url,
            content_type="",
            body=b"",
            transport_error=str(error.reason),
        )


def same_origin(expected_url: str, actual_url: str) -> bool:
    expected = urlparse(expected_url)
    actual = urlparse(actual_url)
    return (
        actual.scheme == expected.scheme
        and actual.hostname == expected.hostname
        and actual.port == expected.port
    )


def parse_health(probe: Probe, expected_origin_url: str) -> tuple[bool, str]:
    if probe.transport_error:
        return False, f"transport={probe.transport_error}"
    if not same_origin(expected_origin_url, probe.final_url):
        return False, f"redirected-off-origin={probe.final_url}"
    if probe.status != 200:
        return False, f"http={probe.status}"
    if "json" not in probe.content_type.lower():
        return False, f"content-type={probe.content_type!r}"

    try:
        payload = json.loads(probe.body.decode("utf-8"))
    except (UnicodeDecodeError, json.JSONDecodeError) as error:
        return False, f"invalid-json={error}"

    if not isinstance(payload, dict):
        return False, "payload-not-object"
    data = payload.get("data")
    meta = payload.get("meta")
    if not isinstance(data, dict) or not isinstance(meta, dict):
        return False, "missing-data-meta"

    expected_values = {
        "service": "SafeContracts",
        "api_version": "v1",
        "status": "ok",
    }
    for key, expected_value in expected_values.items():
        if data.get(key) != expected_value:
            return False, f"data.{key}={data.get(key)!r}"
    if meta.get("api_version") != "v1":
        return False, f"meta.api_version={meta.get('api_version')!r}"

    plugin_version = data.get("plugin_version")
    if not isinstance(plugin_version, str) or not plugin_version.strip():
        return False, "missing-plugin-version"
    return True, plugin_version.strip()


def safe_preview(body: bytes) -> str:
    text = body[:4096].decode("utf-8", "replace")
    title = re.search(r"<title[^>]*>(.*?)</title>", text, flags=re.IGNORECASE | re.DOTALL)
    if title:
        clean = re.sub(r"\s+", " ", title.group(1)).strip()
        return f"html-title={clean[:MAX_PREVIEW]!r}"
    clean = re.sub(r"\s+", " ", text).strip()
    return f"body-preview={clean[:MAX_PREVIEW]!r}"


def fallback_url(base_url: str) -> str:
    parsed = urlparse(base_url)
    root = urlunparse((parsed.scheme, parsed.netloc, "/", "", "", ""))
    return f"{root}?{urlencode({'rest_route': '/safecontracts/v1/health'})}"


def verify(base_url: str) -> int:
    base_url = normalize_base_url(base_url)
    pretty = request(urljoin(base_url, "health"), "pretty")
    pretty_ok, pretty_detail = parse_health(pretty, base_url)
    if pretty_ok:
        print(
            "SafeContracts production health passed "
            f"(route=pretty, host={urlparse(base_url).hostname}, "
            f"plugin_version={pretty_detail}, api=v1)."
        )
        return 0

    fallback = request(fallback_url(base_url), "rest_route")
    fallback_ok, fallback_detail = parse_health(fallback, base_url)
    if fallback_ok:
        fail(
            "pretty /wp-json/ route is not serving the SafeContracts JSON API, "
            "but WordPress ?rest_route= fallback is healthy. Fix the web-server/WordPress "
            "permalink rewrite for /wp-json/. "
            f"pretty: status={pretty.status}, {pretty_detail}, {safe_preview(pretty.body)}; "
            f"fallback plugin_version={fallback_detail}"
        )

    fail(
        "SafeContracts production health is not available through either WordPress REST route. "
        f"pretty: status={pretty.status}, {pretty_detail}, final={pretty.final_url!r}, "
        f"{safe_preview(pretty.body)}; "
        f"fallback: status={fallback.status}, {fallback_detail}, final={fallback.final_url!r}, "
        f"{safe_preview(fallback.body)}"
    )


def main() -> int:
    if len(sys.argv) != 2:
        fail("usage: verify_production_health.py <production-api-base-url>")
    return verify(sys.argv[1])


if __name__ == "__main__":
    raise SystemExit(main())
