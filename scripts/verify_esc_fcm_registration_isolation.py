#!/usr/bin/env python3
"""Fail closed if ESC FCM token registration loses explicit application identity."""

from __future__ import annotations

from pathlib import Path
import sys

ROOT = Path(__file__).resolve().parents[1]
ESC_APPLICATION_ID = "com.safecontracts.enterprise"
SAFE_APPLICATION_ID = "com.safecontracts.safecontracts_mobile"


class ContractError(RuntimeError):
    pass


def fail(message: str) -> None:
    raise ContractError(message)


def read(relative: str) -> str:
    path = ROOT / relative
    if not path.is_file():
        fail(f"missing FCM identity contract file: {relative}")
    return path.read_text(encoding="utf-8")


def require(text: str, marker: str, label: str) -> None:
    if marker not in text:
        fail(f"{label} is missing marker: {marker}")


def main() -> int:
    try:
        client = read("mobile/lib/features/notifications/push_registration.dart")
        controller = read(
            "wordpress-plugin/safecontracts/src/Rest/DevicesController.php"
        )
        service = read(
            "wordpress-plugin/safecontracts/src/Notifications/DeviceTokenService.php"
        )

        require(
            client,
            f"static const applicationId = '{ESC_APPLICATION_ID}';",
            "ESC Flutter FCM client",
        )
        if client.count("'application_id': applicationId") != 3:
            fail(
                "ESC Flutter FCM client must bind application_id on register, token-rotation revoke, and logout revoke"
            )
        require(client, "'devices/register'", "ESC Flutter FCM client")
        require(client, "'devices/revoke'", "ESC Flutter FCM client")

        require(
            controller,
            "['token', 'platform', 'application_id']",
            "ESC device register REST contract",
        )
        require(
            controller,
            "['token', 'application_id']",
            "ESC device revoke REST contract",
        )
        require(
            controller,
            "$body['application_id']",
            "ESC device REST contract",
        )

        require(
            service,
            f"public const APPLICATION_ID = '{ESC_APPLICATION_ID}';",
            "ESC device token service",
        )
        require(
            service,
            "normalizeApplicationId",
            "ESC device token service",
        )
        require(
            service,
            "if ($applicationId !== self::APPLICATION_ID)",
            "ESC device token service",
        )

        for label, text in (
            ("ESC Flutter FCM client", client),
            ("ESC device register REST contract", controller),
            ("ESC device token service", service),
        ):
            if SAFE_APPLICATION_ID in text:
                fail(f"{label} contains forbidden Safe Contract application identity")
    except ContractError as exc:
        print(f"FAIL: {exc}", file=sys.stderr)
        return 1

    print(
        "ESC FCM application-identity contract passed: register/revoke are explicitly bound to com.safecontracts.enterprise"
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
