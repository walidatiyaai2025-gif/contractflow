# ESC-P3-005 — User to Department / Team assignments

## Scope

This task adds an Enterprise Safe Contracts association between active tenant users and internal organization units. It is ESC-only and does not alter Safe Contract/main.

Three concepts remain intentionally separate:

1. **Tenant membership / RBAC** — whether a WordPress user belongs to the SaaS tenant and which tenant-role capability ceiling applies.
2. **Organization unit** — a tenant-owned internal Department or Team from P3-004.
3. **Organization-unit membership** — which Department/Team a tenant user participates in and whether their unit-local assignment role is `member` or `manager`.

An organization-unit `manager` is **not** a tenant administrator and receives no RBAC escalation from this foundation.

## Schema

Schema version `1.23.0` adds `safecontracts_org_unit_memberships` with:

- mandatory `tenant_id`;
- mandatory `org_unit_id`;
- mandatory WordPress `user_id`;
- explicit unit-local `assignment_role` (`member` / `manager`);
- active/inactive status;
- creator/updater actor IDs and timestamps.

The identity key is unique `(tenant_id, org_unit_id, user_id)`. A user can therefore belong to multiple units, while one unit/user pair has one current assignment role. Tenant-first indexes cover list-by-unit and list-by-user access.

## Tenant and authorization boundary

The service and repository fail closed unless Enterprise core tenant enforcement is enabled and a tenant context is locked.

Reads require both the global `ACCESS` ceiling and the active tenant-role ceiling. Mutations require both the global `MANAGE_USERS` ceiling and the tenant-role ceiling.

Before assignment:

- the organization unit is resolved by tenant-scoped `OrgUnitRepository::find()`;
- the target user must have an active membership in the same locked tenant using `TenantMembershipRepository::findActiveMembership(tenant_id, user_id)`.

A numeric WordPress user ID or organization-unit ID is never authorization by itself.

`listForUser()` also verifies active current-tenant membership before returning assignments. `revoke()` intentionally permits cleanup of a previously assigned user after their tenant membership becomes stale/inactive; the revoke is still locked to tenant + organization unit + user.

## Idempotency and concurrency

Assignment uses the database unique identity plus `INSERT ... ON DUPLICATE KEY UPDATE` to atomically create, reactivate, or change the unit-local assignment role without duplicate rows.

Revoke is non-destructive and tenant-scoped. Repeated revoke requests are idempotent because only rows not already inactive are updated.

Tenant RBAC storage is never mutated by this repository. Changing `member` to `manager` here changes only the organization-unit assignment role.

## Future authorization warning

P3-005 does **not** make organization-unit membership an authorization source for contracts, documents, reports or financial data. Any future Department/Team-based visibility or routing must intersect:

- locked tenant context;
- active tenant membership;
- tenant/global RBAC capability ceiling;
- the relevant resource's tenant ownership;
- explicit organization-unit assignment rules.

A stale org-unit row must never restore access after the tenant membership is disabled.

## Full Impact Review

- Business/domain: adds internal user-to-unit association only; SaaS membership/RBAC remains separate.
- Tenant/isolation: mandatory tenant key; repository derives tenant server-side; both unit and target active user are verified in the locked tenant before assignment.
- Database/migrations: additive schema `1.23.0`; tenant-first indexes; unique tenant+unit+user identity; no destructive migration/backfill.
- Backend: bounded list-by-unit/list-by-user, assign/reactivate/change role and non-destructive revoke.
- Authorization: `ACCESS` reads, `MANAGE_USERS` mutations, plus explicit tenant-role ceiling through `TenantAuthorization`.
- REST API: N/A; no route exposed in this foundation task.
- WordPress/admin UI: N/A; no admin surface exposed.
- Flutter/mobile/offline: N/A; no mobile/local-state contract introduced.
- Android identity/environments: N/A; no package/Firebase/release changes.
- Landing/public messaging: N/A; no public feature claim.
- Design system/theme: N/A; no UI.
- Feature registry/plans: no entitlement/public lifecycle claim introduced; later surface exposure must register it explicitly.
- Search/filter/sort: list operations are tenant-scoped and capped at 100 rows per call with bounded offset.
- Reports/import/export/bulk: N/A.
- Notifications/escalation: N/A.
- Audit/compliance: actor IDs/timestamps are stored and domain hooks fire for assign/revoke. Rich audit-event integration remains with consuming surfaces.
- Documents/storage: N/A.
- Localization/timezone/currency: N/A.
- Security/privacy: caller cannot supply tenant ownership; target user must be active in locked tenant before assignment; assignment roles are explicit and cannot reuse tenant RBAC codes.
- Performance/concurrency: tenant-first indexes, bounded reads, atomic upsert, database uniqueness for duplicate races.
- Automated tests: adversarial regression covers migration registration, role separation, core/context failure, foreign unit, stale/foreign target user, tenant predicates, atomic upsert, list bounds, stale-user revoke cleanup and global/tenant-role permission denial.
- CI/build/release: regression is explicitly wired into `scripts/test-php.sh`; ESC Foundation Gate is required.
- Backward compatibility: additive only; no change to tenant membership role/owner state, Parties, org-unit hierarchy, customers or contracts.
- Rollback: no automatic destructive rollback/table drop; code rollback before production use is the safe path.

## Acceptance evidence

Issue #453 remains open until implementation and final status commits both pass exact-source ESC Foundation Gates with the P3-005 regression actually executing.
