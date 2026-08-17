# ESC-P9-008 — Enterprise Contract Tax / VAT Rule Foundation

## Decision

P9-008 introduces immutable, tenant-owned Contract tax/VAT rule configuration evidence for Enterprise Safe Contracts.

It does **not** calculate tax money and it does not change the authoritative P9-006 financial reconciliation. P9-001 `Money` intentionally has no multiplication/rounding primitive, so P9-008 does not invent a tax basis, inclusive/exclusive behavior, stacking/compounding order or monetary rounding mode. Those are explicit future financial-engine decisions.

P9-008 is also jurisdiction-neutral. `tax` and `vat` are generic product classifications only. No country, statutory rate, exemption, reverse-charge, withholding or legal-compliance conclusion is encoded.

## Exact percentage primitive

`PercentageRate` is a fixed-scale, non-floating-point value object for configuration rates only.

- accepts integer or plain decimal string input;
- rejects float input;
- fixed scale: four decimal places;
- valid range: `0.0000` through `100.0000` inclusive;
- rejects negative, scientific, malformed, over-scale and over-100 values;
- canonical comparison uses exact normalized decimal strings;
- defines no multiplication, division or rounding behavior.

This isolates rate storage correctness from later monetary tax calculation semantics.

## Tax rule domain

Each tax rule has one stable server-generated UUID. Every mutation appends a complete immutable revision with:

- unique server-generated revision UUID;
- tenant and Contract ownership;
- exact P9-003 Contract financial currency profile identity;
- stable tax-rule UUID;
- monotonically increasing per-rule revision number;
- kind: `tax` or `vat`;
- required label up to 120 bytes;
- exact percentage rate;
- state: `configured` or terminal `voided`;
- authenticated actor and UTC creation timestamp.

`configured` means only that a rule definition is stored. It does **not** mean approved, legally applicable, effective, posted, payable or included in financial totals.

A configured rule can be revised while the Contract remains mutable for P9-008. An exact retry returns the existing latest revision. Voiding appends a terminal revision that preserves kind, label and rate. Repeating a void is idempotent. A voided rule cannot be revised or reactivated; replacement requires a new stable tax-rule identity.

## Lifecycle boundary

Tax/VAT rule mutations are allowed only when the exact current-tenant Contract is:

- unarchived; and
- `draft` or `active`.

Completed, cancelled or archived Contracts cannot create, revise or void rules. Existing immutable rule evidence remains readable after completion or cancellation.

This allows both pre-award configuration and post-award rule evidence without pretending that a configured rule has monetary effect.

## Schema

P9-008 advances the ESC database schema from `1.50.0` to `1.51.0` through additive `Migration0052EnterpriseContractFinancialTaxRuleRevisions`.

The new table is `safecontracts_contract_financial_tax_rule_revisions` and contains:

- tenant id;
- revision UUID;
- Contract id;
- P9-003 financial currency profile id;
- stable tax-rule UUID;
- revision number;
- tax kind;
- label;
- `DECIMAL(7,4)` rate percent;
- rule state;
- actor;
- UTC creation timestamp.

Integrity/indexing includes:

- unique revision UUID;
- unique `(tenant_id, contract_id, tax_rule_uuid, revision_number)`;
- latest-current lookup index over tenant, Contract, tax-rule identity, revision number and row id;
- kind/state lookup index;
- profile ownership index.

No mutable update columns, `UPDATE`, `DELETE`, destructive rollback, legacy rewrite or backfill are introduced.

All prior migration mappings remain historical facts. P9-007 regression now requires the current schema to be at or beyond its original `1.50.0` boundary while preserving the exact Migration0051 mapping.

## Authorization and consistency boundary

Every current-state read and every mutation runs in one transaction using Contract-first ordering:

1. derive tenant only from core-enforced `TenantContextStore`;
2. lock the exact current-tenant Contract with `FOR UPDATE`;
3. invoke service-owned `VIEW_ALL` / own `VIEW_ASSIGNED` scope authorization against that exact locked row;
4. only after authorization succeeds, lock and validate the P9-003 Contract financial currency profile;
5. for revise/void, lock the exact latest tax-rule revision;
6. validate identities, profile, UUIDs, revision metadata, kind, label, percentage rate and state;
7. append one guarded immutable revision when required;
8. commit; on any authorization, lifecycle, cardinality or invariant failure, roll back and fail closed.

The service requires `ACCESS` for reads and `EDIT_CONTRACTS` for mutations. `TenantAuthorization::allowsCapability()` narrows the global WordPress capability grant.

The final append repeats the tenant, Contract lifecycle and exact profile predicates in `INSERT ... SELECT`, so an append cannot silently escape the locked authority boundary.

## Profile identity

