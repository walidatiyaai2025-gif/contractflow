# SafeContracts P9 final validation — SC-P9-038..044

This closeout validates the remaining mobile operations against the hardened WordPress REST/domain boundary. The mobile client remains a scoped operational client; WordPress remains the business system of record.

## SC-P9-038 — Contract light edits — Validate

- `PATCH /contracts/{id}/light` remains capability gated by `safecontracts_edit_contracts`.
- Only contract number and paired start/end dates are accepted.
- Financial values, lifecycle state and assignment fields are outside the mobile light-edit allow-list.
- Contract scope, archived-state checks and audit events are delegated to `ContractService`.

## SC-P9-039 — Payments list — Validate

- Mobile paging is bounded to pages 1..5 and per-page 1..100.
- Customer/contract/status/due filters remain server-side scoped.
- Ordering is pinned to contractual `due_date ASC` and response metadata is verified.
- Duplicate payment IDs and malformed response envelopes fail closed.

## SC-P9-040 — Payment details — Validate

- Direct payment reads remain protected by server scope.
- 403 and 404 states are surfaced distinctly.
- `due_date`, `expected_payment_date`, original/paid/remaining amounts and status are displayed exactly as supplied by the server.
- Mobile performs no authoritative receivable recalculation.

## SC-P9-041 — Payment light edits — Validate

- The only mobile payment edit is `expected_payment_date`.
- The REST mutation loads the scoped payment and calls `PaymentService::updateDates()` with the existing contractual `due_date`, so the contractual due date cannot be rewritten by the client.
- Unknown fields, malformed dates, archived contracts and missing capabilities fail closed.
- Existing payment audit events record old/new expected date values.

## SC-P9-042 — Collection entry — Validate

- Collection entry is visible only when both remote feature configuration and `safecontracts_manage_collections` permit it.
- Payment method is mandatory; WordPress Media proof/reference remain optional.
- Client amount validation checks syntax only and never parses money into floating point or compares against remaining balance.
- `CollectionService` remains authoritative for transaction locking, ledger reconciliation, over-collection prevention, settlement state and audit events.

## SC-P9-043 — Payment-method lookup — Validate

- Mobile loads payment methods from protected `/reference-data`.
- The backend returns active payment methods only with stable IDs/names/order.
- Duplicate or malformed IDs fail closed on the client.
- No payment-method master list is hardcoded into the mobile application.

## SC-P9-044 — Follow-up workflow — Validate

- The queue/history remain scoped server reads.
- Mutations support exactly note, promise, issue, defer and escalate.
- Note/issue/escalate reject date pollution; promise requires `promised_date`; defer requires `deferred_until`.
- Follow-up state never mutates payment balance/status or contractual due date.
- Mutation UI is capability gated and domain audit remains server owned.

## Executable evidence

- `tests/php/rest_mobile_mutations_016_019.php`
- `tests/php/p9_validation_038_044.php`
- `mobile/test/mobile_validation_038_044_test.dart`
- existing P9/P10 regression suites executed by the repository Quality Gates.
