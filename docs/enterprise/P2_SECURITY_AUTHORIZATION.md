# ESC-P2 Security & Authorization

## Authorization invariant

Enterprise Safe Contracts uses two independent authorization ceilings for tenant-owned operations:

1. WordPress SafeContracts capabilities decide whether the user may perform the class of operation at all.
2. Once an Enterprise `TenantContext` is locked, the current user must also remain an active member of that active tenant.

A WordPress capability alone is never sufficient to access a locked Enterprise tenant. Membership is revalidated at authorization time so a membership that becomes stale after tenant selection fails closed.

## Request order

For protected core business REST routes while ESC core tenant enforcement is enabled:

1. authenticate the WordPress user;
2. resolve the requested tenant from `X-ESC-Tenant-ID` or the single active membership;
3. verify active tenant + active membership and lock `TenantContext`;
4. evaluate SafeContracts access/capability authorization against the locked context;
5. enter tenant-scoped repositories/services.

This ordering prevents global WordPress capabilities from being evaluated as if they independently authorized tenant data.

## Compatibility boundary

`TenantAuthorization` is active only when Enterprise core or non-core tenant enforcement is enabled **and** a tenant context is locked. Legacy Safe Contract behavior and platform-global operations that do not enter tenant-owned context keep their existing WordPress capability behavior.

## P2-001 scope

P2-001 establishes the membership-aware authorization boundary and stale-membership revalidation. Tenant-specific role/capability matrices, delegated administration, plan entitlements and custom role editing are intentionally separate follow-up P2/P13 work.

## Security regression

`tests/php/enterprise_tenant_authorization_p2_001.php` verifies:

- legacy behavior is unchanged when ESC enforcement is disabled;
- a globally-capable user without active membership is denied in a locked tenant;
- active membership plus the required global capability is accepted;
- stale membership after context locking fails closed;
- core and non-core enforcement share the same boundary;
- membership never replaces the global SafeContracts capability ceiling;
- the core REST guard resolves tenant context before tenant-aware authorization.