P9-008 stores no tax monetary amount and therefore does not duplicate currency text on the tax-rule revision. It persists the exact P9-003 financial profile identity and validates that identity on every current read/revision path.

The profile currency itself is still validated as a proper `CurrencyCode` when the profile is locked. This ensures future calculation tasks can build on a valid P9-003 currency domain without P9-008 introducing FX or monetary tax logic.

## Bounded current state

A Contract may own at most 20 stable P9-008 tax-rule identities.

Current-state reads derive the latest immutable revision per rule using `NOT EXISTS` for a newer revision, order deterministically by tax-rule UUID and request 21 rows. The 21st row is an overflow sentinel and fails closed rather than silently truncating.

Duplicate latest identities, malformed revisions, invalid rates, invalid kinds/states or cross-profile evidence also fail closed.

## Relationship to P9-006

P9-006 remains unchanged and authoritative for:

- base value;
- active additions;
- active discounts;
- gross value;
- signed net value.

Its repository does not query `safecontracts_contract_financial_tax_rule_revisions` and its service has no P9-008 dependency.

Therefore configured or voided P9-008 rules have zero monetary effect. A future task must explicitly define tax basis, application mode, stacking/compounding, rounding, effective/posting semantics and reconciliation integration before any tax amount is calculated.

## Full Impact Review

### Domain/business logic — affected

Adds generic immutable tax/VAT rule definition evidence. No tax amount calculation or legal applicability is introduced.

### Tenant isolation — affected

Tenant identity is taken only from locked tenant context. Contract, profile and tax-rule SQL are tenant scoped.

### Database/migrations/indexes — affected

Adds one immutable evidence table via Migration0052 and advances schema to `1.51.0`. No existing table is altered or dropped.

### Backend business logic — affected

Adds `PercentageRate`, tax-rule policy, revision repository and service with bounded current reads plus create/revise/void append semantics.

### Authorization/data scope — affected

Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`. Tenant-role narrowing and locked-row `VIEW_ALL` / own `VIEW_ASSIGNED` scope are mandatory.

### Security/concurrency/idempotency — affected

Contract-first locking closes scope/lifecycle TOCTOU. Profile/latest-rule locking serializes immutable revisions. Exact configured retries and repeated terminal voids are idempotent.

### Currency — affected only for profile identity

P9-003 profile identity is persisted and validated. No monetary tax amount, FX or conversion exists.

### Localization/RTL/timezone — reviewed / N/A

No locale numeric parsing or UI is introduced. Rates use canonical plain decimal strings; timestamps remain UTC evidence.

### Legal/compliance — reviewed / intentionally not inferred

No statutory rate, jurisdiction, exemption, reverse-charge, withholding or legal-compliance determination is encoded. P10 generic audit remains separate.

### Performance — affected

Tax-rule identities are capped at 20 and current reads use a 21st overflow sentinel. P9-006 gains no tax history scan.

### P9-006 reconciliation — reviewed / intentionally unchanged

P9-008 rule evidence has zero authoritative monetary effect.

### REST/API — N/A

No route, public schema or versioned API payload is introduced.

### WordPress/admin UX — N/A

No admin surface is introduced.

### Flutter/mobile/offline — N/A

No client model, cache or financial recomputation is introduced.

### Android/build identity — N/A

No package, Firebase, signing, coexistence or release artifact changes.

### Public landing/product messaging — N/A

No feature claim is published.

### Design/theme — N/A

No UI surface.

### Feature registry/entitlements — N/A

No plan/flag behavior is introduced.

### Search/filter/report/import/export — N/A

No external data surface is introduced.

### Notifications — N/A

No rules or delivery behavior.

### Documents/storage — N/A

No document or object-storage behavior.

### Legacy/backward compatibility — reviewed

Legacy `ContractMoney`, legacy adjustments/payments and historical financial totals are neither read nor synchronized. P9-004/005/006/007 semantics remain unchanged.

### Tests/docs/demo data — affected

`tests/php/enterprise_contract_financial_tax_rules_p9_008.php` covers exact rates, schema/history, locked authorization order, lifecycle matrix, 21st sentinel, duplicate/corrupt state, profile integrity, immutable/idempotent revision behavior, terminal void and P9-006 independence.

### CI/release/rollback — affected

The regression is wired into `scripts/test-php.sh` and `.github/workflows/esc-p9-008.yml`. Rollback is code-only; the additive immutable evidence table is retained and no destructive down migration exists.

## Non-goals

P9-008 does not implement tax amount calculation, tax basis, inclusive/exclusive pricing, compound/stacked tax ordering, rounding, jurisdiction/legal rules, exemptions, reverse charge, withholding, P7 approval integration, authoritative-total integration, retention, penalties, credits, invoices, payment schedules, collections, FX, REST/admin/mobile UI, reports, import/export, notifications, public feature claims, legacy synchronization or production artifacts.
