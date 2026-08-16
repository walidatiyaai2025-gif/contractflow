# ESC-P2 Security & Authorization

## Authorization invariant

Enterprise Safe Contracts uses layered authorization ceilings for tenant-owned operations:

1. WordPress SafeContracts capabilities decide whether the user may perform the class of operation at all.
2. Once an Enterprise `TenantContext` is locked, the current user must remain an active member of that active tenant.
3. Explicit tenant membership roles may further narrow the user's effective data scope and capabilities.

A tenant role never creates a capability that WordPress did not already grant. A WordPress capability alone is never sufficient to access a locked Enterprise tenant. Membership is revalidated at authorization time so a membership that becomes stale after tenant selection fails closed.

## Request order

For protected core business REST routes while ESC core tenant enforcement is enabled:

1. authenticate the WordPress user;
2. resolve the requested tenant from `X-ESC-Tenant-ID` or the single active membership;
3. verify active tenant + active membership and lock `TenantContext`;
4. evaluate SafeContracts access/capability authorization against the locked context;
5. apply the tenant membership role ceiling;
6. enter tenant-scoped repositories/services.

For WordPress admin requests, P2-003 first classifies the request as either tenant-owned or platform-global. Tenant-owned pages/actions resolve and lock tenant context during `admin_init`; direct `current_user_can()` checks are then narrowed through `TenantCapabilityFilter`. Platform-global pages/actions explicitly reset/retain an empty tenant context and therefore keep their global WordPress capability semantics.

This ordering prevents global WordPress capabilities from being evaluated as if they independently authorized tenant data.

## Compatibility boundary

`TenantAuthorization` is active only when Enterprise core or non-core tenant enforcement is enabled **and** a tenant context is locked. Legacy Safe Contract behavior and platform-global operations that do not enter tenant-owned context keep their existing WordPress capability behavior.

## P2-001 — active membership boundary

P2-001 establishes membership-aware authorization and stale-membership revalidation. A globally-capable WordPress user cannot access a locked tenant after membership becomes inactive or the tenant becomes inactive.

## P2-002 — tenant role ceilings

The existing membership fields `role_code` and `is_owner` are authorization inputs. Recognized tenant roles are:

| Tenant role | Data scope ceiling | Tenant capability ceiling |
| --- | --- | --- |
| `tenant_admin` | all | tenant-wide business/user/notification/import/audit operations, subject to matching global WP grants |
| `manager` | all | normal tenant business operations, reporting/export/audit; no notification/import/user administration by role |
| `accountant` | assigned | assigned business scope, payments, collections, follow-ups, reporting/export |
| `viewer` | assigned | assigned/read/report access only |
| `member` | inherit | compatibility role for memberships created before the tenant-role matrix; preserves the existing global WP ceiling without expanding it |

`is_owner=1` raises the tenant role ceiling for a **recognized** role to tenant-wide access/capabilities, but still cannot bypass a missing global WordPress grant. An owner with an unknown/blank `role_code` fails closed.

Unknown or blank explicit role codes fail closed for locked tenant business access. They are never interpreted as administrator roles.

## Effective-scope rule

Tenant scope is a narrowing ceiling only:

- global `VIEW_ALL` + tenant `viewer/accountant` => effective `assigned`;
- global `VIEW_ASSIGNED` + tenant `manager/tenant_admin/owner` => effective `assigned`;
- no global view scope => effective `none` regardless of tenant role;
- legacy `member` => inherits existing global scope until deliberately remapped.

## P2-003 — WordPress admin boundary

SafeContracts historically reused a few WordPress capability names for both platform controls and tenant-owned admin actions. P2-003 resolves that ambiguity by classifying the **request** before capability evaluation instead of assuming the capability name identifies data ownership.

Platform-global control-plane pages include the legacy organization/system settings, payment-method reference catalog, global WordPress users/roles and presence tooling, Firebase deployment credentials/settings, mobile deployment configuration, translations, and the dedicated Enterprise tenant selector. Their matching admin-post actions run with no tenant context.

Tenant-owned pages/actions include the dashboard, customers, contracts, payments, collections, follow-ups, notification business configuration/delivery, imports, reports and archive. Firebase test-push is also tenant-owned because device registrations are tenant-owned even though Firebase credentials are platform-global.

