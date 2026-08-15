# P2 Contract Finance — SC-P2-007..011

This document records the implementation boundary for the SafeContracts contract dates, financial composition, reconciliation, notes and attachment work.

## SC-P2-007 — Contract dates

- Contract start/end dates remain nullable.
- Authorized users with `safecontracts_edit_contracts` can update dates only within their server-side data scope.
- Values must use `YYYY-MM-DD`.
- End date cannot precede start date.
- Changes emit `safecontracts_contract_dates_changed` for later audit-history integration.

## SC-P2-008 — Financial line items

- Dedicated table: `{$wpdb->prefix}safecontracts_contract_financial_items`.
- Standard line items use type `line`.
- Amounts are fixed-point `DECIMAL(20,4)` and application input is normalized to four decimal places.
- Zero-value items are rejected.
- Items are deactivated rather than physically deleted.
- Contract/type/active/order access is indexed for reporting.

## SC-P2-009 — Additions & discounts

The same financial-item ledger supports controlled types:

- `line`
- `addition`
- `discount`

The type list is server-owned and is not inferred from a signed amount. Amounts remain non-negative; discount semantics are represented by the explicit `discount` type.

## SC-P2-010 — Net-value reconciliation

Reconciliation is authoritative on WordPress and returns:

- base value
- standard financial line total
- additions total
- discounts total
- gross value = base + lines + additions
- net value = gross - discounts

SafeContracts uses string-based fixed-scale decimal arithmetic rather than binary floating-point for financial reconciliation. A reconciliation that would produce a negative net contractual value is rejected instead of silently storing an invalid result.

The V1 currency remains a system-level setting; no competing per-contract currency field is introduced.

## SC-P2-011 — Contract notes & attachments

- Contract notes continue to use the existing `notes` field and capability/scope-controlled edit workflow.
- Attachments are WordPress Media Library references rather than copied binary files in custom tables.
- Dedicated relation table: `{$wpdb->prefix}safecontracts_contract_attachments`.
- The same media item can be safely re-linked to a contract through an idempotent unique `(contract_id, media_id)` relation.
- Detaching is non-destructive (`is_active = 0`).
- In real WordPress runtime, the service verifies the supplied media ID resolves to post type `attachment`.

## Security and audit boundary

All mutations in this scope require `safecontracts_edit_contracts` and then enforce Manager/all-data or Accountant/assigned-data scope. Archived contracts reject the new date, financial and attachment mutations. Domain actions are emitted for material changes so P4 audit/history can consume them without duplicating business rules.

No REST CRUD surface is added in this bounded P2 change; mobile remains API-driven and later P8 tasks expose approved operations.
