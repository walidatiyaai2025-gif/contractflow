# SafeContracts — P2 Final Validation & P3 Payment Core

Scope: `SC-P2-022..023` and `SC-P3-001..003`.

## P2 completion validation

### SC-P2-022 — Net-value reconciliation — Validate

Dedicated regression evidence verifies:

- authoritative formula remains `base + financial items + additions - discounts`;
- calculations preserve fixed `DECIMAL(20,4)` precision without PHP floating-point arithmetic;
- reconciliation exposes each component transparently;
- assigned-Accountant scope prevents cross-contract reads.

### SC-P2-023 — Contract notes & attachments — Validate

Dedicated regression evidence verifies:

- notes mutate only through the capability/scope-controlled contract edit path;
- valid WordPress Media IDs can be linked and detached;
- duplicate contract/media links remain idempotent;
- invalid Media IDs cannot mutate contract data;
- archived contracts freeze notes and attachment mutations.

These two validations complete the P2 implementation/validation pairings.

## P3 payment core

### SC-P3-001 — Payment schedule model

Database migration `1.6.0` adds `safecontracts_scheduled_payments` with:

- contract relation;
- positive per-contract sequence number with a unique `(contract_id, sequence_no)` key;
- optional payment reference;
- required due date;
- optional current expected payment date;
- original, paid and remaining amounts using `DECIMAL(20,4)`;
- controlled status;
- follow-up-notes storage reserved for the later follow-up workflow;
- actor/timestamp metadata and reporting indexes.

New payments start with:

- `status = upcoming`;
- `paid_amount = 0.0000`;
- `remaining_amount = original_amount`.

Creation requires `MANAGE_PAYMENTS`, contract data scope and a non-archived contract. Scheduled payment amount must be greater than zero.

### SC-P3-002 — Payment lifecycle

Controlled lifecycle values are:

- `upcoming`
- `due_soon`
- `due`
- `overdue`
- `partially_paid`
- `paid`

Temporal states are allowed to move when date calculations change. `paid` is terminal until an explicit future reversal workflow is introduced. Same-state writes are idempotent.

This task defines lifecycle vocabulary and controlled mutation only. It does **not** implement the later `SC-P3-004` due-soon calculation or `SC-P3-005` overdue calculation.

### SC-P3-003 — Due & expected dates

- Due date is required and must be a valid `YYYY-MM-DD` calendar date.
- Expected payment date is optional and independently editable.
- Expected date may be earlier or later than the original due date; SafeContracts does not invent an unsupported ordering rule.
- Effective operational date is `expected_payment_date` when set, otherwise `due_date`.
- Date mutation requires payment-management capability, contract scope and a non-archived contract.
- Date changes emit a domain event containing old/new due and expected dates for later audit integration.

## Boundaries retained

This batch intentionally does not implement:

- due-soon calculation (`SC-P3-004`);
- overdue calculation (`SC-P3-005`);
- collections, payment methods or proof handling (`SC-P3-006..008`);
- partial/full settlement (`SC-P3-009..010`);
- remaining-balance or cross-payment reconciliation (`SC-P3-011..012`);
- REST endpoints or WordPress/mobile UI owned by later phases.

WordPress remains the source of truth and all payment mutations remain server-authorized.
