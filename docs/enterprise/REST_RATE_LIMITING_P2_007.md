# ESC-P2-007 — Enterprise REST Rate Limiting

## Purpose

ESC-P2-007 adds a server-enforced abuse-control boundary for the Enterprise REST API while preserving the existing `ApiAbuseGuard` request-shape protections. The limiter is a security baseline for brute-force and API abuse; it is **not** a subscription, billing, entitlement, or commercial usage quota.

The limiter is part of Enterprise Safe Contracts only. It is inactive when both Enterprise core and non-core tenant enforcement are disabled, so Safe Contract/non-ESC runtime behavior is unchanged.

## Activation boundary

`EnterpriseRateLimitGuard` is registered on `rest_request_before_callbacks` at priority 20.

The ordering is intentional:

1. `TenantContextStore` resets request tenant context at priority 1.
2. `CoreTenantRestGuard` resolves and locks tenant context for core tenant-owned routes at priority 5.
3. `EnterpriseRateLimitGuard` evaluates the request at priority 20.

The guard returns without touching limiter storage unless either `CoreTenantEnforcement` or `NonCoreTenantEnforcement` is enabled.

Only the `/safecontracts/v1` namespace is considered. `/safecontracts/v1/health` and `OPTIONS` requests are excluded.

## Default policy

Defaults are deliberately explicit and conservative enough for normal ESC admin/mobile traffic:

| Scope | Default ceiling | Window |
| --- | ---: | ---: |
| Login by client IP | 10 attempts | 5 minutes |
| Login by normalized username identity | 20 attempts | 15 minutes |
| Authenticated reads | 300 requests | 1 minute |
| Authenticated writes | 120 requests | 1 minute |
| Other anonymous ESC REST traffic | 60 requests | 1 minute |

Read and write traffic use different bucket classes. `POST`, `PUT`, `PATCH`, and `DELETE` are classified as writes; other normal methods are classified as reads.

## Identity and privacy boundary

Raw abuse identities are never stored in the limiter table.

Each bucket is converted to an HMAC-SHA-256 digest before persistence. The HMAC secret uses the WordPress authentication salt when available. The database therefore stores a fixed 64-character digest rather than raw:

- username;
- password;
- bearer token;
- client IP address;
- tenant header value.

Login uses independent IP and normalized-username buckets. A blocked response deliberately does not identify which bucket was exceeded and therefore does not disclose whether a submitted username exists.

The stable throttling response is HTTP `429` with code `safecontracts_esc_rate_limited` and a `details.retry_after` value.

## Tenant-aware authenticated buckets

Authenticated request identity is derived server-side from:

- the authenticated WordPress user ID; and
- the locked `TenantContext` tenant ID when one has already been established for the route.

A caller-provided `X-ESC-Tenant-ID` value is not used as limiter identity. For core tenant-owned routes, the earlier tenant guard resolves and locks the authoritative tenant context before the limiter runs. A forged tenant header therefore cannot redirect a request into another rate-limit bucket after context locking.

If an authenticated ESC route has no locked tenant context at limiter time, the bucket is explicitly user + `tenant:none`; the limiter never fabricates or trusts a client tenant identifier.

## Reverse proxy / client IP boundary

The limiter reads `REMOTE_ADDR` by default and **does not trust `X-Forwarded-For` directly**. This avoids allowing a caller to rotate a spoofable forwarding header to evade login/anonymous throttling.

Deployments behind a trusted load balancer or reverse proxy may supply a validated effective client IP using the server-side `safecontracts_esc_rate_limit_client_ip` filter. That filter must only consume proxy metadata after the infrastructure trust chain is explicitly configured and verified.

## Persistent atomic storage

Migration `1.18.0` creates the ESC-specific table:

`wp_safecontracts_esc_rate_limits` (using the active WordPress table prefix).

The table contains:

- `bucket_key char(64)` primary key;
- `window_expires_at`;
- `request_count`;
- `updated_at`;
- an index on `window_expires_at` for cleanup.

`EnterpriseRateLimitStore::hit()` uses one `INSERT ... ON DUPLICATE KEY UPDATE` mutation. In the same atomic database operation it either:

- resets an expired fixed window to count `1` with a new expiry; or
- increments the active window count.

This shared-database counter prevents separate PHP workers from maintaining independent in-memory counters that could be bypassed by concurrency or load balancing across workers.

Expired rows are removable through `pruneExpired()`, which enforces a bounded deletion limit. The guard invokes a bounded cleanup when active throttling occurs rather than adding a cleanup query to every successful request.

## Availability behavior

Authorization and tenant isolation remain the authoritative security boundaries. If the limiter's persistence layer throws an unexpected storage/runtime error, `EnterpriseRateLimitGuard` fails open **for rate limiting only**, emits `safecontracts_esc_rate_limit_storage_failed`, and leaves the normal application authorization path intact.

