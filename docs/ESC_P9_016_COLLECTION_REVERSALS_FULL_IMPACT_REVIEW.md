# ESC-P9-016 — Collection Reversal Foundation Full Impact Review

## Scope

P9-016 adds an immutable Enterprise Contract collection-reversal foundation linked permanently to P9-013 collection receipts and their P9-012 payment-schedule identity. It records reversal evidence without mutating receipt or schedule history and enforces an exact write-time reversal ceiling against the current recorded receipt amount.

## Schema and persistence impact

P9-016 adds `Migration0058EnterpriseContractFinancialCollectionReversalRevisions` and advances `Migrator::LATEST_VERSION` from `1.56.0` to `1.57.0`.

The migration adds only `safecontracts_contract_financial_collection_reversal_revisions`. Existing historical mappings remain intact, including `1.56.0 => Migration0057EnterpriseContractFinancialCollectionReceiptRevisions`.

Each row is an immutable full-snapshot revision containing tenant, Contract, P9-003 financial profile, stable reversal UUID, revision UUID/number, linked receipt UUID, linked schedule UUID/sequence snapshot, optional external reference, reversal date, exact DECIMAL(20,4) amount, currency snapshot, state, actor and UTC timestamp.

There is no UPDATE/DELETE mutation path and no destructive migration operation.

## Domain semantics

A reversal has one stable identity and is permanently linked to exactly one P9-013 receipt plus that receipt's P9-012 schedule UUID and stable sequence snapshot.

Current reversal state is either:

- `recorded`
- `voided` (terminal)

Create and changed revise mutations require all of the following under one transaction:

1. exact current-tenant Contract is unarchived and `active`;
2. exact P9-003 financial profile exists;
3. exact latest linked P9-013 receipt remains `recorded`;
4. exact latest linked P9-012 schedule remains `scheduled`;
5. receipt/profile/currency/schedule identity remains internally consistent.

P9-016 never changes receipt state or schedule state.

## Reversal capacity

For create:

`current recorded reversals for receipt + proposed reversal <= current recorded receipt amount`

For changed revise:

`other current recorded reversals for receipt + revised target amount <= current recorded receipt amount`

The target stable reversal UUID is excluded from the revise aggregate. Current latest `voided` reversals consume zero capacity. Exact revise retries return before capacity aggregation because no persisted state changes. Void requires no capacity aggregation because it only reduces current reversal usage.

All aggregation uses P9-001 `Money`; there is no SQL SUM, floating-point arithmetic, implicit rounding or FX.

## Consistency and locking

Mutation lock order is Contract → P9-003 profile → latest P9-013 receipt → latest P9-012 schedule → latest target reversal where applicable. The Contract-first lock serializes financial mutations for the same Contract before dependent state is observed.

Capacity reads execute inside the same transaction, use latest-current reversal revisions only, lock rows with `FOR UPDATE`, order by stable reversal UUID, and use a 1001st sentinel over the 1000 stable-reversal limit.

Guarded immutable INSERT SELECT revalidates the active Contract, profile currency, latest recorded receipt and latest scheduled payment entry immediately before append.

## Tenant isolation and authorization

Tenant identity is derived only from locked `TenantContextStore` with core tenant enforcement enabled. No caller-supplied tenant selector is exposed.

Read service requires `ACCESS`. Create/revise/void require `MANAGE_COLLECTIONS`. Tenant-role narrowing uses `TenantAuthorization::allowsCapability`, while Contract data scope remains `VIEW_ALL` or own `VIEW_ASSIGNED` against the locked Contract row.

## Read semantics

Latest-current reversal reads are deterministic and bounded to 1000 identities plus the 1001st overflow sentinel. Reads validate stored reversal identity/profile/currency/Money/date/state plus authoritative receipt and schedule linkage.

Historical reversal evidence remains readable if a linked receipt or schedule later becomes terminal, provided the linked identities/profile/currency/sequence remain valid. This preserves evidence without granting further mutation.

## P9-012 / P9-013 impact

P9-012 payment schedules and P9-013 collection receipts remain immutable and unchanged. Their repositories have no reverse dependency on P9-016.

P9-013's historical schema boundary remains exactly `1.56.0 => Migration0057`; its regression is made forward-compatible so additive P9-016 schema advancement does not invalidate P9-013 guarantees.

## P9-014 / P9-015 impact

P9-014 settlement reconciliation remains unchanged and does not silently subtract reversals in this foundation slice.

P9-015 collection-capacity enforcement remains unchanged and continues to constrain gross current recorded receipts against scheduled Money. Reversal net-effect integration is deliberately deferred to a separately explicit capability so gross collection and reversal evidence cannot be conflated accidentally.

P9-015's regression is converted to a historical-boundary assertion while preserving the exact P9-013/Migration0057 mapping.

## P9-006 and legacy financial impact

P9-006 Contract-value reconciliation is unchanged. Reversals have no effect on Contract base/addition/discount/variation totals in P9-016.

Legacy `PaymentRepository`, `CollectionRepository`, scheduled-payment and payment-collection paths remain isolated. P9-016 performs no migration, synchronization or write into those tables.

## Surface impact

No REST route, admin UI, Flutter/mobile UI, report, import/export, notification, Firebase behavior, public feature claim or production artifact is introduced by P9-016.

Because no mobile surface changes, existing Flutter format/analyze/test and ESC Android isolation gates remain mandatory regression evidence rather than feature implementation evidence.

## Failure and corruption handling

P9-016 fails closed on invalid tenant context, authorization/data scope, Contract lifecycle, missing or malformed financial profile, missing/voided receipt, missing/voided schedule, receipt-schedule sequence mismatch, cross-profile/currency data, malformed UUID/date/state/Money, duplicate/latest identity anomalies and 1001st-sentinel overflow.

A rejected mutation rolls back and appends no reversal revision.

## Tests and CI

P9-016 adds:

- `tests/php/enterprise_contract_financial_collection_reversals_p9_016.php`
- `.github/workflows/esc-p9-016.yml`
- global backend-gate wiring in `scripts/test-php.sh`

The focused workflow runs P9-013, P9-014 and P9-015 compatibility before the P9-016 regression. The exact PR candidate must also pass the global backend/tenancy suite, ESC Foundation/Android isolation and Flutter mobile gates.

## Rollback posture

Application rollback is code-only, but the schema migration is forward-only and additive. Existing reversal evidence must not be dropped or rewritten during rollback. A rolled-back application version simply does not expose the P9-016 foundation.

## Acceptance gates

Before merge, the exact candidate SHA must prove:

1. schema is exactly `1.57.0` with Migration0058 and all historical mappings preserved;
2. immutable reversal revisions and permanent receipt/schedule linkage behave correctly;
3. create/revise reversal capacity cannot exceed exact current recorded receipt Money;
4. voided reversals consume zero capacity, changed revise excludes target, and exact retry/terminal void remain idempotent;
5. mutation requires active Contract + recorded receipt + scheduled entry under Contract-first locking;
6. latest reads are deterministic, bounded and fail closed on corrupt linkage/profile/currency/state/date/Money;
7. P9-012/P9-013/P9-014/P9-015/P9-006 and legacy behavior remain isolated as scoped;
8. focused P9-016, compatibility, global backend, ESC Foundation/Android isolation and Flutter mobile gates are green on the exact candidate SHA.
