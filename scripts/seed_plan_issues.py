#!/usr/bin/env python3
"""Create any missing SafeContracts planned GitHub Issues.

Designed for GitHub Actions with GITHUB_TOKEN and GITHUB_REPOSITORY.
"""

from __future__ import annotations

import json
import os
import re
import sys
import time
import urllib.error
import urllib.request
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from plan_config import PHASES, iter_tasks, planned_total  # noqa: E402

TOKEN = os.environ.get("GITHUB_TOKEN")
REPO = os.environ.get("GITHUB_REPOSITORY")
API = os.environ.get("GITHUB_API_URL", "https://api.github.com")

if not TOKEN or not REPO:
    raise SystemExit("GITHUB_TOKEN and GITHUB_REPOSITORY are required")


def request(method: str, path: str, payload=None):
    url = f"{API}{path}"
    data = None
    headers = {
        "Accept": "application/vnd.github+json",
        "Authorization": f"Bearer {TOKEN}",
        "X-GitHub-Api-Version": "2022-11-28",
        "User-Agent": "safecontracts-plan-sync",
    }
    if payload is not None:
        data = json.dumps(payload).encode("utf-8")
        headers["Content-Type"] = "application/json"
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try:
        with urllib.request.urlopen(req, timeout=60) as response:
            raw = response.read()
            return json.loads(raw) if raw else None
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"GitHub API {method} {path} failed: {exc.code} {body}") from exc


def ensure_label(name: str, color: str, description: str):
    try:
        request("POST", f"/repos/{REPO}/labels", {
            "name": name,
            "color": color,
            "description": description[:100],
        })
    except RuntimeError as exc:
        # 422 means the label already exists.
        if " 422 " not in str(exc):
            raise


def existing_task_ids() -> set[str]:
    found: set[str] = set()
    page = 1
    pattern = re.compile(r"^(SC-P\d+-\d{3})\b")
    while True:
        items = request(
            "GET",
            f"/repos/{REPO}/issues?state=all&labels=safecontracts-task&per_page=100&page={page}",
        )
        if not items:
            break
        for item in items:
            if "pull_request" in item:
                continue
            match = pattern.match(item.get("title", ""))
            if match:
                found.add(match.group(1))
        if len(items) < 100:
            break
        page += 1
    return found


def task_body(task: dict) -> str:
    return f"""## SafeContracts production task

**Task ID:** `{task['id']}`  
**Phase:** `{task['phase']} — {task['phase_name']}`  
**Workstream:** {task['group']}  
**Activity:** {task['activity']}

### Objective
Deliver the `{task['group']}` work item for the SafeContracts V1 baseline in accordance with `docs/MASTER_PLAN.md`, `docs/DEVELOPMENT_ROADMAP.md`, `docs/DECISIONS.md` and the team GitHub workflow.

### Acceptance criteria
- [ ] Implementation/result is complete for this task's bounded scope.
- [ ] Server-side role/capability and Accountant data-scope rules are preserved where relevant.
- [ ] Financial calculations/data integrity are tested where relevant.
- [ ] API/database/UI impacts are documented in the PR where relevant.
- [ ] Automated/manual validation appropriate to the task is completed.
- [ ] No secrets or sensitive configuration are committed.
- [ ] Related documentation is updated if behavior or architecture changes.
- [ ] PR/commit references `{task['id']}`.

### Status source
GitHub is authoritative:
- Open + unassigned = To Do
- Open + assigned = In Progress
- Closed = Done

> Generated from the approved SafeContracts execution plan. Do not reuse this ID for a different scope.
"""


def main():
    ensure_label("safecontracts-task", "173F67", "SafeContracts production implementation task")
    for phase_code, phase in PHASES.items():
        ensure_label(f"phase:{phase_code}", "2F6FB2", f"SafeContracts {phase_code} — {phase['name']}")

    existing = existing_task_ids()
    created = 0
    for task in iter_tasks():
        if task["id"] in existing:
            continue
        request("POST", f"/repos/{REPO}/issues", {
            "title": task["title"],
            "body": task_body(task),
            "labels": ["safecontracts-task", f"phase:{task['phase']}"],
        })
        created += 1
        # Gentle pacing helps avoid secondary-rate-limit bursts on a fresh repository.
        if created % 20 == 0:
            time.sleep(2)

    print(f"SafeContracts plan total={planned_total()} existing={len(existing)} created={created}")


if __name__ == "__main__":
    main()