This tradeoff prevents a limiter-table incident from causing a platform-wide REST outage. Operations should alert on this event; repeated storage failures mean abuse throttling is degraded and must be investigated.

A successful throttle emits `safecontracts_esc_rate_limited` with the internal scope, route, and retry delay for server-side observability. Those internal values are not returned as identity details to the client.

## Server-side tuning

Defaults may be adjusted through the server-side `safecontracts_esc_rate_limit_policy` filter. Returned values are sanitized to bounded ranges:

- limit: `1..100000`;
- window: `1..86400` seconds.

This hook is deployment configuration, not client input. Do not expose it as a request parameter or tenant-controlled header without a separate product/entitlement design.

## Existing abuse validation remains intact

`ApiAbuseGuard` continues to enforce its request-shape rules, including maximum query parameter count, allowed parameter names, scalar types, and maximum string lengths. Rate limiting complements those controls rather than replacing them.

## Rollout and rollback

Rollout:

1. deploy the `1.18.0` migration and code together;
2. verify the rate-limit table exists and is writable;
3. enable/retain the intended Enterprise tenant enforcement state;
4. monitor rate-limit and storage-failure events;
5. tune policy only from trusted server configuration after observing legitimate traffic.

Rollback:

- disabling both Enterprise enforcement flags makes the limiter a runtime no-op;
- reverting the guard registration/code removes request throttling behavior;
- the `safecontracts_esc_rate_limits` table may remain safely in place during a rollback because no legacy Safe Contract code depends on it;
- no destructive down migration is required for emergency rollback;
- do not port the limiter or migration to `main`/Safe Contract as part of rollback or branch synchronization.

## Full Impact Review

- Business/domain rules: no contract/payment/workflow semantics changed; limiter is an infrastructure security boundary.
- Tenant model/isolation: authenticated bucket uses server-authoritative locked tenant context when present; no client tenant value is accepted as identity.
- Database/migrations/indexes: versioned `1.18.0` migration adds one ESC limiter table with primary-key bucket identity and expiry index.
- Backend business logic: no direct change; REST request pre-callback guard only.
- Authorization/scopes/roles: unchanged; authorization executes independently and remains authoritative.
- REST/API compatibility: ESC REST may now return stable HTTP 429 under abuse ceilings; `/health` and OPTIONS excluded.
- WordPress/admin UI: N/A; no UI added.
- Flutter/mobile: no UI/code change required; client must tolerate normal HTTP 429/retry semantics. Existing Flutter gate remains required.
- Android identity/environments: N/A; no package/application identity change.
- Landing/public messaging: N/A; this internal security control is not promoted as a public feature by this task.
- Design system/theme: N/A.
- Feature registry/plans/entitlements: N/A; explicitly not a commercial quota or plan feature.
- Search/filter/sort/bulk: no behavior change beyond shared REST throttling under excessive request volume.
- Reports/import/export: REST endpoints remain subject to authenticated read/write baseline where applicable; no report semantics changed.
- Notifications/escalation: N/A.
- Audit/compliance: internal limiter events provide operational evidence; no audit-record schema change.
- Documents/storage: N/A.
- Localization/RTL/LTR/timezone/currency: N/A; generic error message and numeric retry delay only.
- Security/privacy: hashed identities, login anti-brute-force buckets, no raw credentials/IP persistence, no direct X-Forwarded-For trust.
- Performance/concurrency/idempotency: atomic DB upsert across workers; expiry cleanup bounded; one counter mutation + one state read per protected bucket hit.
- Automated tests: `tests/php/enterprise_rate_limiting_p2_007.php` plus full backend/tenancy regression gate.
- Documentation/onboarding: this runbook documents policy, privacy, proxy trust, tuning, rollout, and rollback.
- CI/build/release: ESC Foundation, Android/artifact isolation, backend, and Flutter gates remain required before completion.
- Backward compatibility: non-ESC runtime no-op; previous Migration0018 remains registered at schema `1.17.0` while later migrations advance `LATEST_VERSION`.

## Regression coverage

`tests/php/enterprise_rate_limiting_p2_007.php` validates, among other boundaries:

- non-ESC no-op with no limiter database access;
- health and unrelated WordPress REST namespace exclusion;
- explicit default policy;
- login IP + username bucket privacy;
- no raw IP, username, or password in persisted limiter SQL;
- stable 429 and retry metadata without bucket-scope disclosure;
- locked-tenant bucket identity and resistance to forged tenant-header redirection;
- distinct tenant and read/write buckets;
- atomic increment/expiry-reset SQL;
- bounded expired-row cleanup;
- versioned schema and indexes;
- central plugin registration and REST ordering;
- no direct `X-Forwarded-For` trust;
- continued `ApiAbuseGuard` protections.
