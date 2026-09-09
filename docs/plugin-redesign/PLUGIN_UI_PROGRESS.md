# ALKENZY ADV — PLUGIN UI REDESIGN PROGRESS

## Governance checkpoint

- Governance version: `1.1.0`
- Official redesign foundation: `main@f671f436d9fd357de1a79089c29ec700d0572e78`
- Current reference manifest version: `1.1.0`
- Reference baseline date: `2026-08-25`
- Locked reference binaries: `8/8`
- Frozen screen inventory: `34`
- Unassigned screens: `0`
- Overlapping ownership: `0`
- Foundation reference validator: `PASS`
- Foundation Plugin Design Reference Guard: `PASS`
- Foundation Quality Gates: `PASS`
- Implementation start gate: `RELEASED`
- Current approved product release: `0.3.6+10`
- Approved plugin version: `0.3.6`
- Approved functional source: `9171f1c357822f9118eb8058aab6fb145c475fc3`
- Forward-only baseline branch: `release/alkenzy-adv-mobile-0.3.6`

All redesign branches must preserve `FOUNDATION_SHA=f671f436d9fd357de1a79089c29ec700d0572e78` as their common ancestry contract. The locked references remain immutable.

## LEAD — ACTIVE IMPLEMENTATION

- Branch: `feat/alkenzy-mobile-landing-media`
- PR: `#652`
- Frozen scope: SC-001 through SC-012 only, plus protected shared PHP/CSS/JS/navigation/governance surfaces.
- Current implementation state: `OWNER-APPROVED RELEASE 0.3.6+10 — PR #652; MERGE REQUIRES EXACT-HEAD ALL GREEN`

### Owner-approved 2026-08-25 Dashboard continuation

- Added immutable REF_008 as the controlling visual reference for SC-001; REF_003 remains preserved.
- Rebuilding the Dashboard around real monthly settlement-ledger flows, current KPI/accounting authority and responsive Arabic RTL composition.
- Surfacing employee-authored mobile payment follow-up notes on the Dashboard and retaining the existing permission-scoped Follow-ups route.
- Adding capability/nonce-protected demo-data controls with an exact-ID registry so deletion cannot touch non-demo rows.

### LEAD implementation completed in code

- Created the shared locked-reference design-token layer under `assets/admin/plugin-redesign/tokens.css`.
- Created shared compact premium primitives under `assets/admin/plugin-redesign/primitives.css`.
- Created shared WordPress navigation styling under `assets/admin/plugin-redesign/navigation.css`.
- Created LEAD screen refinements under `assets/admin/plugin-redesign/lead-screens.css`.
- Removed the oversized legacy SafeContracts hero/banner from `AdminShell` and restored a compact reference-aligned workspace heading.
- Added permission-aware Dashboard quick actions that route only to real registered plugin pages and preserve WordPress capability checks.
- Rebuilt all eight grouped-navigation landing states while retaining hidden leaf submenu rows in WordPress' authorization structure.
- Redesigned General Settings while preserving `admin-post.php`, nonce validation, `MANAGE_SYSTEM` authorization and server-side `GeneralSettings` persistence.
- Redesigned Migration Recovery and explicitly enqueued the shared redesign assets on the migration-failure boot path.
- LEAD-only boot integration now registers the pre-existing `EmailSettingsPage` through its own `register()` method. SC-028 visual ownership remains WORKER-3.
- Runtime Inspector is covered by the shared system-screen/table/detail primitives; its sanitized diagnostics, clear-history nonce and system capability rules remain unchanged.
- Shared responsive layer now constrains settings split layouts with `minmax(0, 1fr)` under the WordPress mobile breakpoint so RTL controls can shrink without horizontal clipping.

### LEAD acceptance checkpoint

- Plugin Design Reference Guard: PASS on the current redesign line.
- Real WordPress Visual QA: PASS on `7223f413a340814cc159c5bcc10b91ce2b8b0506`.
- A subsequent shared responsive specificity hardening commit is under exact-head Quality/Visual revalidation before final integration approval.

No LEAD screen is `APPROVED` until the final integrated exact-head runtime/evidence gates pass.

## WORKER-1 — FROZEN SCOPE

Customers; Suppliers; Contracts; Archive (SC-013..SC-016).

