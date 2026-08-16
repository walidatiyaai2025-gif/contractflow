# ESC-P3-006 — Legacy Customer ↔ Party compatibility bridge

## Purpose

Enterprise Safe Contracts introduces a generic Party model, but the inherited contract model still stores and validates legacy `customer_id`. P3-006 creates an explicit compatibility bridge without rewriting those mature customer/contract paths.

This is an ESC-only foundation. It does not alter Safe Contract/main.

## Identity boundary

- **Legacy Customer** remains the compatibility identity used by current contracts and existing customer administration.
- **Party** remains the generic enterprise counterparty identity used for multi-role enterprise modeling.
- **Customer Party link** states that one tenant-owned legacy Customer and one tenant-owned Party represent the same customer compatibility identity.

A link does not copy, synchronize or overwrite names, contacts, codes, notes or contract references.

## One-to-one mapping

Schema `1.24.0` adds `safecontracts_customer_party_links` with mandatory:

- `tenant_id`
- `customer_id`
- `party_id`
- server-controlled provenance (`manual` baseline)
- linking actor and timestamps

Unique `(tenant_id, customer_id)` and `(tenant_id, party_id)` keys enforce one-to-one compatibility under concurrency.

The foundation deliberately does not expose relink/unlink. If a mapping is wrong, correction needs a later controlled workflow with explicit audit and downstream-impact checks rather than a silent remap.

## Tenant and authorization boundary

The bridge service/repository fail closed unless Enterprise core tenant enforcement is active and a tenant context is locked.

Reads require global `ACCESS` plus the current tenant-role ceiling. Link creation requires global `MANAGE_REFERENCE_DATA` plus the tenant-role ceiling.

Before creating a link the service verifies:

1. the legacy Customer resolves through the existing tenant-aware `CustomerRepository` in the locked tenant;
2. the Party resolves through fail-closed `PartyRepository` in the same locked tenant;
3. the Party already has the active `customer` business role through `PartyRoleRepository`.

The service never assigns/revokes Party roles itself.

## Idempotency and concurrent conflicts

A repeated exact Customer↔Party pair returns successfully without mutation.

Before insertion, both one-to-one directions are checked for conflicts. The repository then uses a unique-key protected `INSERT ... ON DUPLICATE KEY UPDATE id = id`; a concurrent winner is never rewritten. The service re-reads both unique directions after that insert/no-op. If the persisted pair is not exactly the requested pair, the operation fails closed as a concurrency conflict.

## Legacy compatibility guarantees

P3-006 does **not**:

- alter `safecontracts_customers`;
- alter `safecontracts_contracts.customer_id`;
- migrate contracts to Party IDs;
- grant the Party a customer role;
- auto-match by name/code/email;
- bulk backfill existing customers;
- change the inherited global legacy `internal_code` uniqueness rule.

The global legacy Customer code constraint is inherited technical debt and is intentionally not changed in this bridge task because changing it has wider compatibility consequences. A future bounded migration may make legacy customer codes tenant-local only after duplicate/backfill analysis.

## Full Impact Review

- Business/domain: explicit compatibility identity only; Customer and Party remain separate models.
- Tenant/isolation: bridge tenant ownership is mandatory; Customer, Party, Party role and bridge queries are tenant-scoped.
- Database/migrations: additive schema `1.24.0`; two one-to-one unique keys; no ALTER/backfill of legacy tables.
- Backend: find-by-Customer, find-by-Party and immutable/idempotent exact-pair link creation.
- Authorization: `ACCESS` reads and `MANAGE_REFERENCE_DATA` writes, both narrowed by active tenant role.
- REST/admin/mobile: N/A; no surface exposed in this foundation task.
- Android/release identity: N/A.
- Landing/public claims: N/A; do not market compatibility bridge as a user feature.
- Design system/theme: N/A.
- Feature registry/plans: no public/plan entitlement claim introduced.
- Search/report/import/export/bulk: N/A. Automatic/bulk linking is explicitly excluded.
- Notifications/escalation: N/A.
- Audit/compliance: link actor/timestamps plus domain hook; correction workflow remains later work.
- Documents/storage: N/A.
- Localization/timezone/currency: N/A.
- Security/privacy: IDs are never authorization; caller cannot supply tenant or provenance; Party customer role must pre-exist.
- Performance/concurrency: constant-size unique lookups, tenant-first keys, race-safe insert/no-rewrite plus post-write verification.
- Automated tests: cross-tenant Customer/Party, missing customer role, exact idempotency, both one-to-one conflict directions, concurrent winner conflict, permissions, tenant predicates, schema registration and no legacy/role mutation.
- CI/build/release: regression is explicitly wired into `scripts/test-php.sh`; exact-source ESC Foundation Gate is required.
- Backward compatibility: current contracts continue to store/use `customer_id`; Customer repository/service behavior is unchanged.
- Rollback: additive table/code can be rolled back before production adoption without destructive removal of legacy data.

## Deferred follow-up

Bulk/backfill/matching and contract transition to generic Parties are separate decisions. They require duplicate analysis, audit/provenance, rollout/rollback planning and explicit compatibility testing; they must not be smuggled into this foundation task.
