# ESC-P9-009 — Enterprise Contract Retention Rule Foundation

## Decision

P9-009 introduces immutable, tenant-owned Contract retention configuration evidence for Enterprise Safe Contracts.

It deliberately does **not** calculate retained money, withhold payment, release retained amounts or alter P9-006 reconciliation. Retention basis, caps, certificate/payment linkage, release triggers, defects-liability semantics, partial/final release, stacking and monetary rounding are separate financial-engine decisions and are not inferred here.

## Percentage precision

P9-009 reuses the exact P9-008 `PercentageRate` primitive. No second rate parser, float path or rounding implementation is introduced.

Retention rates therefore:

- are canonical plain decimal values at four-decimal scale;
- range from `0.0000` through `100.0000` inclusive;
- reject floats, malformed values, over-scale input and values above 100;
- have no multiplication/division/rounding semantics in P9-009.

## Retention rule domain

Each retention rule has one stable server-generated UUID. Every mutation appends a complete immutable revision with:

- unique server-generated revision UUID;
- tenant and Contract ownership;
- exact P9-003 Contract financial currency profile identity;
- stable retention-rule UUID;
- monotonically increasing per-rule revision number;
- required label up to 120 bytes;
- exact percentage rate;
- state: `configured` or terminal `voided`;
- authenticated actor and UTC creation timestamp.

`configured` means configuration evidence only. It does not mean effective, withheld, accrued, released, payable, approved or included in a Contract total.

A configured rule can be revised while the Contract remains mutable. An exact label/rate retry returns the current revision without a duplicate write. Voiding appends a terminal revision that preserves the current label and rate. Repeated void is idempotent. A voided rule cannot be revised or reactivated; replacement requires a new stable retention-rule identity.

## Lifecycle boundary

Retention-rule mutations are allowed only when the exact current-tenant Contract is:

- unarchived; and
- `draft` or `active`.

Completed, cancelled or archived Contracts cannot create, revise or void retention rules. Existing immutable evidence remains readable after completion or cancellation.

## Schema

P9-009 advances the ESC database schema from `1.51.0` to `1.52.0` through additive `Migration0053EnterpriseContractFinancialRetentionRuleRevisions`.

The new table is `safecontracts_contract_financial_retention_rule_revisions` and stores:

- tenant id;
- revision UUID;
- Contract id;
- P9-003 financial currency profile id;
- stable retention-rule UUID;
- revision number;
- label;
- `DECIMAL(7,4)` rate percent;
- retention state;
- actor;
- UTC creation timestamp.

Integrity/indexing includes:

- globally unique revision UUID;
- unique `(tenant_id, contract_id, retention_rule_uuid, revision_number)`;
- latest-current lookup index over tenant, Contract, retention identity, revision and row id;
- state index;
- financial-profile ownership index.

No mutable update columns, `UPDATE`, `DELETE`, destructive rollback, legacy rewrite or backfill are introduced.

All previous migration mappings remain historical facts. P9-008 now treats `1.51.0 => Migration0052` as its historical boundary while retaining all Tax/VAT behavioral regression coverage.

## Authorization and consistency boundary

Every current-state read and every mutation runs inside one transaction with Contract-first ordering:

1. derive tenant only from core-enforced `TenantContextStore`;
2. lock the exact current-tenant Contract with `FOR UPDATE`;
3. run service-owned `VIEW_ALL` / own `VIEW_ASSIGNED` authorization against that exact locked row;
4. only after authorization succeeds, lock and validate the P9-003 financial currency profile;
5. for revise/void, lock the exact latest retention-rule revision;
6. validate identities, profile, UUIDs, revision metadata, label, PercentageRate and state;
7. append one guarded immutable revision if a mutation is required;
8. commit; otherwise roll back and fail closed on any authorization, lifecycle, cardinality or integrity error.

The service requires `ACCESS` for reads and `EDIT_CONTRACTS` for mutations. `TenantAuthorization::allowsCapability()` narrows the global WordPress capability grant.

The final `INSERT ... SELECT` repeats tenant, Contract lifecycle and exact profile predicates so the append cannot escape the locked authority boundary.

