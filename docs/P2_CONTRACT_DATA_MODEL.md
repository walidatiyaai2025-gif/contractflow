# P2 Contract Data Model — SC-P2-001

SafeContracts contract data is stored in a dedicated plugin table created through migration `1.3.0`.

## Table

`{$wpdb->prefix}safecontracts_contracts`

Core fields:

- `id` — primary key.
- `contract_number` — required, globally unique business reference.
- `customer_id` — required relation to the SafeContracts customer master table.
- `accountant_user_id` — required responsible WordPress user/accountant relation used by later scope enforcement.
- `status` — stable string state with `draft` as the initial storage baseline. Lifecycle rules are implemented in the dedicated status task.
- `start_date`, `end_date` — nullable contract date fields; editing/validation behavior belongs to the contract-dates task.
- `base_value` — fixed-point `DECIMAL(19,3)` value, avoiding floating-point financial storage.
- `archived_at` — nullable non-destructive archive marker.
- `created_by`, `created_at`, `updated_at` — audit-supporting creation/update metadata.

## Indexes

- Unique contract-number index.
- Customer + status index for customer portfolio/reporting paths.
- Accountant + status index for assigned-accountant scope/work queues.
- Start/end date index for date filtering.
- Archive index for active/archive filtering.

## V1 currency rule

V1 uses one system currency (`DEC-012`). The contract table therefore does not duplicate a per-contract currency code. Monetary values use three-decimal fixed-point precision so the model safely supports currencies such as KWD while remaining deterministic for financial calculations.

## Intentional boundaries

SC-P2-001 establishes storage primitives only. It does **not** implement later task scopes:

- Contract create workflow.
- Contract edit authorization/workflow.
- Customer/accountant assignment validation and reassignment behavior.
- Contract status lifecycle transitions.
- Date validation/edit audit behavior.
- Financial line items.
- Additions and discounts.
- Net-value reconciliation.
- Notes/attachments/change-history behavior.

Those behaviors remain in their dedicated P2 tasks so implementation stays traceable and testable without overlapping work.

## Database portability

The schema uses indexed relation IDs rather than database foreign-key constraints, matching WordPress custom-table portability and lifecycle conventions. Referential validation is enforced in the application/service layer in the relevant workflow tasks.
