# P8 final REST validation — SC-P8-019..028

This slice completes the SafeContracts P8 REST API phase by validating the remaining authentication, authorization, scope and mobile-facing read endpoints against fail-closed production behavior.

## SC-P8-019 — Authentication/session — Validate

- `/me` and `/session` remain protected WordPress-session endpoints.
- The callback now rechecks `Permission::access()` internally instead of relying only on the route permission callback.
- Anonymous/out-of-scope direct callback invocation returns the canonical versioned 403 error.
- Successful session data exposes only authenticated state, user ID, authoritative scope and capability booleans; credentials, cookies, tokens and private keys are not returned.

## SC-P8-020 — Capability enforcement — Validate

Validation found a defense-in-depth gap: several callbacks trusted WordPress route permission callbacks and could be called internally without rechecking the same authorization boundary.

The following callbacks now fail closed before reads/writes when called directly:

- data endpoints through `DataController::guard()`;
- dashboard;
- mobile configuration;
- reference data;
- active/admin payment methods;
- Excel export;
- session endpoints.

Admin payment-method reads/writes still require `MANAGE_REFERENCE_DATA`; Excel export still requires `EXPORT_REPORTS`. Capability denial occurs before repository reads or mutations.

## SC-P8-021 — Accountant scope enforcement — Validate

- `VIEW_ASSIGNED` remains authoritative and pins reads to the current user.
- A caller-supplied `accountant_user_id` cannot widen assigned scope.
- Direct payment/collection objects use the persisted accountant assignment and fail with 403 when foreign.
- `VIEW_ALL` remains the only scope that can intentionally read across accountant assignments.

## SC-P8-022 — Customer endpoints — Validate

- Customer list/detail use bounded repository reads.
- Assigned users remain limited to customers reachable through their contracts.
- Internal customer notes are excluded from REST projection.
- Missing customer detail uses the canonical versioned 404 response.

## SC-P8-023 — Dependent contract filters — Validate

- Customer-dependent contract options reject malformed/non-scalar customer IDs before any database read.
- Valid customer selection is enforced server-side together with accountant scope.
- The API returns metadata allowing the client to offer an “All contracts” option without inventing an unauthorized server record.

## SC-P8-024 — Contract endpoints — Validate

- Contract list/detail remain bounded and assigned scoped.
- Customer/status filters are applied server-side.
- Internal contract notes are excluded from API projection.
- Missing direct contracts return versioned 404 responses.

## SC-P8-025 — Payment endpoints — Validate

- Contractual due date and expected payment date remain separate fields.
- Original/paid/remaining values preserve canonical fixed-point amounts.
- Status and due-date filters remain server-side and accountant scoped.
- REST date ranges now fail closed when `due_to` is earlier than `due_from`; the API no longer silently swaps reversed ranges.

## SC-P8-026 — Collection endpoints — Validate

- Collection list/detail preserve contract/payment/accountant authorization context without leaking it unnecessarily in direct detail projection.
- Internal free-text collection `details` are excluded from REST output.
- Payment-method display data remains available for mobile/report presentation.
- Foreign direct collection reads return 403.

## SC-P8-027 — Follow-up endpoints — Validate

- Follow-up queue/history continue to reuse the domain follow-up scope boundary.
- Missing payment history now returns canonical 404 rather than being translated into generic validation 422.
- Authorized history preserves operational note/state/date fields and explicit response scope metadata.
- History reads remain bounded to the existing 500-row server window.

## SC-P8-028 — Dashboard endpoints — Validate

Validation found that dashboard parsing used the forgiving admin filter normalizer directly. Invalid status/date/ID values could therefore be silently cleared or reordered rather than rejected.

Dashboard REST input now passes through the same strict API boundary used by list endpoints:

- unsupported parameters are rejected;
- non-scalar IDs/status values are rejected;
- invalid calendar dates are rejected;
- reversed due ranges are rejected;
- filters are applied without widening assigned-accountant scope.

Dashboard KPI, customer-option and dependent-contract reads remain bounded/scoped and continue to use the canonical versioned response envelope.

## Shared hardening

`ApiRequest::filters()` is now the canonical strict REST filter parser. `ApiRequest::listQuery()` and `RequestGuard::dashboardFilters()` both use it, while the forgiving `DashboardFilters` normalizer remains available for non-REST admin UX where normalization is intentional.

`RequestGuard::params()` now delegates to `ApiRequest::params()` so tests, JSON/internal calls and production `WP_REST_Request::get_params()` follow one parameter extraction contract.

## Regression evidence

`tests/php/rest_api_validation_019_028.php` exercises all ten task IDs with behavioral checks covering:

- direct callback authorization;
- no reads/writes after denial;
- SQL accountant/customer/contract scope;
- safe field projection;
- 403/404/422 versioned errors;
- reversed date-range rejection;
- follow-up not-found semantics;
- dashboard KPI/options payload and scope.

The suite is wired into `scripts/test-php.sh` after all prior P8 implementation/validation suites and therefore executes as part of repository Quality Gates.
