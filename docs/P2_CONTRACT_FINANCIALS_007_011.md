# SafeContracts — P2 Contract Dates & Financials (SC-P2-007..011)

This delivery implements the next five P2 contract work items while keeping REST/UI, scheduled payments and audit-history persistence in their dedicated later tasks.

## SC-P2-007 — Contract dates

- `start_date` and `end_date` remain on the authoritative contract row.
- Authorized edits require `safecontracts_edit_contracts` and the current server-side contract data scope.
- Dates use strict `YYYY-MM-DD` calendar validation.
- Either date may be empty/null.
- When both exist, `end_date` cannot be earlier than `start_date`.
- Successful changes emit `safecontracts_contract_dates_changed` for later audit-history integration.

## SC-P2-008 — Financial line items

Migration `1.4.0` adds `safecontracts_contract_financial_items` with:

- contract relation;
- required description;
- `DECIMAL(20,4)` amount;
- explicit display order;
- creator/updater/timestamps;
- contract/order reporting index.

The same bounded financial workflow also exposes authorized base-value updates because the existing contract model already contains `base_value` and reconciliation requires an operational way to maintain it.

Amounts are normalized as fixed-point decimal strings and are never calculated with PHP floating-point arithmetic.

## SC-P2-009 — Additions & discounts

Migration `1.4.0` adds `safecontracts_contract_adjustments`.

Allowed server-side types are exactly:

- `addition`
- `discount`

Amounts are stored as positive fixed-point values; the type determines their effect on reconciliation. This avoids ambiguous negative-value conventions in stored rows.

## SC-P2-010 — Net-value reconciliation

Net contractual value is computed server-side and is not stored as a competing mutable total:

`net = base_value + financial_items + additions - discounts`

The reconciliation response exposes every component separately:

- base value;
- financial-item total;
- additions;
- discounts;
- computed net value.

The implementation uses decimal-string arithmetic to avoid binary floating-point drift, including at the full `DECIMAL(20,4)` input range. A negative net is surfaced transparently rather than silently clamped, allowing later validation/reporting layers to flag unusual business data without falsifying the reconciliation.

Scheduled payment totals are intentionally not part of this calculation. The Master Plan explicitly treats payment schedules as a separate receivables workflow and requires transparent reconciliation where contract totals do not necessarily equal scheduled-payment sums.

Read reconciliation obeys the same Manager/all vs Accountant/assigned server-side scope as the contract itself.

## SC-P2-011 — Notes & attachments

Contract notes continue through the existing capability/scope-controlled contract edit workflow.

Migration `1.4.0` adds `safecontracts_contract_attachments` as a document-reference table:

- contract relation;
- WordPress Media `media_id`;
- optional label;
- creator/timestamp;
- unique contract/media pair;
- media lookup index.

SafeContracts does not copy file bytes into custom database rows. Attachments reference valid WordPress Media attachments and can be linked/unlinked without deleting the underlying Media object.

## Authorization

All financial/date/attachment mutations require `safecontracts_edit_contracts` plus current contract data scope. A capability grant does not bypass Accountant assigned-only scope.

## Audit integration

This batch emits domain actions for date, base-value, line-item, adjustment and attachment mutations. Persistent before/after contract history remains `SC-P2-012` and is intentionally not implemented here.

## Validation

`tests/php/contracts_financials.php` covers migration schema, date validation, fixed-point normalization, line items, additions/discounts, exact reconciliation, WordPress Media references, notes and scope isolation. Existing contract/foundation regressions remain enabled in `scripts/test-php.sh`.
