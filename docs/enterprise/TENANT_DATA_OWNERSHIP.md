# ESC Tenant Data Ownership Migration

Enterprise tenant ownership is introduced with an **expand → backfill → verify → enforce → harden** sequence. This document is authoritative for P1 ownership work and exists to prevent accidental reassignment of Safe Contract legacy data.

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

## Phase D — enforce

Runtime enforcement is feature-gated. **Deploying the enforcement code does not enable it.** The environment stays in migration-compatible mode until an operator explicitly enables it after a successful ownership verification.

Enable only after `--verify` reports `ready=true`:

```bash
php scripts/enterprise_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --verify \
  --enable-enforcement
```

The enable command calls `CoreTenantOwnershipBackfill::assertReadyForEnforcement()` before persisting the enforcement flag. It cannot turn enforcement on while unowned rows or parent/child tenant mismatches exist.

When enabled:

- core REST business routes require one server-authorized locked `TenantContext`;
- the client `X-ESC-Tenant-ID` value remains selection input only and never grants membership;
- core list/detail/report queries add tenant predicates;
- customer/contract/payment/collection/follow-up mutations derive `tenant_id` from server context instead of request JSON;
- repositories fail closed if enforcement is enabled without a locked tenant;
- contract financial children, history and attachments validate the selected contract against the current tenant before inserting;
- report/export reads inherit the same tenant-scoped repository boundary;
- archive/deletion/reconciliation paths retain the same tenant predicates;
- audit events inject the locked tenant ID into server-generated audit context and overwrite a caller-supplied tenant value;
- known record IDs do not bypass the tenant predicate.

Controlled remediation may temporarily disable runtime enforcement only after an explicit operational decision:

```bash
php scripts/enterprise_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --disable-enforcement
```

Disabling enforcement is not a normal application mode and must not be used to bypass unresolved ownership defects. Re-run `--verify` before enabling it again.

## Phase E — explicit schema hardening

Schema hardening is intentionally **not** part of the automatic WordPress Migrator. MySQL DDL can auto-commit, so the hardening step is an explicit operator action with fail-closed preflight and post-DDL verification rather than an automatic deployment side effect.

Check readiness first:

```bash
php scripts/enterprise_tenant_schema_harden.php \
  --wp-root=/path/to/wordpress \
  --status
```

The hardener reports ready only when all of the following are true:

- core ownership verification is green;
- runtime tenant enforcement is already enabled;
- there are no duplicate customer `internal_code` values inside the same tenant;
- there are no duplicate `contract_number` values inside the same tenant.

Apply only after the status/preflight is green:

```bash
php scripts/enterprise_tenant_schema_harden.php \
  --wp-root=/path/to/wordpress \
  --apply
```

The hardener then:

- converts `tenant_id` to `NOT NULL` on all nine core-owned tables;
- adds `UNIQUE (tenant_id, internal_code)` for non-null customer internal codes;
- adds `UNIQUE (tenant_id, contract_number)` for contract numbers;
- removes the legacy global uniqueness indexes only after the tenant-scoped unique indexes exist;
- adds tenant-first indexes for customer, contract, financial-child, history, payment, collection and follow-up query shapes;
- verifies that no core tenant column remains nullable, required indexes exist, and legacy global unique indexes are gone;
- persists the hardened marker only after verification succeeds.

The operation is designed to be idempotently re-runnable after partial DDL execution: existing indexes are detected before add/drop operations, and a failed verification does not mark the schema hardened. Operators must still use a normal database backup/change window because DDL rollback is not guaranteed.

After hardening, two different tenants may use the same customer internal code or contract number where designed, while duplicate values inside one tenant remain prohibited.

## Reference data and non-core tables

Payment methods, notifications/delivery state, audit storage, import jobs, deletion/suppression records and other independently queried system tables require their own ownership decisions. Some reference data may be platform-global with tenant overrides rather than duplicated per tenant. These areas must not be assigned ownership mechanically just because the core graph uses `tenant_id`.

Payment methods remain platform-global in this phase. Audit records receive server-derived tenant attribution in `context_json`; making the audit table itself independently tenant-owned/filterable is a separate non-core ownership task.

## Safe Contract separation

All ownership migration, runtime enforcement and schema-hardening code in this document belongs only to `enterprise-safecontracts`. It must not be backported to Safe Contract `main` unless the product owner explicitly requests that exact transfer and its client-data migration impact is separately reviewed.
