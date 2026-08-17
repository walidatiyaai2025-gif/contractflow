# ESC-P9-002 — Tenant Financial Base Currency

Issue: #492

## Decision

The Enterprise tenant registry already owns `default_currency CHAR(3) NOT NULL DEFAULT 'USD'` from the original tenancy foundation. That value is also part of authenticated tenant-directory metadata. P9-002 does not create a second base-currency table or column. It establishes the existing tenant value as the server-side financial base-currency identity used by later P9 services.

This is an Enterprise-only semantic boundary. It does not reinterpret the legacy SafeContracts global `GeneralSettings` currency, and it does not assign currencies to historical legacy contract/payment rows that never persisted per-record currency identity.

`SafeContracts\Finance\TenantBaseCurrencyRepository` accepts an already locked `TenantContext`, requires its tenant id, reads exactly one active tenant row, verifies the returned row identity, and validates/canonicalizes `default_currency` through the P9-001 `CurrencyCode` value object.

There is deliberately no fallback. Missing context, inactive/missing tenant, unexpected multiple rows, row-id mismatch, missing currency or malformed currency all fail closed. The resolver does not use General Settings, WordPress options, an implicit USD, a request parameter or a membership scan as substitute authority.

## Full Impact Review

### Business / domain behavior

Affected only as an internal foundation. Later Enterprise financial objects now have one explicit tenant base-currency source. P9-002 performs no accounting, pricing, payment, conversion or financial mutation.

### Tenant model and isolation

Affected. No new tenant storage is introduced. The existing `safecontracts_tenants.default_currency` value is reused.

The Finance resolver does not accept a raw tenant id. It requires `TenantContext::requireTenantId()`, so the caller must arrive through an already-authorized tenant-selection/service boundary. SQL then constrains the lookup to that exact id plus `status = 'active'` and `LIMIT 1`. The returned row id is checked against the locked context even though the SQL already scopes it. Missing context cannot auto-select a tenant.

The repository itself is not an authorization endpoint. It trusts only the locked context as its tenant-selection input; capabilities and membership authorization remain responsibilities of the boundary that establishes that context.

### Database / migrations / indexes

No change. `Migrator::LATEST_VERSION` remains `1.46.0`; Migration0048 remains unused. The existing tenant primary key and row status are sufficient for the exact single-row lookup. No new index or backfill is needed.

No tenant currency value is rewritten, uppercased or otherwise migrated. Canonicalization occurs only in the returned immutable `CurrencyCode`.

### Backend / source of truth

Affected. The WordPress plugin remains authoritative. `TenantBaseCurrencyRepository` is server-side under `SafeContracts\Finance` and depends on the P9-001 `CurrencyCode` invariant.

### Authorization / roles / capabilities

No new capability or public operation is introduced. The resolver requires a locked tenant context but performs no user-facing authorization itself. A later mutation/read service must keep its own capability and tenant-role checks.

### REST API

No change. No route, field, response contract or mutation is added. Existing `/tenants` and `/me` continue to expose their existing tenant metadata, including `default_currency`, through `TenantDirectoryRepository`.

### Admin UI

No change. There is no currency editor in P9-002.

### Flutter / mobile

No change. No DTO, local calculation, selector or screen is added. Flutter remains an API client and gains no independent financial authority.

### Android identity / build / distribution

No change.

### Landing page / public claims

No change. P9-002 is not a released multi-currency feature by itself.

### Design system

N/A. No visible surface is introduced.

### Feature registry / plans / entitlements

No change. An internal repository is not a customer entitlement.

### Search / reports / import / export

No change. Existing reporting and imports remain on their current legacy semantics. No base-currency aggregation or conversion is added.

### Notifications

N/A. Resolution emits no message and schedules no work.

### Audit

No mutation occurs, so there is no business-state audit event in this task. Future tenant-currency mutation must be separately authorized and audited.

### Documents / storage

No change.

### Localization / timezone / currency

Currency identity is affected. The stored tenant code is parsed only as a canonical three-letter `CurrencyCode`; symbols, locale decimal formats and display formatting are outside this repository. Locale/timezone are untouched.

### Security

Fail-closed controls include:

- tenant context is mandatory;
- no raw/request tenant id is accepted by the Finance repository;
- only the exact active tenant row is queried;
- one-row cardinality is enforced defensively;
- returned row identity must equal the locked context;
- malformed/missing stored currency is rejected by P9-001 validation;
- no General Settings/options/default-USD fallback exists;
- no cross-tenant scan or membership-derived tenant inference exists;
- no write/insert/update/delete path exists;
- no network, FX provider or hidden clock dependency exists.

### Performance

One primary-key tenant lookup with `LIMIT 1`; bounded constant-size result and three-character currency validation. No unbounded iteration or fan-out.

### Concurrency / idempotency

Read-only resolution is naturally idempotent for a stable database snapshot. P9-002 creates no race-prone mutation or lock requirement. A future tenant-currency mutation task must define concurrency semantics independently.

### Tests

`tests/php/enterprise_tenant_base_currency_p9_002.php` covers schema non-change, missing context, exact active-tenant SQL, canonicalization, missing/inactive/mismatched/multi-row failures, malformed stored currency, absence of legacy/default/FX fallbacks, read-only source constraints, unchanged tenant REST wiring and legacy currency separation.

The existing P9-001 Money regression and legacy dashboard currency regression remain independent global-gate checks.

### Documentation / onboarding

This document records the semantic reuse of existing tenant metadata so future P9 work does not introduce duplicate base-currency storage or infer legacy row currencies.

### CI

`.github/workflows/esc-p9-002.yml` validates PHP syntax and the focused adversarial regression on pull requests and integration-branch pushes. `scripts/test-php.sh` also invokes the P9-002 regression, making it part of the normal ESC backend gate.

### Release / rollback

There is no migration or data rewrite. Rollback is code-only: revert the Finance repository, regression, CI workflow, gate invocation and this documentation together. Existing tenant metadata remains intact.

### Backward compatibility

Legacy SafeContracts currency behavior is unchanged. Existing tenant REST metadata is unchanged. No existing tenant row, financial row, report, admin screen or mobile surface is altered.

## Deferred P9 work

P9 still needs separately reviewed persistence and service boundaries for contract financial currency, reporting currency if required, exchange-rate quotes, explicit conversion/revaluation, ledger/accounting records, tax/invoice/payment allocation and all UI/API/reporting surfaces.

Any future cross-currency operation must preserve P9-001's explicit conversion boundary and may use this tenant base currency only as an identified currency, never as permission to convert implicitly.
