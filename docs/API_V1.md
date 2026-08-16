# SafeContracts REST API v1

Base namespace: `/wp-json/safecontracts/v1`

## Security boundary

SafeContracts uses the authenticated WordPress REST session. The API does not introduce a second username/password store or return WordPress cookies, nonces, passwords, OAuth tokens, Firebase credentials, service-account material or private keys.

Protected routes require `safecontracts_access` plus a server-resolved data scope:

- `safecontracts_view_all` → all authorized SafeContracts business records.
- `safecontracts_view_assigned` → records assigned to the current WordPress user only.
- no data-scope capability → protected SafeContracts reads fail closed.

Authorization is capability-based. Clients must not send or rely on role names to obtain access. Direct object IDs are checked through the same scope boundary as lists.

## Enterprise tenant selection (ESC branch)

Enterprise Safe Contracts adds an independent tenant-selection boundary without weakening the existing capability/data-scope checks.

- Stable request header: `X-ESC-Tenant-ID`.
- The header is **selection input only**. It never grants access by itself.
- Server-side code verifies that the authenticated WordPress user has an active membership in the requested active tenant before locking the request `TenantContext`.
- If the user has exactly one active tenant and no header is sent, the server may select it automatically.
- A tenant-required operation fails closed when the user has no active tenant or has multiple active tenants without an explicit selection.
- Tenant context is reset at each REST request boundary so a long-running PHP/test process cannot leak one tenant into another request.
- `GET /tenants` returns only active tenant memberships for the current user and reports the selected tenant id when selection succeeds.
- `GET /me` and `GET /session` may include the selected authorized tenant identity under `data.tenant`; unselected/transition users receive `null` rather than a guessed tenant.

During the staged P1 migration, existing customer/contract/payment tables are not yet treated as tenant-owned merely because this header exists. Business-table ownership is added only after the request context and cross-tenant tests are green.

## Response contract

Successful responses use:

```json
{
  "data": {},
  "meta": {
    "api_version": "v1"
  }
}
```

List responses add bounded pagination/scope metadata. WordPress renders API failures from `WP_Error` using a stable SafeContracts code, message and HTTP status. Internal exceptions and secrets are never returned as diagnostic payloads.

Malformed IDs, statuses, dates and pagination values fail with `safecontracts_invalid_request` instead of being silently converted to an unfiltered request. ESC tenant selection additionally uses stable `esc_tenant_*` errors for malformed headers, missing/ambiguous membership, forbidden tenants and request-context conflicts.

## Routes implemented by SC-P8-001..010 and ESC P1

| Method | Route | Purpose |
|---|---|---|
| GET | `/health` | Public non-secret service health/version metadata |
| GET | `/me` | Authenticated SafeContracts session/capability summary; ESC may include selected tenant |
| GET | `/session` | Alias of `/me` for mobile session bootstrap |
| GET | `/tenants` | ESC-only active tenant memberships available to the authenticated user |
| GET | `/customers` | Scoped customer list |
| GET | `/customers/{id}` | Scoped customer detail |
| GET | `/filters/contracts?customer_id={id}` | Customer-dependent authorized contract options |
| GET | `/contracts` | Scoped contract list |
| GET | `/contracts/{id}` | Scoped contract detail |
| GET | `/payments` | Scoped scheduled-payment list |
| GET | `/payments/{id}` | Scoped payment detail |
| GET | `/collections` | Scoped collection ledger |
| GET | `/collections/{id}` | Scoped collection detail |
| GET | `/followups` | Scoped operational follow-up queue |
| GET | `/payments/{payment_id}/followups` | Scoped append-only follow-up history |

The original P8 tasks intentionally added read-only client surfaces. Later roadmap tasks add mutations with their own capabilities, validation and audit requirements. ESC tenant ownership remains staged so the new platform does not silently reinterpret client data before explicit ownership migrations.

## Filters and paging

The initial read layer accepts the common SafeContracts filters where relevant:

- `customer_id`
- `contract_id`
- `accountant_user_id` (effective only within server-authorized scope)
- `status`
- `due_from` / `due_to` (`YYYY-MM-DD`)
- `page` (1–5)
- `per_page` (1–100)

The underlying read boundary is capped at 500 rows in this phase. P8's later pagination/filter/sort task may evolve transport pagination without weakening scope checks.

The dependent contract endpoint returns only server-authorized contracts. `client_may_offer_all_option=true` means a mobile dropdown may display an **All contracts** choice; it is not a record returned by the server and never widens the user's authorized data set.

## Data semantics

- Contractual `due_date` remains the receivable due-date authority.
- `expected_payment_date` is separate operational information and never rewrites the contractual due date.
- Payment `paid_amount` and `remaining_amount` are server-authoritative values.
- Customer/contract internal free-text notes are not included in these mobile-facing reads.
- Collection free-text details are not included; proof is represented only by safe media metadata/identifier already authorized by the record scope.
- Follow-up promise/deferred dates remain operational follow-up fields and are not presented as contractual due dates.

## Regression evidence

`tests/php/rest_api_001_010.php` validates the original route registration, response conventions, strict malformed-input rejection, reusable WordPress capability guards, assigned-accountant horizontal-access denial, safe field projections, due-date semantics and the read-only boundary.

ESC adds `tests/php/enterprise_tenancy_foundation.php` and `tests/php/enterprise_tenant_rest_context.php` for tenant registry/context, active membership checks, unauthorized cross-tenant selection, ambiguous selection, request-boundary reset and `/me`/`/tenants` identity projection. Flutter tests validate that only the dedicated tenant provider can emit `X-ESC-Tenant-ID`.

All backend tests are executed by `scripts/test-php.sh` and the ESC Foundation Gate additionally runs Flutter format/analyze/test.