`TenantCapabilityFilter` narrows SafeContracts WordPress capabilities only when a tenant context is locked. The shared capability names `MANAGE_SYSTEM`, `MANAGE_REFERENCE_DATA` and `MANAGE_USERS` are still narrowed during tenant-owned execution. They are bypassed only while WordPress is constructing `admin_menu`, so platform-global menu entries remain discoverable; entering the corresponding global page resets tenant context before authorization.

The dedicated `Enterprise Tenant` selector is a control-plane page. It uses the user's global SafeContracts access grant and active membership directory rather than the previous tenant role ceiling. This provides an escape path when a previously selected membership has an invalid/unknown role while keeping tenant data fail closed.

### Archive direct-SQL rule

`ArchivePage` historically queried core archive tables directly instead of going through repositories. Under ESC enforcement those direct reads now require `CoreTenantScope` and add `tenant_id` predicates to customers, contracts, scheduled payments and collections. The platform-global payment-method archive is omitted from the tenant archive. Outside ESC enforcement the legacy Safe Contract query shape is unchanged.

## P2-004 — membership administration invariants

P2-004 introduces the tenant membership mutation domain layer before any membership-management UI or REST endpoint is exposed.

### Recognized versus assignable roles

`member` remains a recognized compatibility role because older Enterprise memberships were created before the explicit role matrix existed. Deliberate new/reactivated assignments may use only `tenant_admin`, `manager`, `accountant`, or `viewer`. The generic membership administration flow therefore cannot create new legacy `member` assignments.

### Actor boundary

Every membership administration operation requires all of the following:

- the actor is the currently authenticated WordPress user;
- the actor has the global `MANAGE_USERS` SafeContracts capability;
- a tenant context is already locked;
- the actor remains an active member of that tenant;
- the actor's tenant role ceiling allows `MANAGE_USERS`.

This preserves the global WordPress capability ceiling while independently enforcing tenant membership and tenant role authorization.

### Mutation ownership

Membership lookups and mutations use the composite tenant/user ownership key. Role updates include `tenant_id + user_id + is_owner = 0`; deactivation includes `tenant_id + user_id`. A user ID by itself is never sufficient to mutate a membership in another tenant.

New memberships are created only for an existing WordPress user, are explicitly `active`, and hard-code `is_owner = 0`. P2-004 exposes no operation that can grant ownership.

### Owner safety

The generic role-assignment flow treats owner memberships as immutable. It cannot demote an owner or change an owner's tenant role. Deactivating an owner additionally requires the actor to be an active owner in the same tenant.

The database deactivation statement contains an atomic same-tenant active-owner count guard. An owner row can be deactivated only when more than one active owner remains, so the last active owner cannot be removed even if concurrent requests race between application-level reads and the write.

### Idempotent role saves

MySQL can report zero affected rows when an active non-owner already has the requested role. A zero-row role update is therefore reconciled by re-reading the same `tenant_id + user_id` key. If the requested non-owner active role is already stored, the operation succeeds idempotently and does not fall through to a duplicate INSERT. An owner or mismatched existing row still fails closed.

## P2-005 — tenant membership admin UI

P2-005 exposes the P2-004 domain service through a dedicated **Tenant Members** WordPress admin page without repurposing the platform-global **Users & Roles** screen.

The page is Enterprise-only: its submenu is registered only while core tenant enforcement is enabled. The page and both mutation actions are classified as tenant-owned, so `AdminTenantContext` resolves and locks the selected tenant before direct admin authorization runs.

All membership data shown by the page comes from `TenantMembershipAdminService::listForCurrentTenant()`. Add/reactivate and role changes call `assignRole()`; deactivation calls `deactivate()`. The UI contains no `$wpdb` access and does not reference the tenant-membership table directly.

Only explicit assignable roles are rendered: `tenant_admin`, `manager`, `accountant`, and `viewer`. Legacy `member` remains readable in existing rows but is not an assignable option; a legacy role row requires deliberate remapping to a supported role before update/reactivation. No form field or handler in this UI can grant `is_owner=1`.

