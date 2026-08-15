# SafeContracts P2 validation — SC-P2-017..021

This validation batch verifies five already-implemented contract capabilities against the production plan and preserves the WordPress backend as the authoritative business-logic boundary.

## SC-P2-017 — Accountant assignment — Validate

Evidence:

- Assignment requires `safecontracts_assign_contracts`.
- The target user must be an eligible SafeContracts Accountant (`ACCESS + CREATE_CONTRACTS + VIEW_ASSIGNED`).
- Authorized unassignment is supported.
- Assigned-only users cannot use the assignment capability to cross their server-side data scope.
- Invalid or out-of-scope assignment attempts do not mutate contract data.
- `safecontracts_contract_accountant_assigned` remains available for history/audit integration.

Automated evidence: `tests/php/contracts_validation_017_021.php` plus the existing `tests/php/contracts_workflow.php` suite.

## SC-P2-018 — Contract status lifecycle — Validate

Validated lifecycle:

- `draft -> active`
- `draft -> cancelled`
- `active -> completed`
- `active -> cancelled`
- `completed` and `cancelled` are terminal.
- Unknown statuses are rejected.
- Archived contracts cannot change lifecycle state.
- Lifecycle changes remain capability- and scope-controlled.

Automated evidence: `tests/php/contracts_validation_017_021.php` and `tests/php/contracts_workflow.php`.

## SC-P2-019 — Contract dates — Validate

Validated rules:

- Dates use strict valid `YYYY-MM-DD` values.
- End date cannot precede start date.
- Either date can be explicitly cleared to `NULL`.
- Invalid ranges perform no mutation.
- Archived contracts are immutable for date changes.
- Date updates remain under the contract edit capability and existing data-scope boundary.

Automated evidence: `tests/php/contracts_validation_017_021.php` and `tests/php/contracts_financials.php`.

## SC-P2-020 — Financial line items — Validate

Validated rules:

- Financial items are stored in the dedicated WordPress-prefixed contract financial table.
- Amounts are authoritative fixed-point values normalized to four decimal places.
- Negative amounts are rejected before persistence.
- Explicit display ordering is persisted.
- Archived contracts cannot receive new financial lines.
- Financial mutations remain capability- and scope-controlled.

Automated evidence: `tests/php/contracts_validation_017_021.php`, `tests/php/contracts_financials.php`, and migration/schema regression tests.

## SC-P2-021 — Additions & discounts — Validate

Validated rules:

- Supported adjustment types are exactly `addition` and `discount`.
- Type input is normalized before persistence.
- Amounts use exact four-decimal fixed-point normalization.
- Unsupported adjustment types are rejected without mutation.
- Archived contracts cannot receive new adjustments.
- Additions and discounts remain separate from payment collection transactions and from future payment-schedule accounting.

Automated evidence: `tests/php/contracts_validation_017_021.php` and `tests/php/contracts_financials.php`.

## Validation-discovered regression repaired

The validation review found that the common `ContractService::editableContract()` path enforced capability and Accountant scope but did not reject an archived contract. That meant date, detail, base-value, financial-item, adjustment and attachment mutation paths could still proceed after archival even though the P2 contract implementation defined archived contracts as frozen for edits.

The shared editable-contract guard now rejects archived rows before any of those mutation repositories are reached. The dedicated validation suite asserts this explicitly for contract dates, financial items and additions/discounts, while the existing lifecycle suite continues to assert archived lifecycle protection.

## Quality-gate requirement

This batch is complete only when the repository standards, PHP/backend suite, Flutter formatting, Flutter analyzer and Flutter tests all pass on the PR head. The new validation suite is executed from `scripts/test-php.sh` so future changes cannot silently remove this coverage.
