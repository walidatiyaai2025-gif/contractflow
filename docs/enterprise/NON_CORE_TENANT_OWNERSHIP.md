# ESC Non-Core Tenant Ownership

This document defines the executable ownership boundary for ESC data that is not part of the core customer/contract/payment graph.

## Ownership classes

### Tenant-owned
- notification rules and templates;
- ESC device tokens/registrations;
- notification deliveries and schedule rows;
- notification suppressions;
- import runs and import errors;
- business-tenant audit rows when browsed/exported independently.

### Platform-global in this phase
- payment-method/reference catalog;
- Firebase application/project identity and service-account material for the deployed ESC environment;
- audit events whose subject is intentionally global, including payment-method, role and system events plus the current global WordPress user-role-change event.

Platform-global rows must not receive a fake business tenant merely to make a migration count reach zero.

## Rollout sequence

**expand → explicit/derived backfill → verify → harden → runtime enforce**

Migration `1.17.0` is expand-only. It adds nullable `tenant_id` plus `esc_tenant_record (tenant_id, id)` to rules, templates, device tokens, deliveries, schedules, suppressions, import runs/errors and audit. It does not assign legacy ownership, change uniqueness or enable runtime enforcement.

## Backfill

Deterministic ownership is derived only from authoritative live relationships:

- schedule → `payment_id` → payment tenant;
- delivery → `payment_id`, then already-owned `rule_id` when needed;
- import error → `import_run_id` → import-run tenant;
- suppression with `scope_type=payment` → `scope_id` → payment tenant;
- suppression with `scope_type=contract` → `scope_id` → contract tenant;
- deterministic audit parents: customer, contract, payment, collection, `import_run`, notification schedule.

Rules, templates, devices, direct deliveries without an owned parent, import runs, unresolved suppressions and unresolved tenant-required audit rows are roots and must be mapped/recreated explicitly. Never copy all legacy rules/templates/tokens/imports into every tenant.

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

`ready=true` requires all tenant-required non-core rows to be owned and all deterministic relationships to be tenant-consistent. Intentionally global audit rows are reported separately and do not block readiness.

## Explicit schema hardening

Hardening is not part of the automatic WordPress migrator. Run it only after ownership verification is green and under a normal database backup/change window.

```bash
php scripts/enterprise_noncore_tenant_schema_harden.php \
  --wp-root=/path/to/wordpress \
  --status
```

Preflight verifies no duplicates exist inside the same tenant for the actual schema keys:

- notification rule `code`;
- notification template `code`;
- device `token_hash`;
- schedule `(rule_id, payment_id, attempt_no)`;
- suppression `(scope_type, scope_id)`;
- import-run `storage_key`.

Apply only after preflight is green:

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

The device scoped unique is mandatory before runtime enforcement: one physical token can then be represented independently under more than one tenant instead of one tenant registration overwriting another.

## Runtime enforcement contract

Runtime non-core enforcement may be enabled only after both ownership verification and schema hardening are green.

When enabled:

- tenant-owned repositories require a locked authorized `TenantContext`;
- known IDs never bypass tenant predicates;
- device register/disable/fanout operates inside the current tenant only;
- scheduler/cron enumerates active tenants explicitly and locks/resets context per tenant;
- background work fails closed without tenant context;
- imports and tenant audit reads/writes are tenant-bound;
- cache keys and generated/import/export/storage keys for tenant-owned data include tenant identity.

Firebase deployment credentials remain environment-global and separate from business-tenant data.

## Safe Contract separation

All work here is ESC-only on `enterprise-safecontracts`. Safe Contract `main` remains unchanged unless a separately reviewed transfer is explicitly requested.