## Bounded current state

A Contract may own at most 10 stable P9-009 retention-rule identities.

Current-state reads derive the latest immutable revision per rule via `NOT EXISTS` for a newer revision, sort deterministically by rule UUID and request 11 rows. The 11th row is an overflow sentinel and causes a closed failure rather than silent truncation.

Duplicate latest identities, malformed revisions, invalid percentages or cross-profile evidence also fail closed.

## Relationship to P9-006

P9-006 remains unchanged and authoritative for base value, active additions, active discounts, gross value and signed net value.

P9-006 does not query P9-009 retention evidence and has no P9-009 dependency. Configured or voided retention rules therefore have zero monetary effect.

A future explicitly scoped task must define at minimum:

- retained-money basis;
- any cap/ceiling;
- calculation and rounding rules;
- effective/posting semantics;
- payment/certificate linkage;
- release triggers and partial/final release;
- interaction with tax, variations, penalties, credits and collections.

## Full Impact Review

### Domain/business logic — affected

Adds immutable generic retention configuration evidence only. No withholding or release calculation is introduced.

### Tenant isolation — affected

Tenant identity comes only from locked context. Contract, profile and retention SQL are tenant scoped.

### Database/migrations/indexes — affected

Adds one immutable evidence table via Migration0053 and advances schema to `1.52.0`. Existing tables are not altered or dropped.

### Backend business logic — affected

Adds retention policy, revision repository and service while reusing P9-008 `PercentageRate`.

### Authorization/data scope — affected

Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`. Tenant-role narrowing and locked-row `VIEW_ALL` / own `VIEW_ASSIGNED` scope are mandatory.

### Security/concurrency/idempotency — affected

Contract-first locking closes scope/lifecycle TOCTOU. Profile/latest-rule locking serializes immutable revisions. Exact retries and repeated terminal voids are idempotent.

### Currency — affected only for profile identity

The exact P9-003 profile id is stored and validated. No retained Money or FX exists.

### Percentage precision — affected

P9-008 `PercentageRate` is reused exactly. No floats or rounding path is introduced.

### Audit/compliance — affected

Immutable revision UUID, actor and UTC timestamp preserve configuration evidence. Generic P10 audit remains separate.

### Performance — affected

Stable rules are capped at 10 and current reads use an 11th overflow sentinel. P9-006 gains no retention-history scan.

### P9-006 reconciliation — reviewed / intentionally unchanged

P9-009 evidence has zero monetary effect.

### REST/API — N/A

No route or public payload is introduced.

### WordPress/admin UI — N/A

No admin surface is introduced.

### Flutter/mobile/offline — N/A

No client model or recomputation is introduced.

### Android/build identity — N/A

No package, Firebase, signing or artifact changes.

### Localization/legal — reviewed / N/A

No locale numeric parsing, jurisdiction inference or statutory/legal rule is introduced.

### Reports/import/export/notifications/documents/search/public claims — N/A

No external surface is introduced.

### Legacy/backward compatibility — reviewed

Legacy ContractMoney, payments, collections and historical financial paths are neither read nor synchronized. P9-004/005/006/007/008 semantics remain unchanged.

### Tests/CI — affected

`tests/php/enterprise_contract_financial_retention_rules_p9_009.php` covers schema/history, PercentageRate reuse, locked authorization order, lifecycle matrix, 11th sentinel, duplicate/corrupt state, profile integrity, immutable/idempotent revisions, terminal void and P9-006 independence. It is wired into `scripts/test-php.sh` and `.github/workflows/esc-p9-009.yml`.

### Release/rollback — reviewed

Rollback is code-only. The additive immutable evidence table is retained; no destructive down migration exists.

## Non-goals

P9-009 does not implement retained-money calculation, basis selection, caps, staged/partial/final release, defects-liability rules, milestone/certificate linkage, approval/effect integration, tax interaction, variations effect, penalties, credits, invoices, payment schedules, collections, FX, REST/admin/mobile UI, reports, import/export, notifications, public feature claims, legacy synchronization or production artifacts.
