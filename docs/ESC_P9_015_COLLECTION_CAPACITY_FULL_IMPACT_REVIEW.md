# ESC-P9-015 — Collection Capacity Guard Full Impact Review

## Scope

P9-015 hardens the existing P9-013 immutable collection-receipt mutation path with an exact write-time aggregate capacity guard. It prevents create/revise operations from making current recorded collection exceed the exact current P9-012 scheduled amount, while preserving P9-014 read-only reconciliation and all immutable evidence.

## Schema impact

None. `Migrator::LATEST_VERSION` remains exactly `1.56.0`; `1.56.0 => Migration0057EnterpriseContractFinancialCollectionReceiptRevisions` remains unchanged and no Migration0058/table/index is introduced.

## Mutation semantics

### Create

After the existing Contract → P9-003 profile → latest P9-012 schedule locks and strict-positive Money normalization, P9-015 reads bounded latest-current receipts for the linked schedule under the same transaction. It validates each row and sums only current `recorded` receipts. The new receipt is allowed only when:

`current_recorded + proposed <= scheduled_amount`

Current latest `voided` receipt revisions consume zero capacity.

### Revise

The exact latest target receipt is locked and permanent schedule/profile linkage is validated first. Exact idempotent retries return before capacity aggregation because they change no persisted state.

For a changed revision, P9-015 reads the same bounded latest-current set, validates every row, excludes the target stable receipt UUID, sums other current `recorded` receipts, and permits the revision only when:

`other_recorded + revised_amount <= scheduled_amount`

### Void

Void performs no capacity aggregation because it can only reduce current recorded usage. All existing P9-013 active-Contract, scheduled-link, profile/currency, immutable append and terminal-void rules remain in force.

## Concurrency and locking

All capacity checks run inside the existing P9-013 mutation transaction after the exact current-tenant Contract has been locked `FOR UPDATE`. Every ESC receipt mutation follows the same Contract-first lock, so two concurrent create/revise operations on the same Contract cannot both validate against stale capacity.

Capacity receipt rows are additionally selected latest-only with `FOR UPDATE`, ordered by stable receipt UUID and bounded by the existing P9-013 1000 + 1001st sentinel.

## Arithmetic and currency integrity

- P9-001 `Money` only.
- P9-003 profile/currency remains authoritative.
- Current schedule amount is validated as strict-positive Money.
- Current receipt amounts are strict-positive Money.
- Aggregation uses `Money::add` and `compare`.
- No SQL SUM, floats, implicit rounding or FX.

## Corruption and cardinality handling

The capacity read fails closed on:

- more than 1000 current receipt identities for the linked schedule;
- malformed/duplicate latest receipt identities;
- receipt linked to a different schedule UUID or stable sequence;
- cross-profile or cross-currency receipt state;
- invalid receipt amount/state/UUID/date/reference metadata;
- invalid or non-positive scheduled amount.

No receipt revision is appended after any failed capacity validation and the transaction rolls back.

## P9-014 reconciliation compatibility

P9-014 remains unchanged and read-only. It continues to derive `over_collected` and `over_collected_total` so pre-existing, imported, corrupt or externally-written historical over-collection evidence is surfaced instead of silently hidden.

P9-015 is prospective write-time prevention for P9-013 mutations; it does not rewrite historical evidence.

## P9-012 / P9-013 impact

- P9-012 schedule repository/service is unchanged and receives no settlement/status mutation.
- P9-013 service authorization remains ACCESS for reads and MANAGE_COLLECTIONS for mutations.
- P9-013 immutable revision, permanent schedule link, exact retry and terminal void semantics remain unchanged.
- The only P9-013 repository extension is the create/revise capacity validation helper and validation of authoritative P9-012 scheduled Money.

## Legacy and Contract-value impact

No coupling is added to legacy `safecontracts_scheduled_payments`, `safecontracts_payment_collections`, PaymentRepository or CollectionRepository. P9-006 Contract-value reconciliation remains unchanged.

## Surface impact

No REST, admin UI, Flutter/mobile UI, Android identity, reports, import/export, notifications, public claims or production artifacts are introduced.

## Regression and CI

P9-015 adds:

- `tests/php/enterprise_contract_financial_collection_capacity_p9_015.php`
- `.github/workflows/esc-p9-015.yml`
- global backend-gate wiring in `scripts/test-php.sh`

Focused CI also runs P9-013 receipt compatibility and P9-014 settlement compatibility on the same candidate SHA.

Coverage includes exact-at-capacity create, over-capacity rollback/no append, voided-receipt zero usage, revise target exclusion, over-capacity revise, exact retry before capacity, 1001st sentinel, corrupt sequence/profile/currency rows, void without capacity read, schema immutability and P9-014 historical over-collection visibility.

## Rollback posture

Code-only rollback. No schema or evidence migration is added; immutable historical receipts remain untouched.

## Acceptance gates

Before merge, the exact candidate SHA must pass:

1. focused P9-015 regression;
2. P9-013 receipt compatibility and P9-014 reconciliation compatibility;
3. P9-012 standalone schedule gate and other existing P9 gates;
4. global backend regression and Enterprise tenancy tests;
5. ESC Foundation / Android coexistence and artifact-isolation gates;
6. Flutter format/analyze/test gate;
7. mergeability/conflict check against current `enterprise-safecontracts`.
