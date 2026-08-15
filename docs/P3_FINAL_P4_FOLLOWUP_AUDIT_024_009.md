# SafeContracts — P3 final validation + P4 follow-up/audit foundation

Scope: `SC-P3-024` and `SC-P4-001..009`.

## P3 financial reconciliation validation

`SC-P3-024` keeps the collection ledger authoritative and independently verifies balanced, drifted and over-collected states. Reconciliation remains assignment-scoped for Accountants.

## P4 follow-up model

- Follow-up is tied to scheduled payments and is stored as append-only history.
- Supported operational states in this slice: Contacted, Promised to Pay, Issue, Deferred and Needs Escalation.
- Promise/deferred dates are operational fields only. They never rewrite the contractual `due_date` or the payment `expected_payment_date` implicitly.
- Follow-up notes are normalized and capped server-side.
- Paid payments and archived contracts reject new follow-up mutations.
- Manager/all-data scope can read the full outstanding queue; Accountant scope is restricted by assigned contract in SQL.
- The queue excludes paid/zero-remaining and archived work, and orders by contractual due date.

## P4 audit model

Migration `1.8.0` adds:

- `{$wpdb->prefix}safecontracts_payment_followups`
- `{$wpdb->prefix}safecontracts_audit_log`

Audit records are application-append-only and carry entity/event/actor/time plus structured before/after/context fields where available.

The recorder consumes existing domain events for:

- contract financial changes;
- payment settlement;
- customer/accountant assignment;
- contract/payment status and date changes;
- follow-up state changes.

It also registers future-facing `safecontracts_export_completed` and `safecontracts_import_completed` hooks so P6/P7 can emit audit events without coupling those phases to P4 internals. Secret-looking context keys are removed before persistence.

Assignment/base-value/date domain events were enriched only by appending previous values to their existing argument lists, preserving the existing leading argument contract for current listeners.

## Automated evidence

- `tests/php/payments_validation_024.php` — `SC-P3-024`.
- `tests/php/followup_audit_001_009.php` — `SC-P4-001..009`.
- Existing P0-P3 suites remain in `scripts/test-php.sh` as regression gates.

No REST endpoint or admin/mobile screen is introduced in this slice; those remain owned by P6/P8/P9.
