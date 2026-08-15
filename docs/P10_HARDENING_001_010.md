# SafeContracts P10 hardening — SC-P10-001..010

This batch opens P10 with deterministic hardening and integration evidence over the already-implemented WordPress backend. It does not create a second authorization layer, financial engine, import engine, Firebase datastore or export implementation.

## SC-P10-001 — Permission penetration tests

- SafeContracts access fails closed when `safecontracts_access` or a valid data scope is missing.
- Direct accountant-resource access is rejected unless the current user owns the assignment or has `safecontracts_view_all`.
- Capability-gated operations remain independently gated.

## SC-P10-002 — Accountant-scope tests

- Assigned scope and view-all scope are differentiated server-side.
- Direct object access cannot be widened by a client-provided filter.
- Excel export requires `safecontracts_export_reports` in addition to normal SafeContracts access.

## SC-P10-003 — Financial regression tests

- Monetary normalization remains string/fixed-point at scale 4.
- Addition, subtraction and net reconciliation are regression-tested with exact decimal expectations.
- Negative remaining balances and values exceeding the supported decimal scale fail closed.
- Authoritative money code contains no float conversion.

## SC-P10-004 — API security tests

- Unknown query parameters, parameter-pollution arrays, oversized values and unknown sort fields fail closed.
- Pagination remains bounded to the established backend window.
- REST source is checked for accidental secret-field contracts.

## SC-P10-005 — Input validation review

- Negative identifiers, impossible dates, unsupported statuses and oversized page sizes are rejected.
- Invalid values are rejected before they can widen scoped business reads.

## SC-P10-006 — Database/index performance

Regression evidence protects composite indexes used by high-frequency scoped reads for contracts, payments, collections, follow-ups, notification retries/device ownership and import status/error lookup. These assertions intentionally protect the current WordPress/dbDelta portability model rather than introducing SQL foreign-key coupling.

## SC-P10-007 — Notification reliability

- Settled payments are suppressed before planning/delivery.
- Recipient escalation is deduplicated.
- Delivery records retain explicit status and retry attempt number.
- Suppression remains observable through SafeContracts event hooks.

## SC-P10-008 — Firebase delivery verification

- Firebase credential storage accepts a backend environment/secret reference, not JSON/private credential contents.
- Safe summaries expose configuration state but not the credential reference.
- Mobile config contains no Firebase private credential material.
- Firebase remains an outbound push transport only; authoritative contract/payment state stays in WordPress.

## SC-P10-009 — Import verification

- Only mapped/validated runs may execute; completed/running/failed runs are terminal.
- All source rows validate before any business mutation starts.
- Candidate writes are transaction-protected with commit/rollback.
- Import update cannot rewrite an existing payment amount/reference.
- Dynamic repository values continue through prepared SQL.

## SC-P10-010 — Excel export verification

- Export requires the dedicated export capability.
- Filters are normalized server-side through the dashboard filter contract.
- Follow-up reads remain bounded and accountant-filter widening depends on explicit view-all capability.
- Server output remains XLSX with explicit base64 REST transport metadata.
- Export contracts contain no credential fields.

## Canonical verification

The Quality Gates run:

- `tests/php/p10_security_financial_001_005.php`
- `tests/php/p10_ops_verification_006_010.php`
- every prior PHP regression suite
- repository standards
- Flutter format, analyze and tests

Any regression discovered by these checks blocks merge of this P10 batch.
