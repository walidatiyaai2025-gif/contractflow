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

Platform-global capability classes such as system administration and the shared reference catalog remain classified separately from tenant business roles. A tenant role must not silently turn platform configuration into tenant-owned configuration.

## Effective-scope rule

Tenant scope is a narrowing ceiling only:

- global `VIEW_ALL` + tenant `viewer/accountant` => effective `assigned`;
- global `VIEW_ASSIGNED` + tenant `manager/tenant_admin/owner` => effective `assigned`;
- no global view scope => effective `none` regardless of tenant role;
- legacy `member` => inherits existing global scope until deliberately remapped.

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
- manager/tenant-admin allowed operations still require matching global grants;
- unknown/blank roles fail closed;
- the compatibility `member` role preserves old memberships without manufacturing privileges;
- owner status cannot manufacture a missing global capability;
- a manager/owner tenant role cannot broaden a globally assigned-only user to all-data scope;
- non-ESC legacy behavior remains unchanged.
