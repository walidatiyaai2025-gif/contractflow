# P3 — Settlement & validation (SC-P3-009..018)

This batch completes four payment-settlement implementation tasks and six validation tasks on top of the existing scheduled-payment and collection-ledger foundation.

## SC-P3-009 — Partial collection

A collection is still an individual immutable ledger transaction. After the transaction is appended, SafeContracts derives the payment cache from the ledger total:

- `paid_amount = SUM(collection transactions)`
- `remaining_amount = original_amount - paid_amount`
- when `0 < paid_amount < original_amount`, payment financial state becomes `partially_paid`

The calculation uses fixed-point `DECIMAL(20,4)` compatible string arithmetic; no floating-point math is used.

## SC-P3-010 — Full settlement

When cumulative collection transactions exactly equal the scheduled payment original amount:

- `paid_amount = original_amount`
- `remaining_amount = 0.0000`
- payment financial state becomes `paid`

The collection ledger remains the source of the derived balance.

## SC-P3-011 — Remaining-balance integrity

Collection recording is transaction-bounded:

1. `START TRANSACTION`
2. lock the scheduled payment row with `FOR UPDATE`
3. validate scope, archive state and active payment method
4. read current collection-ledger total
5. reject projected collections above `original_amount`
6. append collection transaction
7. update cached paid/remaining/status values
8. `COMMIT`

Any failure rolls back. Over-collection is rejected before ledger insertion. Historical over-collected ledgers are detected during reconciliation and are never converted to a negative remaining balance.

## SC-P3-012 — Financial reconciliation

`CollectionService::reconcilePayment()` compares the immutable collection ledger with the scheduled-payment cached fields and returns:

- original amount
- ledger collected amount
- stored vs expected paid amount
- stored vs expected remaining amount
- stored vs expected financial status
- over-collection flag
- consistency flag

`repairPaymentBalance()` is capability- and scope-protected and rewrites only the derived cached balance/status fields from the ledger. It refuses automatic repair when the ledger itself is over-collected because that requires the later explicit correction/reversal workflow rather than silent data deletion.

## SC-P3-013..018 — Validation coverage

`tests/php/payments_validation_013_018.php` independently validates:

- **SC-P3-013 Payment schedule model** — schema, fixed precision, contract sequence uniqueness, schedule creation.
- **SC-P3-014 Payment lifecycle** — approved states, recalculable temporal states, terminal paid state without reversal.
- **SC-P3-015 Due & expected dates** — independent persistence; expected date remains operational only.
- **SC-P3-016 Due-soon calculation** — contractual `due_date`, inclusive 10-day boundary.
- **SC-P3-017 Overdue calculation** — contractual `due_date` remains authoritative regardless of expected date.
- **SC-P3-018 Collection transaction model** — payment-row lock, append-only ledger insert, atomic balance cache update, server-side Accountant scope.

## Locked business rules preserved

- WordPress remains the complete backend and single source of truth.
- Accountant scope is enforced server-side; Manager/all-data capability remains unrestricted by assignment.
- Payment method is mandatory for every collection.
- Collection proof remains optional.
- Contractual `due_date` is authoritative for Upcoming / Due Soon / Due / Overdue.
- `expected_payment_date` is operational follow-up data and never moves contractual delinquency state.
- No hard deletion or silent collection correction is introduced.
- No per-payment currency is introduced; V1 remains single-currency.

## Test execution

`./scripts/test-php.sh` now runs the existing regressions plus:

- `tests/php/payments_settlement_009_012.php`
- `tests/php/payments_validation_013_018.php`

All repository, PHP and mobile quality gates must pass before these tasks are considered complete.
