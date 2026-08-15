# SafeContracts P5 Validation — SC-P5-015..019

This batch validates the reminder and recipient semantics implemented by the existing P5 notification engine. It does not introduce a competing notification path or move authority to mobile/UI.

## Validated tasks

- `SC-P5-015` 10-day default reminder — exactly ten days before contractual `due_date`, Manager + assigned Accountant, settled suppression, legacy template compatibility.
- `SC-P5-016` Role-based recipients — SafeContracts roles only, deterministic de-duplication, no native/unknown role expansion.
- `SC-P5-017` Assigned-accountant targeting — exact assigned user when configured; missing assignment never broadens to unrelated Accountants.
- `SC-P5-018` Due-day reminders — contractual `due_date` is authoritative; `expected_payment_date` cannot create or move the trigger; partial balances remain eligible and zero balance is suppressed.
- `SC-P5-019` Overdue reminders — contractual `due_date` plus configured days-after/repeat cadence; `expected_payment_date` cannot erase overdue; partial balances remain eligible and paid balances are suppressed.

## Validation boundaries

- Financial settlement uses fixed-point remaining balance semantics.
- Recipient resolution stays server-side.
- Missing assignment fails closed for assigned-only targeting.
- Reminder matching ignores operational `expected_payment_date` for contractual due/overdue classification.
- No production schema or notification API changes are required by this validation batch.

## Automated evidence

`tests/php/notifications_validation_015_019.php` is included in `scripts/test-php.sh` and runs with the full P0–P5 regression suite. Repository standards, backend PHP checks, and Flutter format/analyze/test must all pass on the exact PR head before merge.
