# SafeContracts — P4 final validation + P5 notification rules foundation

Scope: `SC-P4-010..015` and `SC-P5-001..004`.

## P4 final validation

The remaining P4 validation tasks independently verify:

- follow-up note normalization, required/length boundaries and audit emission;
- promise-to-pay dates as operational state only, without rewriting payment `due_date` or `expected_payment_date`;
- issue/deferred states and deferred resume dates;
- deterministic append-only operational history with Accountant assignment scope;
- financial audit before/after context and protected audit reads;
- customer/accountant assignment audit before/after IDs.

`tests/php/followup_audit_validation_010_015.php` is the independent P4 completion suite. P4 schemas remain migration-compatible with later phases rather than asserting that `1.8.0` must forever be the latest database version.

## P5 notification rule model

Migration `1.9.0` adds `{$wpdb->prefix}safecontracts_notification_rules` with:

- stable unique rule code;
- name;
- trigger type;
- before-due day offset;
- centrally managed SafeContracts recipient roles;
- explicit assigned-Accountant targeting flag;
- active state;
- actor/timestamp fields;
- indexed active trigger lookup.

The initial delivery slice intentionally supports the `before_due` trigger only. Due-day, overdue, repetition, escalation, templates, Firebase/device tokens and push delivery remain owned by later P5 tasks.

## Default rule

Migration `1.9.0` seeds one rule idempotently:

- code: `default_due_10_days`;
- trigger: `before_due`;
- offset: **10 calendar days**;
- role recipient: **SafeContracts Manager**;
- assigned-Accountant targeting: **enabled**.

This models the approved `Manager + assigned Accountant` default without broadcasting to unrelated Accountants. The seed does not overwrite later administrator edits when rerun.

Trigger evaluation uses the contractual payment `due_date`. `expected_payment_date` remains an operational follow-up field and does not replace the contractual due date for the 10-day rule.

## Recipient security

`RecipientResolver` resolves user IDs server-side from SafeContracts roles plus the contract-assigned Accountant ID. If no Accountant is assigned, the resolver does **not** broaden the target to all Accountants.

Notification rule administration requires `safecontracts_manage_notifications`. Mobile does not own notification rules or recipient authority.

## Automated evidence

- `tests/php/followup_audit_validation_010_015.php` — `SC-P4-010..015`.
- `tests/php/notifications_rules_001_004.php` — `SC-P5-001..004`.
- Existing P0-P4 suites remain in `scripts/test-php.sh` as regression gates.

No Firebase credential, device-token registry, push transport, notification REST endpoint or admin UI is introduced in this slice.
