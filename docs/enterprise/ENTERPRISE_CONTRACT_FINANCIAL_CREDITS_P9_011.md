# ESC-P9-011 — Enterprise Contract Credit Entry Foundation

## Decision

P9-011 introduces immutable, tenant-owned post-award Contract credit proposal evidence for Enterprise Safe Contracts.

It deliberately does **not** approve, post, apply, allocate, refund, settle or reconcile credits. The stored amount is always a positive credit magnitude. P9-011 does not encode credit sign by persisting a negative value and does not silently reduce P9-006 net value.

## Credit domain

Each credit has one stable server-generated UUID. Every mutation appends a complete immutable revision containing:

- unique server-generated revision UUID;
- tenant and Contract ownership;
- exact P9-003 financial profile identity;
- stable credit UUID;
- monotonically increasing per-credit revision number;
- required reason up to 191 bytes;
- non-negative P9-001 `Money` amount;
- P9-003 currency snapshot;
- state: `proposed` or terminal `voided`;
- authenticated actor and UTC creation timestamp.

`proposed` is evidence only. It does not mean approved, effective, posted, applied, refunded, settled or included in Contract totals.

An exact reason/amount retry is idempotent. Changed proposal data appends another revision. Voiding appends a terminal revision preserving the current reason and Money snapshot. Repeated void is idempotent. A voided credit cannot be revised or reactivated; replacement requires a new stable credit identity.

## Lifecycle boundary

Credit mutations are allowed only when the exact current-tenant Contract is unarchived and `active`.

Draft, completed, cancelled or archived Contracts cannot create, revise or void P9-011 credits. Existing immutable credit evidence remains readable after completion or cancellation.

## Schema

P9-011 advances ESC schema from `1.53.0` to `1.54.0` through additive `Migration0055EnterpriseContractFinancialCreditRevisions`.

The new table `safecontracts_contract_financial_credit_revisions` stores tenant id, revision UUID, Contract id, P9-003 profile id, stable credit UUID, revision number, reason, `DECIMAL(20,4)` amount, currency snapshot, state, actor and UTC creation timestamp.

Integrity/indexing includes:

- globally unique revision UUID;
- unique `(tenant_id, contract_id, credit_uuid, revision_number)`;
- latest-current lookup index over tenant, Contract, credit identity, revision and row id;
- credit-state index;
- financial-profile ownership index.

No mutable update columns, `UPDATE`, `DELETE`, destructive rollback, legacy rewrite or backfill are introduced.

All earlier migration mappings remain historical facts. P9-010 treats `1.53.0 => Migration0054` as its historical boundary while retaining penalty behavior regression coverage.

## Authorization and consistency boundary

Every current-state read and mutation runs inside one transaction with Contract-first ordering:

1. derive tenant only from core-enforced `TenantContextStore`;
2. lock the exact current-tenant Contract with `FOR UPDATE`;
3. run service-owned `VIEW_ALL` / own `VIEW_ASSIGNED` authorization against that exact locked row;
4. only after authorization succeeds, lock and validate the P9-003 financial currency profile;
5. canonicalize credit amount with P9-001 `Money` only after authoritative profile currency is known;
6. for revise/void, lock the exact latest credit revision;
7. validate identities, profile, currency, UUIDs, revision metadata, reason, amount and state;
8. append one guarded immutable revision when required;
9. commit; any authorization, lifecycle, cardinality or integrity failure rolls back and fails closed.

The service requires `ACCESS` for reads and `EDIT_CONTRACTS` for mutations. `TenantAuthorization::allowsCapability()` narrows the global WordPress capability grant. Caller supplies no tenant, profile or currency.

The final `INSERT ... SELECT` repeats tenant, active/unarchived Contract state, exact profile id and P9-003 currency predicates so the append cannot escape the locked authority boundary.

## Amount semantics

P9-011 stores only a non-negative P9-001 Money magnitude.

The amount is not stored as a negative number. Whether an effective credit will later subtract from a contractual balance is a future posting/reconciliation decision. Negative caller or persisted amounts fail closed.

