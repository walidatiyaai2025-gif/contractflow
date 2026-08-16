# Enterprise Safe Contracts — Multi-Tenancy & Security

## Objective
Provide strict, testable isolation between organizations while preserving a path from shared-database SaaS to stronger isolation for large enterprise tenants.

## Core model
Introduce a first-class `Tenant`/organization domain with stable tenant identifiers, lifecycle/status, locale, timezone, default currency, plan/entitlements, branding and security settings.

Establish centralized components before broad schema migration:
- TenantResolver
- TenantContext
- Tenant-aware repositories/services
- tenant-aware authorization/scopes
- background-job tenant context
- tenant-aware file/storage addressing

## Isolation rules
Every tenant-owned entity must be scoped on every read/write path. Object IDs, predictable URLs, report filters, export identifiers and attachment paths must never bypass tenant authorization.

Mandatory negative tests include attempts to access another tenant's data through:
- REST object IDs
- nested resources
- search/filter/sort parameters
- exports/import jobs
- attachment/document URLs
- Flutter/mobile requests
- notifications/background jobs
- audit/report endpoints
- cached/local identifiers

## Database strategy
The initial implementation may use shared database tables with a tenant key when approved by ADR. The domain boundaries must allow later dedicated-database or higher-isolation options for selected enterprise tenants without rewriting business behavior.

Indexes and unique constraints must be reviewed for tenant scope, e.g. business references that are unique per organization should generally be constrained by `(tenant_id, value)` rather than globally unless global uniqueness is intentional.

## Access control
ESC authorization combines:
- tenant membership
- roles/capabilities
- data scope
- department/team scope
- contract/party assignment when configured
- feature/plan entitlement where relevant

Menu visibility is UX only; server-side capability + tenant scope is mandatory.

## Operational safety
- No tenant context may be inferred from untrusted client payload alone.
- Tenant switching for platform administrators must be explicit, auditable and protected.
- Background jobs must carry tenant context and fail closed when context is missing.
- Caches must include tenant identity in keys where tenant data is cached.
- Storage paths/object keys must not collide across tenants.
- Logs must aid diagnosis without leaking sensitive tenant data.

## Security roadmap
P0/P1: isolation primitives and tests.
P2: authorization hardening, rate limiting, secure headers/API abuse controls.
Later enterprise controls: MFA, OIDC/SAML SSO, Microsoft Entra ID, SCIM, IP/session policies and compliance-oriented exports where commercially required.
