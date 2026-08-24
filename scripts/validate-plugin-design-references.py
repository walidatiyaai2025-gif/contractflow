#!/usr/bin/env python3
"""Validate ALKENZY ADV locked plugin-design references and ownership governance."""
from __future__ import annotations

import hashlib
import json
import re
import sys
from collections import Counter
from pathlib import Path

ALLOWED_OWNERS = {"LEAD", "WORKER-1", "WORKER-2", "WORKER-3"}
ROW_RE = re.compile(r"^\|\s*(SC-\d{3})\s*\|(.+?)\|(.+?)\|(.+?)\|(.+?)\|\s*\*\*(LEAD|WORKER-1|WORKER-2|WORKER-3)\*\*\s*\|\s*(REF_\d{3})\s*\|", re.MULTILINE)


def sha256(path: Path) -> str:
    h = hashlib.sha256()
    with path.open("rb") as f:
        for chunk in iter(lambda: f.read(1024 * 1024), b""):
            h.update(chunk)
    return h.hexdigest()


def main() -> int:
    root = Path(sys.argv[1]).resolve() if len(sys.argv) > 1 else Path(__file__).resolve().parents[1]
    ref_dir = root / "assets/design/plugin-redesign/reference"
    manifest_path = ref_dir / "REFERENCE_MANIFEST.json"
    matrix_path = root / "docs/plugin-redesign/PLUGIN_UI_SCREEN_MATRIX.md"
    required_docs = [
        root / "docs/plugin-redesign/PLUGIN_UI_CONSTITUTION.md",
        matrix_path,
        root / "docs/plugin-redesign/PLUGIN_UI_PROGRESS.md",
        root / "docs/plugin-redesign/PLUGIN_REDESIGN_EXECUTION_PLAN.md",
    ]
    errors: list[str] = []

    if not manifest_path.is_file():
        errors.append(f"Missing manifest: {manifest_path}")
        manifest = {}
    else:
        try:
            manifest = json.loads(manifest_path.read_text(encoding="utf-8"))
        except Exception as exc:
            errors.append(f"Manifest is invalid JSON: {exc}")
            manifest = {}

    refs = manifest.get("references")
    if not isinstance(refs, list) or not refs:
        errors.append("Manifest 'references' must be a non-empty array.")
        refs = []

    seen_ids: set[str] = set()
    seen_files: set[str] = set()
    for idx, ref in enumerate(refs, start=1):
        if not isinstance(ref, dict):
            errors.append(f"Reference #{idx} is not an object.")
            continue
        rid = str(ref.get("id") or "")
        filename = str(ref.get("file") or "")
        expected = str(ref.get("sha256") or "")
        if not rid:
            errors.append(f"Reference #{idx} has no id.")
        elif rid in seen_ids:
            errors.append(f"Duplicate reference id: {rid}")
        else:
            seen_ids.add(rid)
        if not filename:
            errors.append(f"{rid or '#'+str(idx)} has no file.")
            continue
        if filename in seen_files:
            errors.append(f"Duplicate reference filename: {filename}")
        seen_files.add(filename)
        path = ref_dir / filename
        if ref.get("status") == "LOCKED" or ref.get("approved") is True:
            if not path.is_file():
                errors.append(f"{rid}: locked/approved file missing: {path}")
                continue
            if not re.fullmatch(r"[0-9a-fA-F]{64}", expected):
                errors.append(f"{rid}: invalid/missing SHA-256.")
                continue
            actual = sha256(path)
            if actual.lower() != expected.lower():
                errors.append(f"{rid}: SHA-256 mismatch for {filename}: expected={expected} actual={actual}")

    for doc in required_docs:
        if not doc.is_file():
            errors.append(f"Missing required governance document: {doc}")

    ownership = Counter()
    screen_ids: list[str] = []
    routes: list[str] = []
    if matrix_path.is_file():
        matrix = matrix_path.read_text(encoding="utf-8")
        if re.search(r"\|\s*(?:TBD(?:_BY_LEAD)?|UNASSIGNED|RECONCILE_FROM_REPO)\s*\|", matrix):
            errors.append("Screen matrix contains unresolved ownership/repository placeholders.")
        matches = ROW_RE.findall(matrix)
        if not matches:
            errors.append("No screen ownership rows could be parsed from the matrix.")
        for sid, _screen, route, _callback, _route_status, owner, ref_id in matches:
            screen_ids.append(sid)
            routes.append(route.strip().strip('`'))
            ownership[owner] += 1
            if owner not in ALLOWED_OWNERS:
                errors.append(f"{sid}: invalid owner {owner}")
            if ref_id not in seen_ids:
                errors.append(f"{sid}: unknown reference {ref_id}")
        duplicate_ids = [sid for sid, count in Counter(screen_ids).items() if count > 1]
        if duplicate_ids:
            errors.append(f"Duplicate screen IDs: {', '.join(sorted(duplicate_ids))}")
        duplicate_routes = [route for route, count in Counter(routes).items() if count > 1]
        if duplicate_routes:
            errors.append(f"Overlapping/duplicate screen routes: {', '.join(sorted(duplicate_routes))}")
        if len(screen_ids) != 34:
            errors.append(f"Frozen baseline requires 34 screen rows; found {len(screen_ids)}")
        expected_counts = {"LEAD": 12, "WORKER-1": 4, "WORKER-2": 7, "WORKER-3": 11}
        for owner, expected_count in expected_counts.items():
            if ownership.get(owner, 0) != expected_count:
                errors.append(f"{owner}: expected {expected_count} screens, found {ownership.get(owner, 0)}")

    if errors:
        print("ALKENZY ADV DESIGN REFERENCE VALIDATION: FAIL")
        for error in errors:
            print(f"ERROR: {error}")
        return 1

    counts = ", ".join(f"{owner}={ownership[owner]}" for owner in ["LEAD", "WORKER-1", "WORKER-2", "WORKER-3"])
    print(f"ALKENZY ADV DESIGN REFERENCE VALIDATION: PASS ({len(refs)} references; {len(screen_ids)} screens; {counts}; unassigned=0; overlaps=0)")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
