# ESC-P5-007 — Typed Dynamic Field Numeric Calculation Foundation

## Scope

ESC-P5-007 adds an Enterprise-only, tenant-owned, server-side numerical calculation foundation for Dynamic Fields. It is deliberately narrower than a general formula language and remains separate from the future P9 financial engine.

The calculation surface is declarative JSON AST only. Supported target and source Dynamic Field data types are `integer` and `decimal`. A source field must exist in the locked tenant, be active, and belong to the same Contract Type as the target.

Allowed AST nodes are intentionally bounded:

- `field` — exact Dynamic Field definition reference.
- `constant` — canonical plain-decimal constant.
- `add` — 2 to 16 ordered children.
- `subtract` — exactly 2 ordered children.
- `multiply` — 2 to 8 ordered children.
- `negate` — exactly 1 child.

`divide` is not included because this foundation does not define a rounding/scale contract for division. There is no free-form formula string, PHP/JavaScript/SQL evaluation, template execution, regex expression engine, `eval`, or scientific notation input.

## Persistence

Schema version `1.33.0` registers `Migration0034EnterpriseCustomFieldCalculationRules` and adds two ESC-only tables without altering existing P5 storage:

- `safecontracts_custom_field_calculation_rules`
- `safecontracts_custom_field_calculation_dependencies`

Each calculation rule is tenant owned and unique by tenant + target definition. The rule stores the exact target Contract Type, field-code snapshot, data-type snapshot, SHA-256 definition-configuration hash, and canonical `expression_json` AST.

Each unique source dependency is stored separately with deterministic position, source field-code/data-type snapshots and configuration hash. The dependency table is used for bounded graph validation, stale-definition detection and deterministic replacement.

P5-002 runtime values are not copied or materialized into calculation storage. P5-004 Template Version field snapshots, P5-005 presentation/reporting metadata and P5-006 visibility rules are not rewritten.

## AST and Numeric Contract

`CustomFieldCalculationPolicy` canonicalizes the AST before persistence and evaluation. Unsupported properties or operators fail closed.

Hard limits are:

- maximum decimal precision: 38 digits total;
- maximum decimal scale: 12 fractional digits;
- maximum AST depth: 16;
- maximum AST nodes: 128;
- maximum unique field dependencies: 32.

Constants use plain decimal notation only. Binary floating-point values are not accepted as AST constants. Arithmetic is implemented over canonical decimal strings rather than PHP floating point, so cases such as `0.1 + 0.2` evaluate deterministically to `0.3`.

Addition, subtraction, multiplication and negation are exact within the configured precision/scale bounds. Any result exceeding those bounds fails explicitly rather than rounding silently. An `integer` target rejects a fractional calculated result.

## Rule Authoring and Concurrency

Rule replacement requires an active numeric target and active numeric sources from the same tenant and Contract Type. Direct self-dependency and indirect calculation cycles are rejected before persistence.

The dependency graph scan is bounded by explicit edge, node and traversal-depth limits.

Persistence is transactional:

1. start transaction;
2. sort target/source definition IDs deterministically;
3. lock the current tenant, Contract Type, active numeric definitions using `FOR UPDATE`;
4. atomically upsert the rule while revalidating the target snapshot;
5. delete prior dependency rows for that rule;
6. insert each source dependency while revalidating its current definition snapshot;
7. commit only after every dependency succeeds;
8. roll back on any concurrent drift or persistence failure.

A dedicated adversarial fake-database regression proves that a zero-row dependency insert after rule replacement triggers `ROLLBACK` and never `COMMIT`, preventing partial configuration.

## Evaluation Contract

Evaluation is read-only. It does not insert, update, clear or materialize a P5-002 value.

Evaluation requires:

- an accessible current-tenant contract;
- a P4 Contract Type binding;
- a target Dynamic Field belonging to the bound Type;
- a current non-stale target rule snapshot;
- current active same-Type numeric source definitions matching their stored snapshots;
- current P5-002 source values whose data-type snapshots and definition configuration hashes remain current;
- canonical source JSON that still passes P5 value canonicalization.

Missing or cleared source values never become implicit zero. Stale, invalid, noncanonical or missing sources return explicit invalid evaluation status with no result.

Representative fail-closed statuses include:

- `inactive_target`
- `stale_rule`
- `invalid_rule`
- `missing_source`
- `stale_value`
- `invalid_source`
- `calculation_error`
- `fractional_result`

A valid calculation returns canonical decimal text plus the target data type and canonical dependency IDs. The caller decides how to present/use the result; this task does not persist it.

## Authorization and Tenant Boundary

Repository access requires core tenant enforcement and a locked `TenantContext`; there is no unscoped tenant fallback and object IDs are not authorization.

- Reads/evaluation require `ACCESS` plus the tenant-role authorization ceiling.
- Rule replacement/reset require `MANAGE_REFERENCE_DATA` plus the tenant-role authorization ceiling.
- Evaluation preserves existing contract data scope: `VIEW_ALL` may read any accessible tenant contract; `VIEW_ASSIGNED` is limited to the current user's assigned contract context.

Foreign/missing definition IDs fail after current-tenant lookup. Same-Type/source validation is repeated at write time under locks, not trusted from request-time validation alone.

## Non-Regression Boundaries

ESC-P5-007 intentionally does not change:

