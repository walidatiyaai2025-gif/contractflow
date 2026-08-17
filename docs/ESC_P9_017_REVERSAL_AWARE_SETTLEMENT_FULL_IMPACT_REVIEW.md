# ESC-P9-017 — Reversal-Aware Schedule Settlement Full Impact Review

## Scope

P9-017 extends the existing P9-014 read-only Contract schedule settlement reconciliation so latest-current P9-016 collection reversals reduce effective collection balances. It does not introduce a second settlement repository, mutate receipt/schedule state, or change P9-015 write-time capacity semantics.

## Schema impact

None. `Migrator::LATEST_VERSION` remains exactly `1.57.0`; `1.57.0 => Migration0058EnterpriseContractFinancialCollectionReversalRevisions` remains the latest mapping. No Migration0059/table/index change is introduced.

## Read model

The existing `ContractFinancialScheduleSettlementRepository` keeps one Contract-first transaction and now reads three bounded latest-current sets after locked authorization/profile validation:

1. P9-012 payment-schedule entries;
2. P9-013 collection receipts;
3. P9-016 collection reversals.

Reversal rows are latest-only, ordered deterministically by receipt/date/reversal UUID, and bounded by the existing P9-016 1000 + 1001st sentinel.

## Reversal integrity

Every latest reversal is validated for:

- positive row/revision/actor metadata;
- UUIDv4 revision, reversal, receipt and schedule identities;
- exact current-tenant Contract and P9-003 financial profile;
- exact P9-003 currency;
- positive P9-001 Money amount;
- bounded reference and strict reversal date;
- `recorded|voided` state;
- existing current receipt identity;
- permanent schedule UUID and sequence snapshot equality with the current receipt;
- current schedule identity and sequence equality;
- duplicate latest reversal identities;
- aggregate latest `recorded` reversal amount not exceeding the linked receipt gross amount.

Latest `voided` reversals contribute zero. Reversals linked to a current `voided` receipt remain validated as historical evidence but both the receipt and reversal are excluded from current settlement.

## Arithmetic semantics

For each current `recorded` receipt:

`net receipt = gross receipt - sum(latest recorded reversals)`

All arithmetic uses P9-001 `Money`; there is no SQL SUM, float, implicit rounding or FX path.

Per schedule, P9-017 derives:

- `scheduled_amount`;
- `gross_collected_amount`;
- `reversed_amount`;
- `collected_amount` (effective/net collection);
- `remaining_amount`;
- `over_collected_amount`;
- existing P9-014 settlement state.

The summary similarly exposes current active-schedule `gross_collected_total`, `reversed_total`, net `collected_total`, remaining and over-collected totals. Voided schedules preserve separate gross/reversed/net historical totals.

## Compatibility

- P9-014 remains read-only and keeps its existing repository/service entry point.
- Existing `collected_amount`/`collected_total` now represent effective collection after valid recorded reversals; new gross/reversed fields preserve evidence visibility.
- P9-015 capacity mutation logic is intentionally unchanged. Reusing capacity after a reversal is deferred to P9-018 so read semantics and mutation/concurrency hardening are reviewed separately.
- P9-016 reversal mutation semantics remain unchanged and have no reverse coupling to settlement.
- P9-006 Contract-value reconciliation remains independent.
- Legacy Payments/Collections remain unchanged.
- No REST/admin/mobile UI, reports, import/export, notifications, FX or public feature claim is introduced.

## Concurrency posture

Schedule, receipt and reversal snapshots are read under the same Contract-first transaction used by P9-014. ESC financial mutations lock the same Contract first, preventing a reversal/receipt mutation from being interleaved between independent settlement reads for the same Contract.

## Regression and CI

P9-017 adds:

- `tests/php/enterprise_contract_financial_reversal_aware_settlement_p9_017.php`;
- `.github/workflows/esc-p9-017.yml`;
- global backend-gate wiring in `scripts/test-php.sh`.

Focused CI also runs P9-013 receipt, P9-014 settlement, P9-015 capacity and P9-016 reversal compatibility on the exact candidate SHA.

Coverage includes partial and full reversal netting, multiple reversals, voided reversal zero effect, current voided receipt historical evidence, voided schedule gross/reversed/net evidence, over-reversal rejection, orphan/cross-profile/cross-currency/sequence corruption, duplicate identities, 1001st sentinel and schema immutability.

## Rollback posture

Code-only rollback. No schema or immutable financial evidence is changed.
