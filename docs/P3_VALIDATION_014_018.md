# P3 — Validation SC-P3-014..018

This batch validates the production behavior already implemented for the SafeContracts payment lifecycle, contractual due dates and collection ledger. It does not introduce a competing business model or bypass the server-side capability/scope rules.

## SC-P3-014 — Payment lifecycle

Validation confirms the controlled lifecycle contains `upcoming`, `due_soon`, `due`, `overdue`, `partially_paid` and `paid`; temporal states may recalculate after authorized contractual date changes, while `paid` remains terminal until the explicit reversal/correction workflow is implemented.

## SC-P3-015 — Due & expected dates

Validation confirms `due_date` and `expected_payment_date` persist independently. `expected_payment_date` remains available for operational follow-up but does not replace the contractual due date for delinquency classification. Invalid calendar dates are rejected before mutation.

## SC-P3-016 — Due-soon calculation

Validation confirms the default 10-calendar-day window is inclusive: exactly ten days before contractual `due_date` is `due_soon`, while eleven days remains `upcoming`. A later expected-payment date cannot delay contractual Due Soon.

## SC-P3-017 — Overdue calculation

Validation confirms a contractual due date before today is `overdue`. A later expected-payment date cannot erase Overdue, and an earlier expected-payment date cannot create a false Overdue state when the contractual due date is still in the future.

## SC-P3-018 — Collection transaction model

Validation confirms a collection operation:

1. starts a database transaction;
2. locks the scheduled-payment row with `FOR UPDATE`;
3. validates capability, Accountant/Manager data scope, archive state and payment method;
4. appends the collection ledger transaction;
5. updates the derived scheduled-payment paid/remaining/status cache in the same transaction;
6. commits atomically, or rolls back on failure.

Collection-ledger reads remain server-side scope protected: an Accountant cannot read another Accountant's payment ledger by guessing an ID, while all-data capability can read authorized ledgers.

## Locked rules regression-protected

- WordPress plugin remains the complete backend and source of truth.
- Contractual `due_date` remains authoritative for Upcoming / Due Soon / Due / Overdue.
- `expected_payment_date` remains operational only.
- Payment method remains mandatory for collection entry.
- Collection proof remains optional.
- Collection ledger remains authoritative for settlement values.
- No reversal, hard delete or silent financial correction is added by this validation batch.

## Automated evidence

`tests/php/payments_validation_014_018.php` is executed by `scripts/test-php.sh` together with all existing foundation, contract and payment regression suites. Repository standards, PHP tests and Flutter mobile checks must all pass on the pull-request head before merge.
