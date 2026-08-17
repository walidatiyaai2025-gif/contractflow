#!/usr/bin/env python3
from __future__ import annotations

import argparse
import hashlib
import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.request import Request, urlopen

TARGET_BRANCH = "enterprise-safecontracts"
REQUIRED_CHECKS = {"esc-foundation", "esc-mobile"}
GITHUB_ACTIONS_APP_SLUG = "github-actions"
GITHUB_API_VERSION = "2026-03-10"


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


def resolve_github_actions_app_id() -> int:
    request = Request(
        f"https://api.github.com/apps/{GITHUB_ACTIONS_APP_SLUG}",
        headers={
            "Accept": "application/vnd.github+json",
            "X-GitHub-Api-Version": GITHUB_API_VERSION,
            "User-Agent": "enterprise-safecontracts-branch-protection-audit",
        },
    )
    try:
        with urlopen(request, timeout=20) as response:
            payload = json.load(response)
    except (HTTPError, URLError, OSError, json.JSONDecodeError) as exc:
        raise SystemExit(
            f"FAIL: cannot resolve GitHub Actions App identity from GitHub API: {exc}"
        ) from exc

    if not isinstance(payload, dict) or payload.get("slug") != GITHUB_ACTIONS_APP_SLUG:
        raise SystemExit("FAIL: GitHub API returned an unexpected GitHub Actions App identity")
    app_id = payload.get("id")
    if not isinstance(app_id, int) or app_id <= 0:
        raise SystemExit("FAIL: GitHub Actions App ID must resolve to a positive integer")
    return app_id


def enabled(value: Any) -> bool | None:
    if isinstance(value, bool):
        return value
    if isinstance(value, dict) and isinstance(value.get("enabled"), bool):
        return value["enabled"]
    return None


def add_source(
    sources: dict[str, set[int]],
    unbound: set[str],
    context: Any,
    source_id: Any,
) -> None:
    if not isinstance(context, str) or not context:
        return
    if isinstance(source_id, int):
        sources.setdefault(context, set()).add(source_id)
    else:
        unbound.add(context)


def legacy_status_checks(
    protection: dict[str, Any],
) -> tuple[set[str], bool, dict[str, set[int]], set[str]]:
    block = protection.get("required_status_checks")
    if not isinstance(block, dict):
        return set(), False, {}, set()

    contexts: set[str] = set()
    sources: dict[str, set[int]] = {}
    unbound: set[str] = set()

    raw_contexts = block.get("contexts", [])
    if isinstance(raw_contexts, list):
        for item in raw_contexts:
            if isinstance(item, str):
                contexts.add(item)

    raw_checks = block.get("checks", [])
    if isinstance(raw_checks, list):
        for item in raw_checks:
            if not isinstance(item, dict) or not isinstance(item.get("context"), str):
                continue
            context = item["context"]
            contexts.add(context)
            add_source(sources, unbound, context, item.get("app_id"))

    for context in contexts:
        if context not in sources:
            unbound.add(context)

    return contexts, block.get("strict") is True, sources, unbound


def effective_rules_status_checks(
    rules: list[Any],
) -> tuple[set[str], bool, dict[str, set[int]], set[str]]:
    contexts: set[str] = set()
    sources: dict[str, set[int]] = {}
    unbound: set[str] = set()
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
                if not isinstance(item, dict) or not isinstance(item.get("context"), str):
                    continue
                context = item["context"]
                contexts.add(context)
                add_source(sources, unbound, context, item.get("integration_id"))
    return contexts, strict, sources, unbound


def effective_pull_request_controls(rules: list[Any]) -> tuple[bool, bool]:
    required = False
    conversation_resolution = False
    for rule in rules:
        if not isinstance(rule, dict) or rule.get("type") != "pull_request":
            continue
        required = True
        params = rule.get("parameters")
        if isinstance(params, dict):
            conversation_resolution = (
                conversation_resolution
                or params.get("required_review_thread_resolution") is True
            )
    return required, conversation_resolution


def has_rule(rules: list[Any], rule_type: str) -> bool:
    return any(isinstance(rule, dict) and rule.get("type") == rule_type for rule in rules)