Owner memberships are deliberately read-only in the generic Tenant Members interface. Owner rows expose neither role-edit nor deactivate controls. The deactivate handler also re-reads the current tenant membership list and rejects an owner target before calling the domain service, so a crafted POST cannot turn the hidden UI restriction into an owner-mutation path. Ownership transfer/removal remains a separate future workflow.

Both mutations use WordPress nonces. Page rendering and handlers require `MANAGE_USERS`; because the request is tenant-owned, the P2-003 capability filter and P2-004 service actor boundary independently require the locked tenant role to allow membership administration.

The platform-global WordPress **Users & Roles** screen remains conceptually and programmatically separate: it continues to manage WordPress SafeContracts roles/capabilities, while **Tenant Members** manages membership and role assignment inside the currently selected Enterprise tenant.

## Security regressions

`tests/php/enterprise_tenant_authorization_p2_001.php` verifies:

- legacy behavior is unchanged when ESC enforcement is disabled;
- a globally-capable user without active membership is denied in a locked tenant;
- active membership plus the required global capability is accepted;
- stale membership after context locking fails closed;
- core and non-core enforcement share the same boundary;
- membership never replaces the global SafeContracts capability ceiling;
- the core REST guard resolves tenant context before tenant-aware authorization.

`tests/php/enterprise_tenant_roles_p2_002.php` verifies:

- viewer/accountant roles narrow a global all-data grant to assigned scope;
- explicit roles deny capabilities outside their tenant role ceiling even when WordPress grants them globally;
- shared legacy capability names are narrowed when evaluated inside locked tenant context;
- manager/tenant-admin allowed operations still require matching global grants;
- unknown/blank roles fail closed;
- the compatibility `member` role preserves old memberships without manufacturing privileges;
- owner status cannot manufacture a missing global capability;
- a manager/owner tenant role cannot broaden a globally assigned-only user to all-data scope;
- non-ESC legacy behavior remains unchanged.

`tests/php/enterprise_admin_authorization_p2_003.php` verifies:

- platform-global and tenant-owned admin pages/actions are classified explicitly;
- direct admin capabilities are narrowed by the active tenant role, including overloaded system/reference/user capability names;
- global control-plane capability behavior is preserved with no tenant context;
- unknown tenant roles fail closed in direct admin authorization;
- Enterprise archive direct SQL is tenant-scoped on every tenant-owned table and excludes platform-global payment-method archive rows;
- Safe Contract legacy archive behavior remains unchanged when ESC enforcement is disabled;
- the central capability filter and dedicated tenant selector are wired into runtime.

`tests/php/enterprise_tenant_membership_admin_p2_004.php` verifies:

- compatibility `member` is recognized but not deliberately assignable;
- the four explicit tenant roles are assignable;
- new/reactivated membership writes are scoped to the locked tenant and hard-code `is_owner = 0`;
- non-owner role changes use tenant+user+non-owner predicates;
- generic role assignment cannot mutate owners;
- invalid roles and unknown WordPress users produce no writes;
- deactivation uses tenant+user predicates plus an atomic same-tenant owner-count guard;
- a non-owner actor cannot deactivate an owner;
- the final active owner cannot be deactivated;
- an additional owner may be deactivated only when the guarded write succeeds;
- a manager tenant role cannot administer memberships merely because WordPress granted `MANAGE_USERS` globally;
- repository source exposes no ownership-grant mutation.

`tests/php/enterprise_tenant_membership_idempotency_p2_004.php` verifies that a zero-row UPDATE for an already-correct active role performs no INSERT and reconciles only against the same tenant+user key.

`tests/php/enterprise_tenant_members_admin_ui_p2_005.php` verifies:

- the Tenant Members page is a dedicated tenant-owned Enterprise-only screen;
- list/add/reactivate/role-update/deactivate paths delegate to the P2-004 service;
- the admin page contains no direct membership-table access;
- UI options come only from `TenantRolePolicy::assignableRoles()` and do not expose legacy `member` or owner escalation;
- owner rows are rendered read-only and the generic owner-deactivation path is rejected before service mutation;
- assignment and deactivation both require nonces and `MANAGE_USERS`;
- page and both actions are classified tenant-owned;
- plugin menu/action hooks are registered;
- the platform-global Users & Roles page remains separate from tenant membership policy/service.
