# ALKENZY ADV — PLUGIN UI REDESIGN PROGRESS

## Governance checkpoint

- Governance version: `1.0.0`
- Repository census baseline: `main@7aadd79f23726ed1b4df8b078986a151539820a3`
- Current reference manifest version: `1.0.0`
- Reference baseline date: `2026-08-24`
- Frozen screen inventory: `34`
- Unassigned screens: `0`
- Overlapping ownership: `0`
- Implementation start gate: `BLOCKED UNTIL GOVERNANCE FOUNDATION + 7 LOCKED BINARIES ARE MERGED TO main AND VALIDATION PASSES`

The exact worker branch point is the final `main` SHA produced by the governance-foundation merge. A Git commit cannot contain its own final SHA without changing that SHA, so the immutable branch point is recorded in GitHub merge metadata and must be copied verbatim into every worker PR body at creation.

## COMPLETED — GOVERNANCE PREPARATION

- 7 approved visual references identified with fixed filenames and SHA-256 values.
- Reference manifest frozen.
- Plugin UI Constitution frozen.
- Full current WordPress Admin screen census reconciled against repository source.
- Exactly-one-owner assignment frozen for all 34 screens.
- Shared-file ownership and protected-path rules frozen.
- Branch, PR, merge-order, screenshot, RTL, responsive and Definition-of-Done rules frozen.
- Validation script extended to reject missing/changed references, missing governance documents, unassigned owners and duplicate screen ownership rows.

## IMPLEMENTATION STATUS

No page redesign implementation is authorized before the P0 governance gate passes on `main`. All screen rows remain `NOT STARTED`.

## LEAD current scope — 12 screens

Dashboard; all eight grouped-navigation landing states; General Settings; Runtime Inspector; Migration Recovery.

LEAD also exclusively owns all protected shared files, design tokens, navigation, common primitives, shared CSS/JS and governance files.

## WORKER-1 current scope — 4 screens

Customers; Suppliers; Contracts; Archive.

## WORKER-2 current scope — 7 screens

Payments; Collections/Settlements; Follow-ups; Finance; Reports; Imports; Payment Methods.

### WORKER-2 acceptance-closure checkpoint — 2026-08-24

- Official foundation SHA: `f671f436d9fd357de1a79089c29ec700d0572e78`.
- Branch: `plugin-redesign/worker-2-finance-operations`.
- PR: `#635` (draft; never self-merge).
- Latest implementation checkpoint before this progress commit: `da0ee55ea9c6f056a33bfd63160c89bc57ceb0f0`.
- SC-017 Payments: `IMPLEMENTED` against REF_005; authoritative AR/AP split, per-currency truth, mutations and attachments preserved; Worker #2 premium/refinement, truthful empty state and presentation-only pagination applied.
- SC-018 Collections/Settlements: `IMPLEMENTED` against REF_005; append/reversal reconciliation, method/reference/notes/attachments and direction/currency ledger truth preserved; Worker #2 premium/refinement, truthful empty state and presentation-only pagination applied.
- SC-019 Follow-ups: `IMPLEMENTED` against REF_001; only supported note/promise/issue/defer/escalate operations remain, queue/history failures are not represented as successful empty reads, scoped payment context exposes real counterparty/direction/currency, and internal numeric IDs are no longer used as end-user label fallbacks.
- SC-020 Finance: `IMPLEMENTED` against REF_001; existing FinanceOverviewService AR/AP/per-currency/aging/action-center/work-queue truth retained and visually refined without cross-currency fabrication.
- SC-021 Reports: `IMPLEMENTED` against REF_001; existing report/filter/finance/aging and XLSX-only export path retained and visually refined.
- SC-022 Imports: `IMPLEMENTED` against REF_002; the real Upload → Mapping → Preview/Validation → Execute → Result backend stages remain authoritative; no fabricated wizard stage was added.
- SC-023 Payment Methods: `IMPLEMENTED` against REF_005; active and inactive methods are visible, active methods retain safe soft-delete semantics, inactive methods can be reopened/reactivated through the existing repository save path, and historical settlement references remain intact.
- Arabic zero-debt audit: PASS on the acceptance-fix source (`1103` discovered strings, `0` untranslated). Newly introduced Worker #2 PHP strings reuse repository-authoritative gettext/catalog entries; Worker #2 JavaScript contains no parallel hard-coded Arabic/English dictionary and receives translated empty-state copy through WordPress localization.
- Production UX guard: PASS after removing visible `#<internal id>` label fallbacks from Follow-ups while retaining IDs only as server/URL transport.
- Responsive static hardening: Worker #2 grid children are shrink-safe, table overflow is contained inside table cards, narrow financial tables use deliberate horizontal scroll regions, and pagination arrows follow the actual RTL/LTR document direction.
- Financial authority preserved: Customer = AR/receivable; Supplier = AP/payable; settlement history remains authoritative; currencies remain separate; no fake cross-currency grand total, export path or import stage was introduced.
- Worker-scoped assets only: `assets/admin/plugin-redesign/worker-2/**`; no Lead-owned shared shell/navigation/global token/shared CSS/JS file was modified.
- Plugin Design Reference Guard remained PASS throughout the acceptance-fix sequence; exact-head revalidation is required after this checkpoint commit.
- Repository Quality Gates reached GREEN for repository-standards, backend-foundation, mobile-foundation and release-readiness on the acceptance source; exact-head full workflow revalidation is required after this checkpoint commit.
- Runtime QA harness status: no Lead-provided authenticated WordPress runtime capture harness, handoff, workflow or PR artifact is currently present. Lead PR #637 and Worker #1/#3 redesign PRs independently record real WordPress runtime screenshots as pending. No runtime evidence has been fabricated.
- Remaining acceptance gate: real authenticated WordPress runtime capture/comparison for SC-017..SC-023, Arabic RTL, 390/768/1440 responsive evidence, English LTR smoke, reproducible empty/error/validation/action states, and real functional actions. Rows remain `IMPLEMENTED`, not `READY FOR LEAD`, until this evidence exists and passes.

## WORKER-3 current scope — 11 screens

Notification Center; Notification Delivery Activity; Notification Schedule; Notification Settings; Email Settings; Active Users; Users & Roles; Firebase Settings; Mobile Configuration; Translations; User Guide.

## Latest screenshots

Only the seven locked design references are approved at the governance stage. Runtime implementation screenshots do not exist yet and must not be fabricated.

## Latest visual mismatches

`NOT MEASURED — IMPLEMENTATION HAS NOT STARTED.`

## Known responsive issues

`NOT MEASURED — IMPLEMENTATION HAS NOT STARTED.`

## Known RTL issues

`NOT MEASURED — IMPLEMENTATION HAS NOT STARTED.`

## Next exact task

1. Commit the governance foundation and the seven exact locked reference binaries to the foundation branch.
2. Run `python3 scripts/validate-plugin-design-references.py` from repository root and require PASS.
3. Merge the governance PR to `main`.
4. Record the resulting exact `main` SHA.
5. Only then create all four redesign branches from that exact SHA.