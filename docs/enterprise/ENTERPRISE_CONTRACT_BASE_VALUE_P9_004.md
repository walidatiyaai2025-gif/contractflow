# ESC-P9-004 — Enterprise Contract Base-Value Revisions

## Decision

P9-004 introduces an ESC-only, tenant-owned, append-only base contractual value history. It does not reinterpret or synchronize the legacy `safecontracts_contracts.base_value` column.

The P9-003 Contract financial currency profile is the sole currency identity for this domain. Callers provide an amount only; server-side Finance code loads the existing profile and constructs P9-001 `Money` from that persisted contract currency. No caller currency, tenant selector, fallback currency or FX conversion path exists.

Base-value corrections are allowed only while the current-tenant Contract is an unarchived `draft`. A changed amount appends the next immutable revision. An exact same-amount retry returns the locked latest revision without another INSERT. After draft, contractual value changes belong to later explicit P9 Variation semantics rather than rewriting the original base-value history.

## Persistence

Migration0049 advances the database schema only from `1.47.0` to `1.48.0` and creates `safecontracts_contract_financial_base_value_revisions`.

Each revision stores:

- tenant id;
- server-generated UUID;
- Contract id;
- P9-003 financial currency profile id;
- monotonically increasing per-Contract revision number;
- `DECIMAL(20,4)` amount;
- explicit currency snapshot copied from the locked profile;
- actor id and creation timestamp.

The table has no `updated_at` / `updated_by` columns and the Finance repository exposes no UPDATE or DELETE path. Historical `1.47.0 => Migration0048EnterpriseContractFinancialCurrencyProfiles` mapping remains unchanged.

## Concurrency and idempotency

Mutation is serialized in one database transaction:

1. lock the exact current-tenant Contract;
2. reject archived or non-draft state;
3. lock the exact current-tenant P9-003 profile;
4. validate profile identity and contract currency against the command `Money`;
5. lock the latest revision;
6. return it for an exact same-amount retry, otherwise compute `revision + 1`;
7. perform one guarded `INSERT ... SELECT` that revalidates Contract draft/archive state and profile currency immediately before persistence;
8. commit, or roll back on any invariant failure.

The locked Contract row serializes concurrent first-revision attempts for the same Contract. The unique `(tenant_id, contract_id, revision_number)` key provides an additional database invariant.

## Full Impact Review

### Business/domain model — affected

Adds the first persisted ESC contract monetary value after the P9-001 Money foundation, P9-002 tenant base-currency policy and P9-003 contract currency profile. The value is non-negative and append-only during draft.

### Tenant model/isolation — affected

All repository access requires core tenant enforcement and `TenantContextStore::context()->requireTenantId()`. SQL is constrained to the locked tenant and Contract. Object ids never authorize access.

### Database/migrations/indexes — affected

One additive Migration0049 and one ESC-only table. No ALTER/DROP and no rewrite/backfill of legacy financial tables. Latest reads are bounded and indexed by tenant/Contract/revision.

### Backend business logic — affected

Adds `ContractFinancialBaseValueRevisionRepository` and `ContractFinancialBaseValueRevisionService`. P9-001 `Money` and P9-003 currency profiles remain authoritative boundaries.

### Authorization/scopes/roles — affected

Reads require `ACCESS`; appends require `EDIT_CONTRACTS`. WordPress capability grants remain the global ceiling and `TenantAuthorization::allowsCapability()` narrows them. Existing `VIEW_ALL` / own `VIEW_ASSIGNED` Contract data scope is preserved.

### REST API/version compatibility — N/A

No REST route or payload is introduced in P9-004.

### WordPress/admin UI — N/A

No admin surface is introduced.

### Flutter/mobile/offline state — N/A

No mobile model, route, local state or UI is introduced.

### Android identity/build environments — N/A

No Android/Firebase/artifact identity change.

### Landing/public feature catalog — N/A

P9-004 is not a public feature claim.

### Design system/theme — N/A

No presentation surface.

### Feature registry/plans/entitlements — N/A

No commercial entitlement is exposed by this persistence foundation.

### Search/filter/sort/bulk actions — N/A

Only bounded latest-by-Contract read exists.

### Reports/import/export — N/A

No reporting or import/export execution is introduced.

### Notifications/escalation — N/A

No delivery or timer behavior.

### Audit/compliance — reviewed

The domain itself preserves immutable base-value history with actor and timestamp. Generic cross-domain audit integration remains a later phase; P9-004 does not weaken existing audit behavior.

### Documents/storage — N/A

No documents or binary storage.

### Localization/RTL/LTR/timezone/currency — affected only for currency identity

Currency is the immutable P9-003 contract currency and amounts use P9-001 fixed four-decimal Money. No locale-formatted parsing, exchange rate, conversion or timezone calculation is introduced. Persistence timestamps use UTC.

### Security/privacy/rate limits — reviewed

No public endpoint is added. Tenant context, capability, tenant-role and Contract scope checks fail closed. Caller-controlled tenant and currency selectors are absent.

### Performance/concurrency/idempotency — affected

Reads are bounded to one latest row. Writes lock one Contract, one profile and one latest revision. Exact same-amount retries are idempotent; changed draft amounts append. No unbounded scans are introduced.

### Automated tests — affected

`tests/php/enterprise_contract_financial_base_value_p9_004.php` exercises schema, Money canonicalization, tenant-scoped reads, first append, same-amount retry, changed-amount revision append, negative amount, non-draft rejection, profile-currency mismatch and architectural separation. It is wired into `scripts/test-php.sh`.

### Documentation/demo/onboarding — affected

This document records the P9-004 architecture and impact boundary. No demo/onboarding surface is required yet.

### CI/build/release/rollback — affected

A focused `ESC P9-004 Contract Base Value Revisions` workflow validates syntax and the P9-004 regression on PR/push to `enterprise-safecontracts`. The global ESC Foundation Gate remains authoritative for full backend/mobile isolation regression. Rollback is code-only before deployment; after Migration0049 is deployed the additive table is retained rather than destructively dropped.

### Backward compatibility — reviewed

Legacy `ContractService::updateBaseValue`, `ContractMoney`, `safecontracts_contracts.base_value`, financial items/adjustments, payments, dashboards and General Settings currency semantics remain unchanged. P9-004 never reads legacy base value as an ESC source and never writes it.

## Explicit non-goals

P9-004 does not implement additions, discounts, variations, tax/VAT, retention, penalties, credits, invoices, scheduled payments, collections, exchange rates, conversion/revaluation, reporting, API/UI/mobile exposure, imports/exports, notifications or production artifact publication.
