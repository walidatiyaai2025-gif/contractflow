# ESC-P5-005 — Dynamic Field Presentation and Reporting Metadata Foundation

Issue: #463  
Product: Enterprise Safe Contracts (ESC) only  
Branch: `enterprise-safecontracts`

## Purpose

Add tenant-owned declarative presentation/visibility and reporting metadata to P5 Dynamic Field definitions without introducing executable conditional rules, formulas, report-query execution, export execution, UI behavior, lifecycle changes or Safe Contract coupling.

P5-005 is reference configuration only. It describes where a field is eligible to appear and how later reporting surfaces may classify it; it does not itself render a form, execute a filter, aggregate values, query a report, export data or evaluate a visibility expression.

## Delivered schema

Schema version `1.31.0` adds the additive tenant-owned table:

`safecontracts_custom_field_metadata`

One row may exist per `(tenant_id, definition_id)` and records:

- immutable `data_type_snapshot`;
- `show_in_form`;
- `show_in_summary`;
- `show_in_mobile`;
- `show_in_print`;
- `filterable`;
- `sortable`;
- `groupable`;
- `exportable`;
- `dashboard_visible`;
- optional bounded `report_label`;
- allowlisted `report_data_class`;
- allowlisted `aggregation_policy`;
- actor and timestamp fields.

The migration is additive and does not alter contracts, P5-002 values or P5-004 Template Version field snapshots.

## Deterministic safe defaults

A missing metadata row is valid and resolves in `CustomFieldMetadataService` to deterministic defaults rather than an ambiguous/null policy:

- `show_in_form = true`;
- `show_in_mobile = true`;
- `show_in_summary = false`;
- `show_in_print = false`;
- `filterable = false`;
- `sortable = false`;
- `groupable = false`;
- `exportable = false`;
- `dashboard_visible = false`;
- empty report label;
- type-derived report data class;
- `aggregation_policy = none`.

This keeps core data-entry visibility usable while all secondary/reporting capabilities remain closed unless explicitly enabled.

Writing an input that resolves exactly to the defaults when no row exists is storage-free and idempotent; the service does not create a redundant override row.

## Type-aware reporting policy

`CustomFieldMetadataPolicy` uses bounded allowlists only.

Supported report data classes:

- `dimension`
- `measure`
- `date`
- `identifier`
- `text`

Supported aggregation metadata:

- `none`
- `sum`
- `avg`
- `min`
- `max`

Compatibility is enforced against immutable P5-001 `data_type`:

- `measure` is numeric (`integer` / `decimal`) only;
- `date` is `date` / `datetime` only;
- `identifier` is limited to text/integer/select identities;
- `text` is limited to textual/select/multi-select/boolean representations;
- `dimension` is limited to bounded categorical/identity/date-compatible types;
- non-`none` aggregation requires both a numeric field and `measure` classification;
- long text and multi-select cannot claim sortable eligibility;
- long text, multi-select and decimal cannot claim groupable eligibility under this foundation policy.

The metadata says what later reporting code is allowed to offer; it does not perform the operation.

## Static visibility boundary

The `show_in_*` flags are static presentation eligibility only.

P5-005 deliberately does **not** implement:

- field-to-field conditions;
- value-dependent visibility;
- expression evaluation;
- formulas;
- JavaScript/PHP/SQL snippets;
- workflow predicates;
- regex execution.

Conditional visibility remains a separately bounded P5 task so it can be designed with explicit typed operators, dependency safety and deterministic evaluation rather than smuggling executable logic into metadata.

## Tenant / authorization impact

- Core tenant enforcement is mandatory.
- Tenant context must be locked through `TenantContextStore`.
- Definition lookup is tenant-scoped.
- Metadata lookup is tenant + definition scoped.
- Reads require `ACCESS`.
- Mutations require `MANAGE_REFERENCE_DATA` plus tenant-role narrowing.
- Object IDs are identifiers, never authorization.
- There is no unscoped repository fallback.

Inactive definitions remain readable with existing metadata for administrative/history inspection, but metadata mutation requires an active current-tenant definition.

## Atomic mutation / concurrency impact

The service validates the active definition and normalizes metadata, but persistence does not trust that earlier read alone.

`CustomFieldMetadataRepository::upsert()` uses `INSERT ... SELECT` from the current Dynamic Field definition and revalidates at write time:

