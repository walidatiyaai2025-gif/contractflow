# ESC-P3-004 — Department / Team hierarchy foundation

## Scope

This task adds an Enterprise Safe Contracts internal organization-unit foundation. It is ESC-only and does not alter Safe Contract/main.

An organization unit is an **internal structure record inside one locked SaaS tenant**. It is not:

- a SaaS tenant/workspace;
- a Party/legal counterparty;
- a Party business role;
- a WordPress user or tenant membership;
- a contract owner/routing assignment.

The baseline unit types are deliberately limited to `department` and `team`.

## Schema

Schema version `1.22.0` adds `safecontracts_org_units` with mandatory `tenant_id`, server-generated UUIDv4 identity, optional tenant-local `unit_code`, name, unit type, optional parent, status, bounded metadata and audit actor/timestamps.

Indexes are tenant-first for listing, type filtering and child/hierarchy lookup. `unit_code` uniqueness is tenant-local. Empty codes are persisted as SQL `NULL`, so multiple uncoded units can coexist.

## Tenant and authorization boundary

`OrgUnitRepository` fails closed unless Enterprise core tenant enforcement is enabled and `TenantContextStore` contains a locked tenant. The caller never supplies tenant ownership.

Reads use tenant-aware `ACCESS`. Mutations use tenant-aware `MANAGE_REFERENCE_DATA` for this foundation phase.

Every object lookup, search, update and deactivate predicate includes the locked tenant. Every candidate parent is resolved through the same tenant-scoped repository before mutation, so a valid numeric unit ID from another tenant is not sufficient authorization.

## Hierarchy safety

A unit may be a root or reference one parent unit in the same tenant.

The service rejects:

- non-positive explicit parent identifiers;
- missing/foreign parent identifiers;
- self-parenting;
- reparenting under a descendant;
- pre-existing cyclic ancestry encountered while validating a parent;
- ancestry deeper than `OrgUnitPolicy::MAX_HIERARCHY_DEPTH` (64).

Cycle/depth validation uses bounded tenant-scoped ancestry reads. No unbounded recursive SQL or caller-provided hierarchy path is trusted.

## Compatibility boundaries

P3-004 does not modify `safecontracts_parties`, Party roles, Party relationships, `safecontracts_customers`, contracts, tenant memberships or user authorization assignments.

User-to-unit membership, unit managers, contract ownership/routing, REST/admin/Flutter UI and legacy customer/Party migration remain separate tasks.

## Full Impact Review

- Business/domain: adds internal Department/Team identity only; Party and SaaS tenant semantics remain separate.
- Tenant/isolation: mandatory schema ownership; repository fails closed without locked Enterprise tenant; parent validation is tenant-scoped.
- Database/migrations: additive migration `1.22.0`; no destructive/backfill operation; tenant-first indexes and tenant-local optional code uniqueness.
- Backend: create/find/search/update/deactivate primitives plus bounded parent/cycle validation.
- Authorization: `ACCESS` reads and `MANAGE_REFERENCE_DATA` mutations; no new broad capability introduced.
- REST API: N/A in this foundation task; no route exposed.
- WordPress/admin UI: N/A; no admin surface exposed.
- Flutter/mobile/offline: N/A; no mobile surface or local-state contract introduced.
- Android identity/environments: N/A; no package/Firebase/release identity change.
- Landing/public messaging: N/A; capability remains development/internal and must not be marketed as public from this task.
- Design system/theme: N/A; no UI.
- Feature registry/plans/entitlements: no public/plan claim introduced; later exposure must be registered explicitly.
- Search/filter/sort: repository text search is tenant-scoped and capped to 100 rows with offset capped at 100000.
- Reports/import/export/bulk: N/A.
- Notifications/escalation: N/A.
- Audit/compliance: creator/updater IDs and timestamps are persisted; domain hooks are fired for create/update/deactivate. Rich audit-event integration may be added with the consuming surface.
- Documents/storage: N/A.
- Localization/timezone/currency: names are UTF-8 text; no locale/timezone/currency semantics introduced.
- Security/privacy/rate limits: no public endpoint; reserved fields (`tenant_id`, UUID) are rejected by the mutation contract; metadata is bounded to 20000 encoded bytes.
- Performance/concurrency: tenant-first indexes, bounded search and bounded hierarchy traversal; DB unique key protects tenant-local code collisions.
- Idempotency/concurrency: update/deactivate are tenant-scoped; duplicate unit-code concurrency is delegated to the DB unique key. This foundation intentionally does not invent caller idempotency keys without an API surface.
- Automated tests: `enterprise_org_units_p3_004.php` covers schema registration, policy, tenant lock, spoofing, parent ownership, cycle/depth safety, pagination, permissions and mutation tenant predicates; wired into `scripts/test-php.sh`.
- CI/build/release: ESC Foundation Gate remains the acceptance gate; no artifact identity path changed.
- Backward compatibility: additive only; existing Party/customer/contract data is untouched.
- Rollback: rollback is code/version rollback before production use; destructive automatic table removal is intentionally not introduced.

## Acceptance evidence

Implementation is not considered complete until an exact-source ESC Foundation Gate passes both `esc-foundation` and `esc-mobile`, and Issue #452 records that evidence.
