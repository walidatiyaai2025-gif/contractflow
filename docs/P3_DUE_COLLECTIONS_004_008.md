# P3 — Due state and collections (SC-P3-004..008)

This batch implements five SafeContracts Payments & Collections tasks without pre-implementing partial/full settlement.

## SC-P3-004 / SC-P3-005 — Due Soon and Overdue

- Temporal receivable state is calculated from the **contractual `due_date`**.
- `expected_payment_date` remains an operational follow-up/promise date and never replaces or resets contractual Due/Due Soon/Overdue history.
- Default Due Soon window is 10 calendar days, aligned with the approved default notification timing; callers may supply another non-negative window.
- Contractual date today = `due`; past contractual date = `overdue`; future contractual date inside the window = `due_soon`; otherwise `upcoming`.
- WordPress/site date timezone is used for day boundaries when available.
- `partially_paid` and `paid` remain financial states and are kept conceptually distinct from temporal calculation; the combined service helper preserves those financial states when already set.
- Reads preserve Manager all-data vs Accountant assigned-contract scope.

`PaymentService::effectiveDate()` remains an operational helper (`expected_payment_date ?? due_date`). It is deliberately **not** used for contractual temporal classification.

## SC-P3-006 — Collection transaction model

Migration `1.7.0` adds `safecontracts_payment_collections` with:

- payment relation;
- fixed-point `DECIMAL(20,4)` amount;
- actual collection date;
- mandatory payment-method relation;
- optional reference/details;
- optional WordPress Media proof reference;
- created/updated actor IDs and UTC timestamps;
- reporting indexes by payment/date, method/date and proof Media ID.

Collection writes require `MANAGE_COLLECTIONS`, valid payment scope, and a non-archived contract. Each collection is appended as its own transaction; recording one collection does not silently overwrite payment totals.

## SC-P3-007 — Mandatory payment method

Every new collection must reference a positive, currently active SafeContracts payment-method row. The rule is enforced server-side and cannot be bypassed by mobile/UI clients. Historical collection rows retain their method ID if that reference method is later deactivated.

## SC-P3-008 — Optional proof

Proof is nullable. When provided it must be a valid WordPress Media attachment ID. SafeContracts stores only the Media reference, not duplicate file bytes. Omitting proof never blocks a valid collection transaction.

## Deliberate boundaries

This batch does **not** update `paid_amount`, `remaining_amount`, or settlement status after a collection. Those rules belong to `SC-P3-009 Partial collection`, `SC-P3-010 Full settlement` and `SC-P3-011 Remaining-balance integrity`.

No correction/reversal workflow is introduced in this batch; the later reversal task must be auditable and non-destructive.

## Validation

`tests/php/payments_due_collections.php` covers temporal boundaries, **contractual due-date precedence over expected payment date**, financial-state precedence, active/missing payment methods, optional/invalid proofs, Manager/Accountant scope, archive protection, collection writes and migration idempotence.
