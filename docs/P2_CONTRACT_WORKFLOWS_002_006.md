# P2 Contract Workflows — SC-P2-002..006

Scope: SafeContracts P2 contract workflow tasks `SC-P2-002` through `SC-P2-006`.

## SC-P2-002 — Contract create workflow

Contract creation is a server-side domain operation through `ContractService` and `ContractRepository`.

Rules:

- `safecontracts_create_contracts` is mandatory.
- Contract number is required, trimmed and limited to the schema maximum of 100 characters.
- Customer must exist as an active SafeContracts customer.
- New contracts always start in `draft`; callers cannot inject another initial lifecycle state.
- An Accountant creating without an explicit assignee is automatically assigned to the new contract.
- A Manager/System Administrator can create an unassigned draft or explicitly assign an eligible Accountant when `safecontracts_assign_contracts` is granted.
- Writes use prepared SQL and record `created_by`, `updated_by` and timestamps.
- Creation emits `safecontracts_contract_created` for later audit/history integration.

Dates, base-value editing, financial lines, additions/discounts, attachments and REST/UI workflows remain in their dedicated tasks.

## SC-P2-003 — Contract edit capability

Operational contract editing is enforced server-side by `safecontracts_edit_contracts`.

The initial bounded edit operation covers contract reference/number and notes. Customer assignment, Accountant assignment and lifecycle status use dedicated methods so their authorization cannot be bypassed through a generic update payload.

Capability is necessary but not sufficient: users with assigned-only scope may edit only contracts whose `accountant_user_id` is their own user ID. `safecontracts_view_all` bypasses the assignment filter only because that capability explicitly grants global data scope.

Edits emit `safecontracts_contract_edited`.

## SC-P2-004 — Customer assignment

Changing the contract/customer relation requires `safecontracts_assign_contracts` and access to the target contract under the current data scope.

The target customer must exist and be active. Invalid/inactive customer IDs are rejected before mutation. Successful changes emit `safecontracts_contract_customer_assigned`.

## SC-P2-005 — Accountant assignment

Changing the responsible Accountant requires `safecontracts_assign_contracts` and access to the target contract.

An eligible assignee must have all three baseline Accountant traits:

- `safecontracts_access`
- `safecontracts_create_contracts`
- `safecontracts_view_assigned`

This excludes Viewer and Manager roles from being accidentally used as the responsible Accountant while preserving capability-based customization.

Assignment may be cleared to `NULL` for a controlled unassigned state. Successful changes emit `safecontracts_contract_accountant_assigned`.

## SC-P2-006 — Contract status lifecycle

Contract lifecycle values are controlled server-side:

- `draft`
- `active`
- `completed`
- `cancelled`

Allowed transitions:

- `draft -> active`
- `draft -> cancelled`
- `active -> completed`
- `active -> cancelled`
- same-state calls are idempotently accepted

`completed` and `cancelled` are terminal in this baseline. Archived contracts cannot change lifecycle status. Archive remains the separate `is_archived` state already present in the data model.

Status changes require `safecontracts_edit_contracts`, respect the same data scope as edits and emit `safecontracts_contract_status_changed`.

## Validation

`tests/php/contracts_workflow.php` covers:

- Accountant create and automatic self-assignment;
- create denial without capability;
- Manager assignment during create;
- rejection of ineligible assignees;
- edit capability enforcement;
- assigned-scope enforcement even when edit/assignment capability is explicitly granted;
- active-customer validation;
- Accountant assignment/unassignment;
- controlled status set and transition matrix;
- archived/terminal lifecycle protection;
- mutation domain actions.

The PR must pass repository standards, the full PHP suites and all Flutter foundation gates before issues `#32` through `#36` are closed.