def merge_sources(*source_maps: dict[str, set[int]]) -> dict[str, set[int]]:
    merged: dict[str, set[int]] = {}
    for source_map in source_maps:
        for context, ids in source_map.items():
            merged.setdefault(context, set()).update(ids)
    return merged


def evaluate(
    branch: dict[str, Any],
    protection: dict[str, Any] | None,
    rules: list[Any],
    break_glass_note: str,
    expected_status_check_app_id: int,
) -> dict[str, Any]:
    if not isinstance(expected_status_check_app_id, int) or expected_status_check_app_id <= 0:
        raise ValueError("expected_status_check_app_id must be a positive GitHub App ID")

    legacy = protection or {}
    legacy_checks, legacy_strict, legacy_sources, legacy_unbound = legacy_status_checks(legacy)
    rule_checks, rule_strict, rule_sources, rule_unbound = effective_rules_status_checks(rules)
    rule_pr_required, rule_conversation_resolution = effective_pull_request_controls(rules)
    all_checks = legacy_checks | rule_checks
    all_sources = merge_sources(legacy_sources, rule_sources)
    all_unbound = legacy_unbound | rule_unbound

    protected = branch.get("name") == TARGET_BRANCH and branch.get("protected") is True
    pr_required = isinstance(legacy.get("required_pull_request_reviews"), dict) or rule_pr_required
    strict_status = legacy_strict or rule_strict
    required_checks = REQUIRED_CHECKS.issubset(all_checks)
    required_sources_verified = all(
        expected_status_check_app_id in all_sources.get(context, set())
        for context in REQUIRED_CHECKS
    )

    admin_enforced = enabled(legacy.get("enforce_admins")) is True
    legacy_conversation_resolution = enabled(legacy.get("required_conversation_resolution"))
    conversation_resolution_required = (
        legacy_conversation_resolution is True or rule_conversation_resolution
    )

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
        "required_status_check_sources_verified": required_sources_verified,
        "strict_up_to_date_status_checks": strict_status,
        "administrator_enforcement_verified": admin_enforced,
        "conversation_resolution_required": conversation_resolution_required,
        "force_push_blocked": force_push_blocked,
        "branch_deletion_blocked": deletion_blocked,
        "break_glass_documented": break_glass_documented,
    }
    decision = "PASS" if all(checks.values()) else "FAIL"

    return {
        "schema_version": 3,
        "branch": TARGET_BRANCH,
        "decision": decision,
        "checks": checks,
        "observed_required_checks": sorted(all_checks),
        "required_checks": sorted(REQUIRED_CHECKS),
        "expected_status_check_app_slug": GITHUB_ACTIONS_APP_SLUG,
        "expected_status_check_app_id": expected_status_check_app_id,
        "observed_required_check_source_ids": {
            context: sorted(all_sources.get(context, set()))
            for context in sorted(REQUIRED_CHECKS)
        },
        "unbound_required_check_contexts": sorted(REQUIRED_CHECKS & all_unbound),
        "break_glass_statement": note,
        "sources": {
            "legacy_branch_protection_present": protection is not None,
            "administrator_enforcement_source": (
                "legacy_branch_protection" if admin_enforced else "unverified"
            ),
            "status_check_source_contract": "github_app_id",
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
    parser.add_argument("--expected-status-check-app-id", type=int)
    parser.add_argument("--output", type=Path, required=True)
    return parser.parse_args()


def main() -> int:
    args = parse_args()
    expected_app_id = args.expected_status_check_app_id
    if expected_app_id is None:
        expected_app_id = resolve_github_actions_app_id()
    if expected_app_id <= 0:
        raise SystemExit("FAIL: --expected-status-check-app-id must be a positive GitHub App ID")

    branch = load_json(args.branch_json)
    rules = load_json(args.rules_json)
    protection = load_json(args.protection_json) if args.protection_json else None

    if not isinstance(branch, dict):
        raise SystemExit("FAIL: branch JSON must be an object")
    if not isinstance(rules, list):
        raise SystemExit("FAIL: effective rules JSON must be an array")
    if protection is not None and not isinstance(protection, dict):
        raise SystemExit("FAIL: protection JSON must be an object")

    result = evaluate(branch, protection, rules, args.break_glass_note, expected_app_id)
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
