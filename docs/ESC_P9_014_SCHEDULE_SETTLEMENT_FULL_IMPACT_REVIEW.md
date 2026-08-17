# ESC-P9-014 — Schedule Settlement Reconciliation Full Impact Review

## Scope

P9-014 adds a read-only Enterprise settlement reconciliation layer over P9-012 payment schedules and P9-013 collection receipts. It derives exact operational cash balances without mutating immutable evidence, without changing Contract-value reconciliation, and without touching the mutable legacy payment/collection path.

## Schema and persistence impact

P9-014 introduces **no migration and no table**. `Migrator::LATEST_VERSION` remains exactly `1.56.0`, with `1.56.0 => Migration0057EnterpriseContractFinancialCollectionReceiptRevisions` unchanged. No Migration0058 is introduced.

The settlement repository performs transaction control and SELECTs only. It contains no INSERT, UPDATE or DELETE path.

## Derived settlement semantics

For each latest-current P9-012 schedule entry, P9-014 derives:

- `scheduled_amount`
- `collected_amount`
- `remaining_amount`
- `over_collected_amount`
- `settlement_state`

For a current `scheduled` entry:

- zero collected => `uncollected`
- greater than zero but below scheduled => `partial`
- exactly equal => `settled`
- above scheduled => `over_collected`

For a current P9-012 `voided` entry, settlement state is `voided`. Historical scheduled and collected values remain visible in the row, but the voided entry contributes zero to current remaining/over-collected totals.

P9-014 intentionally does not reject or mutate over-collection. It exposes the condition deterministically so a later write-time enforcement/reversal task can define mutation semantics explicitly.

## Contract summary semantics

The read model derives exact P9-001 Money totals:

- `scheduled_total`: current `scheduled` entries only
- `collected_total`: current latest `recorded` receipts linked to current scheduled entries only
- `remaining_total`: non-negative remaining balances on current scheduled entries
- `over_collected_total`: excess collected amounts on current scheduled entries
- `voided_schedule_collected_total`: recorded receipt evidence linked to current voided schedules

The dedicated voided-schedule collection total prevents historical cash evidence from disappearing silently while ensuring voided schedules do not count as current outstanding receivables.

## Arithmetic and currency integrity

- All aggregation occurs in PHP using P9-001 `Money::add`, `subtract`, `compare` and `isZero`.
- No SQL `SUM`, float arithmetic or implicit rounding is used.
- P9-003 Contract currency profile remains authoritative.
- Every schedule and receipt row must match the exact P9-003 profile and currency.
- No FX path is introduced.

## Consistency and concurrency

Read protocol:

1. start one transaction;
2. lock exact current-tenant Contract with `FOR UPDATE`;
3. authorize against that exact locked Contract;
4. lock exact P9-003 currency profile;
5. read bounded latest-current P9-012 schedules;
6. read bounded latest-current P9-013 receipts;
7. validate and aggregate in memory;
8. commit.

Because all ESC financial mutations follow Contract-first locking, the locked Contract serializes the reconciliation read against schedule/receipt mutations before dependent rows are observed.

## Tenant isolation and authorization

- Tenant identity comes only from locked `TenantContextStore`.
- Core tenant enforcement is mandatory.
- Service requires `Capabilities::ACCESS` only.
- Tenant-role narrowing uses `TenantAuthorization::allowsCapability(Capabilities::ACCESS)`.
- Data scope remains exact locked Contract `VIEW_ALL` or own `VIEW_ASSIGNED`.
- Completed/cancelled/archived Contract evidence remains readable.

## Cardinality and corruption handling

P9-014 reuses existing P9-012/P9-013 limits:

- schedule entries: max 500 with 501st sentinel
- receipt identities: max 1000 with 1001st sentinel

It fails closed on:

- duplicate latest schedule UUIDs
- duplicate latest schedule sequence numbers
- duplicate latest receipt UUIDs
- receipt pointing to missing current schedule identity
- receipt schedule-sequence snapshot mismatch
- cross-profile or cross-currency schedule/receipt data
- malformed UUID/date/state/Money data
- cardinality overflow

Current latest `voided` receipts are validated but contribute zero to collected totals.

## P9-012 / P9-013 impact

P9-014 does not modify P9-012 schedule repository/service or P9-013 receipt repository/service. There is no reverse dependency from those mutation paths to the settlement layer.

No schedule state is automatically changed to settled/partial/over-collected. All P9-014 states are derived read-model values only.

## Legacy Payments / Collections impact

No coupling is added to:

- `SafeContracts\Payments\PaymentRepository`
- `SafeContracts\Collections\CollectionRepository`
- `safecontracts_scheduled_payments`
- `safecontracts_payment_collections`

Legacy behavior is unchanged.

## P9-006 Contract-value reconciliation

P9-006 remains unchanged. Settlement reconciliation describes operational collection against schedule entries; it does not change contractual base/adjustment/variation value and has zero effect on P9-006 totals.

## Surface impact

No REST, admin UI, Flutter/mobile UI, Android identity, reports, import/export, notifications, public claims or production artifacts are introduced.

## Regression and CI impact

P9-014 adds:

- `tests/php/enterprise_contract_financial_schedule_settlement_p9_014.php`
- `.github/workflows/esc-p9-014.yml`
- global backend-gate wiring in `scripts/test-php.sh`

Regression coverage includes the five derived states, exact row balances, summary totals, voided-receipt exclusion, voided-schedule cash visibility, historical lifecycle reads, cardinality sentinels, identity/sequence/link/profile/currency corruption, read-only SQL enforcement and authorization boundaries.

## Rollback posture

Code-only rollback. No schema or immutable evidence is added by P9-014.

## Acceptance gates

Before merge, the exact candidate SHA must pass:

1. focused P9-014 PHP syntax and reconciliation regression;
2. P9-013 compatibility regression (and existing P9-012 standalone gate);
3. global backend regression and Enterprise tenancy tests;
4. ESC Foundation / Android coexistence and artifact-isolation gates;
5. Flutter format/analyze/test gate;
6. mergeability/conflict check against current `enterprise-safecontracts`.
