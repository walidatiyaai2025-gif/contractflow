# ESC-P9-010 — Enterprise Contract Penalty Rule Foundation

## Decision

P9-010 introduces immutable, tenant-owned Contract penalty configuration evidence for Enterprise Safe Contracts.

It deliberately does **not** trigger, accrue, assess, post or reconcile penalties. Trigger conditions, delay/SLA formulas, grace periods, milestone/deliverable linkage, calculation basis, caps, compounding, waivers, application order, tax/retention interaction and monetary rounding are separate financial-engine decisions and are not inferred here.

## Penalty operands

Each penalty rule has one allowlisted configuration mode:

- `fixed_amount`: one non-negative P9-001 `Money` value in the authoritative P9-003 Contract financial currency.
- `percentage`: one exact P9-008 `PercentageRate` value from `0.0000` through `100.0000`.

P9-010 introduces no second Money or rate primitive, no float path and no rounding behavior.

Persistence uses a canonical `configured_value DECIMAL(20,4)` and the P9-003 `currency_code` snapshot for every revision. For `fixed_amount`, the configured value is monetary. For `percentage`, the configured value is a percentage and the stored currency is identity evidence only; it does not make the percentage monetary.

## Penalty rule domain

Each rule has one stable server-generated UUID. Every mutation appends a complete immutable revision containing:

- unique server-generated revision UUID;
- tenant and Contract ownership;
- exact P9-003 financial profile identity;
- stable penalty-rule UUID;
- monotonically increasing per-rule revision number;
- required label up to 120 bytes;
- calculation mode;
- canonical configured value;
- P9-003 currency snapshot;
- state: `configured` or terminal `voided`;
- authenticated actor and UTC creation timestamp.

`configured` means configuration evidence only. It does not mean triggered, accrued, assessed, approved, payable, posted or included in Contract totals.

An exact label/mode/value retry is idempotent. A changed configured rule appends another revision. Voiding appends a terminal revision preserving the latest configuration. Repeated void is idempotent. A voided rule cannot be revised or reactivated; replacement requires a new stable penalty-rule identity.

## Lifecycle boundary

Penalty-rule mutations are allowed only when the exact current-tenant Contract is:

- unarchived; and
- `draft` or `active`.

Completed, cancelled or archived Contracts cannot create, revise or void penalty rules. Existing immutable evidence remains readable after completion or cancellation.

## Schema

P9-010 advances the ESC schema from `1.52.0` to `1.53.0` through additive `Migration0054EnterpriseContractFinancialPenaltyRuleRevisions`.

The new table `safecontracts_contract_financial_penalty_rule_revisions` stores tenant id, revision UUID, Contract id, P9-003 profile id, stable penalty-rule UUID, revision number, label, calculation mode, `DECIMAL(20,4)` configured value, currency snapshot, rule state, actor and UTC creation timestamp.

Integrity/indexing includes:

- globally unique revision UUID;
- unique `(tenant_id, contract_id, penalty_rule_uuid, revision_number)`;
- latest-current lookup index over tenant, Contract, penalty identity, revision and row id;
- mode/state lookup index;
- financial-profile ownership index.

No mutable update columns, `UPDATE`, `DELETE`, destructive rollback, legacy rewrite or backfill are introduced.

All previous migration mappings remain historical facts. P9-009 treats `1.52.0 => Migration0053` as its historical boundary while retaining retention behavior regression coverage.

## Authorization and consistency boundary

Every current-state read and every mutation runs in one transaction using Contract-first ordering:

1. derive tenant only from core-enforced `TenantContextStore`;
2. lock the exact current-tenant Contract with `FOR UPDATE`;
3. run service-owned `VIEW_ALL` / own `VIEW_ASSIGNED` scope authorization against that exact locked row;
4. only after authorization succeeds, lock and validate the P9-003 financial currency profile;
5. canonicalize the configured operand after profile currency is known: P9-001 Money for fixed amount or P9-008 PercentageRate for percentage;
6. for revise/void, lock the exact latest penalty-rule revision;
7. validate identities, profile, currency, UUIDs, revision metadata, label, mode, configured value and state;
8. append one guarded immutable revision when required;
9. commit; any authorization, lifecycle, cardinality or integrity failure rolls back and fails closed.

The service requires `ACCESS` for reads and `EDIT_CONTRACTS` for mutations. `TenantAuthorization::allowsCapability()` narrows the global WordPress capability grant. The caller supplies no tenant, financial profile or currency.

