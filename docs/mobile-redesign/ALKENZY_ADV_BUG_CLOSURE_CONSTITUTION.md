# ALKENZY ADV — Bug Closure Constitution

This document is mandatory for the 101-item ALKENZY ADV mobile bug/UX closure pass and remains binding for all later Alkenzy ADV mobile release work where applicable.

## Source of truth

Every Lead, Worker and QA agent MUST read these files before changing code:

1. `AGENTS.md`
2. `docs/mobile-redesign/ALKENZY_ADV_RELEASE_BASELINE.md`
3. `docs/mobile-redesign/ALKENZY_ADV_BUG_CLOSURE_CONSTITUTION.md`
4. `docs/mobile-redesign/ALKENZY_ADV_BUG_UX_REGISTER_2026-08-24.md`
5. `docs/mobile-redesign/MOBILE_UI_REFERENCE.md`
6. `docs/mobile-redesign/MOBILE_UI_SCREEN_MATRIX.md`
7. `docs/mobile-redesign/MOBILE_UI_PROGRESS.md`

The canonical operational bug register contains **101 items: P0=5, P1=71, P2=25**. No agent may invent, drop, renumber, silently merge, or close an item outside the register.

## Permanent release lineage lock — superseded baseline effective 2026-08-25

The project owner has approved **Alkenzy ADV `0.3.6+10`** (plugin `0.3.6`) as the current release baseline. It supersedes `0.3.5+9` without deleting that release from the ancestry history.

Locked identity:

- approved release: `0.3.6+10`;
- approved plugin version: `0.3.6`;
- immutable baseline branch: `release/alkenzy-adv-mobile-0.3.6`;
- exact approved functional source commit: `9171f1c357822f9118eb8058aab6fb145c475fc3`;
- previous approved release: `0.3.5+9` at `458e3580d07eb182224c3652bb18d3c82b87adbd`;
- original owner-approved lineage ancestor: `3d4dcd2205b5cfa7d0814e5635db577ee5dcefed`;
- approval and merge vehicle: PR `#652`;
- authoritative baseline record: `docs/mobile-redesign/ALKENZY_ADV_RELEASE_BASELINE.md`.

From this point forward:

1. Every new Alkenzy ADV modification MUST start from `release/alkenzy-adv-mobile-0.3.6` or a commit proven to be its descendant.
2. Starting from an older commit, stale PR head, abandoned worker branch, historical APK branch, or any pre-`0.3.6+10` snapshot is forbidden.
3. No future worker may wholesale-copy an older mobile file over the approved baseline. Ports must be surgical and must preserve all accepted behavior unless the project owner explicitly requests removal.
4. `0.3.6+10` is consumed forever. It may not be reused for another release.
5. The next user-facing release must be **at least `0.3.7+11`** unless the project owner explicitly chooses a higher semantic version. Plugin version, versionName and build number must advance together.
6. The version bump must be committed before final APK build. APK filename, CI artifact metadata, checksum, release notes and handoff message must agree on that version.
7. Before release handoff, the Lead must record that the locked release baseline is an ancestor of the new functional release lineage.
8. A later release becomes the new baseline only after explicit project-owner approval and an update to `ALKENZY_ADV_RELEASE_BASELINE.md`; lineage must move forward and may never reset to an older base.

Mandatory block for every post-`0.3.6+10` PR and handoff:

```text
[ALKENZY-ADV-RELEASE-LINEAGE-LOCK]
PREVIOUS-APPROVED-RELEASE: 0.3.6+10
BASELINE-BRANCH: release/alkenzy-adv-mobile-0.3.6
BASELINE-COMMIT: 9171f1c357822f9118eb8058aab6fb145c475fc3
NEW-VERSION: <must be at least 0.3.7+11>
BASELINE-ANCESTOR-VERIFIED: YES
NO-STALE-BRANCH-REPLACEMENT: YES
```

This release-lineage section supersedes any older base/SHA instruction in this document whenever they conflict. Its purpose is to guarantee that accepted changes cannot disappear in a later APK because work restarted from an obsolete snapshot.

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

### Worker #1 — Customers / Navigation / Landing / Shell chrome

Owns exactly:

`B002, B003, B005, B006, B007, B008, B009, B010, B013, B025, B026`

Primary production areas:

- customers
- capability-driven Drawer/Sidebar navigation
- landing PageView/indicator
- local RTL phone rendering/localization fixes within scope
- Bottom Navigation labels/spacing and centered FAB shell chrome

Worker #1 exclusively owns `app_shell.dart` / Bottom Navigation / FAB / Drawer visual-navigation changes during this pass. Other Workers must not rewrite those shared shell files; they may request a small Lead-mediated adaptation if absolutely required.

Must not take Dashboard body, Profile/Auth, Payments, Contracts/Pagination ownership.

### Worker #2 — Dashboard body / data tabs

Owns exactly:

`B012, B014-B024, B027-B030, B071-B080`

Primary production areas:

- Dashboard compact layout
- Summary/KPI/filter hierarchy
- Dashboard Tabs and state
- dashboard-specific responsive behavior

Must not modify App Shell/Drawer/Bottom Navigation/FAB, Profile, Payments business semantics, Contracts/Pagination ownership.

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
- Preserve already-integrated Dashboard Two, premium contract routing, Worker #1 foundation/navigation, Worker #2 customers/suppliers/contracts, Worker #3 payments/finance/operations, and server-authoritative behavior unless the locked release baseline explicitly supersedes an older closure-era implementation.
- Shared primitives must remain backwards compatible unless the Lead explicitly coordinates all consumers.
- Every conflict resolution must be surgical and traceable to bug IDs or to an explicitly approved post-release change.

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

For all post-`0.3.6+10` work, the PR body must additionally include the `[ALKENZY-ADV-RELEASE-LINEAGE-LOCK]` block defined above and the new unified product version.

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

For later releases, release acceptance additionally requires compliance with the permanent release-lineage lock: ancestor verification, a new version/build number, and preservation of the previous approved release behavior unless an explicit owner-approved change supersedes it.

Do not call an APK production-verified unless the repository’s separate signing/UAT requirements in `AGENTS.md` are also satisfied.
