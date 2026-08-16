# ESC-P2-006 — Tenant Membership REST Boundary

## Purpose

ESC-P2-006 exposes tenant membership administration to Enterprise REST clients without weakening the authorization, tenant isolation, role ceilings, and owner-safety invariants established by ESC-P2-001 through ESC-P2-005.

This API is an **Enterprise-only** surface. `TenantMembersController::register()` returns without registering routes unless `CoreTenantEnforcement` is enabled, so Safe Contract/non-ESC runtime behavior does not gain these endpoints.

## Routes

All routes use the existing `safecontracts/v1` namespace:

- `GET /tenant-members` — list memberships for the currently locked tenant and return the explicit assignable tenant roles.
- `POST /tenant-members` — assign/reactivate an existing WordPress user in the currently locked tenant using an explicit assignable role.
- `PUT /tenant-members/{user_id}` — change a non-owner membership role inside the currently locked tenant.
- `DELETE /tenant-members/{user_id}` — deactivate an active non-owner membership inside the currently locked tenant.

The generic API deliberately does not expose owner transfer/removal, user invitation/provisioning, tenant-specific custom capability editing, or a tenant selector in route/payload data.

## Tenant context and authorization order

`/tenant-members` is classified by `CoreTenantRestGuard::isCoreBusinessRoute()` as a core tenant-owned route. Under ESC enforcement the request must therefore resolve a valid tenant membership and lock `TenantContext` before tenant business authorization proceeds.

Every route uses `TenantMembersController::canManage()` as its permission callback. The callback resolves the required tenant context with `TenantRequestContext::resolve($request, true)` and then calls `Permission::capability(Capabilities::MANAGE_USERS, ...)`.

The effective authorization boundary is therefore:

1. authenticated WordPress user;
2. valid server-authorized Enterprise tenant selection/membership;
3. locked tenant context;
4. global WordPress `MANAGE_USERS` grant;
5. tenant membership role ceiling that also allows `MANAGE_USERS`.

A client-supplied tenant identifier is never itself authorization.

## Service-only data boundary

The controller does not query or mutate the tenant-membership table directly and contains no `$wpdb` access. Reads and mutations delegate to `TenantMembershipAdminService`:

- list and post-mutation reads use `listForCurrentTenant()`;
- assignment/reactivation/role change use `assignRole()`;
- deactivation uses `deactivate()`.

The service remains the authoritative P2-004 mutation boundary and owns the tenant-scoped repository predicates and owner-safety invariants.

## Input and cross-tenant protection

The API accepts no `tenant_id` mutation field.

- create accepts only `user_id` and `role_code`;
- update accepts only `role_code` while `user_id` comes from the item route;
- unsupported JSON fields are rejected;
- route user IDs are validated as positive WordPress user IDs;
- tenant ownership is derived only from the locked server-side context;
- membership lookup after mutation is performed against `listForCurrentTenant()`.

This means a foreign tenant ID cannot be smuggled through the route or JSON payload to redirect a membership operation. Object IDs remain identifiers, not authorization.

## Role and ownership safety

Deliberate assignments are validated with `TenantRolePolicy::isAssignable()` and the list response publishes `TenantRolePolicy::assignableRoles()`. The generic endpoint therefore cannot deliberately assign the legacy compatibility `member` role and cannot grant ownership.

Owner memberships may be listed, but generic `PUT` and `DELETE` owner mutation is rejected. The controller re-reads the current tenant membership and rejects an owner target before invoking the mutation service. Ownership transfer/removal remains a separate future workflow with its own explicit rules.

## Response boundary

Membership presentation returns the current-tenant membership fields needed by Enterprise clients (`user_id`, `role_code`, `status`, `is_owner`, and safe WordPress user display metadata when available). It does not echo a caller-provided tenant ID or expose foreign-tenant membership rows.

## Full Impact Review

- Domain/business rules: membership administration semantics unchanged from P2-004; REST is an adapter only.
- Tenant isolation: required locked tenant context; no client-selected mutation tenant; current-tenant service reads only.
- Schema/migrations/indexes: N/A — no schema change.
- Authorization: `MANAGE_USERS` global grant plus tenant role ceiling required.
- REST/API compatibility: new Enterprise-only v1 routes; no Safe Contract endpoint exposure.
- Admin UX: N/A — P2-005 Tenant Members page remains separate and unchanged.
- Flutter/mobile: N/A for P2-006; no mobile consumer added.
- Android identity/environment: N/A.
- Landing/public messaging: N/A; feature is not promoted by this task.
- Design/theme: N/A.
- Feature registry/entitlements: N/A at this security adapter stage; no public/plan claim added.
- Search/filter/report/import/export: N/A.
- Notifications/escalation: N/A.
- Audit/compliance: no new ownership workflow; existing membership service boundary remains authoritative.
- Localization/RTL/timezone/currency: N/A.
- Security/privacy: adversarial route/context/field/owner/service-only regression added.
- Performance/concurrency/idempotency: inherited from P2-004 service; controller introduces no direct write path.
- Tests/docs: `tests/php/enterprise_tenant_members_rest_p2_006.php` plus this document.
- CI/release/rollback: test is wired into `scripts/test-php.sh`; no artifact identity or Safe Contract release path changed.

## Regression evidence

`tests/php/enterprise_tenant_members_rest_p2_006.php` verifies:

- collection and item routes are covered by the core tenant REST guard;
- routes are absent when Enterprise core enforcement is disabled and present when enabled;
- all handlers use the tenant-aware `canManage()` permission callback;
- required tenant resolution and `MANAGE_USERS` authorization are wired;
- ambiguous tenant selection and a foreign client-supplied tenant selection fail closed;
- create/update accept only the documented mutation fields and no `tenant_id` field;
- role validation remains delegated to explicit P2-004 assignable-role policy;
- controller reads/mutates only through `TenantMembershipAdminService` and contains no direct membership SQL;
- generic owner deactivation is rejected before service mutation;
- response presentation does not expose a tenant-id field that could be confused with client-selected ownership.
