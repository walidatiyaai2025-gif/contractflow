# P8 REST — SC-P8-016 Pagination/filter/sort

`SC-P8-016` completes the first REST implementation cycle by defining a deterministic list-query contract for mobile/admin clients.

## Pagination

- `page` is a positive integer with a server safety ceiling of 100000.
- The previous artificial five-page ceiling is removed.
- `per_page` is restricted to 1..100.
- Non-integer/injection-like values fail with the canonical invalid-request envelope.
- Responses retain `page`, `per_page`, `returned`, `available_in_bounded_read` and `has_more` metadata.

The underlying V1 read repositories remain bounded to 500 rows. The larger page ceiling defines a stable API contract without implying an unbounded database read; later performance work can replace the bounded in-memory read with SQL-level pagination without changing client parameters.

## Filters

Existing server-side filters remain authoritative:

- customer
- contract
- accountant where the caller has all-data scope
- contract/payment status allowlists
- contractual due-date range

`due_from` and `due_to` are strict `YYYY-MM-DD` values and an inverted range (`due_from > due_to`) is rejected.

Accountant scoping remains independent of query parameters: assigned-only users cannot widen their scope through filters.

## Sort contract

Sort fields are allow-listed per resource and are never interpolated into SQL from user input.

- Customers: `id`, `name`, `internal_code` — default `name asc`.
- Contracts: `id`, `contract_number`, `customer_name`, `status`, `start_date`, `end_date`, `base_value` — default `id desc`.
- Payments: `id`, `due_date`, `expected_payment_date`, `sequence_no`, `remaining_amount`, `status`, `customer_name`, `contract_number` — default `due_date asc`.
- Collections: `id`, `collection_date`, `amount`, `customer_name`, `contract_number` — default `collection_date desc`.
- Follow-up queue: `payment_id`, `due_date`, `remaining_amount`, `status`, `customer_id`, `contract_id` — default `due_date asc`.
- Follow-up history: `id`, `created_at`, `state` — default `created_at desc`.

Direction is limited to `asc` or `desc`. Rows are sorted deterministically before page slicing, with stable entity-ID tie breakers where available. Effective `sort` and `direction` are returned in response metadata.

## Error behavior

Unsupported sort fields/directions, malformed pagination and invalid filter ranges are converted by the existing controller guard into `safecontracts_invalid_request` with HTTP 422 semantics.

## Regression evidence

`tests/php/rest_api_016.php` covers deep valid pages, pagination bounds, invalid integers, sort allowlists, invalid directions, inverted date ranges, combined filters/page/sort state, sorting-before-slicing, metadata and canonical 422 behavior.

The test is wired into `scripts/test-php.sh` and runs in Quality Gates alongside `rest_api_001_010.php` and `rest_api_011_015.php`.
