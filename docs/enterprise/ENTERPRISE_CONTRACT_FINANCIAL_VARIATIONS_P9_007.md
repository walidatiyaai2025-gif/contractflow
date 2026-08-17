# ESC-P9-007 — Enterprise Contract Financial Variations / Change Orders

## Decision

P9-007 introduces immutable, tenant-owned post-draft Contract variation/change-order proposal history for Enterprise Safe Contracts.

A P9-007 variation is financial evidence attached to an existing unarchived `active` Contract. It is not an approval decision and it is not yet an effective financial posting. P9-006 remains the authoritative reconciliation of the P9-004 base value plus current P9-005 additions/discounts; P9-007 rows have zero effect on those totals until a later, separately scoped approval/effect/posting task defines that boundary.

No legacy financial table, REST route, admin UI, Flutter model, FX path or production artifact is introduced.

## Domain model

Each variation has one stable server-generated UUID. Every mutation appends a full immutable revision with:

- a unique server-generated revision UUID;
- Contract and tenant ownership;
- the exact P9-003 financial currency profile identity;
- a monotonically increasing per-variation revision number;
- direction: `addition` or `discount`;
- required description up to 191 bytes;
- non-negative P9-001 `Money` amount at four-decimal scale;
- the P9-003 currency snapshot;
- state: `proposed` or terminal `voided`;
- authenticated actor and UTC creation timestamp.

`proposed` deliberately does not mean approved, released, effective, posted or included in Contract totals. No Finance-owned approval lifecycle is invented in P9-007.

A proposed variation may be revised while the Contract remains active; an exact retry returns the existing latest revision. A void appends a terminal revision that preserves the latest direction, description, amount and currency. Repeating the void is idempotent. A voided variation cannot be revised or reactivated; replacement requires a new stable variation identity.

## Schema

P9-007 advances the ESC database schema from `1.49.0` to `1.50.0` with additive `Migration0051EnterpriseContractFinancialVariationRevisions`.

The new table is `safecontracts_contract_financial_variation_revisions`. It has no mutable update columns and P9-007 defines no `UPDATE`, `DELETE`, destructive rollback, legacy rewrite or backfill path.

Key integrity/indexing rules include:

- globally unique revision UUID;
- unique `(tenant_id, contract_id, variation_uuid, revision_number)`;
- latest-current lookup index over tenant, Contract, variation identity, revision number and row id;
- direction/state index;
- financial profile ownership index.

All earlier migration/version mappings remain unchanged historical boundaries.

## Authorization and consistency boundary

Every current-state read and every mutation executes in one transaction and uses the same Contract-first ordering:

1. derive tenant only from core-enforced `TenantContextStore`;
2. lock the exact current-tenant Contract with `FOR UPDATE`;
3. invoke service-owned `VIEW_ALL` / own `VIEW_ASSIGNED` scope authorization against that exact locked row;
4. only after scope succeeds, read and lock the P9-003 financial currency profile;
5. then read current variation state or lock the exact latest variation revision;
6. validate identities, profile, currency, UUIDs, revision metadata, state and Money invariants;
7. append one guarded immutable revision when mutation is required;
8. commit, otherwise roll back and fail closed.

Mutations additionally require the locked Contract to be unarchived and exactly `active`. Draft, completed, cancelled or archived Contracts cannot create, revise or void P9-007 variations. Historical variation evidence remains readable after lifecycle completion.

The service requires `ACCESS` for reads and `EDIT_CONTRACTS` for mutations, with `TenantAuthorization::allowsCapability()` narrowing global WordPress capability grants.

## Currency authority

The caller supplies no tenant and no currency. The locked P9-003 Contract financial currency profile is the only currency authority. P9-001 `Money` is built only after that profile is known.

Negative amounts, malformed persisted values, profile drift, currency drift and unsupported states/directions fail closed. There is no FX, exchange-rate, conversion or revaluation behavior.

## Bounded current state

A Contract may own at most 200 stable variation identities.

Current-state reads derive the latest immutable revision per variation using `NOT EXISTS` for a newer revision, order deterministically by variation UUID and request 201 rows. The 201st row is an overflow sentinel and causes a closed failure rather than silent truncation.

Duplicate latest identities or malformed current evidence also fail closed.

## Relationship to P9-006

P9-006 is intentionally unchanged. Its repository does not query the P9-007 table and its service has no dependency on P9-007 variation classes.

Therefore both `proposed` and `voided` P9-007 rows have zero authoritative effect on `base_value`, `additions_total`, `discounts_total`, `gross_value` or `net_value`. A later task must explicitly define approval/effect/posting semantics before any variation can enter reconciliation.

## Full Impact Review

### Domain/business logic — affected

Adds post-draft financial change-order proposal evidence for active Contracts. It does not add approval/effect semantics.

### Tenant isolation — affected

Tenant identity comes only from locked context. Every Contract, profile and variation query is tenant scoped.

### Database/migrations/indexes — affected

Adds one immutable evidence table in Migration0051 and advances schema to `1.50.0`. No existing table is altered or dropped.

### Backend business logic — affected

Adds variation policy, revision repository and service with bounded current reads plus create/revise/void append semantics.

### Authorization/data scope — affected

Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`. Tenant role narrowing and locked-row `VIEW_ALL` / own `VIEW_ASSIGNED` scope are mandatory.

### Security/concurrency/idempotency — affected

Contract-first locking removes the scope/lifecycle TOCTOU window. Profile and latest variation locks serialize immutable revisions. Exact proposed-revision and terminal-void retries are idempotent.

### Currency/localization — affected only for canonical financial identity

P9-003 + P9-001 only. No locale parsing, FX or revaluation.

### Audit/compliance — affected

Immutable revision UUID, actor and UTC timestamp preserve proposal evidence. Generic P10 audit/event integration is not added here.

### Performance — affected

Stable identities are capped at 200 and current reads use a 201st overflow sentinel. No unbounded history scan is added to the authoritative P9-006 reconciliation.

### P9-006 reconciliation — reviewed / intentionally unchanged

P9-007 proposed/voided variations are not included in authoritative totals.

### REST/API — N/A

No route or public payload.

### WordPress/admin UI — N/A

No administrative surface.

### Flutter/mobile/offline — N/A

No client model or local recomputation.

### Android/build/release identity — N/A

No Android, Firebase, package, signing or artifact changes.

### Legacy compatibility — reviewed

Legacy Contract adjustments, Contract money calculations, payments and other historical financial paths are neither read nor synchronized.

### Reports/import/export/notifications/documents/search/public claims — N/A

No surface introduced.

### Tests/CI — affected

`tests/php/enterprise_contract_financial_variations_p9_007.php` verifies schema/history, policy bounds, locked authorization order, lifecycle matrix, 201st sentinel, immutable/idempotent revision behavior, terminal void, profile/currency integrity and P9-006 independence. It is wired into `scripts/test-php.sh` and `.github/workflows/esc-p9-007.yml`.

P9-005 and P9-006 schema assertions remain historical regressions by requiring the current schema to be at or beyond their original boundaries while retaining their exact migration mappings.

### Release/rollback — reviewed

Rollback is code-only. The additive immutable evidence table is retained; no destructive down migration is defined.

## Non-goals

P7 approval integration, approval/effect/posting semantics, authoritative reconciliation integration, taxes/VAT, retention, penalties, credits, invoices, payment schedules, collections, FX, REST/admin/mobile UI, reports, import/export, notifications, public feature claims, legacy synchronization and production artifacts are outside P9-007.