The final `INSERT ... SELECT` repeats tenant, Contract lifecycle, exact profile id and P9-003 currency predicates, so the append cannot escape the locked authority boundary.

## Bounded current state

A Contract may own at most 20 stable P9-010 penalty-rule identities.

Current-state reads derive the latest immutable revision per rule using `NOT EXISTS` for a newer revision, sort deterministically by penalty-rule UUID and request 21 rows. The 21st row is an overflow sentinel and fails closed instead of silently truncating.

Duplicate latest identities, malformed revisions, invalid modes/values, negative fixed amounts, percentage values above 100, invalid currency or cross-profile/cross-currency evidence also fail closed.

## Relationship to P9-006

P9-006 remains unchanged and authoritative for base value, active additions, active discounts, gross value and signed net value.

P9-006 does not query P9-010 penalty evidence and has no P9-010 dependency. Configured or voided penalty rules therefore have zero monetary effect.

A later explicitly scoped task must define at minimum:

- trigger/effective semantics;
- calculation basis;
- delay/SLA or other trigger linkage;
- grace period and accrual frequency if applicable;
- caps/ceilings;
- rounding;
- waiver/reversal semantics;
- posting/application order;
- interaction with variations, tax, retention, credits, payments and collections.

## Full Impact Review

### Domain/business logic — affected

Adds immutable generic penalty configuration evidence only. No trigger, accrual or assessment calculation is introduced.

### Tenant isolation — affected

Tenant identity comes only from locked context. Contract, profile and penalty SQL are tenant scoped.

### Database/migrations/indexes — affected

Adds one immutable evidence table through Migration0054 and advances schema to `1.53.0`. Existing tables are not altered or dropped.

### Backend business logic — affected

Adds penalty policy, revision repository and service while reusing P9-001 Money and P9-008 PercentageRate.

### Authorization/data scope — affected

Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`. Tenant-role narrowing and locked-row `VIEW_ALL` / own `VIEW_ASSIGNED` scope are mandatory.

### Security/concurrency/idempotency — affected

Contract-first locking closes scope/lifecycle TOCTOU. Profile/latest-rule locking serializes immutable revisions. Exact retries and repeated terminal voids are idempotent.

### Currency — affected

Fixed operands are canonical P9-001 Money in P9-003 currency. Every revision persists and validates the P9-003 currency snapshot. No FX exists.

### Percentage precision — affected

Percentage mode reuses P9-008 PercentageRate. No floats or rounding path is introduced.

### Audit/compliance — affected

Immutable revision UUID, actor and UTC timestamp preserve configuration evidence. Generic P10 audit remains separate.

### Performance — affected

Stable penalty identities are capped at 20 and current reads use a 21st overflow sentinel. P9-006 gains no penalty-history scan.

### P9-006 reconciliation — reviewed / intentionally unchanged

P9-010 evidence has zero monetary effect.

### REST/API — N/A

No route or public payload.

### WordPress/admin UI — N/A

No admin surface.

### Flutter/mobile/offline — N/A

No client model or recomputation.

### Android/build identity — N/A

No package, Firebase, signing or artifact changes.

### Localization/legal — reviewed / N/A

No locale numeric parsing, jurisdiction inference or statutory penalty rule is introduced.

### Reports/import/export/notifications/documents/search/public claims — N/A

No external surface is introduced.

### Legacy/backward compatibility — reviewed

Legacy ContractMoney, adjustments, payments, collections and historical financial paths are neither read nor synchronized. P9-004 through P9-009 semantics remain unchanged.

### Tests/CI — affected

`tests/php/enterprise_contract_financial_penalty_rules_p9_010.php` covers schema/history, operand modes, Money/PercentageRate reuse, locked authorization order, lifecycle matrix, 21st sentinel, duplicate/corrupt state, profile/currency integrity, exact operand validation, immutable/idempotent revisions, terminal void and P9-006 independence. It is wired into `scripts/test-php.sh` and `.github/workflows/esc-p9-010.yml`.

### Release/rollback — reviewed

Rollback is code-only. The additive immutable evidence table is retained; no destructive down migration exists.

## Non-goals

P9-010 does not implement penalty triggers, delay-day/SLA formulas, milestone/deliverable linkage, grace periods, accrual schedules, basis selection, caps, compounding, waivers, approval/effect/posting, monetary reconciliation integration, tax/retention/variation interaction, credits, invoices, payment schedules, collections, FX, REST/admin/mobile UI, reports, import/export, notifications, public feature claims, legacy synchronization or production artifacts.
