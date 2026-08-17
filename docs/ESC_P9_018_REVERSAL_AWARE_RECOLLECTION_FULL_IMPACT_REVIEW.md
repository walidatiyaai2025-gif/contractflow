# ESC-P9-018 — Reversal-Aware Recollection Capacity Full Impact Review

## Scope
P9-018 extends the existing P9-015 write-time collection-capacity guard so immutable P9-016 recorded reversal evidence restores exact recollection capacity on the same P9-012 schedule entry. It changes no persistence schema and introduces no new mutation surface.

## Schema and persistence impact
No migration is added. `Migrator::LATEST_VERSION` remains exactly `1.57.0` and the existing `1.57.0 => Migration0058EnterpriseContractFinancialCollectionReversalRevisions` mapping remains authoritative. P9-018 adds no table, column, index, UPDATE/DELETE path, backfill or legacy rewrite. Existing P9-013 receipt revisions and P9-016 reversal revisions remain immutable evidence.

## Capacity semantics
Effective receipt usage is `recorded receipt gross - current recorded reversal total`. Current latest voided reversals contribute zero. Current voided receipts consume zero schedule capacity, matching P9-017 settlement semantics.

Create requires `sum(current effective recorded receipt usage) + proposed gross receipt <= scheduled Money`.

Changed revise excludes the target stable receipt from other usage, preserves its current recorded reversal aggregate, requires `target reversals <= proposed revised gross`, computes `proposed gross - target reversals`, then requires other effective usage plus that projected target usage to remain within scheduled Money. Exact revise idempotency stays before capacity aggregation. Receipt void remains unchanged and performs no capacity aggregation.

## Reversal evidence validation
The receipt repository reads latest-current P9-016 reversal rows under the same transaction as the capacity decision. The read is bounded to `ContractFinancialCollectionReversalPolicy::MAX_REVERSALS + 1`, ordered by reversal UUID and locked `FOR UPDATE`.

P9-018 fails closed on duplicate reversal identities, orphan/different-schedule receipt linkage, profile/currency/schedule UUID or sequence mismatch, malformed reversal metadata, non-positive reversal Money, 1001st-sentinel overflow, recorded reversal aggregate above receipt gross, and revised receipt gross below its recorded reversal aggregate.

All arithmetic uses P9-001 `Money`; there is no SQL `SUM`, float arithmetic, rounding or FX path.

## Consistency and locking
The existing Contract-first P9-013/P9-015 transaction remains authoritative. Capacity is evaluated after Contract, financial profile and exact schedule locking. Revise locks the exact target receipt before broader capacity reads. The capacity guard then locks latest-current schedule receipts followed by latest-current schedule reversals. P9-016 reversal mutations also serialize through the Contract lock.

## Historical compatibility
P9-016's original absence assertion is converted to a durable dependency boundary: the P9-016 reversal mutation repository must not depend on the receipt-repository implementation. This permits P9-018 receipt capacity to consume immutable reversal evidence without changing P9-016 mutation semantics. P9-015 compatibility remains green when no recorded reversals exist. P9-017 settlement logic remains unchanged.

## Authorization and tenant isolation
No service or capability changes are introduced. Receipt mutations keep their existing tenant/Contract authorization and `MANAGE_COLLECTIONS` capability. Reversal-capacity reads are current-tenant, Contract and exact-schedule scoped; no caller tenant/currency selector is added.

## Surface and legacy impact
No REST/admin/mobile/report/search/import/export/notification/Firebase/public feature or production artifact is introduced. Legacy mutable Payments/Collections remain unchanged and isolated.

## Tests and CI
P9-018 adds `tests/php/enterprise_contract_financial_reversal_aware_recollection_p9_018.php`, `.github/workflows/esc-p9-018.yml`, global `scripts/test-php.sh` wiring, and this review. Focused CI runs P9-015, P9-016 and P9-017 compatibility before P9-018. Exact-head ESC Foundation/backend/Android isolation and Flutter mobile gates remain required before merge.

## Acceptance evidence
The regression covers exact recollection restored by recorded reversal, rejection above net schedule capacity, zero effect from voided reversal, revise netting of target reversals, rejection below target reversal aggregate, corruption fail-closed cases, over-reversal fail-closed behavior, and schema staying exactly `1.57.0`.

## Rollback posture
P9-018 is code-only and schema-free. Application rollback restores gross P9-015 write-time capacity behavior without rewriting stored receipt or reversal evidence; no database rollback is required.