- exact definition ID;
- locked tenant ownership;
- `status = active`;
- exact immutable `data_type`.

The write then uses an atomic duplicate-key update for the metadata row.

If the definition is concurrently deactivated or no longer matches the validated data type, the insert-select affects zero rows and the repository fails closed.

`reset()` similarly deletes only through a tenant + definition + active-definition + exact-data-type join.

The service avoids exact-value writes before reaching the repository:

- absent + exact defaults => no row/no write;
- existing + exact same metadata => no write;
- reset when absent => no write.

## Stored type snapshot

Every persisted metadata row stores `data_type_snapshot`.

On read, if the stored snapshot differs from the current definition type, the service fails closed rather than silently interpreting reporting metadata under another type contract.

P5-001 makes data type immutable after definition creation, so a mismatch represents inconsistent/corrupt state rather than a supported migration path.

## P5-002 runtime-value impact

P5-005 never writes `safecontracts_custom_field_values` and does not change value validation, canonicalization, configuration hashes, draft-only mutation rules or historical reads.

Presentation/reporting metadata is not part of a P5-002 value's validation configuration hash because it does not change the semantic validity of the stored field value.

## P5-004 Template snapshot impact

P5-005 does not rewrite `safecontracts_contract_template_version_fields` and does not retroactively change a published Template Version snapshot.

Important historical-consumption boundary:

- P5-004 snapshots remain authoritative for the historical field identity/configuration they already store.
- P5-005 is live definition-level presentation/reporting configuration.
- A future renderer/report consumer must **not** reinterpret a published Template Version historically by silently substituting later live P5-005 metadata.
- If Template-Version-specific presentation/reporting metadata must be historical, that metadata must be snapshotted explicitly in a separately bounded task/versioned contract rather than being implicitly inherited from the live definition.

This task therefore adds no hidden drift path to existing published Template snapshots.

## Database / migration impact

Migration `Migration0032EnterpriseCustomFieldMetadata` is registered at `1.31.0`.

The P5-004 regression was made forward-compatible: it continues to require schema `1.30.0` and its migration registration without incorrectly asserting that `1.30.0` must remain the latest schema forever.

No legacy schema alteration or backfill is introduced.

## API / admin / mobile impact

No REST route, WordPress admin UI, Flutter screen, mobile/offline state or public endpoint is added.

The service remains an internal enterprise-domain capability for later UI/reporting integration.

Flutter format/analyze/test remains part of the mandatory ESC Gate and stayed green.

## Android / release-isolation impact

No Android package name, namespace, icon, Firebase app/project, FCM namespace, signing identity, artifact path or environment configuration changes are introduced.

ESC Android identity and verified-artifact isolation Gates remain mandatory and green.

## Reporting / export impact

P5-005 does not implement a report engine.

Specifically:

- no dynamic report SQL is generated;
- no filter query is executed;
- no sort/group query is executed;
- no aggregate is calculated;
- no dashboard dataset is built;
- no export file is generated.

The domain service does not directly access `$wpdb` or invoke database query execution; persistence is delegated to the tenant-safe metadata repository. `exportable`, `filterable`, `sortable`, `groupable` and `dashboard_visible` are policy metadata only.

Actual reporting execution remains P12 scope and must independently enforce tenant/data-scope, query bounds and metadata eligibility.

## Security / privacy impact

- Metadata properties are explicitly allowlisted.
- Boolean flags require actual booleans.
- Report label is stripped/trimmed and bounded to 191 bytes.
- Report classes and aggregation policies are bounded enums.
- Type compatibility fails closed.
- Unknown properties such as `expression` fail closed.
- No `eval`, `exec`, scripting, SQL fragments or expression language is introduced.
- No new public mutation endpoint or CSRF surface is introduced.
- Tenant authorization remains server-side.

## Audit / compliance impact

Metadata rows retain created/updated actors and timestamps.

Successful updates/resets emit internal domain actions for later audit/integration consumers. No separate audit event stream is created in this bounded task.

The explicit/default distinction makes later compliance evidence clearer: absence of a row has a deterministic meaning rather than an implementation-dependent fallback.

## Localization / RTL / design-system impact

No UI is introduced, so there is no immediate RTL/LTR or theme implementation.

