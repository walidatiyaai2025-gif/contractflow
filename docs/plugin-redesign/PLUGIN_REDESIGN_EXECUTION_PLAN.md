# ALKENZY ADV — PLUGIN REDESIGN EXECUTION PLAN

**Status:** FROZEN GOVERNANCE BASELINE — IMPLEMENTATION MUST NOT START UNTIL THE P0 FOUNDATION IS ON `main`.

## 1. Mission and non-negotiable gate

This plan governs the Premium redesign of the **SafeContracts / Alkenzy ADV WordPress Admin plugin**. The visual references define appearance; the current WordPress/SafeContracts runtime defines data, permissions, routes, persistence, financial truth and actions.

Worker #1, #2 and #3 are **not released** until all of the following are present together on `main` and the validator passes:

1. the locked reference binaries under `assets/design/plugin-redesign/reference/`;
2. `REFERENCE_MANIFEST.json` with verified SHA-256 values;
3. `PLUGIN_UI_CONSTITUTION.md`;
4. `PLUGIN_UI_SCREEN_MATRIX.md`;
5. `PLUGIN_UI_PROGRESS.md`;
6. this execution plan;
7. the mandatory `AGENTS.md` visual-governance rule;
8. `scripts/validate-plugin-design-references.py`;
9. zero unassigned screens;
10. zero overlapping ownership.

A branch containing only part of this foundation is not an implementation base.

## 2. Source census and scope boundary

The screen inventory was reconciled from the current SafeContracts Admin boot and navigation source on `main` baseline `7aadd79f23726ed1b4df8b078986a151539820a3`, including `Plugin.php`, `AdminShell.php`, `AdminNavigationGroups.php`, registered `*Page` classes and the conditional migration-recovery path.

The frozen matrix contains **34 logical admin screens/states**. A route owner automatically owns all user-visible modes inside that route: list/create/edit/detail, tabs, modals, filters, pagination, bulk actions, confirmations, empty/loading/error states and responsive variants. This prevents artificial splitting of one PHP route across workers.

The existing `EmailSettingsPage` class is included as a latent UI screen even though the audited `Plugin.php` boot does not currently register it. This eliminates a future unowned page; any boot/navigation registration change remains LEAD-only.

Public marketing/store policy pages and the Flutter app are outside this plugin-redesign execution plan.

## 3. Exactly-one-owner allocation

| Owner | Count | Exclusive screen scope |
|---|---:|---|
| **LEAD** | 12 | Dashboard; 8 grouped-navigation landing states; General Settings; Runtime Inspector; Migration Recovery |
| **WORKER-1** | 4 | Customers; Suppliers; Contracts; Archive |
| **WORKER-2** | 7 | Payments; Collections/Settlements; Follow-ups; Finance; Reports; Imports; Payment Methods |
| **WORKER-3** | 11 | Notification Center; Delivery Activity; Notification Schedule; Notification Settings; Email Settings; Active Users; Users & Roles; Firebase Settings; Mobile Configuration; Translations; User Guide |

**Total = 34. Unassigned = 0. Overlap = 0.** `PLUGIN_UI_SCREEN_MATRIX.md` is the row-level authority.

No worker may take a screen because another worker is blocked or idle. Ownership changes require a Lead-authored matrix update merged before the receiving worker edits that screen.

## 4. Locked references and visual authority

The locked reference files are immutable after they land. Their exact bytes are guarded by SHA-256 in `REFERENCE_MANIFEST.json`. The project owner's 2026-08-25 Dashboard request added REF_008 without altering historical REF_003 and returned SC-001 to re-review.

Every matrix row has one controlling Reference ID. The reference controls layout hierarchy, density, spacing, palette, card language, forms, tables, charts, statuses and navigation language. Real repository/backend values and permissions always override mock values shown in artwork.

## 5. Shared-file ownership — LEAD ONLY

The following are **protected shared surfaces**. Workers MUST NOT edit them in their implementation PRs without an explicit Lead handoff.

