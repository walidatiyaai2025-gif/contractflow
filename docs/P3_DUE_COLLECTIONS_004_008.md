# P3 — Due state and collections (SC-P3-004..008)

This batch implements the next five SafeContracts Payments & Collections tasks without pre-implementing settlement/balance tasks.

## SC-P3-004 / SC-P3-005 — Due Soon and Overdue

- Temporal states are calculated from the payment **effective operational date**: `expected_payment_date` when present, otherwise `due_date`.
- Default Due Soon window is 10 calendar days, aligned with the locked default notification timing; callers may supply another non-negative window.
- Exact effective date = `due`; past effective date = `overdue`; future date inside the window = `due_soon`; otherwise `upcoming`.
- `partially_paid` and `paid` remain financial states and take precedence over temporal recalculation.
- Reads preserve Manager all-data vs Accountant assigned-contract scope.

## SC-P3-006 — Collection transaction model

Migration `1.7.0` adds `safecontracts_payment_collections` with:

- payment relation
- fixed-point `DECIMAL(20,4)` amount
- collection date
- mandatory payment-method relation
- optional reference/details
- optional WordPress Media proof reference
- created/updated actor IDs and UTC timestamps
- reporting indexes by payment/date, method/date and proof Media ID

Collection writes require `MANAGE_COLLECTIONS`, valid payment scope, and a non-archived contract.

## SC-P3-007 — Mandatory payment method

Every new collection must reference a positive, currently active SafeContracts payment-method row. Historical records keep the method ID even if that reference data is later deactivated.

## SC-P3-008 — Optional proof

Proof is nullable. When provided it must be a valid WordPress Media attachment ID. SafeContracts stores only the Media reference, not duplicate file bytes.

## Deliberate boundaries

This batch does **not** update `paid_amount`, `remaining_amount`, or settlement status after a collection. Those rules belong to SC-P3-009 Partial collection, SC-P3-010 Full settlement and SC-P3-011 Remaining-balance integrity. No reversal workflow is introduced in this batch.

## Validation

`tests/php/payments_due_collections.php` covers temporal boundaries, expected-date precedence, payment financial-state precedence, active/missing payment methods, optional/invalid proofs, Manager/Accountant scope, archive protection, append-only collection writes and migration idempotence.
