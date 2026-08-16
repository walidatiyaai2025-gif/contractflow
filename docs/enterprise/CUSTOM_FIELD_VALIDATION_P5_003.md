# ESC-P5-003 — Contract Dynamic Field Validation / Readiness Engine

Issue: #461  
Product: Enterprise Safe Contracts (ESC) only  
Branch: `enterprise-safecontracts`

## Purpose

Provide a deterministic, tenant-safe, read-only readiness check for Dynamic Field data before later lifecycle/workflow work consumes it. The engine evaluates the current P4 Contract Type binding, P5-001 Dynamic Field definitions and P5-002 stored values without mutating contracts, definitions, values, lifecycle state, or Safe Contract.

## Delivered behavior

`CustomFieldValidationService::validateContract()` returns a bounded readiness result containing:

- `ready`
- `error_count`
- `warning_count`
- `definition_count`
- `set_value_count`
- deterministic issue records

Issue codes currently emitted:

- `missing_required` — error when an active required definition has no set value.
- `stale_configuration` — warning when a stored definition configuration hash differs from the current definition configuration.
- `invalid_value` — error when stored JSON is invalid or no longer validates under current type/options/rules.
- `noncanonical_value` — warning when a value still validates but its stored JSON is not the current canonical representation.
- `orphan_value` — error for a missing or other-Contract-Type definition; warning for a preserved value belonging to an inactive historical definition.
- `type_snapshot_mismatch` — error when the stored P5-002 data type snapshot differs from the current definition data type.
- `validation_limit_exceeded` — error when the bounded readiness scan detects more than 500 active definitions or set values.

Warnings remain visible but do not independently make a contract unready. Any error makes `ready=false`.

## Tenant and authorization impact

- Core tenant enforcement is mandatory.
- The current tenant ID must be locked in `TenantContextStore`; there is no unscoped repository fallback.
- Contract lookup is tenant-scoped.
- P4 configuration binding lookup is tenant-scoped.
- Active definition scans are tenant + bound Contract Type scoped.
- Stored value scans are tenant + contract scoped and LEFT JOIN current tenant definitions so missing historical definitions remain observable rather than disappearing.
- Reads require `ACCESS` and preserve the existing contract data-scope model: `VIEW_ALL` or the current user's own assignment through `VIEW_ASSIGNED`.
- Object IDs are never authorization.

## Database / migration impact

No migration is introduced by P5-003.

The engine reads existing tables only:

- `safecontracts_contracts`
- `safecontracts_contract_configuration_bindings`
- `safecontracts_custom_field_definitions`
- `safecontracts_custom_field_values`

No table, column, index, legacy contract record, P4 binding, P5 definition or P5 value is written by readiness validation.

## Performance and bounded-read impact

Readiness processing is intentionally bounded to 500 definitions and 500 set values per contract evaluation.

The repository allows an internal maximum of 501 returned rows. Row 501 is a sentinel used only to detect overflow. The service then:

1. Emits `validation_limit_exceeded` as an error.
2. Processes/reports at most the first 500 rows.
3. Never silently reports `ready=true` after truncating an oversized field set.

This closes the false-ready failure mode that would occur if repository queries were hard-clamped to exactly 500 before overflow detection.

## Concurrency / configuration-change impact

P5-003 is read-only and does not attempt to lock configuration. Instead it deliberately exposes drift:

- P5-002 values retain their definition configuration hash and data type snapshot.
- The readiness engine compares those snapshots with the current definition.
- Configuration drift remains observable as `stale_configuration` even when the stored value still validates.
- Current validation is re-run against the current definition/options/rules.
- No automatic re-hash, rewrite, deletion or repair is performed.

This keeps later P6 lifecycle/workflow decisions explicit rather than silently normalizing historical runtime data.

## Backward compatibility

- Existing Safe Contract behavior is unchanged.
- `main` is not modified.
- Existing legacy contract columns and `ContractStatus` behavior are unchanged.
- P4 configuration bindings are not changed.
- P5-001 definitions are not changed by validation.
- P5-002 values remain historical/runtime data and are not rewritten.
- No lifecycle transition is blocked by P5-003 itself; P6/later work may consume the readiness result explicitly.

## API / admin / mobile impact

No new REST route, WordPress admin surface, Flutter screen, offline state, Android identity, Firebase configuration or public landing-page claim is introduced in P5-003.

The validation service is an internal enterprise domain capability intended for later bounded integration.

