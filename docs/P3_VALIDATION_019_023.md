# P3 — Validation SC-P3-019..023

This validation batch confirms the production behavior already implemented for SafeContracts collection entry and payment settlement. It does not introduce a competing financial model, REST surface, UI behavior, or schema migration.

## SC-P3-019 — Mandatory payment method

Validation confirms every collection requires a SafeContracts payment-method ID. Missing methods are rejected before any transaction begins, inactive or unknown methods roll back without a ledger write, and active methods are persisted on the collection transaction.

## SC-P3-020 — Optional collection proof

Validation confirms proof remains optional. Omitted proof is stored as `NULL`, a supplied proof must reference a WordPress Media attachment, and invalid proof IDs are rejected before any financial transaction begins.

## SC-P3-021 — Partial collection

Validation confirms an authorized partial collection is recorded atomically with the settlement cache update. Exact fixed-point arithmetic updates cumulative `paid_amount`, exact `remaining_amount`, and `partially_paid` status without PHP floating-point arithmetic.

## SC-P3-022 — Full settlement

Validation confirms collecting the exact remaining amount after a partial collection produces cumulative paid amount equal to original amount, remaining balance `0.0000`, and `paid` status in the same database transaction.

## SC-P3-023 — Remaining-balance integrity

Validation confirms over-collection is rejected and that pre-existing divergence among the authoritative collection ledger, cached `paid_amount`, cached `remaining_amount`, and financial status blocks further settlement mutation. All such failures roll back cleanly instead of compounding corrupted state.

## Locked invariants

- WordPress remains the SafeContracts backend/source of truth.
- Collection ledger remains the authoritative collected-amount source.
- Payment method is mandatory for every collection.
- Collection proof is optional and uses a WordPress Media reference when supplied.
- `paid_amount = SUM(collection ledger)`.
- `remaining_amount = original_amount - paid_amount`.
- `paid_amount + remaining_amount = original_amount`.
- Over-collection is not silently clamped or tolerated.
- Partial settlement yields `partially_paid`; exact full settlement yields `paid`.
- No reversal, deletion, REST, UI, or financial reconciliation-validation behavior for SC-P3-024 is added in this batch.

## Automated evidence

`tests/php/payments_validation_019_023.php` is registered in `scripts/test-php.sh` and runs together with all existing foundation, contract, payment, collection, settlement, and prior P3 validation suites. Repository standards, backend tests, and Flutter mobile checks must all pass on the exact pull-request head before merge.
