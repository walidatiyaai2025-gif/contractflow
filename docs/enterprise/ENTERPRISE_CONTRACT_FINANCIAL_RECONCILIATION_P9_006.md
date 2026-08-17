# ESC-P9-006 — Enterprise Contract Financial Reconciliation

## Decision

P9-006 introduces the authoritative read-only Enterprise Contract financial reconciliation snapshot over the persisted P9-004 base value and the latest current states of P9-005 additions and discounts.

It introduces no persistence, migration, REST route, admin UI, Flutter surface or legacy synchronization. The schema remains `1.49.0` with historical `Migration0050EnterpriseContractFinancialAdjustmentRevisions` mapping unchanged.

The P9-003 Contract financial currency profile is the only currency authority. P9-004 and P9-005 records must reference that exact profile and currency. Missing or malformed persisted state fails closed; there is no implicit zero base value, fallback currency, FX or conversion path.

## Authoritative calculation

All arithmetic uses P9-001 `Money` at fixed four-decimal scale.

- `base_value` = latest explicit P9-004 base-value revision.
- `additions_total` = sum of latest P9-005 lines whose state is `active` and kind is `addition`.
- `discounts_total` = sum of latest P9-005 lines whose state is `active` and kind is `discount`.
- `gross_value` = `base_value + additions_total`.
- `net_value` = `gross_value - discounts_total`.
- latest `voided` lines contribute zero financially and increment `voided_line_count`.

The net is intentionally signed. A negative result is returned canonically and is not clamped or rejected. Any later lifecycle rule about negative contractual net value is outside P9-006.

The returned snapshot is an array of canonical scalar values only:

- currency;
- base value;
- additions total;
- discounts total;
- gross value;
- net value;
- active addition count;
- active discount count;
- voided-line count.

No mutable financial domain object is returned.

## Consistency and authorization boundary

Reconciliation executes in one database transaction.

1. Derive tenant identity only from locked `TenantContextStore` with core tenant enforcement enabled.
2. Lock the exact current-tenant Contract using `FOR UPDATE`.
3. Invoke the service-owned data-scope authorization callback against that exact locked Contract row.
4. Only after authorization succeeds, read exactly one P9-003 financial currency profile.
5. Read the latest explicit P9-004 base-value revision.
6. Read only latest P9-005 line revisions, bounded to 200 lines plus a 201st overflow sentinel.
7. Validate identity, profile, currency, UUID, revision, kind/state and non-negative persisted monetary amounts.
8. Commit the read snapshot; on any invariant or authorization failure, roll back and fail closed.

P9-003, P9-004 and P9-005 mutation paths serialize through the same Contract row. Holding the Contract lock therefore prevents reconciliation from mixing before/after states from a concurrent financial mutation.

The service requires `ACCESS`, applies `TenantAuthorization::allowsCapability()` as the tenant-role narrowing boundary, and preserves existing Contract `VIEW_ALL` / own `VIEW_ASSIGNED` data scope inside the locked-row callback.

## Bounded current adjustment read

Current P9-005 state is derived from immutable history using `NOT EXISTS` for a newer revision of the same tenant/Contract/line identity. Results are ordered deterministically and requested with `LIMIT 201`.

A 201st current line is an overflow sentinel and fails closed. Duplicate latest line identities, invalid UUIDs, invalid kind/state, invalid revision metadata, profile drift, currency drift, negative stored amounts or malformed rows also fail closed.

Voided rows remain part of current historical state, are validated fully, are counted, and contribute zero to sums.

## Base-value integrity

Reconciliation requires an explicit latest P9-004 revision. An absent base revision is an error rather than `0.0000`.

The latest base row must have valid positive identity metadata, a valid UUIDv4, the expected Contract id, the exact P9-003 profile id, a positive bounded revision number, a positive actor id, a non-negative P9-001 Money amount and currency equal to the profile currency.

## Full Impact Review

### Domain/business logic — affected

Adds the first authoritative ESC Contract base/addition/discount reconciliation. No post-draft variation semantics are introduced.

### Tenant isolation — affected

All SQL is current-tenant and Contract scoped. Caller-provided tenant identity is absent.

### Database/migrations/indexes — N/A

No migration, table, column, index, backfill, ALTER or DROP. Schema remains `1.49.0`.

### Backend business logic — affected

Adds `ContractFinancialReconciliationRepository` and `ContractFinancialReconciliationService`.

### Authorization/data scope — affected

Requires `ACCESS`, tenant-role narrowing and locked-row `VIEW_ALL` / own `VIEW_ASSIGNED` scope before financial rows are read.

### REST/API — N/A

No route or public payload is introduced.

### WordPress/admin UI — N/A

No admin surface is introduced.

### Flutter/mobile/offline — N/A

No client model or financial recomputation is introduced.

### Android/build identity — N/A

No Android, Firebase, signing or package identity changes.

### Currency/localization — affected only for canonical currency identity

P9-003 currency and P9-001 Money are mandatory. No locale parsing, exchange rate, conversion or revaluation exists.

### Security/concurrency — affected

Contract-first locking plus authorization-before-financial-read closes assignment TOCTOU and produces one coherent state relative to P9-003/004/005 writers.

### Performance — affected

One Contract lock, one bounded profile read, one latest-base read and at most 201 current adjustment rows. No unbounded financial history scan is introduced.

### Audit/compliance — N/A for mutation

The operation is read-only and emits no mutation audit event. Existing immutable P9-004/P9-005 histories remain the evidence source.

### Reports/import/export/notifications/documents — N/A

No reporting, import/export, notification, document or storage surface is introduced.

### Backward compatibility — reviewed

Legacy Contract base value, legacy adjustments, legacy financial totals, dashboards and other historical financial behavior are neither read nor reinterpreted by P9-006.

### Tests/CI — affected

`tests/php/enterprise_contract_financial_reconciliation_p9_006.php` covers the locked authorization boundary, schema stability, missing/corrupt base state, cross-currency adjustments, duplicate latest identities, the 201st-line sentinel, voided-line behavior and signed negative net arithmetic. It is wired into `scripts/test-php.sh` and `.github/workflows/esc-p9-006.yml`.

### Release/rollback — reviewed

Code-only rollback. There is no migration or production artifact publication in P9-006.

## Non-goals

P9-006 does not implement financial mutations, variations/change orders, taxes/VAT, retention, penalties, credits, invoices, payment schedules, collections, FX, conversion/revaluation, lifecycle blocking, REST/admin/mobile UI, reports, imports/exports, notifications, public feature claims, legacy synchronization or production artifacts.