- Production PR: `#638`.
- Responsive runtime defects found by acceptance have been fixed rather than waived.
- Current production head includes shrinkable contract editor controls and mobile file-input hardening.
- Exact-head runtime QA is being rerun before READY FOR LEAD.

## WORKER-2 — READY FOR LEAD

Payments; Collections/Settlements; Follow-ups; Finance; Reports; Imports; Payment Methods (SC-017..SC-023).

- Production PR: `#635` at `b8d7f063bec3a28d5bfdb8e40398d993f9d662e3`.
- Production Quality Gates: PASS.
- Production Plugin Design Reference Guard: PASS.
- Real WordPress Worker 2 Functional QA: PASS.
- Real WordPress Visual QA: PASS.
- QA-overlay Quality Gates: PASS.
- QA-overlay Plugin Design Reference Guard: PASS.
- No fake data, cross-currency aggregation, weakened assertion or visual-only closure was accepted.

## WORKER-3 — FROZEN SCOPE

Notification Center; Notification Delivery Activity; Notification Schedule; Notification Settings; Email Settings; Active Users; Users & Roles; Firebase Settings; Mobile Configuration; Translations; User Guide (SC-024..SC-034).

- Production PR: `#636`.
- SC-031 narrow RTL acceptance exposed a CSS-grid min-content overflow in the shared settings split layout.
- The shared LEAD responsive layer was hardened with a higher-specificity `minmax(0, 1fr)` rule so the later premium stylesheet cannot restore a non-shrinkable `1fr` track.
- Real WordPress Visual QA is being rerun before READY FOR LEAD.

SC-028 Email Settings remains boot-registered by the LEAD because `Plugin.php` is a protected shared file. WORKER-3 still owns the screen markup/visual implementation and its runtime evidence.

## Latest screenshots

Runtime screenshot artifacts are generated by the real WordPress + MySQL + authenticated wp-admin Playwright acceptance workflows. The eight locked reference images remain the visual source of truth; workflow GREEN is necessary but does not authorize fabricated evidence.

## Known responsive issues

No known responsive defect is being waived. The currently discovered Worker #1 contract-editor and Worker #3 Firebase RTL overflows have production/shared fixes committed and are under exact-head rerun.

## Known RTL issues

Arabic RTL remains the primary acceptance direction. The final integration cannot advance until exact-head RTL evidence is GREEN for every worker and the integrated Lead.

## Next exact task

1. Finish exact-head W1 and W3 runtime/Quality revalidation; fix any remaining real defects.
2. Advance fully-green worker PRs to READY FOR LEAD.
3. Integrate accepted worker branches in a controlled sequence without QA-only overlays.
4. After integration, rerun Plugin Design Reference Guard, repository Quality Gates and real WordPress Visual QA on the exact integrated head.
5. Only after integrated ALL GREEN, advance the LEAD release candidate to `main` and package the user-visible plugin release.

## LEAD demo-data visibility correction — 2026-08-25

- Candidate branch: `bugfix/demo-data-visible-repeatable`
- Candidate unified version: `0.3.7+11` (plugin `0.3.7`); approved `0.3.6+10` remains the locked baseline until owner acceptance.
- Scope: SC-001 Dashboard demo-data controls and the existing business tables/read models they exercise. No locked visual reference was changed.
- Creation now accumulates independent 500-row-per-table batches instead of blocking after the first batch; an advisory database lock serializes rapid/concurrent mutations.
- The Dashboard keeps Create and Delete visible together, shows batch/table/row totals, links directly to Customers, Suppliers, Contracts, Payments and Follow-ups, and displays the sanitized technical reason when a transaction fails.
- Seeded values now cover realistic customer/supplier identities, AP/AR contracts, coherent paid/partial/unpaid payment states, matching settlement totals, employee follow-up states/dates, notifications and import outcomes.
- Delete-all still uses exact stored primary keys plus per-batch markers in reverse dependency order; it never truncates a table or selects rows by broad business values.
- Added real WordPress + MySQL runtime QA for first batch, repeated second batch, read-model visibility, exact delete/restore, and a final retained batch for screenshot capture.
- Pending: exact-head repository Quality Gates and Plugin Redesign Visual QA on the PR before merge or approval.
