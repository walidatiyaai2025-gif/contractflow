# ESC-P7-001 — Workflow Transition Approval Route Definitions

## Status

Implementation candidate under validation on `enterprise-safecontracts`.

This task is Enterprise Safe Contracts (ESC) only. It does not change Safe Contract/main and must not create a merge path to the client product.

## Goal

Provide tenant-owned, declarative Approval Route definitions attached to one exact Workflow Version Transition. A route contains sequential stages; each stage contains a parallel candidate selector set and an explicit decision policy.

P7-001 is definition/publication infrastructure only. It does **not** create runtime Approval Requests, decisions, delegation, transition blocking/release, timers, escalation, REST/admin/mobile surfaces, or legacy `ContractStatus` synchronization.

## Domain boundary

An Approval Route is owned by:

- `tenant_id`
- immutable Workflow identity
- exact Workflow Version identity
- exact Transition identity
- immutable Transition/source/destination snapshots captured when the route is authored

A configured route contains:

1. ordered stages (`position_no`), executed sequentially by future P7 runtime work;
2. ordered selectors inside each stage, representing a parallel candidate set;
3. a decision policy of `all` or `quorum`;
4. a canonical quorum threshold when `quorum` is selected.

An empty route is explicit valid configuration and means that no Approval Route is configured for that Transition.

## Schema impact

Additive schema version: `1.38.0`.

Dedicated ESC tables:

- `safecontracts_workflow_transition_approval_routes`
- `safecontracts_workflow_transition_approval_stages`
- `safecontracts_workflow_transition_approval_selectors`

The schema does not alter legacy contract tables, Workflow runtime instance/history tables, P5 Dynamic Field tables, or Safe Contract storage.

Key persistence invariants:

- one route per tenant + Workflow Version + Transition;
- stage position and stage code unique inside a route;
- selector position and canonical selector key unique inside a stage;
- route stores Transition/source/destination snapshots;
- selector shape is explicit (`tenant_user` XOR `tenant_role` target columns);
- author/audit timestamps are retained on route, stage, and selector rows.

## Declarative policy and limits

Allowlisted stage policies:

- `all`: `required_approvals` must be canonical `0`;
- `quorum`: `required_approvals` must be at least 1 and no greater than the selector count.

Allowlisted selectors:

- `tenant_user`: positive integer user ID; must resolve to an active membership in the active current tenant;
- `tenant_role`: must satisfy `TenantRolePolicy::isAssignable`; compatibility role `member` is deliberately excluded.

Unsupported in P7-001:

- expressions or arbitrary JSON conditions;
- PHP callbacks, scripts, regex callbacks, templates or executable rule languages;
- OrgUnit/Department/Team approver selectors;
- external identities;
- runtime approval state or decisions.

Bounds:

- maximum 32 stages per route;
- maximum 64 selectors per stage;
- maximum 256 selectors per route;
- stage names capped at the policy byte limit;
- server controls stage and selector positions after normalization.

## Tenant isolation and authorization

Reads require global `ACCESS` plus tenant-role authorization.

Authoring requires global `MANAGE_REFERENCE_DATA` plus tenant-role narrowing. Object IDs are never authorization.

The authoring path re-resolves the Workflow, exact Workflow Version and Transition in the active tenant and rejects foreign/missing identities. The authoritative transactional write locks the exact draft Transition joined to its active Workflow, active same-tenant Contract Type, exact draft Version, and endpoint States.

`tenant_user` selectors are validated against `safecontracts_tenant_memberships` joined to the active tenant. Publication revalidates membership rather than trusting author-time existence.

## Draft replacement and atomicity

Route replacement executes in one database transaction.

Expected write sequence:

1. lock the exact active tenant draft Transition;
2. lock any existing exact route identity;
3. remove old selectors, stages, then route if present;
4. for a non-empty replacement, validate/lock referenced tenant users;
5. insert authoritative route snapshot;
6. insert stages and selectors;
7. commit only after every row is persisted successfully;
8. roll back on any validation, membership or persistence failure.

Published Workflow Version routes are immutable. Mutation is rejected before route writes.

## Workflow publication impact

P6 Workflow runtime semantics remain unchanged.

Before the one-way draft → published Workflow Version update, Workflow publication must validate P7 route definitions in the same publication transaction. Publication fails closed when any route is:

- orphaned from the exact Transition;
- stale relative to Transition/source/destination snapshots;
- structurally over bounds;
- non-contiguous in stage/selector positions;
- configured with unsupported decision policy or selector type;
- configured with invalid quorum semantics;
- configured with an inactive/foreign `tenant_user` selector;
- configured with a non-assignable `tenant_role` selector.

No route is consulted by `ContractWorkflowTransitionService` in P7-001. Runtime transition execution remains P6 behavior until a later P7 task explicitly introduces Approval Request semantics.

