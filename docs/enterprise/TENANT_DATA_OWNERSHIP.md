# ESC Tenant Data Ownership Migration

Enterprise tenant ownership is introduced with an **expand → backfill → verify → enforce** sequence. This document is authoritative for P1 ownership work and exists to prevent accidental reassignment of Safe Contract legacy data.

## Phase A — expand

Migration `1.16.0` adds nullable `tenant_id` plus an `esc_tenant_record (tenant_id, id)` index to the core contract graph:

- customers
- contracts
- contract financial items
- contract adjustments
- contract attachments
- contract history
- scheduled payments
- payment collections
- payment follow-ups

Nullable ownership is intentional during this phase. Existing rows are preserved byte-for-byte apart from the new ownership column/index. Runtime read/write enforcement is not activated merely because the columns exist.

## Phase B — explicit legacy backfill

No migration guesses the owner of existing business data. An operator must select an already-created, active Enterprise tenant after reviewing the legacy dataset.

Dry-run/verify against the deployed ESC environment:

```bash
php scripts/enterprise_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --verify
```

Apply the reviewed default tenant for all still-unowned legacy **customers**, then derive descendants from their parent relationships:

```bash
php scripts/enterprise_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --tenant-id=17 \
  --apply \
  --verify
```

The apply operation runs transactionally. It never overwrites an existing `tenant_id`. Contracts inherit from customers; contract children and payments inherit from contracts; collections and follow-ups inherit from payments. If unowned rows or cross-tenant relationship mismatches remain, the transaction is rolled back and enforcement must not proceed.

For a dataset that belongs to more than one future tenant, pre-map the root customers to their reviewed tenants before using a default tenant for any remainder. Do not use the default-tenant command as a substitute for a real mapping decision.

## Phase C — verify

`CoreTenantOwnershipBackfill::report()` returns:

- unowned row counts for each core table;
- cross-tenant relationship mismatch counts;
- `ready=true` only when both totals are zero.

A zero-unowned result alone is not sufficient: child and parent tenant ownership must agree.

## Phase D — enforce (separate task)

Only after Phase C is green may a later migration/service change:

- require tenant context for core business writes;
- add tenant predicates to list/detail/mutation/export queries;
- convert nullable ownership to enforced ownership where appropriate;
- replace global uniqueness with tenant-scoped uniqueness such as `(tenant_id, contract_number)`;
- add tenant-first indexes for actual query shapes;
- deny cross-tenant attachment/media access;
- activate cross-tenant mutation/export/deletion regression gates.

Enforcement is intentionally not bundled into migration `1.16.0`. Keeping expansion and enforcement separate makes rollback, verification and legacy-data remediation auditable.

## Reference data and non-core tables

Payment methods, notifications/delivery state, audit, import jobs, deletion/suppression records and other independently queried system tables require their own ownership decisions. Some reference data may be platform-global with tenant overrides rather than duplicated per tenant. These areas must not be assigned ownership mechanically just because the core graph uses `tenant_id`.

## Safe Contract separation

All ownership migration code in this document belongs only to `enterprise-safecontracts`. It must not be backported to Safe Contract `main` unless the product owner explicitly requests that exact transfer and its client-data migration impact is separately reviewed.
