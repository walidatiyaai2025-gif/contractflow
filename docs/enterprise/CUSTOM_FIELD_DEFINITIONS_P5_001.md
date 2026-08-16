# ESC-P5-001 — Dynamic Field Definition Foundation

## Purpose
P5-001 introduces tenant-owned declarative Dynamic/Custom Field definitions for ESC Contract Types. It defines field schema/configuration only; it does not store values on contracts and does not execute formulas or conditional logic.

## Domain boundary
- Every definition belongs to one Contract Type in the locked tenant.
- `field_code` and `data_type` are stable/immutable after creation.
- Display metadata and declarative configuration may be edited only while the owning Contract Type remains active.
- Deactivation is non-destructive and preserves historical configuration.
- Initial data types: text, long_text, integer, decimal, boolean, date, datetime, select and multi_select.
- Select/multi-select options are declarative bounded JSON only.
- Validation metadata is allowlisted by data type; no regex/formula/code execution is included.

## Full Impact Review

### Business requirement / domain model
Implemented as a Contract-Type-owned field-definition catalog. Contract values, template overrides/field sets, formulas, conditional visibility and runtime required-field enforcement are deferred.

### Tenant model / isolation
Every definition has mandatory `tenant_id`. Definition and Contract Type reads are tenant-scoped through locked `TenantContextStore`; no unscoped fallback exists.

### Database / migrations / indexes
Schema `1.28.0` adds `safecontracts_custom_field_definitions` with unique tenant+Contract-Type+field-code identity and tenant-first list/status indexes. No existing contract, template or P4 binding table is altered.

### Backend business logic
The service validates immutable machine identity, supported data type, required/display metadata, select options and type-aware validation metadata. Creation and configuration updates require an active current-tenant Contract Type. Persistence rechecks that active Contract Type at write time to close concurrent deactivation races.

### Authorization
Reads require `ACCESS`; mutations require `MANAGE_REFERENCE_DATA`, both narrowed by tenant-role authorization.

### API compatibility
No REST endpoint or existing API payload changes are included.

### WordPress/admin UI
N/A in P5-001.

### Flutter/mobile/offline state
N/A in P5-001; existing mobile CI remains mandatory.

### Android identity / release isolation
No identity change. Existing ESC Android and release-artifact isolation checks remain mandatory.

### Landing/public messaging / feature registry
No Public feature claim is made. Dynamic fields remain internal foundation work until later feature-registry and product-surface tasks.

### Design system / theme
N/A; no UI surface is introduced.

### Search/filter/report/import/export
The definition catalog has bounded tenant/type/status search. Runtime contract-value filtering/reporting/import/export is deferred until value storage and query semantics exist.

### Notifications / escalation
N/A.

### Audit / compliance
Created/updated actors and timestamps are retained; domain hooks are emitted for create/update/deactivate. Dedicated ESC audit integration is deferred to the audit roadmap.

### Documents/storage
N/A.

### Localization/timezone/currency
Labels/help text are stored as configuration strings; localized field-label catalogs are deferred. Date/datetime validation metadata uses explicit ISO-style boundaries. Financial/currency semantics are not introduced here.

### Security / privacy
Definitions are data, not code. Objects/resources/non-finite values are rejected from option configuration. Validation keys are allowlisted by type. Regex, PHP/JS/SQL expressions and formula evaluation are intentionally absent. Cross-tenant Contract Type/definition IDs fail closed.

### Performance / concurrency / idempotency
Indexes support tenant/type/status ordered listing. Option count and encoded payload sizes are bounded. Create/update persistence atomically rechecks active Contract Type ownership so concurrent deactivation cannot race service validation. Stable code/type are immutable to protect future stored-value compatibility.

### Automated tests
`tests/php/enterprise_custom_field_definitions_p5_001.php` covers schema/migration registration, code/type policies, options, duplicate-value rejection, declarative validation allowlists/ranges, tenant/Contract-Type boundaries, immutable fields, bounded search, authorization, non-destructive deactivation and absence of legacy/P4 mutations. It is explicitly wired into `scripts/test-php.sh`.

### Release / rollback / backward compatibility
The migration is additive. Existing Safe Contract and ESC contracts without Dynamic Field definitions continue unchanged. Rolling back application usage can leave the new table unused without altering legacy contract behavior. No Safe Contract/main merge or backport is created.

## Deferred work
- P5-002 contract field values/materialization.
- Template field-set composition/overrides.
- Formula/calculation engine.
- Conditional visibility engine.
- REST/admin/Flutter authoring and entry surfaces.
- Runtime filtering/reporting/import/export.
- Public feature registry/landing claims.
