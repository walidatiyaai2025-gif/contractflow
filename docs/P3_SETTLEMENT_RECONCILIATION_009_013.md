# P3 — Settlement & reconciliation (SC-P3-009..013)

This batch turns the collection ledger into authoritative payment settlement state while preserving the collection model introduced in SC-P3-006..008.

## Financial invariants

For every scheduled payment:

- `ledger_collected = SUM(collection.amount)`.
- `paid_amount = ledger_collected`.
- `remaining_amount = original_amount - ledger_collected`.
- `paid_amount + remaining_amount = original_amount`.
- `ledger_collected` may never exceed `original_amount`.
- All arithmetic uses exact fixed-point `DECIMAL(20,4)` semantics; PHP floating point is not used.

If an existing stored payment does not reconcile with its ledger, new collection mutation is blocked instead of compounding corrupted financial state. The reconciliation read exposes the mismatch transparently.

## SC-P3-009 — Partial collection

A positive collection below the current remaining balance:

1. locks the payment row inside a database transaction;
2. verifies scope, archive state and active payment method;
3. verifies existing ledger/stored balance integrity;
4. appends the collection transaction;
5. sets cumulative `paid_amount` from the ledger total;
6. recalculates `remaining_amount` exactly;
7. sets financial status to `partially_paid`;
8. commits collection + balance/status changes together.

## SC-P3-010 — Full settlement

When the new cumulative collection total equals `original_amount` exactly:

- `paid_amount = original_amount`;
- `remaining_amount = 0.0000`;
- status becomes `paid`.

No tolerance or float comparison is used.

## SC-P3-011 — Remaining-balance integrity

Collection writes are serialized by locking the scheduled-payment row (`SELECT ... FOR UPDATE`) inside an explicit transaction.

The service rejects:

- collection amount greater than remaining balance;
- an existing collection ledger already above original amount;
- stored `paid_amount` different from the ledger total;
- stored `remaining_amount` different from `original - ledger`;
- stored `paid + remaining` different from original;
- a financial status inconsistent with an existing non-zero ledger.

Any failure after the transaction begins is rolled back. Successful ledger append and payment balance/status update are committed together.

## SC-P3-012 — Financial reconciliation

`CollectionService::reconcilePayment()` returns an authorized, scope-aware financial reconciliation containing:

- original amount;
- collection-ledger total;
- stored paid amount;
- stored remaining amount;
- expected remaining amount;
- stored status;
- expected financial status when collections exist;
- explicit over-collection flag;
- overall balanced flag.

Negative reconciliation variance is surfaced (for example `-1.0000`) rather than clamped or hidden.

## SC-P3-013 — Payment schedule model validation

The dedicated settlement validation suite re-validates the payment schedule data model at migration `1.7.0`:

- contract relation;
- explicit per-contract sequence;
- contractual due date;
- nullable operational expected date;
- original/paid/remaining `DECIMAL(20,4)` values;
- controlled status storage;
- unique `(contract_id, sequence_no)`;
- contract/status/due index;
- migration idempotence.

No schema migration is required for SC-P3-009..013; the existing payment and collection tables already contain the required fields.

## Validation

- `tests/php/payments_due_collections.php` keeps SC-P3-004..008 regression coverage current after settlement behavior is introduced.
- `tests/php/payments_settlement.php` covers SC-P3-009..013, including partial/full settlement, exact arithmetic, transaction commit/rollback, over-collection rejection, integrity mismatch rejection, reconciliation transparency and Accountant scope.
- `scripts/test-php.sh` runs both suites plus all prior backend regressions.
