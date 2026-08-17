# ESC-P9-012 — Contract Payment Schedule Full Impact Review

## Scope

P9-012 adds an Enterprise-only, tenant-safe, immutable Contract payment-schedule entry foundation for planned receivable instalments. It deliberately does **not** reuse or synchronize the mutable legacy payment/collection path.

## Domain model

- Each schedule entry owns one stable server-generated UUID.
- Each mutation appends a full immutable revision with a server-generated revision UUID and monotonically increasing revision number.
- State is limited to `scheduled` or terminal `voided`.
- Each entry has one stable positive sequence number, optional bounded reference, contractual due date, strict-positive Money amount, P9-003 financial profile identity and exact currency snapshot.
- Sequence numbers are permanently owned by their schedule-entry identity and cannot be reused by a different identity, including after voiding.
- Revisions cannot change sequence number.
- Mutation is allowed only while the exact current-tenant Contract is unarchived and `active`.
- Completed/cancelled historical schedule evidence remains readable.

## Authorization and tenant isolation

- Tenant identity is derived only from locked `TenantContextStore`; caller supplies no tenant.
- Core tenant enforcement is mandatory.
- Reads require `Capabilities::ACCESS`.
- Create/revise/void require `Capabilities::MANAGE_PAYMENTS`.
- WordPress grants remain the ceiling and are narrowed by `TenantAuthorization::allowsCapability`.
- Existing Contract data scope remains `VIEW_ALL` or own `VIEW_ASSIGNED`.
- The exact current-tenant Contract is locked with `FOR UPDATE` before profile or schedule state is observed, and authorization is performed against that exact locked row.

## Currency and monetary integrity

- Amounts reuse P9-001 `Money`; no duplicate decimal parser or float path is introduced.
- Amount must be strictly greater than zero.
- Canonicalization occurs only after the authoritative P9-003 Contract financial currency profile is locked.
- Every revision persists and validates the exact P9-003 financial profile ID and currency snapshot.
- No FX or caller-provided currency is accepted.

## Date and sequence integrity

- Contractual due date is strict valid `YYYY-MM-DD` calendar data.
- P9-012 does not introduce an expected-payment or operational follow-up date.
- Sequence must be a positive integer within the supported DB range.
- Creation proves the sequence has never appeared in any historical revision for the Contract before append.
- Current reads reject duplicate latest sequence numbers as corruption.
- Current reads are deterministic by sequence number then stable entry UUID.

## Persistence and migration impact

- Additive `Migration0056EnterpriseContractFinancialPaymentScheduleEntryRevisions` advances ESC schema from `1.54.0` to `1.55.0`.
- New table: `safecontracts_contract_financial_payment_schedule_entry_revisions`.
- Historical mappings remain unchanged, including exact `1.54.0 => Migration0055EnterpriseContractFinancialCreditRevisions`.
- Evidence is append-only: no UPDATE/DELETE path and no destructive migration rollback/drop/rewrite.
- Maximum 500 stable schedule identities per Contract; current reads request 501 rows and fail closed on overflow.

## Legacy Payments / Collections isolation

P9-012 is intentionally independent from:

- `SafeContracts\Payments\PaymentRepository`
- `SafeContracts\Payments\PaymentService`
- `safecontracts_scheduled_payments`
- `SafeContracts\Collections\CollectionRepository`
- `safecontracts_payment_collections`

The Enterprise schedule repository/service does not read, write, mirror, update or synchronize those legacy models or tables. Legacy payment/collection code also remains unchanged and contains no reverse dependency on the new Enterprise schedule classes.

The following settlement concepts remain outside P9-012:

- paid amount
- remaining amount
- partially-paid/paid status lifecycle
- collection transactions
- payment method
- proof attachment
- expected payment/follow-up date
- allocation/application
- settlement

## P9-006 reconciliation impact

P9-006 remains authoritative and unchanged. Payment-schedule entries are planning evidence only and have zero Contract-value effect. P9-012 does not require schedule totals to equal Contract net value because tax, retention, penalty and credit effect/posting semantics are not yet defined.

## Surface impact

No REST, admin UI, Flutter/mobile UI, Android identity, reports, import/export, notifications, public feature claims or production artifacts are introduced by P9-012.

## Security / concurrency / idempotency

- One transaction per read or mutation.
- Contract-first row lock prevents authorization against stale or cross-tenant state.
- Profile is locked after Contract authorization.
- Create proves capacity, unused generated UUID and unused historical sequence under the Contract lock.
- Revise/void lock exact latest entry revision.
- Exact revise retries return the existing latest revision without append.
- Repeated void returns the terminal revision without append.
- Voided entries cannot be revised or reactivated.
- Any authorization, lifecycle, cardinality, currency/profile, validation or corruption failure rolls back and fails closed.

## Regression and CI impact

P9-012 adds:

- `tests/php/enterprise_contract_financial_payment_schedule_p9_012.php`
- `.github/workflows/esc-p9-012.yml`
- global backend-gate wiring in `scripts/test-php.sh`

Coverage includes schema history, tenant isolation, Contract-first authorization, strict Money/date validation, 501st sentinel, duplicate latest identity/sequence corruption, permanent sequence non-reuse, profile/currency integrity, lifecycle restrictions, idempotency, terminal void, legacy isolation and P9-006 independence.

### Historical regression debt corrected

During P9-012 review, the global `scripts/test-php.sh` gate was found not to invoke the already-existing P9-011 Credits regression. P9-012 corrects that gap by wiring both P9-011 and P9-012 into the global backend gate. P9-011 itself is converted only from a latest-schema assertion to an exact historical `1.54.0 => Migration0055` boundary; all Credit behavioral coverage remains in place.

## Rollback posture

Rollback is code-level only. Migration0056 is additive and immutable schedule evidence is retained. No destructive down migration, legacy-table rewrite or synchronization rollback is introduced.

## Acceptance gates

Before merge, the exact candidate SHA must pass:

1. focused ESC-P9-012 PHP syntax and behavioral regression;
2. P9-011 historical compatibility regression;
3. global backend regression and Enterprise tenancy tests;
4. ESC Foundation / Android coexistence and artifact-isolation gates;
5. Flutter format/analyze/test gate;
6. mergeability/conflict check against current `enterprise-safecontracts`.