## Concurrency review

### Protected invariants

- draft Transition is locked during replacement;
- existing route identity is locked before destructive replacement;
- publication locks route/stage/selector configuration and performs validation before version publication;
- active tenant-user membership must remain valid through the authoritative transaction boundary;
- any partial replacement rolls back.

### Deterministic membership-lock ordering blocker

At the time this review was recorded, tenant-user membership rows are acquired according to authored stage/selector traversal order. Two concurrent operations referencing the same user set in opposite selector order could therefore acquire shared membership locks in opposite order.

**P7-001 must not be considered complete until membership locking is made deterministic (for example, unique tenant-user IDs collected and locked in ascending numeric order) and a regression/source assertion proves that invariant for authoring and publication paths.**

This blocker is isolated to ESC P7 authoring/publication concurrency; it does not justify any Safe Contract/main change.

## Read-bound review

Authoritative publication uses sentinel reads (`MAX + 1`) for stages/selectors and fails closed when configured rows exceed supported bounds.

The ordinary read surface must likewise never silently present an over-limit stored route as valid. Any `MAX + 1` result must be explicitly rejected rather than truncated or returned as a supported route. This is part of final P7-001 hardening validation.

## Security and privacy impact

- no secret material is introduced;
- no arbitrary executable rule payload is stored;
- selector targets are tenant-scoped identities/roles only;
- cross-tenant identity substitution must fail closed;
- published definition mutation remains prohibited;
- no public API is exposed by this task;
- no notification or external delivery is performed;
- no financial/payment authority is implied by an approval selector.

## Audit impact

P7-001 stores author/update identity and timestamps for declarative configuration. Runtime Approval Request/decision audit events are out of scope and belong to later P7 tasks.

The existing Workflow publication action remains the publication boundary. Future approval-runtime auditing must not infer decisions from definition rows.

## API / Admin / Flutter / Public impact

No P7-001 surface is exposed through:

- REST API;
- WordPress admin UI;
- Flutter/mobile UI;
- public landing page;
- plan/entitlement claims;
- import/export/report execution.

Future surfaces must consume the same tenant-owned service/repository boundary rather than bypassing it with direct table access.

## Localization, timezone and currency impact

No new timezone or currency arithmetic is introduced. Stage names are display text but no new UI/localization surface is published in P7-001.

Approval semantics must not be coupled to currency, locale, or tenant timezone at this definition-foundation stage.

## Performance and indexing impact

The schema includes tenant/version/transition route lookup keys, tenant/route stage keys, and tenant/route/stage selector keys. User/role selector indexes support future resolution work.

All authoring/publication scans are bounded. Runtime candidate expansion and large-tenant approval queue performance are explicitly deferred to later P7 tasks and must be load-tested when introduced.

## Backward compatibility

P7-001 is additive and opt-in:

- Workflows without routes retain existing P6 behavior;
- existing published Workflow Versions are not rewritten;
- existing Workflow Instances/transition history are not rewritten;
- legacy `ContractStatus` is not synchronized;
- P5 fields/guards are not rewritten;
- Safe Contract/main remains unchanged.

## Test expectations

The explicitly wired P7-001 backend regression must cover at minimum:

- migration registration and additive schema;
- route/stage/selector uniqueness and bounds;
- `all` and `quorum` normalization;
- unsupported policies/selectors;
- duplicate stages/selectors;
- legacy `member` role rejection;
- active same-tenant user membership enforcement;
- published immutability;
- capability and tenant-role denial;
- rollback on membership/persistence failure;
- stale route snapshot publication failure;
- inactive user and invalid role publication failure;
- Workflow publication validation before the published update;
- proof that P6 runtime transition code is not coupled to Approval Routes;
- deterministic tenant-user membership lock order;
- fail-closed bounded ordinary route reads.

The ESC Foundation Gate must run this regression explicitly; file presence alone is not completion evidence.

## Release / artifact impact

No Enterprise Plugin/APK is verified or retained solely because P7-001 source lands. Existing ESC artifact isolation rules remain mandatory, and the outstanding real-device Safe Contract + ESC coexistence acceptance remains an independent release blocker.

## Completion gate

P7-001 is complete only when all of the following are true:

- schema/policy/repository/service implementation is present on `enterprise-safecontracts`;
- publication validation is inside the authoritative Workflow publication transaction;
- deterministic membership locking and fail-closed bounded reads are hardened;
- P7-001 regression is explicitly wired and green;
- all existing backend/tenant isolation regressions remain green;
- Android identity/artifact isolation remains green;
- Flutter format/analyze/test remains green;
- this Full Impact Review and Master Plan status reference exact validated source;
- final exact-source/final completion Gates are green;
- Issue #470 is closed only after that evidence exists.
