# ESC-P5-006 — Typed Declarative Dynamic Field Conditional Visibility

Issue: #464  
Product: Enterprise Safe Contracts (ESC) only  
Branch: `enterprise-safecontracts`

## Purpose

Add safe conditional visibility to P5 Dynamic Fields using typed structured rule rows instead of executable expressions.

The foundation supports authoring, deterministic dependency validation and read-only contract-time evaluation while keeping P5-005 static presentation eligibility, P6 workflow conditions and future formula/calculation logic separate.

## Delivered schema

Schema `1.32.0` adds two tenant-owned tables:

- `safecontracts_custom_field_visibility_rules`
- `safecontracts_custom_field_visibility_conditions`

A target Dynamic Field can have at most one rule set per tenant. A rule stores:

- target definition identity;
- Contract Type identity;
- `match_mode` (`all` / `any`);
- target field-code snapshot;
- target data-type snapshot;
- target configuration hash;
- actors/timestamps.

Each ordered condition stores:

- target/rule identity;
- deterministic position;
- source Dynamic Field definition identity;
- allowlisted operator;
- canonical typed operand JSON or NULL;
- source field-code snapshot;
- source data-type snapshot;
- source configuration hash;
- actors/timestamps.

The migration is additive and does not alter contracts, values, Template snapshots or P5-005 metadata.

## Rule semantics

Rules support:

- `match_mode = all`: every condition must match;
- `match_mode = any`: at least one condition must match.

Maximum conditions per rule: 32.

Initial operators are type-aware:

- all P5 field types: `is_set`, `is_not_set`, `eq`, `neq`;
- integer/decimal/date/datetime: `gt`, `gte`, `lt`, `lte`;
- multi-select: `contains`.

There is deliberately no generic string `contains`, regex, script, expression string or arbitrary operator plug-in in this foundation.

## Typed operand handling

Operands are canonicalized through the existing P5 typed value policy.

Examples:

- decimal `010.500` becomes canonical `10.5`;
- date/datetime operands must satisfy existing real-date/time parsing and bounds;
- select equality remains scalar-type strict;
- multi-select `contains` accepts one typed option scalar and validates it against the configured option set.

`is_set` / `is_not_set` persist no operand.

Missing or cleared source values have explicit semantics:

- `is_set` => false;
- `is_not_set` => true;
- every value comparison, including `neq`, => false.

This avoids treating absence as an arbitrary inequality match.

## Decimal / ordered comparisons

Decimal conditions compare canonical decimal strings without binary-float conversion.

Date and canonical UTC datetime values use deterministic canonical ordering. Integer comparisons remain integer comparisons.

## Tenant and Contract Type boundaries

Rule authoring requires:

- core tenant enforcement;
- locked tenant context;
- active target definition in the current tenant;
- every source definition active in the current tenant;
- every source belonging to the same Contract Type as the target;
- positive definition identities;
- no target self-reference.

Reads require `ACCESS`.

Mutations require `MANAGE_REFERENCE_DATA` plus the established tenant-role capability ceiling.

Object IDs are never authorization.

## Dependency-cycle prevention

Conditional visibility dependencies form a directed graph from target field to source fields.

Before replacement, the service loads the current same-Contract-Type dependency graph, removes the old outgoing edges for the target and inserts the proposed sources in memory.

The graph is bounded:

- maximum 32 conditions per rule;
- maximum 200 graph nodes;
- maximum 6,400 graph edges, queried with a 6,401st sentinel so overflow fails closed;
- maximum traversal depth 64.

Authoring rejects:

- self-dependency;
- direct cycles;
- indirect cycles;
- graph node/edge/depth overflow.

Cycle detection is deterministic and performed before database mutation.

## Atomic replacement / concurrency

Rule replacement is transactional.

1. `START TRANSACTION`.
2. Build the unique set of target + source definition IDs.
3. Sort IDs numerically and lock all active same-tenant/same-Contract-Type definitions using one deterministic `ORDER BY id ASC FOR UPDATE` query.
4. Persist the rule header through guarded `INSERT ... SELECT` from the target definition.
5. The upsert uses `LAST_INSERT_ID(id)` so duplicate/no-change updates still return the stable rule ID.
6. `insert_id` is explicitly reset before the guarded write. A target snapshot mismatch that produces zero rows cannot fall back to an old rule ID and therefore fails closed.
7. Delete only that tenant/rule/target's previous condition rows inside the same transaction.
8. Reinsert each condition using guarded `INSERT ... SELECT` from the locked source definition, rechecking field code, data type, options and validation configuration.
9. `COMMIT` only after every condition succeeds.
10. Any exception or zero-row source persistence executes `ROLLBACK`.

