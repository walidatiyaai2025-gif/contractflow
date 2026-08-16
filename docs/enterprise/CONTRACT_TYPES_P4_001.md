# ESC-P4-001 — Contract Type catalog foundation

## Purpose

P4-001 adds tenant-owned Contract Type configuration to Enterprise Safe Contracts without changing the inherited contract schema or lifecycle. Contract Type is the configuration anchor that later P4 tasks may use for templates and, in later phases, custom fields/workflows.

This task is ESC-only and does not alter Safe Contract/main.

## Boundary

- **Contract Type**: tenant-owned configuration identity such as `construction.main` or `it_support`.
- **Contract Template**: not implemented in P4-001; planned separately.
- **Existing Contract**: continues to use the inherited schema and `ContractStatus` behavior in this task.

No existing contract receives a `contract_type_id` and no existing contract is backfilled or reclassified here.

## Schema

Schema `1.25.0` adds `safecontracts_contract_types` with mandatory tenant ownership, server-generated UUIDv4, tenant-local stable `type_code`, display name, optional description/category, active/inactive status, bounded metadata, actor IDs and timestamps.

`(tenant_id, type_code)` is unique. Tenant-first indexes cover status/name and category/status listing.

## Stable code rule

`type_code` is normalized to lowercase machine-code form. Whitespace becomes `_`; supported characters are letters, numbers, `.`, `_` and `-`. The code is accepted only at creation and is intentionally absent from the update contract and update SQL.

This prevents templates, custom fields, workflows or integrations added later from depending on a mutable business key.

## Tenant / authorization boundary

Repository access fails closed unless Enterprise core tenant enforcement is active and a tenant context is locked. Every object/list/mutation predicate derives tenant ownership from `TenantContextStore`.

Reads require global `ACCESS` plus the tenant-role capability ceiling. Mutations require global `MANAGE_REFERENCE_DATA` plus the tenant-role ceiling.

Caller-supplied `tenant_id` and UUID are rejected.

## Lifecycle

Contract Type status is `active` or `inactive`. Deactivation is non-destructive and idempotent. General metadata updates do not alter the stable code or status.

P4-001 deliberately does not modify the inherited `ContractStatus` state machine. Contract Type-specific lifecycle configuration, if introduced, must be a later explicit task with compatibility/migration analysis.

## Full Impact Review

- Business/domain: adds generic tenant Contract Type catalog only; no industry-specific table fork.
- Tenant/isolation: mandatory tenant key, fail-closed repository, tenant predicates on reads/writes.
- Database/migrations: additive `1.25.0`; tenant-local code uniqueness; no existing contract-table alteration.
- Backend: create/find/search/update display metadata/deactivate.
- Authorization: `ACCESS` reads; `MANAGE_REFERENCE_DATA` writes; tenant-role ceiling enforced.
- REST/admin/Flutter: N/A in foundation; no surface exposed.
- Android/release identity: N/A.
- Landing/public claims: N/A; not public from this task.
- Design system/theme: N/A.
- Feature registry/plans: no entitlement/public-lifecycle claim introduced.
- Search: tenant-scoped, optional status/text filters, max 100 rows, bounded offset.
- Reports/import/export/bulk: N/A.
- Notifications/audit: actor/timestamps plus domain hooks; rich audit integration belongs to consuming surfaces.
- Localization/timezone/currency: UTF-8 display metadata only; no currency/timezone semantics introduced.
- Security/privacy: tenant/UUID spoofing rejected; metadata bounded to 20,000 encoded bytes; type code machine-policy validated.
- Performance/concurrency: tenant-first indexes and DB uniqueness protect duplicate code races.
- Automated tests: schema registration, policy, tenant lock, spoofing, search bounds, immutable code, cross-tenant ID, deactivate idempotency, permission ceilings and unchanged legacy contract/lifecycle source.
- CI/build/release: regression explicitly wired into `scripts/test-php.sh`; exact-source ESC Foundation Gate required.
- Backward compatibility: existing contracts and `ContractStatus` remain unchanged.
- Rollback: additive code/table only; no destructive automatic rollback introduced.

## Deferred P4 work

Contract Templates, versioning/publishing, contract-to-type binding, existing-contract migration/backfill, custom-field binding and workflow binding are separate bounded tasks.