### Shared PHP / boot / navigation

- `wordpress-plugin/safecontracts/src/Plugin.php`
- `wordpress-plugin/safecontracts/src/Admin/AdminShell.php`
- `wordpress-plugin/safecontracts/src/Admin/AdminNavigationGroups.php`
- `wordpress-plugin/safecontracts/src/Admin/NavigationCleanup.php`
- `wordpress-plugin/safecontracts/src/Admin/AdminPageSummaryInjector.php`
- `wordpress-plugin/safecontracts/src/Admin/AdminSummaryCards.php`
- `wordpress-plugin/safecontracts/src/Admin/AdminPeriodFilter.php`
- `wordpress-plugin/safecontracts/src/Admin/AdminLookupOptions.php`
- `wordpress-plugin/safecontracts/src/Support/Brand.php`

### Existing shared assets

All pre-existing shared admin CSS/JS is LEAD-owned, including:

- `wordpress-plugin/safecontracts/assets/admin/safecontracts-admin.css`
- `wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-core.css`
- `wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-ops.css`
- `wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-settings.css`
- `wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-responsive.css`
- `wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-v2.css`
- `wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-financial-v3.css`
- `wordpress-plugin/safecontracts/assets/admin/contract-payment-tree.css`
- `wordpress-plugin/safecontracts/assets/admin/safecontracts-admin-premium.css`
- `wordpress-plugin/safecontracts/assets/admin/*.js`

### Reserved redesign shared layer

The LEAD owns any future common design-token/primitives files, including:

- `wordpress-plugin/safecontracts/assets/admin/plugin-redesign/tokens.css`
- `wordpress-plugin/safecontracts/assets/admin/plugin-redesign/primitives.css`
- `wordpress-plugin/safecontracts/assets/admin/plugin-redesign/navigation.css`
- `wordpress-plugin/safecontracts/assets/admin/plugin-redesign/shared.js`

### Governance

- `assets/design/plugin-redesign/reference/**`
- `docs/plugin-redesign/**`
- `scripts/validate-plugin-design-references.py`

Workers may propose matrix/progress row updates in their PRs, but the Lead is the final editor/merger of governance files.

## 6. Worker asset isolation

A worker may edit the PHP class(es) that render its owned screens and may add new **worker-scoped** assets only under:

- WORKER-1: `wordpress-plugin/safecontracts/assets/admin/plugin-redesign/worker-1/**`
- WORKER-2: `wordpress-plugin/safecontracts/assets/admin/plugin-redesign/worker-2/**`
- WORKER-3: `wordpress-plugin/safecontracts/assets/admin/plugin-redesign/worker-3/**`

If an owned page needs a shared token, navigation change, global enqueue, common primitive or global CSS/JS modification, the worker records the smallest required shared change in its PR and stops that portion. The LEAD implements/reconciles the shared change. No worker edits a protected shared file “just to unblock itself.”

## 7. Branch contract

After the foundation is merged, every redesign branch MUST be created from the **same exact governance `main` SHA** returned by the merge:

- LEAD: `plugin-redesign/lead-integration`
- WORKER-1: `plugin-redesign/worker-1-parties-contracts`
- WORKER-2: `plugin-redesign/worker-2-finance-operations`
- WORKER-3: `plugin-redesign/worker-3-notifications-access-settings`

Each PR body must state `FOUNDATION_SHA=<exact sha>`. A worker branch created from an older commit is invalid and must be recreated/rebased before implementation.

## 8. PR rules

1. No direct worker commits to `main`.
2. Workers never self-merge.
3. One worker PR may contain only that worker's matrix rows plus worker-scoped assets/tests/evidence.
4. Protected shared files in a worker diff are an automatic review failure unless the Lead explicitly authored/approved that exact shared change.
5. No business behavior may be rewritten merely to match mock data.
6. No fake values, fake success, demo rows or hard-coded financial figures.
7. Existing WordPress permissions, nonces, `admin-post.php`, `admin.php?page=`, admin notices, REST/service/persistence authority and accessibility behavior remain intact.
8. Every PR updates its assigned matrix rows and screenshot evidence status.
9. Every PR runs the repository Quality Gates plus the plugin-design validator.
10. A worker reports a dependency instead of editing another owner's page.

