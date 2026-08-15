# P10 Validation Evidence — SC-P10-017..026

This batch validates the first ten P10 hardening controls against the current merged backend/mobile surface. It deliberately does not duplicate SC-P10-011..016, which were implemented independently in PR #349.

## SC-P10-017 — Permission penetration tests — Validate

- Missing SafeContracts access and missing data scope both fail closed.
- Assigned scope rejects horizontal accountant IDs.
- Runtime route enumeration verifies `/health` is the only intentionally public SafeContracts REST endpoint; every other registered route declares a non-public permission callback.

## SC-P10-018 — Accountant-scope tests — Validate

- Assigned users may access their own accountant scope but not null/other-accountant resources.
- `VIEW_ALL` is the only tested capability that widens accountant scope.
- Notification inbox/read logic remains current-user scoped.
- Report accountant-filter widening remains conditional on server-side `VIEW_ALL`.

## SC-P10-019 — Financial regression tests — Validate

- Exact DECIMAL(20,4)-style string arithmetic is revalidated for normalize/add/subtract/reconcile and negative-balance rejection.
- Collection source must retain transaction, payment locking, ledger total, stored-integrity and over-collection guards.
- Mobile library is scanned to prevent `double.parse`/`num.parse` from becoming an authoritative financial calculation path.

## SC-P10-020 — API security tests — Validate

- Unknown parameters, parameter-pollution arrays, oversized scalars and reads beyond the bounded window fail closed.
- Generic internal failures map to a 500 envelope without exposing exception details.

## SC-P10-021 — Input validation review — Validate

- Negative IDs, impossible calendar dates, unknown statuses, oversized pagination, negative money and >4-decimal money are rejected.
- Contract/payment field-length and calendar-date guards are pinned by regression evidence.

## SC-P10-022 — Database/index performance — Validate

- Composite indexes are pinned for scoped contract/customer, payment due/status, collection ledger, follow-up timeline, notification retry/device and import status/error paths.
- API list processing retains the hard 500-row bounded window.

## SC-P10-023 — Notification reliability — Validate

- Settled payments are suppressed before delivery work.
- Recipient escalation remains deduplicated.
- Delivery logging preserves attempt/result state.
- Retry backoff remains deterministic (60/120/240 seconds) and stops after the configured transport retry bound.

## SC-P10-024 — Firebase delivery verification — Validate + hardening repair

Validation identified one real gap: `PushDeliveryService` previously accepted arbitrary scalar keys in `payload.data`.

Repair:
- push metadata is now allow-listed to `payment_id`, `rule_code`, `attempt_no` only;
- metadata count/types/positive IDs/string length and rule-code format are bounded;
- external URL/unknown metadata fails before device lookup or Firebase transport;
- raw Firebase credential JSON remains rejected and mobile config exposes no server credential reference/value.

Firebase remains delivery transport only; business state is not sourced from push metadata.

## SC-P10-025 — Import verification — Validate

- Only mapped/validated runs execute; terminal runs are immutable.
- All row validation completes before business writes.
- Each candidate row is transaction protected with rollback.
- Existing payment amount/reference cannot be silently rewritten by import update.
- Dynamic persistence remains prepared SQL.

## SC-P10-026 — Excel export verification — Validate

- Export requires an independent export capability in addition to normal SafeContracts access/scope.
- Server export uses normalized dashboard filters, bounded follow-up reads and explicit `VIEW_ALL` gating for accountant filtering.
- XLSX content type and base64 REST transport metadata stay stable.
- Export source/transport contracts contain no password/private-key/service-account fields.

## Executable evidence

- `tests/php/p10_validation_017_026.php`
- existing `tests/php/p10_security_financial_001_005.php`
- existing `tests/php/p10_ops_verification_006_010.php`
- existing `tests/php/p10_release_readiness_011_016.php`
- full PHP lint/regression runner `scripts/test-php.sh`
- repository standards and Flutter format/analyze/test Quality Gates
