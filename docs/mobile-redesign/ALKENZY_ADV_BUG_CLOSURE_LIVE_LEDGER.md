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

- `[TODO]`: **B001-B101**
- `[IN-PROGRESS]`: none proven by a pushed Worker commit yet
- `[BLOCKED]`: none
- `[READY-FOR-QA]`: none
- `[QA-FAILED]`: none
- `[QA-PASS]`: none
- `[CLOSED]`: none

## Frozen production ownership

- Worker #1: `B002, B003, B005-B010, B013, B025-B026`
- Worker #2: `B012, B014-B024, B027-B030, B071-B080`
- Worker #3: `B031-B054`
- Worker #4: `B001, B004, B011, B055-B070`
- Worker #5: `B081-B101`
- QA: acceptance only for `B001-B101`; no production ownership

## P0 fast-track

1. `B001` — Worker #4 — Payment Status
2. `B002` — Worker #1 — Customer related-data loading
3. `B003` — Worker #1 — Sidebar/Drawer
4. `B036` — Worker #3 — Logout
5. `B084` — Worker #5 — API Pagination

A P0 slice must be reviewed and handed to QA as soon as it reaches `[READY-FOR-QA]`; it must not wait for the rest of its Worker scope.

## Shared-file ownership

- Worker #1 exclusively owns app-shell navigation chrome, Drawer/Sidebar, Bottom Navigation and centered FAB.
- Worker #4 exclusively owns global typography tokens and global AppBar typography primitives.
- Worker #2 owns Dashboard BODY only and must not rewrite shell navigation files.
- Worker #5 owns Pagination/List Toolbar/Contracts list state.
- Any other shared-file change requires Lead mediation and surgical conflict reconciliation.

## Lead integration note

After the frozen base, the integration branch contains a Lead-level CI compatibility fix for Firebase Messaging's new `AuthorizationStatus.deniedPermanently` enum value. It maps that state to the existing denied mobile permission state. This is a CI/dependency compatibility correction and closes no Bug ID.

## Promotion rule

Only QA may promote `[READY-FOR-QA]` to `[QA-PASS]`. The Lead may mark `[CLOSED]` only after that QA PASS exists on integrated code and all applicable automated/runtime/reference evidence is complete.
