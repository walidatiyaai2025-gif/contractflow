# SafeContracts P5 Notification Delivery — SC-P5-005..014

This batch extends the server-owned notification foundation without moving notification authority into mobile or admin UI.

## Completed scope

- `SC-P5-005` Due-day reminders — contractual `due_date` only.
- `SC-P5-006` Overdue reminders — contractual `due_date` plus explicit days-after offset.
- `SC-P5-007` Repeat & escalation rules — bounded cadence and final-repeat escalation roles.
- `SC-P5-008` Notification templates — allow-listed placeholders and deterministic rendering.
- `SC-P5-009` Firebase settings — public metadata plus secret/environment reference, never raw service credentials.
- `SC-P5-010` Device-token registry — authenticated user ownership, SHA-256 uniqueness and revoke support.
- `SC-P5-011` Push delivery — FCM v1 transport boundary plus transport-independent delivery service.
- `SC-P5-012` Delivery retry & logging — append-only attempt log, three bounded retries and exponential backoff.
- `SC-P5-013` Settled-payment suppression — paid or zero-remaining payments stop before template/transport work.
- `SC-P5-014` Notification rule model — expanded validation and backward compatibility checks.

## Data model

Migration `1.10.0` expands `safecontracts_notification_rules` with overdue offsets, repeat cadence, escalation roles and template linkage, and adds:

- `safecontracts_notification_templates`
- `safecontracts_device_tokens`
- `safecontracts_notification_deliveries`

The existing `default_due_10_days` rule remains untouched and active. Due-day and overdue support is implemented but not force-enabled as new default rules.

## Security boundaries

- Contractual `due_date` remains authoritative; `expected_payment_date` cannot move due-day or overdue reminders.
- Recipient resolution remains server-side.
- Missing assigned Accountant never broadens to all Accountants.
- Raw Firebase service credentials are not stored by this slice; SafeContracts stores only a server secret/environment reference.
- Raw device tokens are stored only in the dedicated registry because delivery requires them; operational events and delivery logs identify devices by IDs/hashes and never log raw tokens.
- Template placeholders use a closed allow-list.
- Paid/zero-remaining payments are suppressed before rendering or transport.

## Firebase transport

`FirebasePushTransport` sends FCM HTTP v1 messages. The short-lived OAuth access token is supplied through the server-side `safecontracts_firebase_access_token` filter using the configured credential reference. Missing auth/config returns a structured delivery failure for retry/logging rather than leaking secrets or failing silently.

## Validation

- `tests/php/notifications_delivery_005_013.php`
- `tests/php/notifications_validation_014.php`
- Full existing P0-P5 regression suite remains enabled in `scripts/test-php.sh`.
