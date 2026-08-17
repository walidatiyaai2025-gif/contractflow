# ESC-P9-005 — Enterprise Contract Additions and Discounts

## Decision

P9-005 introduces ESC-only Contract additions and discounts as revisioned financial lines. It does not reuse, migrate, mirror or reconcile the legacy `safecontracts_contract_adjustments` table.

Every adjustment line owns a stable server-generated UUID. Every change is an immutable full-snapshot revision with its own server-generated UUID and monotonically increasing per-line revision number. Initial kinds are only `addition` and `discount`. A line is either `active` or terminal `voided`.

Draft corrections append another active revision. Voiding appends a terminal voided revision while preserving the prior kind, description, amount and currency snapshot. A voided line cannot be reactivated; a replacement is a new line identity. There is no in-place mutation or physical deletion path.

The caller never selects currency. The service loads the existing P9-003 Contract financial currency profile, creates P9-001 `Money`, and the repository re-locks/revalidates the profile before persistence. All adjustment amounts are non-negative. P9-005 does not yet define authoritative Contract net-value reconciliation.

## Persistence

Migration0050 advances only the ESC database schema from `1.48.0` to `1.49.0` and creates `safecontracts_contract_financial_adjustment_revisions`.

Each revision stores:
- current tenant id;
- unique revision UUID;
- Contract id;
- P9-003 financial currency profile id;
- stable line UUID;
- per-line revision number;
- adjustment kind (`addition` or `discount`);
- bounded description;
- `DECIMAL(20,4)` amount;
- explicit currency snapshot copied from the locked profile;
- line state (`active` or `voided`);
- actor and UTC creation timestamp.

Unique `(tenant_id, contract_id, line_uuid, revision_number)` prevents duplicate revision numbers. The latest-line index supports bounded current-state reads. No update timestamps exist because revisions are immutable.

## Mutation protocol

All mutation paths use a consistent lock order to reduce deadlock risk:

1. lock the exact current-tenant Contract;
2. require unarchived `draft` state;
3. lock the exact current-tenant P9-003 financial currency profile;
4. for revise/void, lock the latest exact line revision;
5. revalidate profile identity/currency and line state;
6. append one guarded revision or return the existing revision for an idempotent retry;
7. commit, otherwise roll back.

Creation additionally counts stable line identities under the locked Contract and fails at 200. It then proves the generated line UUID is unused before revision 1 is inserted. Contract locking serializes competing line creation for the same Contract.

The final insert is `INSERT ... SELECT` from the locked Contract/profile relationship and repeats the tenant, draft/archive, profile-id and profile-currency predicates immediately before persistence.

## Current-line reads

Current state is derived, never stored as a mutable pointer. The repository selects only rows for which no newer revision of the same tenant/Contract/line exists. It requests at most 201 rows: 200 supported lines plus one overflow sentinel. A 201st current line fails closed instead of silently truncating.

Every returned latest row is normalized and validated against the current-tenant P9-003 profile. Orphaned profile links, profile-id drift, currency drift, invalid UUID/kind/state/revision data, negative stored amounts, duplicate latest line identities or overflow all fail closed.

## Full Impact Review

### Business/domain model — affected

Adds revisioned draft Contract additions and discounts. It intentionally does not yet calculate net Contract value or introduce post-draft variations.

### Tenant model/isolation — affected

All Finance repository access requires core tenant enforcement and the locked `TenantContextStore` tenant. All SQL is tenant-scoped. Contract/line ids are identifiers only, never authorization.

### Database/migrations/indexes — affected

One additive Migration0050. Historical `1.48.0 => Migration0049EnterpriseContractFinancialBaseValueRevisions` remains unchanged. No legacy table rewrite, backfill, ALTER or DROP.

### Backend business logic — affected

Adds `ContractFinancialAdjustmentPolicy`, `ContractFinancialAdjustmentRevisionRepository` and `ContractFinancialAdjustmentRevisionService`.

### Authorization/scopes/roles — affected

Reads require `ACCESS`; create/revise/void require `EDIT_CONTRACTS`. `TenantAuthorization::allowsCapability()` remains a tenant-role narrowing ceiling. Existing Contract `VIEW_ALL` / own `VIEW_ASSIGNED` scope is preserved.

### REST/API compatibility — N/A

No route or payload is introduced.

### WordPress/admin UI — N/A

No admin surface is introduced.

### Flutter/mobile/offline state — N/A

No mobile surface or client financial calculation is introduced.

### Android identity/build environments — N/A

No Android, Firebase, signing or artifact identity change.

### Landing/public feature catalog — N/A

No public availability claim.

### Design system/theme — N/A

No presentation surface.

### Feature registry/plans/entitlements — N/A

No commercial entitlement exposed.

### Search/filter/sort/bulk actions — reviewed

Only a bounded current-line read exists. Generic search/bulk capability remains later work.

### Reports/import/export — N/A

No financial aggregation/report/import/export execution is introduced.

### Notifications/escalation — N/A

No timers or delivery behavior.

### Audit/compliance — reviewed

Full immutable line revisions store actor/time and preserve prior snapshots. Generic cross-domain audit integration remains later phase work and existing audit behavior is not weakened.

### Documents/storage — N/A

No binary or document storage.

### Localization/RTL/LTR/timezone/currency — affected only for currency identity

Amounts use canonical fixed-scale P9-001 `Money`. Currency comes only from P9-003. No locale numeric parsing, FX, conversion, revaluation or timezone calculation is introduced. Creation timestamps use UTC.

### Security/privacy/rate limits — reviewed

No public endpoint is added. Caller tenant/currency selectors are absent. Input kind/state/UUID/description and persisted rows are allowlisted and bounded.

### Performance/concurrency/idempotency — affected

Maximum 200 stable line identities per Contract. Latest current read uses a 201st sentinel. Mutations serialize through the Contract and use consistent Contract → Profile → latest-line locking. Exact unchanged active revisions and repeated voids are idempotent.

### Automated tests — affected

`tests/php/enterprise_contract_financial_adjustments_p9_005.php` covers migration, policy bounds, tenant/profile current reads, overflow, create/revise/void behavior, idempotency, terminal void semantics, lock ordering, draft enforcement, negative/currency failures, service authorization and legacy isolation. It is wired into `scripts/test-php.sh` and a focused P9-005 workflow.

### Documentation/demo/onboarding — affected

This document is the P9-005 architecture/impact record. No UI onboarding is exposed yet.

### CI/build/release/rollback — affected

Focused CI validates PHP syntax and the executable P9-005 regression. The global ESC Foundation Gate remains the full backend/mobile-isolation authority. After Migration0050 is deployed, rollback retains the additive history table rather than destructively dropping financial evidence.

### Backward compatibility — reviewed

Legacy `safecontracts_contract_adjustments`, `ContractService::addAdjustment`, `ContractMoney`, `ContractRepository::financialTotals`, legacy base value, payments and General Settings currency semantics remain unchanged and unsynchronized.

## Non-goals

P9-005 does not implement net-value reconciliation, variations/change orders, taxes/VAT, retention, penalties, credits, invoices, payment schedules, collections, exchange rates, conversion/revaluation, reports, REST/UI/mobile, imports/exports, notifications or production artifacts.
