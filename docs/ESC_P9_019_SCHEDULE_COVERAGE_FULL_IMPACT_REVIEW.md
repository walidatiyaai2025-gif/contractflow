# ESC-P9-019 — Contract Schedule Coverage Full Impact Review

## Scope
P9-019 adds a schema-free, read-only Contract-level reconciliation between the authoritative P9-006 financial net value and current P9-012 payment schedule coverage. P9-012 explicitly deferred schedule-total reconciliation; P9-019 fills that read-model gap without introducing a mutation guard.

## Authoritative financial arithmetic
P9-006 arithmetic is extracted into `ContractFinancialReconciliationCalculator`. The existing P9-006 service delegates to this calculator, and P9-019 uses the same calculator for base + active additions - active discounts. This prevents a second Contract net-value formula from emerging.

Signed P9-006 net values are preserved exactly. No clamp or lifecycle interpretation is added for negative net values.

## Atomic snapshot
`ContractFinancialScheduleCoverageRepository` owns one Contract-first transaction. It locks and authorizes the exact current-tenant Contract before observing:

1. the single P9-003 financial currency profile;
2. the latest P9-004 base-value revision;
3. bounded latest-current P9-005 adjustments;
4. bounded latest-current P9-012 schedule entries.

Financial and schedule evidence are therefore evaluated from one coherent serialized Contract snapshot rather than composing two independent read transactions.

## Coverage semantics
Current latest `scheduled` entries contribute to `scheduled_total`. Current latest `voided` entries contribute zero current coverage and are surfaced separately in `voided_scheduled_total`.

The service returns:

- `contract_net_value`;
- `scheduled_total`;
- `voided_scheduled_total`;
- signed `schedule_delta = scheduled_total - contract_net_value`;
- `coverage_state = under_scheduled|aligned|over_scheduled`;
- current scheduled and voided entry counts.

Coverage state uses exact P9-001 Money comparison. A negative authoritative Contract net remains signed and is compared mathematically.

## Integrity and bounds
The snapshot fails closed on missing/corrupt base/profile evidence, duplicate latest adjustment identities, duplicate latest schedule identities or sequence numbers, cross-profile/cross-currency rows, malformed UUID/state/date/reference/Money metadata, negative base/adjustment values, non-positive schedule amounts, and 201st/501st cardinality overflow.

All arithmetic uses P9-001 `Money`; there is no SQL SUM, float, implicit rounding or FX.

## Authorization
P9-019 is ACCESS-only with tenant-role narrowing. Data scope is evaluated against the exact locked Contract using existing VIEW_ALL / own VIEW_ASSIGNED semantics before financial or schedule state is read.

## Compatibility
- P9-006 remains the authoritative Contract value formula through the shared calculator.
- P9-012 schedule mutation behavior remains unchanged.
- P9-014/P9-017 settlement remains independent and read-only.
- P9-018 collection capacity remains unchanged.
- Legacy Payments/Collections remain unchanged.
- No REST/admin/mobile UI, reports, import/export, notifications, schema or public feature claims are introduced.
- Write-time schedule-total enforcement is intentionally deferred until this read model is validated.

## Schema
None. `Migrator::LATEST_VERSION` remains `1.57.0`; Migration0058 remains latest. No Migration0059/table/index change.

## Regression and CI
P9-019 adds `tests/php/enterprise_contract_financial_schedule_coverage_p9_019.php`, `.github/workflows/esc-p9-019.yml`, and global backend-gate wiring.

Coverage includes aligned/under/over schedule totals, signed deltas, voided schedule evidence, negative authoritative net, Contract-first authorization ordering, duplicate schedule sequence rejection, cross-currency/profile corruption, 201st adjustment and 501st schedule sentinels, schema immutability and shared P9-006 arithmetic.

## Rollback
Code-only rollback. No financial or schedule evidence is rewritten.