- Safe Contract/client `main` or any client release line;
- P5-001 Dynamic Field definition semantics beyond consuming numeric definitions;
- P5-002 value persistence, clear semantics or value materialization;
- P5-003 readiness/lifecycle behavior;
- P5-004 Template Version field snapshots;
- P5-005 presentation/reporting metadata;
- P5-006 conditional visibility rules;
- P9 financial calculations, money, currency, tax, payment or settlement logic;
- REST API contracts;
- WordPress/admin UI;
- Flutter/mobile UI/offline state;
- Android package/Firebase/signing/release identity;
- landing-page/public feature claims;
- notifications, documents or audit/event behavior.

This foundation is backend/domain infrastructure only. Future UI/API/template integration must consume the same canonical service contract rather than adding a second calculation language.

## Full Impact Review

### Business requirement / domain model
Implemented a bounded numeric Dynamic Field calculation primitive suitable for configuration-driven Contract Types while explicitly excluding financial-engine semantics and general executable formulas.

### Tenant model / isolation
All rule/dependency rows are tenant owned. Repository/service operations require locked tenant context. Target/source IDs are re-resolved under tenant scope, and contract evaluation remains tenant/data-scope constrained.

### Database / migrations / indexes
Migration `0034` is additive and registered as schema `1.33.0`. Unique/indexed tenant-target and dependency keys support deterministic rule identity and graph lookup. No existing P5 or contract table is altered.

### Backend business logic
Added dedicated calculation policy, repository and service/evaluator. Numeric arithmetic is fixed-point/string based, bounded and deterministic.

### Authorization / scopes / roles
Reads require `ACCESS`; authoring/reset requires `MANAGE_REFERENCE_DATA`; tenant-role ceilings apply. Contract evaluation keeps `VIEW_ALL` / own `VIEW_ASSIGNED` behavior.

### REST API / version compatibility
N/A for this foundation. No route or payload contract changed.

### WordPress/admin UI
N/A. No calculation authoring UI is introduced.

### Flutter/mobile UI / offline state
N/A. No mobile model, screen, cache or offline calculation implementation is introduced.

### Android identity / environments
N/A. ESC package/application/Firebase/signing/artifact isolation remains unchanged and continues to be validated by the ESC Foundation Gate.

### Landing / public messaging
No public feature claim is added. Until a later product task marks an exposed calculation capability Public in the Feature Registry, the landing page must not market this backend foundation as generally available.

### Design system / theme
N/A.

### Feature registry / plans / entitlements
No new registry/plan surface is added in this foundation task. Future exposed authoring/evaluation surfaces must define entitlement before public use.

### Search / filter / sort / bulk
N/A. Calculation rules are not exposed through generic search/bulk execution.

### Reports / import / export
No report-query, aggregation, import or export execution is added. P5-005 remains declarative metadata only.

### Notifications / escalation
N/A.

### Audit / compliance
No new audit event stream is introduced. Rule mutations emit bounded domain actions for later audit integration; no sensitive expression execution surface exists.

### Documents / storage
N/A.

### Localization / RTL / timezone / currency
Numeric AST constants are locale-independent canonical plain decimals. No localized numeric parser or currency semantics are introduced.

### Security / privacy / rate limits
The attack surface is intentionally closed: strict AST allowlist, strict keys, no executable string language, no division ambiguity, no exponent notation, bounded graph/AST/dependency sizes and tenant-scoped authorization. Existing rate-limit/API surfaces are unchanged.

### Performance / concurrency / idempotency
AST evaluation and graph traversal are bounded. Definition locking is deterministic. Rule replacement is transactional and fails closed on concurrent definition drift. Exact rule identity remains one row per tenant target.

### Automated tests
`tests/php/enterprise_custom_field_calculations_p5_007.php` is explicitly wired into `scripts/test-php.sh`. The regression covers schema registration, AST canonicalization/limits, exact decimal arithmetic, precision/scale overflow, unsupported operators, source/target type and tenant boundaries, cycle detection, transactional rollback, authorization/data scope, stale/missing/noncanonical P5 sources, integer fractional rejection, read-only evaluation and P5 domain isolation.

### Documentation / onboarding / demo data
This document is the P5-007 domain and Full Impact Review record. No demo data/onboarding surface is required for this backend foundation.

### CI / build / release / rollback
The ESC Foundation Gate validates PHP syntax/backend regressions, tenant enforcement, Android identity/release isolation, artifact isolation and Flutter quality checks. The migration is additive; rollback of feature exposure is achieved by not authoring/using calculation rules, while destructive schema rollback is intentionally not automated.

### Backward compatibility
Existing Safe Contract behavior and ESC P5-001 through P5-006 persisted contracts remain compatible because the feature adds separate tables and read-only consumption of existing P5-002 source values.

## CI Evidence

Implementation validation on branch `enterprise-safecontracts`, head `67c382e4933270764809af409e4113db68f06f9c`, reached and passed the explicitly wired P5-007 regression with **95 assertions**. The same backend job also passed existing P5-001, P5-002, P5-003, P5-004 and P5-005 regressions plus the complete backend/tenant regression set, foundation validation, Android identity isolation and Enterprise verified-artifact policy.

This implementation validation is not the closure proof by itself. Issue #465 must be closed only after the documentation/Master Plan commits are on the branch and a fresh ESC Foundation Gate is green on that exact final source head, including the Flutter job.
