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

### WORKER-2 implementation checkpoint — 2026-08-24

- Official foundation SHA: `f671f436d9fd357de1a79089c29ec700d0572e78`.
- Branch: `plugin-redesign/worker-2-finance-operations`.
- PR: `#635` (draft; never self-merge).
- SC-017 Payments: `IMPLEMENTED` against REF_005; authoritative AR/AP split, per-currency truth, mutations and attachments preserved; Worker #2 premium/refinement, empty/feedback/pagination states applied.
- SC-018 Collections/Settlements: `IMPLEMENTED` against REF_005; append/reversal reconciliation, method/reference/notes/attachments and direction/currency ledger truth preserved; Worker #2 premium/refinement, empty/feedback/pagination states applied.
- SC-019 Follow-ups: `IMPLEMENTED` against REF_001; only supported note/promise/issue/defer/escalate operations remain, queue/history failures are no longer represented as successful empty reads, and scoped payment context exposes real counterparty/direction/currency without changing append-only history.
- SC-020 Finance: `IMPLEMENTED` against REF_001; existing FinanceOverviewService AR/AP/per-currency/aging/action-center/work-queue truth retained and visually refined without cross-currency fabrication.
- SC-021 Reports: `IMPLEMENTED` against REF_001; existing report/filter/finance/aging and XLSX-only export path retained and visually refined.
- SC-022 Imports: `IMPLEMENTED` against REF_002; the real Upload → Mapping → Preview/Validation → Execute → Result backend stages remain authoritative; no fabricated wizard stage was added.
- SC-023 Payment Methods: `IMPLEMENTED` against REF_005; active and inactive methods are visible, active methods retain soft-delete/deactivation semantics, inactive methods can be reopened/reactivated through the existing repository save path, and historical settlement references remain intact.
- Worker-scoped assets only: `assets/admin/plugin-redesign/worker-2/**`; no Lead-owned shared shell/navigation/global token/shared CSS/JS file was modified.
- Quality Gates: active on the exact PR head; previous failures were limited to source-contract wording regressions and were corrected without weakening business rules.
- Runtime screenshot / locked-reference comparison / RTL / responsive evidence: still required before any Worker #2 row may advance from `IMPLEMENTED` to `READY FOR LEAD`. No screenshot evidence has been fabricated.

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