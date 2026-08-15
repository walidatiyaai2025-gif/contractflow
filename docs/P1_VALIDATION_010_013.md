# P1 Master Data Validation — SC-P1-010..013

This report records permanent validation evidence for the final four P1 Master Data tasks. The implementation source remains the merged P1 work; this document does not duplicate production logic.

## SC-P1-010 — Payment method master table — Validate

Validated production properties:

- Dedicated `safecontracts_payment_methods` table created through the versioned migration framework.
- Stable unique `code` key.
- Editable `name`, `display_order`, and `is_active` fields.
- Active/order index supports authoritative ordered reference-data reads.
- Repository reads/writes use the prefixed custom table and prepared SQL.

Automated evidence lives in `tests/php/run.php` and is executed by `scripts/test-php.sh` in the repository Quality Gates.

## SC-P1-011 — Default payment methods — Validate

Validated default reference data:

1. Cash (`cash`, order 10)
2. Bank Transfer (`bank_transfer`, order 20)
3. Wallet (`wallet`, order 30)

The seed uses `ON DUPLICATE KEY UPDATE`, making migration retries safe and preserving stable codes. Automated tests assert all three defaults and idempotency.

## SC-P1-012 — Reference-data administration — Validate

Validated administration behavior:

- Payment Methods is exposed as the functional reference-data section of SafeContracts Settings.
- Administration is protected by `safecontracts_manage_reference_data`.
- The default grant is limited to SafeContracts System Administrator and native WordPress Administrator.
- Manager and Accountant do not receive reference-data administration by default.
- WordPress writes are nonce-protected and routed through the shared repository instead of duplicating persistence rules.
- Create/update by stable code supports rename, display order, activation and deactivation.

## SC-P1-013 — Reference-data APIs — Validate

Validated API behavior in namespace `safecontracts/v1`:

- `GET /payment-methods` uses normal SafeContracts access authorization and returns active methods only.
- Active methods are ordered by `display_order`, then name.
- `GET /admin/payment-methods` includes inactive rows and requires reference-data administration capability.
- `POST /admin/payment-methods` requires the same capability, sanitizes inputs, validates code/name, and writes through prepared SQL.
- Invalid writes return a validation error without mutating reference data.

## Historical implementation evidence

- PR #87 established the payment-method table and default seed.
- PR #88 implemented administration, APIs, authorization and expanded regression coverage.
- PR #88 Quality Gates run `31875914771` completed successfully with repository standards, backend tests and Flutter regression gates green.

The PR carrying this validation report must also pass the current Quality Gates before SC-P1-010..013 are closed.
