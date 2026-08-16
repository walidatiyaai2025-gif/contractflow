# ESC Non-Core Tenant Ownership

This document defines the executable ownership boundary for ESC data that is not part of the core customer/contract/payment graph.

## Ownership classes

### Tenant-owned
The following records can be queried, mutated, delivered, exported or processed independently and therefore require direct tenant ownership:

- notification rules;
- notification templates;
- device tokens/registrations used for ESC notifications;
- notification deliveries;
- notification schedule rows;
- notification suppressions;
- import runs;
- import errors;
- business-tenant audit rows when browsed/exported independently.

### Platform-global in this phase

- payment-method/reference catalog;
- Firebase application/project identity and service-account material for the deployed ESC environment;
- platform-global audit events whose subject is intentionally global, including payment-method, role and system administration events, plus the current global WordPress user-role change event.

Environment credentials identify the ESC deployment, not a business tenant. They must not be duplicated mechanically per tenant. Likewise, platform-global audit rows must not be assigned a fake business tenant just to make a migration count reach zero.

## Migration sequence

Non-core tenancy follows the same staged safety model as core ownership:

**expand → explicit/derived backfill → verify → harden → runtime enforce**

Migration `1.17.0` performs **expand only**. It adds nullable `tenant_id` and an `esc_tenant_record (tenant_id, id)` lookup index to the tenant-owned tables listed above. It does not assign existing rows, change uniqueness, make ownership non-null, alter delivery behavior or activate runtime enforcement.

## Backfill rules

Ownership must be derived only where the relationship is deterministic:

- notification delivery and schedule rows derive ownership from their payment tenant;
- import errors derive from their import run;
- payment suppressions derive from payment ownership and unresolved rule suppressions may derive from an already-owned rule;
- audit rows with deterministic parent IDs may derive from customer, contract, payment, collection, import-run or notification-schedule ownership.

The following roots cannot be guessed from legacy data and need explicit reviewed mapping/recreation:

- notification rules/templates;
- device tokens;
- import runs;
- unresolved suppressions;
- tenant-required audit rows that cannot be tied deterministically to an owned parent.

Do **not** copy every legacy notification rule/template/token/import into every tenant. A default tenant may be used only after an operator has reviewed and declared that the selected legacy root data belongs to that tenant.

### Deterministic derivation

```bash
php scripts/enterprise_noncore_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --derive
```

This operation is transactional and never assigns notification-rule, template, device-token or import-run roots.

### Explicit root mapping

```bash
php scripts/enterprise_noncore_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --tenant-id=17 \
  --roots=rules,templates,devices,imports \
  --verify
```

Available root groups are `rules`, `templates`, `devices`, `imports`, `suppressions` and `audit`. The command updates only unowned rows in the named groups, then derives children from authoritative parents. Existing ownership is never overwritten.

The `audit` root group applies only to audit events classified as tenant-required. It deliberately excludes platform-global audit classes. Partial reviewed mappings may commit with `ready=false`; that is expected until every tenant-owned root has been mapped.

If any cross-tenant parent/child mismatch exists after a derivation or reviewed root assignment, the transaction is rolled back.

### Verification

```bash
php scripts/enterprise_noncore_tenant_backfill.php \
  --wp-root=/path/to/wordpress \
  --verify
```

The report separates tenant-owned rows still missing ownership, cross-tenant parent/child mismatches and intentionally unowned platform-global audit rows. `ready=true` means tenant-required non-core ownership is complete and internally consistent; platform-global audit rows do not block readiness.

## Explicit schema hardening

Non-core hardening is **not** an automatic WordPress migration. Run it only after the ownership verifier is green and within a normal database backup/change window.

Check status/preflight:

```bash
php scripts/enterprise_noncore_tenant_schema_harden.php \
  --wp-root=/path/to/wordpress \
  --status
```

Preflight blocks hardening if there are duplicate values inside the same tenant for:

- notification rule code;
- notification template code;
- device token hash;
- delivery idempotency key;
- schedule rule/payment/attempt tuple;
- suppression uniqueness tuple.

Apply only after preflight is green:

```bash
php scripts/enterprise_noncore_tenant_schema_harden.php \
  --wp-root=/path/to/wordpress \
  --apply
```

The hardener then:

- makes `tenant_id` NOT NULL for rules, templates, devices, deliveries, schedules, suppressions, import runs and import errors;
- intentionally leaves audit `tenant_id` nullable so defined platform-global audit events remain representable;
- replaces global unique indexes with tenant-scoped unique indexes for rule/template code, device token hash, delivery idempotency key, schedule attempts and suppressions;
- adds tenant-first indexes for device lookup, delivery history, due schedules, import status/errors and audit browsing;
- removes legacy global unique indexes only after scoped replacements exist;
- verifies the resulting structure before persisting the non-core hardened marker.

This ordering is required before runtime notification/device enforcement because a single physical device token must be representable independently in more than one tenant without moving or overwriting another tenant's registration.

## Runtime requirements after enforcement

- every non-core tenant-owned repository requires one locked authorized TenantContext;
- known IDs never bypass tenant predicates;
- notification fanout selects devices only from the current tenant;
- a WordPress user may have device registrations in multiple tenants without cross-tenant delivery;
- scheduler/cron execution enumerates tenants explicitly and locks one tenant before reading rules, payments, schedules or deliveries;
- background work fails closed when tenant context is missing or stale;
- import run/error reads, mapping and execution are tenant-bound;
- private import storage keys include tenant identity;
- audit reads/exports use direct tenant ownership rather than text matching context JSON;
- cache keys for tenant-owned data include tenant identity;
- generated/export/storage object keys cannot collide across tenants.

Runtime non-core enforcement must not be enabled until both the ownership verifier and schema hardener are green.

## Safe Contract separation

All non-core tenancy work is ESC-only on `enterprise-safecontracts`. Safe Contract `main` remains unchanged unless the product owner explicitly requests a separately reviewed transfer.
