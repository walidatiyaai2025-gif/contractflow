# ESC-P3-003 — Party-to-Party Relationship Foundation

## Purpose

ESC-P3-003 introduces a tenant-owned graph edge between two existing Parties without changing Party identity, Party business-role assignments, legacy customers, contracts, departments or public product surfaces.

A Party relationship answers **how two Parties are related to each other**. It is different from:

- `party_kind`, which answers what one Party is;
- Party business roles, which answer how one Party participates commercially;
- SaaS tenant ownership, which is the security/isolation boundary;
- contract participation, which is contract-specific and belongs to later contract-domain work.

## Baseline relationship policy

The initial stored relationship codes are:

- `parent_of`
- `represents`
- `guarantees_for`
- `contact_for`
- `owns`
- `affiliated_with`

Each relationship definition exposes an inverse presentation code and whether the relationship is symmetric:

| Stored code | Inverse presentation code | Symmetric |
| --- | --- | --- |
| `parent_of` | `child_of` | no |
| `represents` | `represented_by` | no |
| `guarantees_for` | `guaranteed_by` | no |
| `contact_for` | `has_contact` | no |
| `owns` | `owned_by` | no |
| `affiliated_with` | `affiliated_with` | yes |

Inverse presentation codes are **derived semantics**, not additional rows or accepted stored relationship codes. The database therefore has one authoritative edge rather than two mirrored rows that could diverge.

## Direction and symmetric canonicalization

Directional relationships preserve the supplied source and target.

Example:

`Party A --owns--> Party B`

The inverse UI/API label may later be rendered as:

`Party B --owned_by--> Party A`

without creating a second database row.

For `affiliated_with`, direction has no business meaning. The service canonicalizes the endpoint IDs so the smaller Party ID is stored as source and the larger as target. This means `A affiliated_with B` and `B affiliated_with A` resolve to the same unique row and cannot create reverse duplicates.

## Schema

Migration `1.21.0` adds `safecontracts_party_relationships` with:

- mandatory `tenant_id`;
- mandatory `source_party_id`;
- mandatory `target_party_id`;
- stable `relationship_code`;
- `active` / `inactive` status;
- optional `valid_from` / `valid_to` dates;
- bounded metadata JSON;
- assignment/revocation actor fields;
- creation/update/revocation timestamps.

Constraints/indexes:

- unique `(tenant_id, source_party_id, target_party_id, relationship_code)`;
- tenant-first outgoing lookup index;
- tenant-first incoming lookup index;
- tenant-first relationship-type lookup index.

Self-relations are rejected by the service for every baseline type.

## Tenant isolation boundary

Every operation requires ESC core tenant enforcement and a locked `TenantContext`.

Before assigning or revoking an edge, `PartyRelationshipService` verifies **both** endpoints with `PartyRepository::find()`. Because Party lookup itself is tenant-locked, a source or target Party ID from another tenant cannot be used as authorization.

Relationship reads also join both source and target IDs back to `safecontracts_parties` with the same relationship tenant ID. This is defensive validation against corrupt/orphaned relationship rows: an edge is not returned unless both endpoints resolve inside the same tenant.

The relationship repository has no unscoped fallback and every query/mutation carries the server-authoritative tenant predicate.

No caller-supplied `tenant_id` option exists.

## Authorization

Foundation permissions intentionally reuse existing tenant-aware capability ceilings:

- read/list: `Capabilities::ACCESS`;
- assign/revoke: `Capabilities::MANAGE_REFERENCE_DATA`.

A future P3 admin/API task may introduce narrower capabilities only after the actual Party-management surface is designed.

## Assignment idempotency and reactivation

Relationship assignment uses one atomic `INSERT ... ON DUPLICATE KEY UPDATE` mutation against the unique edge identity.

Behavior:

- missing edge → create active row;
- inactive edge → reactivate and replace effective dates/metadata/assignment actor with the new assignment state;
- already-active edge → remain active and preserve existing edge dates/metadata/assignment actor.

Repeated identical assignment cannot create duplicate rows under concurrent workers.

## Non-destructive revoke

Revocation does not delete the relationship row.

The exact tenant + source + target + relationship row is marked inactive. Revocation actor/time changes only when the previous status was active. Repeating revoke is therefore safe and does not continuously rewrite historical revoke evidence.