## 9. Controlled merge order

Parallel implementation is allowed only after the foundation SHA exists. Integration is serialized:

1. **WORKER-1** — parties/contracts/archive;
2. **WORKER-2** — finance/operations;
3. **WORKER-3** — notifications/access/settings/help;
4. **LEAD final integration** — shared primitives/navigation/dashboard/system screens and cross-screen consistency;
5. **LEAD release acceptance** — full matrix re-run, validator, screenshots, RTL/responsive sweep, exact-source Quality Gates.

Before each merge, the branch must be reconciled with latest `main` without taking another worker's unmerged scope. If a shared dependency must land earlier, it is a separate LEAD-owned PR and the affected worker rebases after it lands.

## 10. Screenshot evidence contract

Real implementation screenshots live under `docs/plugin-redesign/screenshots/<screen-id>/`. For every matrix screen before `READY FOR LEAD`, capture from the **real WordPress runtime**, not a static HTML mock:

- Arabic RTL desktop: target viewport approximately 1440×1000 or larger;
- Arabic RTL narrow/mobile-admin: target width 390px (acceptable evidence range 375–430px);
- English LTR desktop smoke screenshot;
- additional 768px tablet evidence when a breakpoint materially changes layout.

Each screen folder must include `VISUAL_QA.md` naming the controlling Reference ID, runtime URL/slug, viewport, language/direction, known differences and disposition. Do not duplicate or alter the locked reference binary; link to its canonical path.

No screen receives visual approval from code inspection alone.

## 11. RTL and responsive requirements

Arabic RTL is the primary acceptance direction. Required checks include logical spacing, icon direction, table flow, pagination arrows, field alignment, number/currency readability, chart labels, modal alignment and WordPress Admin compatibility. English/LTR must remain usable and semantically correct.

At minimum test widths: 390px narrow, 768px tablet and 1440px desktop. No horizontal page overflow is allowed except deliberate data-table scroll regions. No control may become unreachable or clipped.

## 12. Functional acceptance requirements

Visual work cannot weaken runtime truth. For each owned screen verify, where applicable:

- authorization/capabilities and tenant/data scope;
- nonce and CSRF behavior;
- create/edit/delete/archive/bulk actions;
- filters, pagination and search;
- real service/repository persistence;
- errors/notices and failure paths;
- empty states;
- file/attachment behavior;
- AP/AR direction, currency separation and backend-authoritative finance values;
- notification state/actions;
- accessibility labels and keyboard behavior.

## 13. Definition of Done — per screen

A screen is DONE only when all are true:

- matrix owner and Reference ID are present;
- visual hierarchy matches the locked direction with no unresolved major mismatch;
- real data/business actions are preserved;
- permissions/nonces/persistence remain authoritative;
- Arabic RTL passes;
- English LTR smoke passes;
- 390/768/1440 responsive checks pass;
- loading/empty/error/action states relevant to the route pass;
- required screenshot set and `VISUAL_QA.md` exist;
- PHP/static tests and repository Quality Gates are green;
- `python3 scripts/validate-plugin-design-references.py` passes;
- no protected shared file was changed outside the Lead process;
- Lead marks the row `APPROVED`.

## 14. Definition of Done — whole redesign

The redesign is complete only when all 34 matrix rows are `APPROVED`, all references still hash correctly, no ownership ambiguity exists, full RTL/responsive QA is green, real WordPress runtime evidence exists, and final exact-source repository Quality Gates pass.

## 15. Continuity / handoff

Every stopping point must update `PLUGIN_UI_PROGRESS.md` with last completed screen, current screen, changed files, tests, screenshots, known mismatches/blockers and next exact task. Chat history is never an authoritative project checkpoint.
