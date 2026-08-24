# ALKENZY ADV — 101 Bug Closure Live Ledger

```text
[ALKENZY-ADV-BUG-CLOSURE-LOCK]
REGISTER: docs/mobile-redesign/ALKENZY_ADV_BUG_UX_REGISTER_2026-08-24.md
TOTAL: 101
MODE: BUGFIX / ACCEPTANCE CLOSURE ONLY
P0-FIRST: YES
NO-NEW-FEATURES: YES
NO-SCOPE-OVERLAP: YES
NO-BUSINESS-RULE-REWRITE: YES
SERVER-AUTHORITATIVE: YES
NO-FAKE-DATA: YES
NO-VISUAL-ONLY-CLOSURE: YES
```

Canonical register: `docs/mobile-redesign/ALKENZY_ADV_BUG_UX_REGISTER_2026-08-24.md`

Frozen implementation base: `1984f91b95387934d300a0df4336f6d10a1dbfce`

Integration branch: `feat/alkenzy-mobile-reference-redesign`

## Counters

- TOTAL CLOSED: **0 / 101**
- P0 CLOSED: **0 / 5**
- P1 CLOSED: **0 / 71**
- P2 CLOSED: **0 / 25**
- READY-FOR-QA: **0**
- QA-FAILED: **0**
- BLOCKED: **0**

No item is `[CLOSED]` unless QA has passed it on the integrated exact head. Existing redesign implementation is not retroactively counted as closure evidence.

## Exact live status sets

- `[TODO]`: `B001, B002-B011, B013, B025-B026, B055-B070` (**30**)
- `[IN-PROGRESS]`: `B012, B014-B024, B027-B054, B071-B101` (**71**)
- `[BLOCKED]`: none
- `[READY-FOR-QA]`: none
- `[QA-FAILED]`: none
- `[QA-PASS]`: none
- `[CLOSED]`: none

`[IN-PROGRESS]` means an owned Worker lane has pushed production implementation under review; it does not imply acceptance. Worker #1 currently has workflow-only commits and is therefore not counted as production progress. Worker #4 has no pushed implementation yet.

## Frozen production ownership

- Worker #1: `B002, B003, B005-B010, B013, B025-B026`
- Worker #2: `B012, B014-B024, B027-B030, B071-B080`
- Worker #3: `B031-B054`
- Worker #4: `B001, B004, B011, B055-B070`
- Worker #5: `B081-B101`
- QA: acceptance only for `B001-B101`; no production ownership

## Active Worker lanes

- Worker #2 — PR #646 — Dashboard BODY scope — `[IN-PROGRESS]`, queued behind P0 Wave A.
- Worker #3 — PR #644 — exact head under CI/review — includes P0 `B036` logout.
- Worker #5 — PR #645 — exact head under CI/review — includes P0 `B084` server-authoritative pagination.
- Worker #1 — branch exists but current pushed diff is workflow-only; `B002/B003` are not yet production-fixed.
- Worker #4 — branch exists at frozen base with no production commits; `B001` has not started on GitHub evidence.

## P0 fast-track

1. `B001` — Worker #4 — Payment Status — `[TODO]`
2. `B002` — Worker #1 — Customer related-data loading — `[TODO]`
3. `B003` — Worker #1 — Sidebar/Drawer — `[TODO]`
4. `B036` — Worker #3 — Logout — `[IN-PROGRESS]`; targeted tests added, exact-head CI required before QA handoff.
5. `B084` — Worker #5 — API Pagination — `[IN-PROGRESS]`; backend + Flutter consumer implementation added, focused Flutter acceptance tests and exact-head CI required before QA handoff.

A P0 slice must be reviewed and handed to QA as soon as it reaches `[READY-FOR-QA]`; it must not wait for the rest of its Worker scope.

## Shared-file ownership

- Worker #1 exclusively owns app-shell navigation chrome, Drawer/Sidebar, Bottom Navigation and centered FAB.
- Worker #4 exclusively owns global typography tokens and global AppBar typography primitives.
- Worker #2 owns Dashboard BODY only and must not rewrite shell navigation files.
- Worker #5 owns Pagination/List Toolbar/Contracts list state.
- Any other shared-file change requires Lead mediation and surgical conflict reconciliation.

## Lead integration note

After the frozen base, the integration branch contains a Lead-level CI compatibility fix for Firebase Messaging's new `AuthorizationStatus.deniedPermanently` enum value. It maps that state to the existing denied mobile permission state. A subsequent format-only correction applied canonical Dart formatting. These are CI/dependency compatibility corrections and close no Bug ID.

## Promotion rule

Only QA may promote `[READY-FOR-QA]` to `[QA-PASS]`. The Lead may mark `[CLOSED]` only after that QA PASS exists on integrated code and all applicable automated/runtime/reference evidence is complete.
