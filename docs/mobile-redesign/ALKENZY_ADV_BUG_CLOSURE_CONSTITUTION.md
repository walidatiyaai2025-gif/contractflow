# ALKENZY ADV — Bug Closure Constitution

This document is mandatory for the 101-item ALKENZY ADV mobile bug/UX closure pass.

## Source of truth

Every Lead, Worker and QA agent MUST read these files before changing code:

1. `AGENTS.md`
2. `docs/mobile-redesign/ALKENZY_ADV_BUG_CLOSURE_CONSTITUTION.md`
3. `docs/mobile-redesign/ALKENZY_ADV_BUG_UX_REGISTER_2026-08-24.md`
4. `docs/mobile-redesign/MOBILE_UI_REFERENCE.md`
5. `docs/mobile-redesign/MOBILE_UI_SCREEN_MATRIX.md`
6. `docs/mobile-redesign/MOBILE_UI_PROGRESS.md`

The canonical operational bug register contains **101 items: P0=5, P1=71, P2=25**. No agent may invent, drop, renumber, silently merge, or close an item outside the register.

## Universal execution flag

Every PR description, handoff and agent checkpoint MUST carry this exact block:

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

A BUG MAY BE MARKED CLOSED ONLY AFTER:
REPRODUCED
-> ROOT CAUSE KNOWN
-> REAL UI/API/DATA PATH VERIFIED
-> FIXED
-> AR RTL VERIFIED
-> EN LTR VERIFIED
-> RESPONSIVE VERIFIED
-> LOADING/EMPTY/ERROR/RETRY VERIFIED
-> FLUTTER ANALYZE GREEN
-> FLUTTER TEST GREEN
-> SCREENSHOT EVIDENCE CAPTURED
-> QA PASS

STATUS FLAGS:
[TODO]
[IN-PROGRESS]
[BLOCKED]
[READY-FOR-QA]
[QA-FAILED]
[QA-PASS]
[CLOSED]

