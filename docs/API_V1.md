# SafeContracts REST API v1

Base namespace: `/wp-json/safecontracts/v1`

## Security boundary

SafeContracts uses the authenticated WordPress REST session. The API does not introduce a second username/password store or return WordPress cookies, nonces, passwords, OAuth tokens, Firebase credentials, service-account material or private keys.

Protected routes require `safecontracts_access` plus a server-resolved data scope:

- `safecontracts_view_all` → all authorized SafeContracts business records.
- `safecontracts_view_assigned` → records assigned to the current WordPress user only.
- no data-scope capability → protected SafeContracts reads and mutations fail closed.

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

Malformed IDs, statuses, dates, mutation fields and pagination values fail instead of being silently converted to an unfiltered or wider request.

## Read routes

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
| GET | `/dashboard` | Scoped dashboard KPIs/options |
| GET | `/mobile-config` | Non-secret mobile feature/config bootstrap |
| GET | `/reference-data` | Active mobile reference data |
| GET | `/reports/excel` | Capability-gated server-generated XLSX export |

## Narrow mobile mutation routes

P9 adds only the mutation surfaces needed by approved mobile workflows:

| Method | Route | Capability | Behavior |
|---|---|---|---|
| PATCH | `/contracts/{id}/light` | `safecontracts_edit_contracts` | Contract number and paired start/end dates only; delegates to `ContractService` |
| PATCH | `/payments/{id}/expected-date` | `safecontracts_manage_payments` | Operational expected-payment date only; preserves contractual due date and delegates to `PaymentService` |
| POST | `/collections/record` | `safecontracts_manage_collections` | Records a collection through `CollectionService`; payment method required, proof optional |
| POST | `/payments/{payment_id}/followups/record` | `safecontracts_manage_followups` | Operational note/promise/issue/defer/escalate through `FollowUpService` |

These endpoints additionally require normal SafeContracts access and data scope. They reject unknown fields, recheck operation capabilities in the callback, contain no presentation-layer SQL and reuse the domain services' validation, archive restrictions, assignment scope, financial/follow-up state rules and audit hooks.

## Filters and paging

List reads accept the common SafeContracts filters where relevant:

- `customer_id`
- `contract_id`
- `accountant_user_id` (effective only within server-authorized scope)
- `status`
- `due_from` / `due_to` (`YYYY-MM-DD`)
- `page` (1–5)
- `per_page` (1–100)
- endpoint-specific `sort` / `order` allow-lists

The underlying read boundary is capped at 500 rows. The dependent contract endpoint returns only server-authorized contracts. `client_may_offer_all_option=true` means a mobile dropdown may display an **All contracts** choice; it is not a record returned by the server and never widens the user's authorized data set.

## Data semantics

- Contractual `due_date` remains the receivable due-date authority.
- `expected_payment_date` is separate operational information and never rewrites the contractual due date.
- Payment `paid_amount` and `remaining_amount` are server-authoritative values.
- Customer/contract internal free-text notes are not included in mobile-facing reads.
- Collection free-text details are not included in read projections; proof is represented only by safe media metadata/identifier already authorized by record scope.
- Collection settlement and remaining-balance validation are server-side domain responsibilities, never mobile calculations.
- Follow-up promise/deferred dates remain operational fields and are not contractual due dates.
- Follow-up mutations never directly mutate payment status or financial balances from the mobile request.

## Regression evidence

REST suites under `tests/php/rest_api_*.php` validate the P8 read/version/security contract. `tests/php/rest_mobile_mutations_010_019.php` validates the narrow P9 mutation routes, capability gates, fail-closed field allow-lists and domain-service delegation. All are executed by `scripts/test-php.sh` and repository Quality Gates.
