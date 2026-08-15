#!/usr/bin/env python3
"""Regenerate docs/PROJECT_STATUS.md from SafeContracts GitHub Issues."""

from __future__ import annotations

import json
import os
import re
import sys
import urllib.error
import urllib.request
from datetime import datetime, timezone
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from plan_config import PHASES, iter_tasks, planned_total  # noqa: E402

TOKEN = os.environ.get("GITHUB_TOKEN")
REPO = os.environ.get("GITHUB_REPOSITORY")
API = os.environ.get("GITHUB_API_URL", "https://api.github.com")
ROOT = Path(__file__).resolve().parents[1]
STATUS_FILE = ROOT / "docs" / "PROJECT_STATUS.md"
START = "<!-- SAFECONTRACTS_STATUS_START -->"
END = "<!-- SAFECONTRACTS_STATUS_END -->"

if not TOKEN or not REPO:
    raise SystemExit("GITHUB_TOKEN and GITHUB_REPOSITORY are required")


def get(path: str):
    req = urllib.request.Request(
        f"{API}{path}",
        headers={
            "Accept": "application/vnd.github+json",
            "Authorization": f"Bearer {TOKEN}",
            "X-GitHub-Api-Version": "2022-11-28",
            "User-Agent": "safecontracts-status-sync",
        },
        method="GET",
    )
    try:
        with urllib.request.urlopen(req, timeout=60) as response:
            return json.loads(response.read())
    except urllib.error.HTTPError as exc:
        body = exc.read().decode("utf-8", errors="replace")
        raise RuntimeError(f"GitHub API GET {path} failed: {exc.code} {body}") from exc


def load_issues():
    issues = []
    page = 1
    while True:
        batch = get(f"/repos/{REPO}/issues?state=all&labels=safecontracts-task&per_page=100&page={page}")
        if not batch:
            break
        issues.extend(item for item in batch if "pull_request" not in item)
        if len(batch) < 100:
            break
        page += 1
    return issues


def main():
    planned = {task["id"]: task for task in iter_tasks()}
    by_id = {}
    pattern = re.compile(r"^(SC-P\d+-\d{3})\b")
    for issue in load_issues():
        match = pattern.match(issue.get("title", ""))
        if match and match.group(1) in planned:
            by_id[match.group(1)] = issue

    rows = []
    total_todo = total_progress = total_done = 0

    for phase_code, phase in PHASES.items():
        counts = {"todo": 0, "progress": 0, "done": 0}
        phase_issues = []
        for task_id, task in planned.items():
            if task["phase"] != phase_code:
                continue
            issue = by_id.get(task_id)
            if issue is None:
                counts["todo"] += 1
                continue
            phase_issues.append(issue)
            if issue.get("state") == "closed":
                counts["done"] += 1
            elif issue.get("assignees"):
                counts["progress"] += 1
            else:
                counts["todo"] += 1

        total_todo += counts["todo"]
        total_progress += counts["progress"]
        total_done += counts["done"]
        completion = (counts["done"] / phase["count"] * 100) if phase["count"] else 0
        issue_link = f"https://github.com/{REPO}/issues?q=is%3Aissue+label%3Aphase%3A{phase_code}"
        rows.append(
            f"| {phase_code} | [{phase['name']}]({issue_link}) | {phase['count']} | "
            f"{counts['todo']} | {counts['progress']} | {counts['done']} | {completion:.1f}% |"
        )

    overall = total_done / planned_total() * 100
    timestamp = datetime.now(timezone.utc).strftime("%Y-%m-%d %H:%M UTC")
    table = "\n".join([
        START,
        "",
        "| Phase | Plan item | Planned | To Do | In Progress | Done | Completion |",
        "|---|---|---:|---:|---:|---:|---:|",
        *rows,
        f"| **TOTAL** |  | **{planned_total()}** | **{total_todo}** | **{total_progress}** | **{total_done}** | **{overall:.1f}%** |",
        "",
        f"_Last automatic sync: {timestamp}. GitHub Issues found: {len(by_id)}/{planned_total()}._",
        "",
        END,
    ])

    current = STATUS_FILE.read_text(encoding="utf-8")
    if START not in current or END not in current:
        raise SystemExit("Status markers not found")
    prefix, remainder = current.split(START, 1)
    _, suffix = remainder.split(END, 1)
    STATUS_FILE.write_text(prefix + table + suffix, encoding="utf-8")
    print(f"Updated {STATUS_FILE} — done={total_done} in_progress={total_progress} todo={total_todo}")


if __name__ == "__main__":
    main()
