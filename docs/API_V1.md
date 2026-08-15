# SafeContracts REST API v1

Base namespace: `/wp-json/safecontracts/v1`

## Security boundary

SafeContracts uses the authenticated WordPress REST session. The API does not introduce a second username/password store or return WordPress cookies, nonces, passwords, OAuth tokens, Firebase credentials, service-account material or private keys.

Protected routes require `safecontracts_access` plus a server-resolved data scope:

- `safecontracts_view_all` → all authorized SafeContracts business records.
- `safecontracts_view_assigned` → records assigned to the current WordPress user only.
- no data-scope capability → protected SafeContracts reads fail closed.

Authorization is capability-based. Clients must not send or rely on role names to obtain access. Direct object IDs are checked through the same scope boundary as lists.

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

Malformed IDs, statuses, dates and pagination values fail with `safecontracts_invalid_request` instead of being silently converted to an unfiltered request.

## Routes implemented by SC-P8-001..010

| Method | Route | Purpose |
|---|---|---|
| GET | `/health` | Public non-secret service health/version metadata |
| GET | `/me` | Authenticated SafeContracts session/capability summary |
| GET | `/session` | Alias of `/me` for mobile session bootstrap |
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

These ten P8 tasks intentionally add **read-only** API surfaces. Contract/payment/collection/follow-up mutation routes are not implemented early; later roadmap tasks will add them with their own capabilities, validation and audit requirements.

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

`tests/php/rest_api_001_010.php` validates route registration, response conventions, strict malformed-input rejection, reusable WordPress capability guards, assigned-accountant horizontal-access denial, safe field projections, due-date semantics and the read-only boundary. It is executed by `scripts/test-php.sh` and therefore by the repository Quality Gates workflow.