NEVER USE [CLOSED] WITHOUT QA-PASS.
```

## Frozen ownership map

No bug may have two production owners at the same time.

### Worker #1 — Customers / Navigation / Landing

Owns exactly:

`B002, B003, B005, B006, B007, B008, B009, B010, B013`

Primary production areas:

- customers
- capability-driven Drawer/Sidebar navigation
- landing PageView/indicator
- local RTL phone rendering/localization fixes within scope

Must not take Dashboard, Profile/Auth, Payments, Contracts/Pagination ownership.

### Worker #2 — Dashboard

Owns exactly:

`B012, B014-B030, B071-B080`

Primary production areas:

- Dashboard compact layout
- Summary/KPI/filter hierarchy
- Dashboard Tabs and state
- dashboard-specific responsive behavior

Must not take App Shell/Drawer, Profile, Payments business semantics, Contracts/Pagination ownership.

### Worker #3 — Profile / Authentication presentation/runtime

Owns exactly:

`B031-B054`

Primary production areas:

- Premium Profile
- real logout path
- profile language toggle/runtime direction
- profile responsive/no-scroll acceptance

Must not take Dashboard, Contracts/Pagination or payment business semantics.

### Worker #4 — Payments / Payment Details / Global Typography

Owns exactly:

`B001, B004, B011, B055-B070`

Primary production areas:

- payment status correctness
- payment-detail presentation
- global typography/AppBar primitives
- shared typography tokens

Worker #4 exclusively owns shared typography token changes during this pass. Other Workers consume the tokens and may not independently rewrite them.

### Worker #5 — Lists / Pagination / Contracts

Owns exactly:

`B081-B101`

Primary production areas:

- reusable pagination
- reusable list toolbar
- Contracts filters/state/tabs
- real backend pagination wiring

Must not take Dashboard or global typography ownership.

### QA Agent — Acceptance only

Owns no production feature scope.

QA validates `B001-B101`, maintains the acceptance ledger, returns failures to the original owner, and is the only role allowed to promote `[READY-FOR-QA]` to `[QA-PASS]`.

### Lead — Integration / final closure

Lead owns integration, conflict arbitration, CI, release evidence, final matrix/progress, and the final APK. Lead does not randomly absorb Worker implementation scope unless an owner is explicitly blocked and ownership is reassigned in the ledger first.

## P0 order

P0 bugs are the first merge/QA priority:

1. `B001` payment status correctness.
2. `B002` customer related-data loading.
3. `B003` real Sidebar/Drawer navigation.
4. `B036` real logout/session cleanup.
5. `B084` real API-backed pagination.

A Worker may continue independent P1/P2 work while another P0 is in QA, but the Lead must review and integrate a ready P0 slice immediately rather than wait for the Worker’s full scope.

## Server-authoritative safety rules

- WordPress + SafeContracts remain the business source of truth.
- Flutter must not create a competing financial truth.
- Do not fabricate payment status, totals, permissions, presence, availability, cleanup success, collection success or API results.
- Do not weaken permission/capability gates to make UI tests pass.
- Do not change financial semantics without proving the existing authoritative backend contract requires it.
- Do not hide a backend error with an empty state.
- Do not turn an error into fake success.

## Shared-file conflict rules

- Never resolve conflicts by wholesale replacement of a shared file.
- Preserve already-integrated Dashboard Two, premium contract routing, Worker #1 foundation/navigation, Worker #2 customers/suppliers/contracts, Worker #3 payments/finance/operations, and server-authoritative behavior.
- Shared primitives must remain backwards compatible unless the Lead explicitly coordinates all consumers.
- Every conflict resolution must be surgical and traceable to bug IDs.

## Branch / PR naming

Recommended branches:

- `bugfix/alkenzy-register-w1-customer-navigation`
- `bugfix/alkenzy-register-w2-dashboard`
- `bugfix/alkenzy-register-w3-profile-auth`
- `bugfix/alkenzy-register-w4-payments-typography`
- `bugfix/alkenzy-register-w5-contracts-lists`
- `qa/alkenzy-register-acceptance`

Every PR title must contain:

`[ALKENZY-BUGFIX][W#][Bxxx-Bxxx]`

Each PR body must list exact bug IDs and their current status.

## Mandatory Worker checkpoint format

Every Worker reports:

```text
OWNER:
CURRENT HEAD:
BUGS CLOSED:
READY FOR QA:
IN PROGRESS:
BLOCKED:
TEST STATUS:
SCREENSHOT STATUS:
NEXT EXACT BUG:
```

## Mandatory QA failure format

```text
BUG ID:
REPRODUCTION:
EXPECTED:
ACTUAL:
ROOT/LIKELY LAYER:
EXACT ROUTE/FILE:
EVIDENCE:
RETURN TO OWNER:
```

## Definition of Done

No item may be `[CLOSED]` unless all applicable checks below pass:

- reproduction captured before the fix;
- root cause known;
- real UI → controller/service → API → backend/data path verified for functional items;
- Arabic RTL;
- English LTR;
- supported widths `320 / 360 / 375 / 390 / 412 / 430`;
- no horizontal overflow or important clipping;
- Loading / Empty / Error / Retry states correct;
- page/filter/tab state preserved where required;
- no duplicate/double requests introduced;
- `dart format lib test` clean;
- `flutter analyze` GREEN;
- `flutter test` GREEN;
- relevant screenshot/video acceptance evidence captured;
- QA PASS on the integrated exact head.

## Final release gate

The redesign/bug closure may be declared complete only when:

- `CLOSED = 101 / 101`;
- `P0 remaining = 0`;
- `P1 remaining = 0`;
- `P2 remaining = 0`;
- `QA-FAILED = 0`;
- exact-head Quality Gates GREEN;
- exact-head Mobile Reference Capture GREEN;
- final screenshot/reference evidence complete;
- `MOBILE_UI_SCREEN_MATRIX.md` and `MOBILE_UI_PROGRESS.md` match reality;
- release-candidate Android build succeeds from that exact head.

Do not call an APK production-verified unless the repository’s separate signing/UAT requirements in `AGENTS.md` are also satisfied.
