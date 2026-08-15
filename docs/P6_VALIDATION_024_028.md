# P6 validation — SC-P6-024..028

This validation slice hardens the SafeContracts dashboard and core admin screens without moving P8 REST work forward.

## SC-P6-024 — Dashboard KPIs

- KPI queries remain server-side in `AdminReadRepository`.
- `VIEW_ASSIGNED` always binds accountant scope to the current user; query parameters can only narrow, never widen, scope.
- Overdue exposure continues to use contractual `due_date` plus positive `remaining_amount`.
- Empty aggregate results return deterministic zero values.

## SC-P6-025 — Dashboard filters

- IDs accept only non-negative integer scalars; arrays, booleans, decimals, negatives and malformed values fail closed to `0`.
- Status remains a closed contract/payment allow-list.
- Dates accept valid `YYYY-MM-DD` scalar values only; malformed values fail closed and inverted valid windows normalize deterministically.
- Customer + contract filters are both enforced server-side, preserving dependent-contract semantics.

## SC-P6-026 — Customers screen

- Customer list and selected edit lookup use the same scoped `AdminReadRepository::customers()` boundary.
- An assigned-scope user cannot reveal a customer by changing `customer_id` unless an assigned contract makes that customer visible.
- Mutation remains delegated to `CustomerService` and output remains WordPress-escaped.

## SC-P6-027 — Contracts screen

- Create submissions require `CREATE_CONTRACTS`.
- Existing-contract submissions require `EDIT_CONTRACTS` and/or `ASSIGN_CONTRACTS`; these capabilities remain independent.
- Selected contract reads retain assignment scope.
- Archived contracts remain frozen by `ContractService` and render read-only.

## SC-P6-028 — Payments screen

- `PaymentService::find()` provides a bounded direct payment-ID read with the same `VIEW_ALL` / `VIEW_ASSIGNED` scope rules as mutations.
- The admin payment detail screen no longer falls back to scanning up to 500 payment rows to locate an explicit payment ID.
- Terminal detection uses exact fixed-point comparison (`ContractMoney`) and treats payments on archived contracts as read-only.
- Contractual `due_date` remains authoritative for due/overdue classification; `expected_payment_date` remains operational only.

## Regression coverage

`tests/php/admin_ui_validation_024_028.php` covers malformed filters, KPI/read scope, customer edit scope, archived contract/payment behavior, independent contract mutation capabilities, direct scoped payment detail lookup, and due-date authority. It is part of `scripts/test-php.sh` and therefore runs in the repository Quality Gates workflow.