## Security / privacy impact

- No executable formulas, expressions, SQL, PHP, JavaScript, templates or regex execution is introduced.
- Invalid stored JSON fails closed as a readiness error.
- Foreign contracts and missing P4 bindings fail closed.
- Cross-tenant IDs cannot bypass tenant-scoped repository reads.
- The engine performs no mutation and therefore does not create a new write/CSRF surface.
- Error output identifies field definition IDs/codes needed for remediation but does not intentionally expose data from another tenant.

## Audit / notification / documents / reporting impact

No audit mutation is emitted because validation is read-only. No notification, document, export, import or reporting execution is introduced. Later consumers may decide how to surface readiness results and whether those decisions require audit evidence.

## Localization / design system / landing impact

No UI copy or public feature is exposed by this task. Therefore RTL/LTR, theme tokens, white-label branding and landing-page messaging are not changed. Any future UI must localize issue messages/codes rather than treating the current service text as final presentation copy.

## Automated regression coverage

`tests/php/enterprise_custom_field_validation_p5_003.php` is explicitly wired into `scripts/test-php.sh` after P5-002.

Coverage includes:

- tenant enforcement and locked tenant context;
- valid readiness;
- required-field completeness;
- stale configuration warning;
- invalid JSON/current-value validation;
- noncanonical value warning;
- data type snapshot mismatch;
- missing definition orphan error;
- inactive historical definition warning;
- other-Contract-Type orphan error;
- missing P4 binding;
- foreign contract rejection;
- 501-row definition overflow fail-closed behavior;
- 501-row value overflow fail-closed behavior;
- `VIEW_ALL` / `VIEW_ASSIGNED` contract data scope;
- global and tenant-role authorization;
- zero database mutation path.

Initial wiring validation exposed two integration defects before completion:

1. The gate script first referenced a non-existent `enterprise_custom_field_readiness_p5_003.php` filename instead of the actual `enterprise_custom_field_validation_p5_003.php` regression.
2. Once the real regression ran, it exposed that repository queries still clamped the requested `501` sentinel row to `500`, making overflow detection impossible. The repository now uses internal `MAX_SCAN_ROWS = 501`, while the service processing bound remains 500.

Implementation Gate #312 passed on head `4546feba36a9e697085898435df9908c7e474d5d` with backend/tenancy regression, ESC Android identity/artifact isolation and Flutter format/analyze/test all green.

## Full Impact Review checklist

- Business/domain requirement: reviewed — deterministic read-only contract Dynamic Field readiness delivered.
- Tenant model/isolation: reviewed — locked tenant and tenant-scoped contract/binding/definition/value reads.
- Database/migrations/indexes: reviewed — no schema change; bounded existing-table reads only.
- Backend business logic: implemented.
- Authorization/scopes/roles: reviewed — ACCESS + existing contract data scope.
- REST/API compatibility: N/A in this task; no route added.
- WordPress/admin UI: N/A in this task.
- Flutter/mobile/offline: N/A in this task; gate remains green.
- Android identity/build environments: unchanged; isolation gate remains mandatory.
- Landing/public messaging: no public claim.
- Design system/theme: N/A.
- Feature registry/plans: not promoted/public in this task.
- Search/filter/sort/bulk actions: N/A; readiness issues are deterministically ordered by bounded source scans.
- Reports/import/export: N/A.
- Notifications/escalation: N/A.
- Audit/compliance: read-only; no mutation event introduced.
- Documents/storage: N/A.
- Localization/RTL/timezone/currency: no UI; date/datetime value semantics remain owned by P5-002 validation policy.
- Security/privacy/rate limits: reviewed; no new external/write surface.
- Performance/concurrency/idempotency: bounded 500 processing + 501 sentinel; read-only and deterministic.
- Automated tests: P5-003 regression wired into backend gate.
- Documentation/demo/onboarding: this Full Impact Review added; no public/demo exposure yet.
- CI/build/release/rollback: implementation Gate #312 green; rollback is removal of read-only service/repository/test wiring with no data migration rollback.
- Backward compatibility: Safe Contract/main and existing lifecycle behavior unchanged.

## Explicit non-goals / follow-up boundary

P5-003 does **not** implement formulas, conditional visibility, Template field-set snapshots, automatic value repair/revalidation writes, lifecycle-transition blocking, REST/admin/Flutter UI or public marketing. Those remain separately bounded ESC tasks.