The deterministic definition lock order reduces deadlock risk between concurrent rule authoring requests.

Dedicated adversarial regression proves both critical failure paths:

- target-header zero-row drift fails before destructive condition deletion and rolls back;
- source-condition zero-row drift after deletion rolls back and never commits a partial rule.

## Reset semantics

`resetRule()` removes the conditional rule entirely and returns the target to neutral conditional behavior.

Reset:

- requires active same-tenant target definition;
- starts a transaction;
- locks the target definition;
- locks the exact rule row;
- deletes conditions first;
- deletes the rule header;
- commits atomically.

Reset of an already-unconfigured target is storage-free and idempotent.

## Read semantics

`getRule()` returns either:

- explicit `configured=false`, or
- the stored `all`/`any` rule and ordered typed conditions.

Reading rules never mutates state.

## Contract-time evaluator

`evaluate(contractId, targetDefinitionId)` is read-only.

It requires:

- tenant-scoped contract existence;
- existing P4 Contract Type binding;
- target definition belonging to the bound Contract Type;
- contract data-scope authorization using `VIEW_ALL` or own `VIEW_ASSIGNED` semantics.

The evaluator returns:

- whether a rule is configured;
- whether the rule is valid/current;
- `conditional_visible`;
- deterministic status (`not_configured`, `matched`, `not_matched`, `stale_rule`, `stale_value`, etc.);
- bounded per-condition results;
- diagnostics for fail-closed invalid states.

The evaluator performs zero writes.

## Stale-rule / stale-value safety

Evaluation never assumes a stored rule remains valid forever.

It rechecks:

- target Contract Type;
- target field code;
- target data type;
- target configuration hash;
- each source definition still exists;
- each source remains active;
- each source remains in the bound Contract Type;
- source field code/type/configuration snapshot;
- canonical stored operand;
- source value data-type snapshot;
- source value definition-configuration hash;
- source value canonical validity under the current definition.

A stale target/source rule or value fails conditional visibility closed instead of executing under changed configuration.

## P5-005 static visibility composition

P5-005 remains the outer static presentation eligibility boundary.

P5-006 returns **conditional** visibility only. It does not mutate or bypass static flags such as `show_in_form`, `show_in_mobile`, `show_in_print` or `show_in_summary`.

A later UI consumer must compose the two layers so a statically disabled surface cannot be made visible by a conditional match.

`not_configured` returns neutral `conditional_visible=true`, allowing the static P5-005 policy to remain authoritative when no conditional rule exists.

## P5-002 runtime-value impact

P5-006 reads P5-002 values only.

It never:

- writes values;
- clears values;
- changes canonicalization;
- changes definition configuration hashes;
- bypasses draft-only value mutation rules.

Stale P5-002 values are detected and fail evaluation closed.

## P5-004 historical Template boundary

P5-006 does not rewrite or reinterpret existing P5-004 Template Version field snapshots.

Conditional rules are live definition-level reference configuration in this task.

A future historical renderer must **not** take a previously published Template Version and silently apply whatever live P5-006 rule happens to exist later. If conditional rules must participate in immutable Template rendering, they require an explicit versioned snapshot integration in a separately bounded task.

This is the same historical-drift discipline documented for P5-005 metadata.

## P6 workflow / P7 approval boundary

P5-006 rules control field presentation eligibility only.

They are not:

- workflow transition predicates;
- approval routing conditions;
- lifecycle guards;
- obligation triggers;
- financial formula predicates.

P6/P7 condition systems must have their own domain authorization, state-machine semantics and audit model rather than reusing presentation rules implicitly.

## Formula/calculation boundary

No calculation engine is introduced.

There is no:

- arithmetic expression language;
- formula dependency graph;
- derived-value persistence;
- PHP/JavaScript execution;
- SQL expression injection;
- regex/pattern execution.

A future calculation task must be independently typed, bounded, cycle-safe and server-authoritative.

## API / admin / Flutter impact

No REST route, WordPress admin surface, Flutter screen, offline rule cache or public endpoint is added.

The capability remains an internal server-side foundation for later UI integration.

Flutter format/analyze/test remains part of the mandatory ESC Gate.

## Android / release isolation impact

No application ID, namespace, Firebase identity, FCM namespace, signing lineage, icon, artifact path or release environment changes are introduced.

ESC Android and artifact-isolation Gates remain mandatory.

## Reporting / search / import / export impact

No reporting, search, import, export or bulk execution is added.

Visibility evaluation is contract + target scoped and bounded to at most 32 source-value reads in one tenant-scoped query.

## Audit / compliance impact

