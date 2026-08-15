# P2 Contract History & Validation — SC-P2-012..016

## SC-P2-012 — Contract history — Implement

SafeContracts now maintains an append-only contract timeline in `safecontracts_contract_history`.

Each row stores:

- contract ID
- stable action code
- actor WordPress user ID when available
- structured JSON context
- UTC creation timestamp

The recorder subscribes to the existing contract-domain actions for create, edit, dates, base value, financial items, adjustments, attachments, customer assignment, Accountant assignment and status changes. History capture failure emits `safecontracts_contract_history_failed` rather than hiding the operational mutation.

History reads are exposed through `ContractHistoryService` and enforce `safecontracts_access` plus the same Manager all-data / Accountant assigned-contract scope used by contract operations. REST/UI exposure remains owned by their later phases.

This contract-specific timeline does not replace the broader P4 audit subsystem, which will cover cross-entity audit requirements such as permission changes, imports, exports, notification rules and other modules.

## SC-P2-013 — Contract data model — Validate

Validated against the live migration baseline:

- unique contract number
- required customer relation
- nullable controlled-draft Accountant relation
- lifecycle/status fields
- date fields and indexes
- fixed-point `DECIMAL(20,4)` base value
- non-destructive archive state
- actor/timestamp traceability
- customer/status and Accountant/status portfolio indexes
- no competing per-contract currency field in V1

## SC-P2-014 — Contract create workflow — Validate

Independent validation confirms:

- `CREATE_CONTRACTS` is mandatory
- active customer validation is server-side
- Accountant create auto-assigns the current Accountant when no assignee is supplied
- contract number normalization remains active
- all new contracts begin in `draft`
- denied creation performs no mutation

## SC-P2-015 — Contract edit capability — Validate

Independent validation confirms:

- `EDIT_CONTRACTS` is a separate capability
- authorized edits update contract details/notes
- missing edit capability causes no mutation
- explicitly granted edit capability still cannot bypass assigned Accountant scope

## SC-P2-016 — Customer assignment — Validate

Independent validation confirms:

- `ASSIGN_CONTRACTS` is required
- the target customer must be active
- assignment never bypasses contract data scope
- successful assignment emits the existing customer-assignment domain action used by contract history/audit integration

## Automated validation

`scripts/test-php.sh` runs:

1. foundation regression
2. contract schema regression
3. contract workflow regression
4. contract financial regression
5. contract history regression
6. independent SC-P2-013..016 validation regression

Repository, backend and mobile Quality Gates must all be green before these tasks are considered complete.
