# ESC Non-Core Tenant Ownership

This document defines the first executable ownership boundary for ESC data that is not part of the core customer/contract/payment graph.

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
- audit log rows when browsed/exported independently.

### Platform-global in this phase

- payment-method/reference catalog;
- Firebase application/project identity and service-account material for the deployed ESC environment.

Environment credentials identify the ESC deployment, not a business tenant. They must not be duplicated mechanically per tenant.

## Migration sequence

Non-core tenancy follows the same staged safety model as core ownership:

**expand → explicit/derived backfill → verify → runtime enforce → harden**

Migration `1.17.0` performs **expand only**. It adds nullable `tenant_id` and an `esc_tenant_record (tenant_id, id)` lookup index to the tenant-owned tables listed above. It does not assign existing rows, change uniqueness, make ownership non-null, alter delivery behavior or activate runtime enforcement.

## Backfill rules

Ownership must be derived only where the relationship is deterministic:

- notification delivery and schedule rows may derive ownership from their payment/contract tenant;
- import errors derive from their import run;
- audit rows may derive from a deterministic tenant-owned parent or from reviewed server-attributed tenant evidence.

The following roots cannot be guessed from legacy data and need explicit reviewed mapping/recreation:

- notification rules/templates;
- suppressions;
- device tokens;
- import runs.

Do **not** copy every legacy notification rule/template/token/import into every tenant. A default tenant may be used only after an operator has reviewed and declared that the legacy root data belongs to that tenant.

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

## Hardening rules

Only after a non-core ownership verifier is green may later work:

- make tenant ownership non-null where appropriate;
- replace global business/config uniqueness with tenant-scoped uniqueness where designed;
- add tenant-first indexes matching delivery, schedule, inbox, import and audit query shapes;
- activate runtime enforcement/background tenant iteration.

No destructive DDL is allowed automatically just because the expand migration has run.

## Safe Contract separation

All non-core tenancy work is ESC-only on `enterprise-safecontracts`. Safe Contract `main` remains unchanged unless the product owner explicitly requests a separately reviewed transfer.
