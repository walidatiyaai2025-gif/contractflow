# P2 Contract History & Validation — SC-P2-012..016

Scope: SafeContracts P2 tasks SC-P2-012 through SC-P2-016.

## SC-P2-012 — Contract history — Implement

SafeContracts now maintains a dedicated append-only contract history timeline:

- table: `{$wpdb->prefix}safecontracts_contract_history`;
- contract ID, event type, actor user ID, JSON state snapshot and timestamp;
- indexed newest-first reads by contract;
- recorder registered during plugin boot and subscribed to material P2 contract domain events;
- history remains preserved during plugin deactivation;
- history reads are server-side scope controlled: Manager/all scope sees all accessible contracts, Accountant/assigned scope sees only their assigned contract;
- server clamps history page size to a maximum of 500 rows per read.

The P2 history is deliberately contract-focused. The later P4 audit work can extend cross-entity before/after auditing without replacing this contract timeline.

## SC-P2-013 — Contract data model — Validate

**PASS criteria covered by automated regression tests:**

- dedicated prefixed contract table;
- required unique contract number;
- customer relation and optional responsible Accountant relation;
- controlled lifecycle status;
- nullable start/end dates;
- fixed-point `DECIMAL(20,4)` base value;
- notes and non-destructive archive state;
- creator/updater traceability and reporting indexes;
- single-currency V1 remains system-level rather than a competing per-contract field;
- migrations are idempotent and deactivation is non-destructive.

Primary evidence: `tests/php/contracts_schema.php`.

## SC-P2-014 — Contract create workflow — Validate

**PASS criteria covered by automated regression tests:**

- requires `safecontracts_create_contracts`;
- requires an active SafeContracts customer;
- Accountant/assigned-scope creation auto-assigns the current Accountant;
- Manager/all-scope creation may explicitly assign an eligible Accountant only when assignment capability is present;
- ineligible assignees are rejected before mutation;
- new contracts start in the controlled `draft` state;
- prepared writes use the dedicated contracts table;
- creation emits the contract-created domain event.

Primary evidence: `tests/php/contracts_workflow.php`.

## SC-P2-015 — Contract edit capability — Validate

**PASS criteria covered by automated regression tests:**

- `safecontracts_edit_contracts` remains independent from role identity;
- missing edit capability rejects mutations;
- possession of edit capability does not bypass Accountant assigned-data scope;
- an Accountant granted edit capability can edit their own assigned contract;
- Manager/all-data scope can edit according to capability;
- date, financial and attachment mutations also reuse the same edit-capability + scope boundary.

Primary evidence: `tests/php/contracts_workflow.php` and `tests/php/contracts_financials.php`.

## SC-P2-016 — Customer assignment — Validate

**PASS criteria covered by automated regression tests:**

- customer reassignment requires `safecontracts_assign_contracts`;
- current contract data scope is checked before reassignment;
- target customer must exist and be active;
- invalid/inactive customer causes no contract mutation;
- successful assignment updates the relation and emits `safecontracts_contract_customer_assigned` for history/audit consumers.

Primary evidence: `tests/php/contracts_workflow.php`.

## Quality gate requirement

The PR carrying this report must pass all SafeContracts Quality Gates before Issues SC-P2-012..016 are completed:

1. repository standards;
2. PHP/backend syntax and regression suites, including contract schema/workflow/financial/history tests;
3. Flutter formatting, analysis and tests.
