# ESC-P3-001 — Generic Party Foundation

## Purpose

ESC-P3-001 introduces the first Enterprise Safe Contracts Party domain primitive without destructively rewriting the legacy Safe Contract customer model.

A **Party** is a tenant-owned real-world/legal identity that may participate in contracts and future business relationships. The Party core intentionally separates intrinsic identity from business role.

## Intrinsic Party kind

The initial `party_kind` policy is deliberately small:

- `organization`
- `individual`
- `government`
- `other`

These values describe what the party *is*.

The following concepts are **not** Party kinds and must not be overloaded into `party_kind`:

- customer
- supplier
- vendor
- contractor
- agent
- buyer
- seller
- landlord
- tenant/lessee
- consultant
- subcontractor

Those concepts describe what a Party *does in a business context*. They belong in a future tenant-owned Party role/relationship layer so one organization can be both customer and supplier without duplicated identity records.

## Schema

Migration `1.19.0` adds `safecontracts_parties` with mandatory tenant ownership from day one.

Core fields:

- numeric internal ID;
- server-generated UUIDv4 stable identity;
- mandatory `tenant_id`;
- optional tenant-local `party_code`;
- display and optional legal name;
- intrinsic Party kind;
- optional country, registration number, tax number, email and phone;
- `active` / `inactive` status;
- bounded metadata JSON for non-schema-critical extension data;
- created/updated actor and UTC timestamps.

Indexes/constraints:

- globally unique UUID;
- tenant-local unique `(tenant_id, party_code)`; NULL codes remain allowed for multiple uncoded parties;
- tenant-first status/name index;
- tenant-first kind/name index;
- tenant-first country/registration lookup index.

`tenant_id` is `NOT NULL`. Unlike legacy compatibility tables, the Party repository has no unscoped fallback path.

## Tenant and authorization boundary

`PartyRepository` requires:

1. ESC core tenant enforcement to be enabled; and
2. `TenantContextStore` to contain a locked tenant ID.

Every read/search/update predicate contains the server-authoritative tenant ID. Every create obtains `tenant_id` only from the locked context. The mutation service does not accept `tenant_id` or UUID fields from callers.

Current authorization uses existing tenant-aware capability ceilings:

- reads/search: `Capabilities::ACCESS`;
- create/update: `Capabilities::MANAGE_REFERENCE_DATA`.

This is a deliberate P3-001 bootstrap decision. A later P3 authorization task may introduce narrower Party-specific capabilities after Party roles and administrative surfaces are defined.

## Service validation

`PartyService` validates and normalizes:

- required display name;
- explicit Party kind policy;
- explicit status policy;
- optional two-letter uppercase country code;
- email syntax/length;
- bounded registration, tax, phone and code fields;
- metadata must be array/object-compatible JSON and is capped at 20,000 encoded bytes;
- search text length is bounded;
- pagination is bounded to at most 100 records per request.

Unsupported mutation fields fail closed. UUID is generated server-side as UUIDv4.

## Legacy Customer compatibility strategy

P3-001 **does not change** `safecontracts_customers`, `CustomerRepository`, `CustomerService`, contract foreign keys, customer UI, REST routes or mobile behavior.

This separation is intentional. The legacy customer table originated before ESC tenancy and has historical assumptions such as a globally unique `internal_code`; it later received nullable tenant ownership through the controlled core tenancy expansion. Directly converting it into the generic Party table would mix data migration, domain redesign and compatibility risk in one step.

A later bounded task must design and validate the transition. Recommended sequence:

1. inventory legacy customer usage and all customer foreign keys/API/UI/report/import/export dependencies;
2. introduce an explicit compatibility/link strategy (`customer -> party`) rather than silently reinterpreting IDs;
3. determine deterministic tenant ownership for every legacy customer before any backfill;
4. create or link Party rows idempotently with provenance;
5. verify duplicate-code/name/contact edge cases per tenant;
6. dual-read/compatibility behavior only if required and explicitly tested;
7. migrate dependent domain references in controlled phases;
8. preserve rollback until all affected surfaces are validated;
9. never perform this migration on Safe Contract/main as an incidental ESC synchronization step.

No automatic customer-to-Party backfill is part of P3-001.

## Search and performance

Party search is bounded and tenant-first. The repository supports:

- current-tenant browse ordered by display name + ID;
- current-tenant text match across display name, legal name, Party code and email;
- maximum page size 100;
- bounded offset.

The schema provides tenant-first indexes for the primary browse/status/kind/registration access patterns. If later full-text/fuzzy search requirements exceed this baseline, they should be implemented as a separate ESC search task with measured query plans rather than by removing tenant predicates.

## API/UI/mobile/public impact

P3-001 deliberately exposes no new REST route, WordPress admin page or Flutter screen. It is a domain/storage foundation for later P3 tasks.

The public landing page and Feature Registry must not claim a generic Party management feature as Public merely because the underlying foundation exists.

## Full Impact Review

- Business/domain model: adds generic Party identity; separates intrinsic kind from future roles.
- Tenant model/isolation: mandatory tenant ownership; repository fails closed without ESC core enforcement + locked tenant.
- Database/migrations/indexes: versioned `1.19.0` migration and dedicated tenant-first Party table/indexes.
- Backend business logic: new Party policy/repository/service only; legacy Customer domain unchanged.
- Authorization/scopes/roles: existing tenant-aware ACCESS / MANAGE_REFERENCE_DATA ceilings reused for foundation phase.
- REST/API compatibility: N/A; no endpoint added.
- WordPress/admin UI: N/A; no page added.
- Flutter/mobile/offline state: N/A; no consumer added.
- Android identity/environments: N/A; no Android change.
- Landing/public messaging: N/A; do not market foundation as an available feature.
- Design/theme: N/A.
- Feature registry/plans/entitlements: N/A for foundation; future public surface must register explicitly.
- Search/filter/sort/bulk: bounded repository search primitive only; no public bulk operations.
- Reports/import/export: no behavior change; later compatibility task must review all customer-dependent paths.
- Notifications/escalation: N/A.
- Audit/compliance: created/updated actor/timestamps stored; future Party lifecycle/audit policy may expand events.
- Documents/storage: N/A.
- Localization/RTL/LTR/timezone/currency: Party names are Unicode-capable; no locale-specific UI added.
- Security/privacy: strict field validation; tenant/UUID spoof fields rejected; email/phone/tax/registration remain tenant-owned business data.
- Performance/concurrency/idempotency: tenant-first indexes; tenant code uniqueness; UUID uniqueness; bounded search. Customer backfill intentionally deferred until an idempotent design exists.
- Automated tests: `tests/php/enterprise_party_foundation_p3_001.php` plus full ESC backend regression.
- Documentation/onboarding: this compatibility/model boundary document.
- CI/build/release/rollback: schema additive; rollback can leave unused Party table in place if code is reverted; no destructive customer change.
- Backward compatibility: legacy Customer table/service/UI/API remain untouched by P3-001; no Safe Contract/main port.

## Regression evidence

`tests/php/enterprise_party_foundation_p3_001.php` verifies:

- mandatory tenant ownership and expected schema/indexes;
- Party kind policy excludes business-role terms;
- fail-closed behavior without core enforcement or locked tenant;
- tenant-derived create ownership and server-generated UUIDv4;
- NULL handling for optional tenant code;
- rejection of caller-supplied `tenant_id` and `uuid`;
- tenant predicates in object reads, bounded search and updates;
- cross-tenant update miss cannot mutate data;
- current tenant update succeeds only with tenant predicate;
- ACCESS and MANAGE_REFERENCE_DATA authorization boundaries;
- legacy Customer repository remains separate.
