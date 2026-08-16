# ESC-P5-002 — Contract Dynamic Field Values

## Purpose
P5-002 adds tenant-owned typed Dynamic Field values to ESC contracts. Values use P5-001 definitions and require the contract's P4 Contract Type binding. This remains an internal backend foundation: no formula engine, conditional visibility, REST/admin/Flutter surface or lifecycle-transition enforcement is introduced.

## Domain boundary
- A contract must have a P4 configuration binding with a Contract Type before Dynamic Field values can be stored.
- A definition must be active and belong to that same bound Contract Type at write/clear time.
- Values are canonicalized by immutable definition `data_type` plus current declarative options/validation metadata.
- Writes and clears are allowed only while the tenant-owned contract remains an unarchived `draft`.
- Historical set values remain readable after the contract leaves draft.
- Clear is non-destructive: the current value row remains with `is_set = 0` and `value_json = NULL`.
- Required-field completeness is a read-only query in this task; lifecycle transition blocking is deferred.

## Typed value semantics
- `text` / `long_text`: string values with hard size bounds plus configured min/max length.
- `integer`: canonical PHP integer within supported range and configured min/max.
- `decimal`: canonical plain-decimal string; binary floating-point representation is not persisted.
- `boolean`: strict JSON boolean.
- `date`: real `YYYY-MM-DD` calendar date with configured boundaries.
- `datetime`: ISO-8601 input with timezone, canonicalized to UTC seconds (`...Z`) with configured boundaries.
- `select`: strict type-and-value match against configured option values.
- `multi_select`: ordered unique list of strict configured option values with configured min/max item counts.

## Full Impact Review

### Business requirement / domain model
Implemented as one current value row per tenant+contract+definition. Definitions remain reference configuration. No formulas, conditional visibility, template field-set override, derived value or lifecycle-transition hook is included.

### Tenant model / isolation
Contract, P4 binding, definition and values are all resolved through the locked tenant context. Object IDs alone never authorize access. There is no unscoped fallback.

### Database / migrations / indexes
Schema `1.29.0` adds `safecontracts_custom_field_values` only. Unique `(tenant_id, contract_id, definition_id)` enforces one current row. Tenant-first contract/set and definition/set indexes support current-value and completeness access. No existing contract, binding or definition table is altered.

### Backend business logic
The service preserves existing contract data scope, validates draft/archive state, requires the P4 binding and same-Type active definition, canonicalizes typed values, provides exact-value/config idempotency, non-destructive clear, historical reads and missing-required lookup.

### Atomic persistence / concurrency
Service-level validation is not trusted as the final authority. `saveValue` uses tenant-scoped `INSERT … SELECT` joins to atomically revalidate that:
- the contract is still an unarchived draft;
- the P4 binding still exists for that contract;
- the definition is still active and still belongs to the binding's Contract Type;
- immutable `field_code`/`data_type` and the exact options/validation JSON used for value validation still match at write time.
A zero-row write fails closed. Clear uses an atomic EXISTS guard with the same contract/binding/definition/config prerequisites; concurrent already-cleared state is treated idempotently.

### Definition configuration history
Each set/cleared row stores `data_type_snapshot` and a SHA-256 `definition_config_hash` over the bound type/code/data-type/options/validation configuration. Historical reads do not require the definition to remain active. P5-002 does not rewrite existing values when a definition's editable configuration later changes; future edits validate against the then-current configuration and persist its new hash.

### Authorization / data scope
Reads require `ACCESS`; mutations require `EDIT_CONTRACTS`, both narrowed by tenant-role authorization. Contract visibility remains `VIEW_ALL`, or `VIEW_ASSIGNED` for the current assigned accountant where applicable.

### API compatibility
No REST endpoint or existing API contract changes are introduced.

### WordPress/admin UI
N/A in P5-002.

### Flutter/mobile/offline state
N/A in P5-002. Existing Flutter format/analyze/test remain mandatory in CI.

### Android identity / release isolation
No identity change. Existing ESC Android and artifact-isolation checks remain mandatory.

### Landing / public messaging / feature registry
No Public feature claim is made. Dynamic Field values remain internal foundation work pending later product-surface and feature-registry tasks.

### Search / filtering / reports / import / export
Only bounded current-value listing and missing-required lookup are included. General typed filtering/index extraction/reporting/import/export are deferred.

### Notifications / escalation
N/A.

### Audit / compliance
Value rows retain created/updated actors and timestamps; set/clear domain hooks are emitted. Dedicated immutable value-history/audit event integration remains part of later audit work.

### Documents / storage
N/A.

### Localization / timezone / currency
Datetime input requires timezone and is stored as canonical UTC. Date values are timezone-free calendar dates. Decimal is generic and not a currency/financial engine. Localized field labels remain definition/UI work.

### Security / privacy
No executable expressions are evaluated. Stored values are JSON data. Select matching is strict by scalar type and value. Invalid JSON, invalid calendar dates, non-finite numbers, duplicate multi-select items and out-of-range values fail closed. Output escaping remains the responsibility of later presentation surfaces.

### Performance
Lists and required completeness are bounded. The value table has tenant-first indexes. Large text values have hard limits. Formula/report query extraction is intentionally deferred rather than overloading this foundation table prematurely.

### Automated tests
`tests/php/enterprise_custom_field_values_p5_002.php` covers schema registration, all nine typed canonicalizers, boundaries, strict select semantics, multi-select uniqueness/counts, tenant enforcement, P4 binding/type checks, active definition checks, atomic SQL guards, idempotent set/clear, post-draft/archive denial, historical reads, bounded listing, required completeness, data scope and authorization. It is explicitly wired into `scripts/test-php.sh`.

### Release / rollback / backward compatibility
The migration is additive. Existing contracts without Dynamic Field values remain unchanged. No legacy contract columns, P4 binding rows or P5 definition rows are rewritten. No Safe Contract/main merge or backport is created.

## Deferred work
- Formula/calculation engine.
- Conditional visibility engine.
- Template field-set composition/overrides.
- Lifecycle transition blocking based on required completeness.
- Immutable value-history/audit ledger.
- REST/admin/Flutter entry and display surfaces.
- Typed report/filter indexes, import/export and bulk operations.
- Public feature registry/landing claims.
