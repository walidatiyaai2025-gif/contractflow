# ESC-P9-018 — Reversal-Aware Collection Capacity Full Impact Review

## Scope
P9-018 hardens the existing P9-015 write-time capacity guard so latest P9-016 recorded reversals release effective schedule capacity. It extends only the existing P9-013 receipt repository.

## Schema
None. `Migrator::LATEST_VERSION` stays `1.57.0`; Migration0058 remains latest. No Migration0059/table/index change.

## Effective capacity
For each current `recorded` receipt:

`effective usage = receipt gross - latest recorded reversal total`

Latest `voided` reversals contribute zero. Current `voided` receipts consume zero capacity while linked reversal evidence is still validated.

Create requires:

`existing effective usage + proposed gross <= scheduled amount`

Changed revise excludes the target from existing usage, requires target recorded reversals `<= proposed gross`, and then requires:

`other effective usage + (proposed gross - target reversals) <= scheduled amount`

Exact idempotent revise retries still return before capacity aggregation. Receipt void remains capacity-read free because it only reduces usage.

## Integrity and concurrency
Capacity locks bounded latest-current receipts and reversals under the existing Contract-first transaction. Relevant reversal evidence must preserve Contract/profile/currency, receipt identity, schedule UUID/sequence, positive P9-001 Money, valid state/date/reference and unique latest identity. Recorded reversal aggregate may not exceed linked receipt gross. Corruption fails closed before append.

All arithmetic uses P9-001 `Money`; no SQL SUM, float, implicit rounding or FX. Receipt and reversal mutations share the Contract-first serialization boundary, preventing stale capacity decisions on the same Contract.

## Compatibility
P9-013 immutable receipt semantics, P9-016 reversal mutation, P9-017 read-only settlement, P9-006 Contract-value reconciliation and legacy Payments/Collections remain separate. No REST/admin/mobile UI, reporting, import/export, notifications or schema surface is added.

## Regression and CI
Adds `tests/php/enterprise_contract_financial_reversal_aware_capacity_p9_018.php`, `.github/workflows/esc-p9-018.yml`, and global backend-gate wiring. Focused CI runs P9-013 through P9-018 compatibility on the same SHA.

Coverage includes exact reopened capacity, four-decimal over-capacity rejection, multiple/voided reversals, net revise, revise below reversal history, voided receipt zero usage, over-reversal/corruption/cardinality failure, exact retry and void bypass.

## Rollback
Code-only rollback. No immutable receipt/reversal evidence is rewritten.