Rule and condition rows retain actors/timestamps. Successful replace/reset operations emit internal domain actions for later audit/integration consumers.

No separate audit event table is introduced in this bounded task.

## Security / privacy impact

- strict tenant scoping;
- same-Contract-Type source enforcement;
- bounded conditions and dependency graph;
- strict operator allowlists;
- typed canonical operands;
- no executable syntax;
- no regex engine;
- no arbitrary query fragments;
- fail-closed stale rule/value handling;
- zero-write evaluator;
- contract data-scope enforcement.

No new public CSRF/rate-limit surface is introduced because there is no external endpoint in this task.

## Localization / RTL / design-system impact

No UI is introduced. Rule configuration uses machine operators/IDs and canonical typed operands, so no RTL/LTR behavior is embedded in domain logic.

Future UI labels and operator translations belong to the ESC localization/design-system layers.

## Feature registry / landing / plans impact

P5-006 remains a Development capability.

It is not Public, is not added to the landing page, and does not change subscription/plan entitlement behavior.

## Automated regression coverage

The backend Gate explicitly executes:

- `tests/php/enterprise_custom_field_visibility_p5_006.php`
- `tests/php/enterprise_custom_field_visibility_atomic_failure_p5_006.php`
- `tests/php/enterprise_custom_field_visibility_reset_p5_006.php`

Coverage includes:

- additive schema/version registration;
- rule/condition tenant ownership and uniqueness;
- typed operator allowlists;
- typed/canonical operands;
- decimal ordered comparison without binary float;
- explicit missing-value semantics;
- valid transactional replacement;
- deterministic target/source definition locking;
- target/source exact configuration revalidation;
- duplicate semantic condition rejection;
- wrong-Contract-Type/inactive source rejection;
- self-reference rejection;
- indirect-cycle rejection;
- condition-count bound;
- `all` / `any` evaluation;
- multi-select membership;
- neutral unconfigured behavior;
- stale target rule detection;
- stale source rule detection;
- stale source-value detection;
- tenant/contract/P4 binding boundaries;
- `VIEW_ALL` / own `VIEW_ASSIGNED` contract scope;
- capability denial;
- zero evaluator writes;
- target-drift rollback before destructive delete;
- source-drift rollback after delete;
- configured rule hydration;
- transactional reset;
- reset idempotency;
- no P5-002/P5-004/P5-005 schema rewrites;
- absence of executable/regex expression logic.

The P5-005 migration regression was made forward-compatible so schema `1.31.0` remains required/registered without incorrectly assuming it will always be the latest migration.

The final implementation Gate after all three P5-006 regressions were wired passed together with all earlier backend/tenancy regressions, Android/artifact isolation and Flutter format/analyze/test.

## Full Impact Review checklist

- Business/domain requirement: implemented — typed conditional field presentation.
- Tenant model/isolation: enforced on definitions, rules, values, contracts and binding.
- Database/migrations/indexes: additive schema `1.32.0`, tenant-first uniqueness/indexes.
- Backend business logic: authoring, cycle validation, transactional replacement/reset and evaluator implemented.
- Authorization/scopes/roles: ACCESS reads/evaluation; MANAGE_REFERENCE_DATA mutations; contract VIEW scope preserved.
- REST/API compatibility: no route added.
- WordPress/admin UI: N/A in this task.
- Flutter/mobile UI/offline: N/A; CI remains mandatory.
- Android identity/build environments: unchanged.
- Landing/public messaging: no public claim.
- Theme/design system: no UI surface yet.
- Feature registry/plans: remains Development/internal.
- Search/filter/sort/bulk: N/A.
- Reports/import/export: N/A.
- Notifications/escalation: N/A.
- Audit/compliance: actor timestamps + domain actions; no new audit stream.
- Documents/storage: N/A.
- Localization/RTL/timezone/currency: no UI/localized domain logic.
- Security/privacy/rate limits: bounded typed DSL only; no external endpoint/executable syntax.
- Performance/concurrency/idempotency: max 32 conditions, bounded graph, deterministic row locking, transactional rollback, reset idempotency.
- Automated tests: three explicitly wired P5-006 regressions.
- Documentation/demo/onboarding: this Full Impact Review added; no public/demo exposure.
- CI/build/release/rollback: implementation Gate green; additive tables/code are isolated from legacy contract data.
- Backward compatibility: Safe Contract/main, P5-002 values, P5-004 snapshots and P5-005 static metadata unchanged.

## Explicit non-goals / follow-up boundary

P5-006 does not implement formula/calculation execution, workflow/approval conditions, lifecycle blocking, Template-Version rule snapshots, REST/admin/Flutter UI or public marketing.
