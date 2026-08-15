# SafeContracts P4 Final Validation — SC-P4-010..015

This document records the independent validation evidence for the final six tasks in **P4 — Follow-up & Audit**.

## Scope

- `SC-P4-010` — Follow-up notes — Validate
- `SC-P4-011` — Promise-to-pay state — Validate
- `SC-P4-012` — Issue/deferred state — Validate
- `SC-P4-013` — Operational status history — Validate
- `SC-P4-014` — Financial audit trail — Validate
- `SC-P4-015` — Assignment audit trail — Validate

## Validation findings repaired in this batch

### 1. WordPress action accepted-argument semantics

The existing PHP test stub delivered every action argument to every callback. WordPress core defaults `accepted_args` to one when an action is registered without an explicit value. That meant earlier tests could pass while `AuditRecorder` would receive only the first domain-event argument in production.

The validation batch repairs this by:

- making the PHP stub respect per-callback accepted-argument counts;
- registering SafeContracts audit callbacks with an explicit accepted-argument capacity;
- validating follow-up, settlement and assignment events under those WordPress-compatible semantics.

### 2. Settlement before/after audit payload

`AuditRecorder` already had a structured mapping for pre-settlement paid amount, remaining amount and status, but the collection service event emitted only the new state. The validation batch extends the existing `safecontracts_payment_settled` event with trailing prior-state values while preserving all existing leading arguments.

This keeps the event backward-compatible and allows the financial audit row to persist real before/after settlement state.

## Automated evidence

`tests/php/followup_audit_validation_010_015.php` validates:

### SC-P4-010 — Follow-up notes

- required note validation;
- 5,000-character boundary;
- whitespace normalization;
- append-only persistence;
- dedicated follow-up audit event;
- Accountant assignment-scope denial before mutation.

### SC-P4-011 — Promise-to-pay

- controlled operational state persistence;
- strict calendar validation;
- promised date in audit context;
- no update of contractual `due_date`;
- no update of operational `expected_payment_date`.

### SC-P4-012 — Issue/deferred

- required issue note;
- deferred resume date persistence;
- strict deferred-date validation;
- no destructive follow-up mutation;
- archived-contract boundary;
- paid-payment boundary.

### SC-P4-013 — Operational status history

- deterministic newest-first ordering;
- server-side 1..500 limit clamping;
- read-only behavior;
- Accountant assignment-scope enforcement.

### SC-P4-014 — Financial audit trail

- full domain-event argument delivery under WordPress-compatible action semantics;
- base-value before/after values;
- settlement new and prior paid/remaining/status values;
- actor identity and server timestamp;
- protected audit reads;
- server-bounded audit reads.

### SC-P4-015 — Assignment audit trail

- customer old/new IDs;
- Accountant old/new user IDs;
- actor identity;
- server timestamp;
- deterministic audit timeline reads.

## Regression policy

The full SafeContracts backend suite remains enabled. P4 is not considered complete until repository standards, all PHP suites, Flutter formatting, Flutter analyzer and Flutter tests pass on the exact pull-request head.

## Architecture rules preserved

- WordPress remains the single source of truth.
- Follow-up state does not replace contractual payment state.
- Promise/deferred dates do not rewrite contractual due dates.
- Accountant scope is enforced server-side.
- Follow-up and audit history are append-only application workflows.
- Audit reads require the dedicated audit capability.
- No secret configuration is introduced or exposed.
