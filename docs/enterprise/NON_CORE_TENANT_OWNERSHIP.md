# ESC Non-Core Tenant Ownership

This document defines the executable ownership boundary for ESC data that is not part of the core customer/contract/payment graph.

## Ownership classes

### Tenant-owned
- notification rules and templates;
- ESC device tokens/registrations;
- notification deliveries and schedule rows;
- notification suppressions;
- notification read-state user metadata;
- notification dispatch-time and email enable/from-name/from-address business settings;
- import runs and import errors;
- tenant-qualified import private-storage keys and paths;
- business-tenant audit rows when browsed/exported independently.

### Platform-global in this phase
- payment-method/reference catalog;
- Firebase application/project identity, project-keyed OAuth access-token cache, and service-account material for the deployed ESC environment;
- audit events whose subject is intentionally global, including payment-method, role and system events plus the current global WordPress user-role-change event.

Platform-global rows must not receive a fake business tenant merely to make a migration count reach zero. Platform-global audit rows remain distinguishable from tenant audit rows and must never be silently reassigned during migration. Firebase deployment identity is global because it belongs to the ESC environment, not to a business tenant; FCM registration tokens and notification routing/read-state/configuration remain tenant-owned business data.

## Rollout sequence

**expand → explicit/derived backfill → verify → runtime enforce → adversarial validate → harden**

Migration `1.17.0` is expand-only. It adds nullable `tenant_id` plus `esc_tenant_record (tenant_id, id)` to rules, templates, device tokens, deliveries, schedules, suppressions, import runs/errors and audit. It does not assign legacy ownership, change uniqueness or enable runtime enforcement.

The stages are intentionally ordered. Runtime enforcement begins only after ownership verification is green, while the schema is still reversible/nullable. The adversarial-validation stage then proves tenant predicates, known-ID isolation, stale/foreign membership rejection, queue iteration, import boundaries, notification fan-out, settings/read-state key separation and audit behavior under real/adversarial execution. Schema hardening is the final persistence constraint only after those runtime gates are green.

## Backfill

Deterministic ownership is derived only from authoritative live relationships:

- schedule → `payment_id` → payment tenant;
- delivery → `payment_id`, then already-owned `rule_id` when needed;
- import error → `import_run_id` → import-run tenant;
- suppression with `scope_type=payment` → `scope_id` → payment tenant;
- suppression with `scope_type=contract` → `scope_id` → contract tenant;
- deterministic audit parents: customer, contract, payment, collection, `import_run`, notification schedule.

Rules, templates, devices, direct deliveries without an owned parent, import runs, unresolved suppressions and unresolved tenant-required audit rows are roots and require **explicit reviewed mapping/recreation**. Do **not** copy every legacy notification rule/template/token/import into every tenant. Never clone ambiguous notification configuration as a guessed fan-out merely to eliminate nullable rows.

Deterministic derivation:

```bash
php scripts/enterprise_noncore_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --derive
```

Explicit reviewed roots:

```bash
php scripts/enterprise_noncore_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --tenant-id=17 \
  --roots=rules,templates,devices,deliveries,imports,suppressions,audit \
  --verify
```

Existing ownership is never overwritten. Parent/child cross-tenant mismatch causes rollback. Partial explicit mappings may commit with `ready=false` until all tenant-required roots are reviewed.

Verification only:

```bash
php scripts/enterprise_noncore_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --verify
```

`ready=true` requires all tenant-required non-core rows to be owned and all deterministic relationships to be tenant-consistent. Intentionally global audit rows are reported separately and do not block readiness. Runtime enforcement must remain disabled while unresolved tenant-required roots or cross-tenant mismatches exist.

## Runtime enforcement contract

Runtime non-core enforcement may be enabled only after ownership verification is green. It must be exercised and pass adversarial validation before schema hardening so application-level isolation is proven independently of database NOT NULL/scoped-unique constraints.

When enabled:

- tenant-owned repositories require a locked authorized `TenantContext`;
- known IDs never bypass tenant predicates;
- device register/disable/fanout operates inside the current tenant only;
- recipient resolution and the final push/email transport hop re-check active membership in the current active tenant so stale schedules cannot notify removed/foreign users;
- scheduler/cron execution enumerates tenants explicitly and locks/resets context per tenant;
- every background job carries or establishes one explicit tenant context and fails closed if it cannot;
- imports and tenant audit reads/writes are tenant-bound;
- import error insertion verifies that the parent run belongs to the active tenant;
- notification read-state, dispatch-time, email settings, scheduler state and other tenant-owned option/meta keys include tenant identity;
- generated/import/export/storage keys for tenant-owned data include tenant identity;
- cross-tenant parent/child relations are rejected even if a caller supplies a known numeric ID;
- platform-global catalog/audit/Firebase operations use an explicit global path rather than impersonating a business tenant.

Firebase deployment credentials remain environment-global and separate from business-tenant data. FCM registration tokens and notification routing records are tenant-owned business data even though the Firebase project itself is environment-global.

### Background isolation requirements

For scheduler/cron and other asynchronous execution:

1. enumerate eligible tenant IDs from the tenant registry, never from unscoped business rows;
2. lock exactly one tenant into `TenantContext` before tenant-owned reads/writes;
3. use tenant-qualified lock, dedupe, cache, setting and cursor keys;
4. process only that tenant's notification schedules, imports and other queued work;
5. clear/reset tenant context in `finally`-equivalent cleanup before moving to the next tenant;
6. record tenant identity in operational/audit evidence;
7. fail closed when tenant identity is absent, invalid or changes during a unit of work.

A background worker must never execute one unscoped query over all tenants and then filter rows in application memory.

## Explicit schema hardening

Hardening is not part of the automatic WordPress migrator. Run it only after ownership verification, runtime enforcement **and adversarial validation** are green and under a normal database backup/change window.

```bash
php scripts/enterprise_noncore_tenant_schema_harden.php \
  --wp-root=/path/to/wordpress \
  --status
```

Preflight verifies no duplicates exist inside the same tenant for the actual schema keys and refuses readiness unless runtime enforcement is enabled:

- notification rule `code`;
- notification template `code`;
- device `token_hash`;
- schedule `(rule_id, payment_id, attempt_no)`;
- suppression `(scope_type, scope_id)`;
- import-run `storage_key`.

Apply only after runtime/adversarial gates and preflight are green:

```bash
php scripts/enterprise_noncore_tenant_schema_harden.php \
  --wp-root=/path/to/wordpress \
  --apply
```

The hardener:

- makes `tenant_id` NOT NULL for rules, templates, devices, deliveries, schedules, suppressions, import runs and import errors;
- leaves audit `tenant_id` nullable so defined platform-global audit rows remain representable;
- replaces global unique indexes with tenant-scoped uniqueness for rule/template code, device token hash, schedule attempts, suppression scope and import storage key;
- adds tenant-first indexes using live fields such as device `is_active`, schedule `scheduled_for`, suppression `scope_type/scope_id`, and import-error `import_run_id`;
- removes legacy global unique indexes only after scoped replacements exist;
- verifies structure before persisting the hardening marker.

Before hardening, runtime enforcement must already prevent cross-tenant device-token overwrite/read/fan-out at the repository/service layer. After hardening, the scoped unique `(tenant_id, token_hash)` constraint additionally allows one physical token value to be represented independently under different tenants and prevents same-tenant duplicates at the database layer.

## Verification evidence

P1-006 is not complete from migration DDL alone. Evidence must show all of the following:

- unresolved tenant-required root count is zero;
- deterministic parent/child ownership verification is green;
- adversarial known-ID requests cannot read/write another tenant's notification/import/audit rows;
- registering/disabling a device token in tenant A cannot mutate tenant B;
- role/explicit/assigned-accountant recipients are filtered to active membership in the current tenant;
- stale membership is revalidated again at the final push/email transport boundary;
- notification fan-out consumes only current-tenant registrations and schedules;
- notification read-state and business settings cannot collide across tenants;
- imports cannot attach rows or errors to another tenant's run and new import storage keys/paths are tenant-qualified;
- scheduler/background execution cannot continue without a locked tenant context and cannot leak context/options into the next tenant;
- tenant audit views preserve tenant context and platform-global audit rows stay explicitly global;
- Firebase project/service-account/OAuth cache remains explicitly environment-global rather than being given a fake tenant;
- schema-hardening preflight is green before DDL and post-DDL verification is green afterward;
- full backend regression remains green.

## Safe Contract separation

All work here is ESC-only on `enterprise-safecontracts`. Safe Contract `main` remains unchanged unless a separately reviewed transfer is explicitly requested.
