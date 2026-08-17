#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

TARGET_BRANCH = "enterprise-safecontracts"
REQUIRED_CHECKS = {"esc-foundation", "esc-mobile"}


def load_json(path: Path) -> Any:
    try:
        return json.loads(path.read_text(encoding="utf-8"))
    except (OSError, json.JSONDecodeError) as exc:
        raise SystemExit(f"FAIL: cannot read valid JSON from {path}: {exc}") from exc


def sha256_file(path: Path) -> str:
    digest = hashlib.sha256()
    with path.open("rb") as handle:
        for chunk in iter(lambda: handle.read(1024 * 1024), b""):
            digest.update(chunk)
    return digest.hexdigest()


def enabled(value: Any) -> bool | None:
    if isinstance(value, bool):
        return value
    if isinstance(value, dict) and isinstance(value.get("enabled"), bool):
        return value["enabled"]
    return None


def legacy_status_checks(protection: dict[str, Any]) -> tuple[set[str], bool]:
    block = protection.get("required_status_checks")
    if not isinstance(block, dict):
        return set(), False

    contexts: set[str] = set()
    raw_contexts = block.get("contexts", [])
    if isinstance(raw_contexts, list):
        contexts.update(item for item in raw_contexts if isinstance(item, str))

    raw_checks = block.get("checks", [])
    if isinstance(raw_checks, list):
        for item in raw_checks:
            if isinstance(item, dict) and isinstance(item.get("context"), str):
                contexts.add(item["context"])

    return contexts, block.get("strict") is True


def effective_rules_status_checks(rules: list[Any]) -> tuple[set[str], bool]:
    contexts: set[str] = set()
    strict = False
    for rule in rules:
        if not isinstance(rule, dict) or rule.get("type") != "required_status_checks":
            continue
        params = rule.get("parameters")
        if not isinstance(params, dict):
            continue
        strict = strict or params.get("strict_required_status_checks_policy") is True
        raw_checks = params.get("required_status_checks", [])
        if isinstance(raw_checks, list):
            for item in raw_checks:
                if isinstance(item, dict) and isinstance(item.get("context"), str):
                    contexts.add(item["context"])
    return contexts, strict


def has_rule(rules: list[Any], rule_type: str) -> bool:
    return any(isinstance(rule, dict) and rule.get("type") == rule_type for rule in rules)


def evaluate(
    branch: dict[str, Any],
    protection: dict[str, Any] | None,
    rules: list[Any],
    break_glass_note: str,
) -> dict[str, Any]:
    legacy = protection or {}
    legacy_checks, legacy_strict = legacy_status_checks(legacy)
    rule_checks, rule_strict = effective_rules_status_checks(rules)
    all_checks = legacy_checks | rule_checks

    protected = branch.get("name") == TARGET_BRANCH and branch.get("protected") is True
    pr_required = isinstance(legacy.get("required_pull_request_reviews"), dict) or has_rule(
        rules, "pull_request"
    )
    strict_status = legacy_strict or rule_strict
    required_checks = REQUIRED_CHECKS.issubset(all_checks)

    legacy_force = enabled(legacy.get("allow_force_pushes"))
    force_push_blocked = legacy_force is False or has_rule(rules, "non_fast_forward")

    legacy_delete = enabled(legacy.get("allow_deletions"))
    deletion_blocked = legacy_delete is False or has_rule(rules, "deletion")

    note = break_glass_note.strip()
    break_glass_documented = len(note) >= 12

    checks = {
        "protected_branch": protected,
        "pull_request_required": pr_required,
        "required_status_checks_present": required_checks,
        "strict_up_to_date_status_checks": strict_status,
        "force_push_blocked": force_push_blocked,
        "branch_deletion_blocked": deletion_blocked,
        "break_glass_documented": break_glass_documented,
    }
    decision = "PASS" if all(checks.values()) else "FAIL"

    return {
        "schema_version": 1,
        "branch": TARGET_BRANCH,
        "decision": decision,
        "checks": checks,
        "observed_required_checks": sorted(all_checks),
        "required_checks": sorted(REQUIRED_CHECKS),
        "break_glass_statement": note,
        "sources": {
            "legacy_branch_protection_present": protection is not None,
            "effective_rule_types": sorted(
                {
                    rule["type"]
                    for rule in rules
                    if isinstance(rule, dict) and isinstance(rule.get("type"), str)
                }
            ),
        },
    }


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Audit captured GitHub enforcement for enterprise-safecontracts."
    )
    parser.add_argument("--branch-json", type=Path, required=True)
    parser.add_argument("--rules-json", type=Path, required=True)
    parser.add_argument("--protection-json", type=Path)
    parser.add_argument("--break-glass-note", required=True)
    parser.add_argument("--output", type=Path, required=True)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    branch = load_json(args.branch_json)
    rules = load_json(args.rules_json)
    protection = load_json(args.protection_json) if args.protection_json else None

    if not isinstance(branch, dict):
        raise SystemExit("FAIL: branch JSON must be an object")
    if not isinstance(rules, list):
        raise SystemExit("FAIL: effective rules JSON must be an array")
    if protection is not None and not isinstance(protection, dict):
        raise SystemExit("FAIL: protection JSON must be an object")

    result = evaluate(branch, protection, rules, args.break_glass_note)
    result["captured_input_sha256"] = {
        "branch_json": sha256_file(args.branch_json),
        "rules_json": sha256_file(args.rules_json),
        "protection_json": sha256_file(args.protection_json)
        if args.protection_json
        else None,
    }
    result["audited_utc"] = datetime.now(timezone.utc).isoformat().replace("+00:00", "Z")

    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(
        json.dumps(result, indent=2, sort_keys=True) + "\n",
        encoding="utf-8",
    )

    if result["decision"] != "PASS":
        failed = [name for name, passed in result["checks"].items() if not passed]
        print("FAIL: ESC branch enforcement audit failed: " + ", ".join(failed))
        return 1

    print("PASS: ESC branch enforcement audit satisfied all required controls")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
