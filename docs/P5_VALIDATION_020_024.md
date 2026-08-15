# SafeContracts P5 Validation — SC-P5-020..024

This batch validates the remaining notification-delivery configuration boundaries already implemented in P5. It does not create a competing delivery path or move authority into UI/mobile.

## Validated tasks

- `SC-P5-020` Repeat & escalation rules — exact cadence, bounded attempts, final-repeat escalation only, settled suppression.
- `SC-P5-021` Notification templates — closed placeholder allow-list, deterministic rendering, fail-closed missing/inactive templates, capability-gated writes.
- `SC-P5-022` Firebase settings — public metadata plus server credential reference only, no raw credential storage, fail-closed missing config/auth.
- `SC-P5-023` Device-token registry — authenticated ownership, SHA-256 uniqueness, owner-scoped revoke, raw token confined to registry.
- `SC-P5-024` Push delivery — active-device delivery, structured outcomes, transport exceptions normalized, direct FCM HTTP v1 request boundary.

## Security and correctness boundaries

- `due_date` remains authoritative for repeat/escalation scheduling.
- Escalation roles are added only at the final configured repeat.
- Template context cannot introduce unsupported placeholders or raw markup.
- Firebase service credentials are never persisted; only an environment/secret reference is stored.
- Missing Firebase credential reference or short-lived access token must fail closed before remote HTTP.
- Raw device tokens are required only inside the dedicated registry and outbound FCM request; operational events and delivery logs must never contain them.
- FCM access tokens are Authorization-header material only and never appear in the request body or delivery log.

## Automated evidence

`tests/php/notifications_validation_020_024.php` is included in `scripts/test-php.sh` and runs with the complete P0–P5 regression suite. Repository standards, backend PHP checks, and Flutter format/analyze/test must all pass on the exact PR merge candidate before merge.
