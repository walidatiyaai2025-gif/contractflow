# SafeContracts — P2 Closeout + P3 Payment Foundation

Scope: `SC-P2-022`, `SC-P2-023`, `SC-P3-001`, `SC-P3-002`, `SC-P3-003`.

## P2 closeout validations

### SC-P2-022 — Net-value reconciliation — Validate

Validation is executable in `tests/php/contracts_validation_022_023.php` and confirms:

- net value is calculated as base + financial lines + additions - discounts;
- each component remains visible rather than being collapsed into an opaque total;
- fixed-scale decimal arithmetic avoids binary floating-point loss;
- reconciliation remains transparent even if discounts make the result negative;
- Accountant assigned-data scope is enforced on reconciliation reads.

### SC-P2-023 — Contract notes & attachments — Validate

The same dedicated validation suite confirms:

- notes are capability/scope controlled;
- attachments are WordPress Media references, not duplicate file storage;
- relation creation is idempotent by contract/media;
- detach removes only the SafeContracts relation and not the Media object;
- invalid non-Media references are rejected;
- archived contracts freeze notes and attachment mutation paths.

## SC-P3-001 — Payment schedule model — Implement

Migration `1.6.0` adds `{$wpdb->prefix}safecontracts_scheduled_payments` with:

- contract relation;
- unique sequence number per contract;
- optional operational reference;
- exact `DECIMAL(20,4)` original amount;
- required contractual due date;
- separate optional expected payment date;
- explicit non-destructive cancellation metadata;
- actor/timestamp traceability;
- contract/due/expected-date indexes for reporting and lifecycle queries.

No per-payment currency is introduced. SafeContracts V1 keeps currency at system level.

Paid and remaining amounts are intentionally **not duplicated in the schedule row at this stage**. P3 collection transactions are the planned authoritative source for collection totals; later P3 tasks will aggregate those transactions and feed the lifecycle engine.

## SC-P3-002 — Payment lifecycle — Implement

`SafeContracts\Payments\PaymentState` implements the server-side lifecycle primitives:

- `upcoming`
- `due_soon`
- `due`
- `overdue`
- `partially_paid`
- `paid`
- `cancelled`

Financial and temporal state are kept conceptually separable. `PaymentState::temporal()` can still report `overdue` while `derive()` reports `partially_paid`, which lets future UI/report code show both facts without corrupting either meaning.

The lifecycle integrity layer rejects overpayment input and invalid dates. The default due-soon window is 10 days, matching the approved default reminder baseline, while the method accepts a configurable window for later notification/report settings.

`PaymentService::cancel()` creates an explicit terminal cancellation and emits `safecontracts_payment_cancelled` for later audit integration.

## SC-P3-003 — Due & expected dates — Implement

`PaymentService` enforces strict valid `YYYY-MM-DD` dates. The contractual `due_date` is required; `expected_payment_date` is optional and may be changed or cleared independently.

The lifecycle engine uses **contractual due date**, not expected date, for due/overdue classification. This preserves the approved business rule that an operational promise/expectation must never rewrite contractual due history.

Payment date mutation:

- requires `safecontracts_manage_payments`;
- enforces Manager/all or Accountant/assigned contract scope server-side;
- rejects cancelled payments, archived contracts and terminal contracts;
- emits `safecontracts_payment_dates_changed` with old/new due and expected values for later P4 audit recording.

## Validation gate

`scripts/test-php.sh` executes both the P2 closeout suite and P3 payment-foundation suite in addition to all existing backend regressions. The pull request must also pass repository standards and Flutter format/analyze/test gates before merge.
