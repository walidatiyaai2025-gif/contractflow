# P6 Dashboard & Core Screens — SC-P6-004..008

This slice turns the SafeContracts WordPress admin shell into the first operational workspace.

## Delivered

- **SC-P6-004 Dashboard KPIs** — scoped contract count, scheduled receivables, remaining balance, overdue exposure and collected amount.
- **SC-P6-005 Dashboard filters** — customer, dependent contract, accountant, status and due-window filters. Assigned users cannot widen scope through query parameters.
- **SC-P6-006 Customers screens** — scoped list plus capability-gated create/update flow. Optional `internal_code` persists as SQL `NULL` when blank so the unique index does not block multiple customers without a code.
- **SC-P6-007 Contracts screens** — scoped list/detail/create/edit/assignment entry points that delegate domain mutations and reconciliation to `ContractService`.
- **SC-P6-008 Payments screens** — scoped scheduled-payment list/detail/create/date-edit entry points that delegate to `PaymentService`; contractual `due_date` remains authoritative and settled payments are rendered read-only.

## Security and architecture boundaries

- Presentation page classes contain no direct `$wpdb` access.
- `AdminReadRepository` is the single read-model boundary for dashboard/list SQL and applies `VIEW_ALL` / `VIEW_ASSIGNED` scope server-side.
- Customer writes require `safecontracts_manage_reference_data`.
- Contract/payment mutations remain governed by their existing domain services and capabilities.
- Admin navigation hiding remains UX-only and never replaces authorization.

## UX

- Existing SafeContracts navy/blue + green identity is retained.
- Core dashboard styles layer after the base stylesheet instead of replacing it.
- Layouts collapse to single-column controls/cards on narrow admin/mobile viewports and remain RTL-compatible.