Reassignment can later reactivate the same unique edge.

## Effective dates

`valid_from` and `valid_to` are optional ISO calendar dates in `YYYY-MM-DD` form.

Validation rejects:

- malformed dates;
- impossible calendar dates;
- `valid_to` earlier than `valid_from`.

Effective dates describe business validity; they do not change tenant ownership or authorization.

## Metadata

Optional relationship metadata must be supplied as an array/object-compatible value and is JSON encoded server-side. Encoded metadata is capped at 20,000 bytes in this foundation.

Metadata must not be used as an authorization bypass, tenant selector or substitute for a future normalized domain field that becomes operationally important.

## Compatibility boundaries

P3-003 does **not**:

- alter `party_kind`;
- assign/revoke Party business roles;
- create/link/backfill `safecontracts_customers`;
- migrate customer or contract foreign keys;
- create department/team hierarchy;
- create contract-specific counterparty bindings;
- expose REST/admin/Flutter UI;
- publish a landing-page feature claim.

These remain separate bounded tasks.

## Why no automatic inverse row

Automatically inserting inverse rows creates two mutable records for one logical fact. Under retries/concurrency, one side can succeed while the other fails or later be revoked independently. That produces contradictory graph state.

ESC instead stores one authoritative edge and exposes inverse semantics from `PartyRelationshipPolicy`. Future API/reporting layers can query incoming vs outgoing edges and render the correct inverse label deterministically.

## Full Impact Review

- Business/domain model: adds generic typed Party-to-Party graph edge; identity and role concepts stay separate.
- Tenant model/isolation: both endpoints must exist in locked tenant; all queries are tenant-scoped; reads defensively join both Party endpoints to tenant ownership.
- Database/migrations/indexes: additive versioned schema `1.21.0`, unique edge identity, tenant-first outgoing/incoming/type indexes.
- Backend business logic: policy + repository + service only; no existing customer/contract workflow behavior changed.
- Authorization/scopes/roles: ACCESS reads, MANAGE_REFERENCE_DATA mutations.
- REST/API compatibility: N/A; no route added.
- WordPress/admin UI: N/A.
- Flutter/mobile/offline: N/A.
- Android identity/environments: N/A.
- Landing/public messaging: N/A; foundation must not be marketed as Public.
- Design system/theme: N/A.
- Feature registry/plans/entitlements: N/A for foundation.
- Search/filter/sort/bulk: indexed outgoing/incoming/type foundations only; no public bulk operation.
- Reports/import/export: N/A now; future graph reports/imports must retain tenant and endpoint validation.
- Notifications/escalation: N/A.
- Audit/compliance: assignment/revocation actor and timestamps retained; no destructive delete.
- Documents/storage: N/A.
- Localization/RTL/LTR/timezone/currency: machine relationship codes only; future labels require localized presentation.
- Security/privacy: caller cannot select tenant; both endpoints are server-verified; self relation and unsupported types fail closed.
- Performance/concurrency/idempotency: unique key + atomic upsert; symmetric canonicalization; tenant-first indexes; non-destructive revoke.
- Automated tests: `tests/php/enterprise_party_relationships_p3_003.php` plus full ESC regression gate.
- Documentation/onboarding: this relationship semantics/isolation document.
- CI/build/release/rollback: additive schema; code rollback may leave unused relationship rows/table without affecting legacy Safe Contract data.
- Backward compatibility: no customer/role/contract migration and no Safe Contract/main port.

## Regression coverage

`tests/php/enterprise_party_relationships_p3_003.php` validates:

- schema ownership, uniqueness and tenant-first indexes;
- registered schema version;
- explicit direction/inverse/symmetry policy;
- inverse labels are not duplicated stored relationship codes;
- fail-closed behavior outside Enterprise tenant context;
- self and unsupported relationships rejected before data access;
- current-tenant Party verification before listing/mutation;
- defensive endpoint joins for relationship reads;
- directional assignment and effective dates/metadata;
- atomic duplicate/reactivation semantics;
- symmetric endpoint canonicalization;
- source and target cross-tenant spoof attempts;
- invalid date ranges and unsupported options;
- tenant-scoped non-destructive revoke;
- symmetric revoke canonicalization;
- authorization denial before data access;
- no Party-kind, Party-role, customer or contract mutation path.