No FX, currency conversion, refund logic, application balance or invoice allocation is introduced.

## Bounded current state

A Contract may own at most 100 stable P9-011 credit identities.

Current-state reads derive the latest immutable revision per credit using `NOT EXISTS` for a newer revision, sort deterministically by credit UUID and request 101 rows. The 101st row is an overflow sentinel and causes a closed failure rather than silent truncation.

Duplicate latest identities, malformed revisions, negative Money, invalid currency or cross-profile/cross-currency evidence also fail closed.

## Relationship to P9-006

P9-006 remains unchanged and authoritative for base value, active additions, active discounts, gross value and signed net value.

P9-006 does not query P9-011 credit evidence and has no P9-011 dependency. Proposed or voided credits therefore have zero authoritative monetary effect.

A future explicitly scoped task must define at minimum approval/effect/posting semantics, application order, allocation/partial application, invoice or payment linkage, refund behavior, tax/retention/penalty interaction and settlement/reversal behavior before credits can alter financial totals.

## Full Impact Review

### Domain/business logic — affected

Adds immutable post-award credit proposal evidence only. No posting/application/refund behavior is introduced.

### Tenant isolation — affected

Tenant identity comes only from locked context. Contract, profile and credit SQL are tenant scoped.

### Database/migrations/indexes — affected

Adds one immutable evidence table through Migration0055 and advances schema to `1.54.0`. Existing tables are not altered or dropped.

### Backend business logic — affected

Adds credit policy, revision repository and service using P9-001 Money.

### Authorization/data scope — affected

Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`. Tenant-role narrowing and locked-row `VIEW_ALL` / own `VIEW_ASSIGNED` scope are mandatory.

### Security/concurrency/idempotency — affected

Contract-first locking closes scope/lifecycle TOCTOU. Profile/latest-credit locking serializes immutable revisions. Exact retries and repeated terminal voids are idempotent.

### Currency — affected

Credit amount is canonical P9-001 Money in P9-003 currency. Every revision persists and validates the P9-003 currency snapshot. No FX exists.

### Audit/compliance — affected

Immutable revision UUID, actor and UTC timestamp preserve proposal evidence. Generic P10 audit remains separate.

### Performance — affected

Stable credits are capped at 100 and current reads use a 101st overflow sentinel. P9-006 gains no credit-history scan.

### P9-006 reconciliation — reviewed / intentionally unchanged

P9-011 evidence has zero monetary effect.

### REST/API — N/A

No route or public payload.

### WordPress/admin UI — N/A

No admin surface.

### Flutter/mobile/offline — N/A

No client model or recomputation.

### Android/build identity — N/A

No package, Firebase, signing or artifact changes.

### Localization/legal — reviewed / N/A

No locale numeric parsing or jurisdiction-specific credit rule is introduced.

### Reports/import/export/notifications/documents/search/public claims — N/A

No external surface is introduced.

### Legacy/backward compatibility — reviewed

Legacy ContractMoney, adjustments, payments, collections and historical financial paths are neither read nor synchronized. P9-004 through P9-010 semantics remain unchanged.

### Tests/CI — affected

`tests/php/enterprise_contract_financial_credits_p9_011.php` covers schema/history, policy bounds, Contract-first authorization, active-only lifecycle, 101st sentinel, duplicate/corrupt state, profile/currency integrity, Money validation, immutable/idempotent revisions, terminal void and P9-006 independence. It is wired into `scripts/test-php.sh` and `.github/workflows/esc-p9-011.yml`.

### Release/rollback — reviewed

Rollback is code-only. The additive immutable evidence table is retained; no destructive down migration exists.

## Non-goals

P9-011 does not implement credit approval/effect/posting, invoice or credit-note linkage, refunds, allocation/application, partial application, settlements, tax/retention/penalty interaction, authoritative-total integration, payment schedules, collections, FX, REST/admin/mobile UI, reports, import/export, notifications, public feature claims, legacy synchronization or production artifacts.
