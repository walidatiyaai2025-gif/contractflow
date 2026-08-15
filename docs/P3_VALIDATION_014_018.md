# P3 validation — SC-P3-014..018

This validation batch closes five Payments & Collections validation tasks against the post-settlement SafeContracts baseline. It does not introduce new product behavior or schema changes.

## SC-P3-014 — Payment lifecycle

Validation proves:

- lifecycle vocabulary remains `upcoming`, `due_soon`, `due`, `overdue`, `partially_paid`, `paid`;
- temporal states may be recalculated when contractual due dates change;
- `paid` remains terminal until an explicit later reversal workflow exists;
- authorized lifecycle mutations persist and emit `safecontracts_payment_status_changed`;
- rejected paid-state exits do not mutate data.

## SC-P3-015 — Due & expected dates

Validation proves:

- contractual `due_date` and operational `expected_payment_date` persist independently;
- date edits emit `safecontracts_payment_dates_changed` for later audit integration;
- `effectiveDate()` exposes expected date for operational follow-up and falls back to due date when absent;
- invalid calendar dates are rejected before mutation.

The locked aging rule remains: expected payment date does not replace contractual due date for Due/Due Soon/Overdue classification.

## SC-P3-016 — Due Soon

Validation proves the default 10-calendar-day boundary:

- exactly 10 days before contractual due date = `due_soon`;
- 11 days before contractual due date = `upcoming`;
- a later expected payment date cannot delay contractual Due Soon;
- negative windows are rejected.

## SC-P3-017 — Overdue

Validation proves:

- a past contractual due date = `overdue`;
- a later expected payment date cannot erase contractual Overdue;
- an earlier expected payment date cannot create false contractual Overdue;
- financial `partially_paid` state remains distinct from temporal aging in the combined service helper.

## SC-P3-018 — Collection transaction model

Validation runs against the settlement-integrated collection implementation and proves:

- scheduled payment row is locked with `SELECT ... FOR UPDATE`;
- collection ledger row and payment settlement cache update occur inside one transaction;
- successful mutation starts with `START TRANSACTION` and ends with `COMMIT`;
- ledger and settlement domain events are emitted;
- Accountant scope is enforced on collection reads;
- normalized ledger reads preserve financial amount and transaction reference.

## Regression boundary

Existing P3 implementation/regression suites continue to own implementation-level coverage for settlement integrity and reconciliation. This batch adds explicit acceptance evidence for SC-P3-014..018 only.

No database migration is required; current schema version remains unchanged.
