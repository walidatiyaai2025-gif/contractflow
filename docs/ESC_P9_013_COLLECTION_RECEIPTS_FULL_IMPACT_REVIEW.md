# ESC-P9-013 — Contract Collection Receipts Full Impact Review

## Scope

P9-013 adds an Enterprise-only, tenant-safe, immutable Contract collection-receipt foundation linked explicitly to P9-012 payment-schedule identities. It records receipt evidence without coupling Enterprise finance to the mutable legacy collections/settlement path and without inventing aggregate remaining-balance semantics.

## Domain model

- Each receipt owns one stable server-generated UUID.
- Each mutation appends a full immutable revision with its own server-generated revision UUID and monotonically increasing revision number.
- Receipt state is limited to `recorded` or terminal `voided`.
- Each receipt is permanently linked to one stable P9-012 `schedule_entry_uuid`; revisions cannot relink it.
- Every revision persists the linked P9-012 stable sequence snapshot, strict received date, optional bounded external reference, strictly-positive P9-001 Money, P9-003 profile identity/currency snapshot, actor and UTC creation time.
- Receipt mutation is allowed only while the exact current-tenant Contract is unarchived and `active`.
- Completed/cancelled historical receipt evidence remains readable.

## P9-012 schedule linkage

Mutation lock order is deliberately:

1. exact current-tenant Contract `FOR UPDATE`;
2. exact P9-003 financial profile;
3. exact latest linked P9-012 schedule entry `FOR UPDATE`;
4. exact latest receipt revision when revising/voiding.

The linked schedule must be the same tenant/Contract/profile/currency and must remain `scheduled` for receipt mutation. The receipt stores the stable schedule sequence snapshot and rejects any later attempt to relink the receipt to another schedule UUID or sequence.

Historical receipt reads do not require the current schedule state to remain `scheduled`; receipts remain readable if the schedule is later voided. Reads still fail closed if the linked latest schedule identity/profile/sequence is missing or corrupt.

P9-013 never UPDATEs or DELETEs P9-012 schedule rows.

## Authorization and tenant isolation

- Tenant identity is derived only from locked `TenantContextStore`; caller supplies no tenant.
- Core tenant enforcement is mandatory.
- Reads require `Capabilities::ACCESS`.
- Create/revise/void require `Capabilities::MANAGE_COLLECTIONS`.
- WordPress capability grants remain the ceiling and are narrowed by `TenantAuthorization::allowsCapability`.
- Existing Contract scope remains `VIEW_ALL` or own `VIEW_ASSIGNED` against the exact locked Contract row.

## Currency and monetary integrity

- Receipt amount reuses P9-001 `Money`; no duplicate decimal parser, float arithmetic or rounding path is introduced.
- Receipt amount must be strictly greater than zero.
- Canonicalization occurs only after the authoritative P9-003 profile is locked.
- Every revision persists and validates exact P9-003 profile ID and currency snapshot.
- Caller supplies no currency and no FX path is introduced.

## Settlement arithmetic deliberately deferred

P9-013 records receipt evidence only. It does **not** define or calculate:

- aggregate paid amount per schedule entry;
- remaining amount;
- aggregate over-collection enforcement;
- partial/fully-paid schedule state;
- automatic schedule closure/settlement;
- allocation or application order;
- payment-method semantics;
- proof attachment semantics.

A later explicit settlement/reconciliation task must define aggregate constraints atomically across multiple receipts. P9-013 therefore does not silently cap an individual receipt to schedule amount or mutate P9-012 state.

## Persistence and migration impact

- Additive `Migration0057EnterpriseContractFinancialCollectionReceiptRevisions` advances ESC schema from `1.55.0` to `1.56.0`.
- New table: `safecontracts_contract_financial_collection_receipt_revisions`.
- Exact historical mappings remain unchanged, including `1.55.0 => Migration0056EnterpriseContractFinancialPaymentScheduleEntryRevisions`.
- Receipt evidence is append-only: no UPDATE/DELETE path and no destructive migration rollback/drop/rewrite.
- Maximum 1000 stable receipt identities per Contract; current reads request 1001 rows and fail closed on overflow.

## Legacy Payments / Collections isolation

P9-013 is intentionally independent from:

- `SafeContracts\Payments\PaymentRepository`
- `SafeContracts\Collections\CollectionRepository`
- `safecontracts_scheduled_payments`
- `safecontracts_payment_collections`

The new repository/service does not read, write, mirror, synchronize or mutate those legacy models/tables. Legacy payment/collection repositories also remain unchanged and contain no dependency on the new Enterprise receipt classes.

## P9-006 reconciliation impact

P9-006 Contract-value reconciliation remains authoritative and unchanged. Collection receipt evidence has zero Contract-value effect; receipt recording is cash/collection evidence, not a mutation of contractual base/adjustment/variation value.

## Surface impact

No REST, admin UI, Flutter/mobile UI, Android identity, reports, import/export, notifications, public feature claims or production artifacts are introduced by P9-013.

## Security / concurrency / idempotency

- One transaction per read or mutation.
- Contract-first locking ensures authorization cannot race with tenant/lifecycle changes.
- Profile and linked schedule are locked before receipt state is observed for mutation.
- Creation proves receipt capacity and generated UUID uniqueness under the Contract lock.
- Revise/void lock exact latest receipt revision.
- Exact revise retries return the current latest revision without append.
- Repeated void returns the terminal revision without append.
- Voided receipts cannot be revised or reactivated.
- Schedule relinking fails closed.
- Any authorization, lifecycle, schedule, profile/currency, validation, cardinality or corruption failure rolls back and fails closed.

## Regression and CI impact

P9-013 adds:

- `tests/php/enterprise_contract_financial_collection_receipts_p9_013.php`
- `.github/workflows/esc-p9-013.yml`
- global backend-gate wiring in `scripts/test-php.sh`

Coverage includes schema history, Contract-first authorization, 1001st sentinel, strict Money/date/reference validation, schedule linkage and sequence snapshots, historical read after schedule void, missing/corrupt schedule failure, active-only mutation, `MANAGE_COLLECTIONS`, idempotency, terminal void, schedule relinking rejection, legacy isolation and P9-006 independence.

P9-012 is converted only from a latest-schema assertion to the exact historical `1.55.0 => Migration0056` boundary. All P9-012 behavioral coverage remains intact.

## Rollback posture

Rollback is code-level only. Migration0057 is additive and immutable receipt evidence is retained. No destructive down migration, schedule rewrite or legacy synchronization rollback is introduced.

## Acceptance gates

Before merge, the exact candidate SHA must pass:

1. focused ESC-P9-013 PHP syntax and behavioral regression;
2. P9-012 historical compatibility regression;
3. global backend regression and Enterprise tenancy tests;
4. ESC Foundation / Android coexistence and artifact-isolation gates;
5. Flutter format/analyze/test gate;
6. mergeability/conflict check against current `enterprise-safecontracts`.