`report_label` is Unicode-capable stored text and should be localized/presented by future UI according to ESC localization policy. This foundation does not treat it as an executable template or HTML surface.

## Feature registry / landing / plans impact

P5-005 remains an internal Development capability.

It is not marked Public, is not added to the landing page, and does not modify plan entitlement or subscription behavior. Later P13/P16 work may expose the capability after an actual supported user surface exists.

## Automated regression coverage

`tests/php/enterprise_custom_field_metadata_p5_005.php` is explicitly wired into `scripts/test-php.sh` after P5-004 regressions.

The final implementation regression passes **75 assertions** covering:

- additive schema/version/unique identity;
- deterministic conservative defaults;
- bounded report-class and aggregation allowlists;
- type-derived default classes;
- normalized numeric measure metadata;
- incompatible report-class rejection;
- incompatible aggregation rejection;
- sort/group restrictions;
- strict boolean flags;
- bounded report label;
- unknown/expression property rejection;
- Enterprise enforcement and locked tenant context;
- tenant-scoped definition and metadata reads;
- absent-default get without writes;
- absent exact-default upsert idempotency;
- non-default atomic upsert;
- active tenant definition + exact data-type write guard;
- duplicate-key atomic persistence;
- stored typed hydration;
- exact existing-metadata no-op;
- foreign definition rejection;
- inactive definition mutation rejection;
- numeric measure aggregation metadata;
- guarded reset and reset idempotency;
- global and tenant-role capability denial;
- no contract/value/P5-004 migration mutation;
- no direct database/report query execution in the service;
- no executable policy engine.

## CI findings and correction

The first correctly wired P5-005 run, Gate #336, reached the P5-005 regression after all earlier P4/P5 regressions had passed. Runtime/domain assertions passed; the final source check was brittle because it rejected the substring `export`, which naturally appears in the declarative field name `exportable`.

That assertion was replaced with an architecture-level check that the service contains neither direct `$wpdb` access nor direct query execution. Domain rules were not weakened.

Final implementation Gate #337 passed on head `5f46ed42b254531ac9170ffd83fdf53449e2b910` with P5-005 **75/75 assertions**, all earlier backend/tenancy regressions, ESC Android/artifact isolation, and Flutter format/analyze/test green.

## Full Impact Review checklist

- Business/domain requirement: implemented — static presentation + reporting metadata foundation.
- Tenant model/isolation: reviewed and enforced in reads/writes.
- Database/migrations/indexes: additive schema `1.31.0`, tenant-first unique/indexes.
- Backend business logic: implemented with deterministic defaults and type-aware policy.
- Authorization/scopes/roles: ACCESS reads; MANAGE_REFERENCE_DATA mutations with tenant-role ceiling.
- REST/API compatibility: N/A; no route added.
- WordPress/admin UI: N/A in this task.
- Flutter/mobile UI/offline: N/A; Gate remains green.
- Android identity/build environments: unchanged; isolation Gate green.
- Landing/public messaging: no public claim.
- Design system/theme: no UI surface yet.
- Feature registry/plans: remains internal Development capability.
- Search/filter/sort/bulk: metadata eligibility only; no execution introduced.
- Reports/import/export: metadata only; no report/export execution introduced.
- Notifications/escalation: N/A.
- Audit/compliance: actors/timestamps + internal domain actions; no new audit stream.
- Documents/storage: N/A.
- Localization/RTL/timezone/currency: no UI; report label stored as bounded Unicode text.
- Security/privacy/rate limits: no new external surface; bounded allowlists and fail-closed type/tenant guards.
- Performance/concurrency/idempotency: one row per field; exact no-op suppression; atomic active-definition/type guards.
- Automated tests: P5-005 regression explicitly wired, 75 assertions green.
- Documentation/demo/onboarding: this Full Impact Review added; no public/demo exposure.
- CI/build/release/rollback: implementation Gate #337 green; additive table can be removed before production adoption without legacy-data rollback.
- Backward compatibility: Safe Contract/main, existing contracts, P5-002 values and P5-004 snapshots unchanged.

## Explicit non-goals / follow-up boundary

P5-005 does not implement conditional visibility, calculation/formula execution, report/filter/sort/group/aggregate execution, exports, REST/admin/Flutter UI, lifecycle blocking or public marketing.
