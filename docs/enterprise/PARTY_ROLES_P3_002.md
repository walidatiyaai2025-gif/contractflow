# ESC-P3-002 — Party Business-Role Assignments

## Purpose

ESC-P3-002 adds tenant-owned business-role assignments on top of the generic Party identity introduced by ESC-P3-001.

The model deliberately separates two questions:

- `party_kind`: **what the entity is** (`organization`, `individual`, `government`, `other`).
- Party role: **how that Party participates in business** (`customer`, `supplier`, `vendor`, and so on).

A single Party may hold several business roles in the same tenant without duplicating the Party identity record. For example, one organization may simultaneously be a customer, supplier and contractor.

## Baseline role policy

The initial stable role codes are:

- `customer`
- `supplier`
- `vendor`
- `contractor`
- `subcontractor`
- `agent`
- `consultant`
- `landlord`
- `lessee`
- `buyer`
- `seller`
- `other`

Role codes are normalized to lower case and unsupported values fail closed.

### Why `lessee`, not `tenant`

`tenant` already has a security-critical meaning in ESC: the SaaS tenant/workspace that owns data and defines the isolation boundary. Using the same token for a real-estate contractual role would create ambiguity in APIs, audit logs, reporting and authorization code.

The real-estate counterparty role is therefore named `lessee`. The SaaS concept remains `tenant` exclusively.

Intrinsic Party kinds such as `organization` are also not valid business-role codes.

## Schema

Migration `1.20.0` adds `safecontracts_party_roles` with:

- `id` — internal row ID;
- mandatory `tenant_id`;
- mandatory `party_id`;
- stable `role_code`;
- `active` / `inactive` status;
- assignment/revocation actor fields;
- created/updated/revoked timestamps.

Constraints and indexes:

- unique `(tenant_id, party_id, role_code)` prevents duplicate role rows;
- `(tenant_id, role_code, status, party_id)` supports tenant role queries;
- `(tenant_id, party_id, status, id)` supports Party role listing.

The assignment table is additive and does not alter `safecontracts_parties` or `safecontracts_customers`.

## Tenant isolation boundary

`PartyRoleRepository` requires:

1. ESC core tenant enforcement; and
2. a locked `TenantContext`.

There is no unscoped fallback.

`PartyRoleService` also verifies that the target Party exists in the current locked tenant through `PartyRepository::find()` before listing, assigning or revoking a role. A numeric Party ID from another tenant therefore cannot be used as authorization.

Every role-table read/mutation includes the server-authoritative tenant ID. No client-supplied tenant field is accepted by this service layer.

## Authorization

For this foundation task:

- role reads require tenant-aware `Capabilities::ACCESS`;
- assignment/revocation requires tenant-aware `Capabilities::MANAGE_REFERENCE_DATA`.

These reuse the current ESC authorization ceiling without inventing an unreviewed Party-specific capability. A later P3 authorization/UI task may introduce narrower permissions if the product surface requires them.

## Idempotent assignment and reactivation

Assignment uses one atomic `INSERT ... ON DUPLICATE KEY UPDATE` statement against the unique `(tenant_id, party_id, role_code)` key.

Behavior:

- no existing row → create active assignment;
- existing inactive row → reactivate it and clear revoke metadata;
- existing active row → remain active and preserve the original assignment metadata.

This makes repeated assignment safe and prevents duplicate rows under concurrent workers.

## Idempotent non-destructive revoke

Revoke does not delete the assignment row. It updates the exact tenant + Party + role row to `inactive`.

Revocation actor/time metadata changes only when the prior row was active. Repeating the same revoke therefore remains safe and does not continuously rewrite historical revocation metadata.

Keeping the row supports future audit/history/reporting work and allows assignment reactivation without creating duplicate identity rows.

## Legacy Customer compatibility boundary

Assigning the `customer` Party role **does not**:

- create a `safecontracts_customers` row;
- update a legacy customer;
- link a Party to a legacy customer;
- migrate contract/customer foreign keys;
- change current Safe Contract customer behavior.

Legacy customer compatibility/backfill remains a separate bounded P3 task. That future work must design deterministic tenant ownership, idempotent linking/backfill, dependency migration and rollback before touching existing customer data.

P3-002 therefore creates a semantic business role only; it is not a compatibility bridge.

## Party relationships are separate

This task does not introduce a general Party-to-Party relationship graph.

Examples such as parent/subsidiary, represented-by, contact-for, guarantor-of or other relational edges require their own model because they have two Party endpoints, directionality, relationship type, dates and possibly contract context. Mixing them into simple role assignments would make both concepts less precise.

A later P3 task should model those relationships explicitly.

## API/UI/mobile/public impact

P3-002 adds no REST routes, WordPress admin page or Flutter screen. It is a domain/storage foundation.

The landing page and future Feature Registry must not advertise Party role management as Public merely because the underlying schema/service exists.

## Full Impact Review

- Business/domain model: separates reusable Party identity from multiple business roles.
- Tenant model/isolation: mandatory tenant ownership; Party existence and every role operation are current-tenant scoped.
- Database/migrations/indexes: additive schema `1.20.0`, unique tenant+Party+role, tenant-first indexes.
- Backend logic: new explicit role policy, repository and service; Party core itself is unchanged.
- Authorization/scopes/roles: tenant-aware ACCESS for reads and MANAGE_REFERENCE_DATA for mutations.
- REST/API compatibility: N/A; no endpoint added.
- WordPress/admin UI: N/A.
- Flutter/mobile/offline: N/A.
- Android identity/environments: N/A.
- Landing/public messaging: N/A; no public claim.
- Design/theme: N/A.
- Feature registry/plans/entitlements: N/A for foundation.
- Search/filter/sort/bulk: indexed role lookup foundation only; no public bulk operation.
- Reports/import/export: N/A now; later role-aware reports/imports must retain tenant predicates.
- Notifications/escalation: N/A.
- Audit/compliance: assignment/revocation actors and timestamps retained; rows are not destructively deleted.
- Documents/storage: N/A.
- Localization/RTL/LTR/timezone/currency: stable machine role codes only; future labels must be localized separately.
- Security/privacy: no caller tenant selector; target Party must exist in locked tenant; unsupported role codes fail closed.
- Performance/concurrency/idempotency: unique key + atomic upsert; non-destructive idempotent revoke; tenant-first indexes.
- Automated tests: `tests/php/enterprise_party_roles_p3_002.php` plus full ESC backend regression.
- Documentation/onboarding: this document defines role semantics and compatibility boundaries.
- CI/build/release/rollback: additive schema; reverting code can leave unused role rows/table without harming legacy customer behavior.
- Backward compatibility: no customer migration, no Party-kind rewrite, no Safe Contract/main change.

## Regression evidence

`tests/php/enterprise_party_roles_p3_002.php` validates:

- mandatory tenant/Party ownership and schema indexes;
- explicit role policy and normalization;
- `tenant` and intrinsic Party kinds are not accepted as business roles;
- fail-closed behavior without core enforcement or locked tenant context;
- Party ownership validation before role reads/mutations;
- multiple roles per Party;
- tenant-scoped role listing;
- atomic duplicate/re-activation assignment;
- repeated assignment idempotency;
- cross-tenant Party ID rejection before mutation;
- non-destructive tenant-scoped revoke and repeated revoke safety;
- authorization denial before data access;
- no mutation of `party_kind` or legacy `safecontracts_customers`.
