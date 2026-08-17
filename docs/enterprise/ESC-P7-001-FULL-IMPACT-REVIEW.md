# ESC-P7-001 Full Impact Review — Workflow Transition Approval Route Definitions

## Scope

ESC-P7-001 adds the first Enterprise Approval Engine definition layer: tenant-owned, versioned Approval Route configuration attached to an exact Workflow Version Transition. It models ordered sequential stages and parallel approver selectors but deliberately does not create runtime Approval Requests, decisions, delegation, or transition blocking/release behavior.

## Database impact

Migration `1.38.0` adds three dedicated tenant-owned tables only:

- `safecontracts_workflow_transition_approval_routes`
- `safecontracts_workflow_transition_approval_stages`
- `safecontracts_workflow_transition_approval_selectors`

No existing Safe Contracts, P4/P5, P6 runtime, contract lifecycle, or legacy status table is altered.

Each route is unique per tenant + exact Workflow Version + Transition and snapshots the Transition code plus source/destination State identity. Stages have deterministic unique route-local positions and codes. Selectors have deterministic positions and a canonical `selector_key` so duplicate semantic selectors cannot exist inside one stage.

## Declarative policy impact

The definition language is deliberately bounded:

- maximum 32 stages per route;
- maximum 64 selectors per stage;
- maximum 256 selectors per route;
- stage decision policy is only `all` or `quorum`;
- `all` canonicalizes to `required_approvals = 0` and rejects caller-defined thresholds;
- `quorum` requires an integer threshold from 1 through the selector count;
- each stage must have at least one selector;
- selectors are only `tenant_user` or `tenant_role` in P7-001;
- `tenant_user` requires a positive user ID;
- `tenant_role` must be an assignable Enterprise tenant role; the legacy compatibility `member` role is intentionally not assignable for route authoring;
- stage codes reuse the bounded Workflow machine-code normalization;
- no generic condition, expression, script, callback, regex rule, or arbitrary JSON execution surface is introduced.

An empty stage list is explicit valid configuration meaning that the Transition has no Approval Route.

## Authoring and transaction impact

Reads require `ACCESS`. Draft route replacement requires `MANAGE_REFERENCE_DATA`, with the existing global WordPress capability ceiling plus tenant-role narrowing.

Replacement starts one transaction and locks the exact current-tenant Transition joined to its active Workflow, active Contract Type, and exact draft Workflow Version. Before any route delete/insert, all referenced `tenant_user` IDs are deduplicated and locked in ascending numeric order against active memberships in the same active tenant. This makes invalid membership fail before any destructive/persistence write and prevents overlapping routes with opposite selector order from acquiring shared membership locks in opposite order.

Existing selectors, stages, and route identity are removed only after those membership locks are valid and only within the same transaction. New route/stage/selector rows are persisted from the canonical server-normalized policy. Any failure rolls back the entire replacement.

Published Workflow Version route definitions are immutable.

## Read-bound impact

Ordinary `getRoute()` reads use sentinel limits of `MAX + 1` and now fail closed rather than silently returning an over-limit stored route. A stored route with no stages, more than 32 stages, a stage with no selectors, more than 64 selectors in a stage, or more than 256 selectors route-wide is rejected as inconsistent/unsupported.

This keeps authoring, ordinary reads and publication aligned on the same bounded model and prevents corrupt/manual legacy rows from being interpreted as supported configuration.

## Workflow publication impact

P6-001 publication remains the single one-way draft-to-published operation. Inside the existing publication transaction, after graph validation and P6-004 guard validation, P7-001 revalidates stored Approval Route data before the Workflow Version row can be marked published.

Publication validation is bounded and fail-closed for:

- orphaned or stale route/Transition snapshots;
- missing or excessive stages;
- non-contiguous stage positions;
- malformed/unsupported decision policy;
- invalid quorum thresholds;
- missing or excessive selectors;
- non-contiguous selector positions;
- route-wide selector overflow;
- malformed typed selector storage;
- inactive/foreign `tenant_user` memberships;
- unsupported or non-assignable tenant roles.

The validator rebuilds policy input from stored rows and runs the same canonical `ApprovalRoutePolicy`, so authoring and publication do not have divergent rule sets. During publication, referenced user IDs are collected while the exact route/stage/selector configuration remains locked, then unique memberships are acquired in ascending numeric order before the Workflow Version update. A membership cannot drift after the authoritative check and overlapping publications cannot invert shared user-lock order.

## Runtime P6 impact

P7-001 intentionally makes no change to `ContractWorkflowTransitionService` or `ContractWorkflowTransitionRepository`. A configured Approval Route therefore does not yet block, release, delay, or otherwise change a P6 transition. This isolates definition/versioning work from the later runtime Approval Request/Decision design and prevents incomplete approval semantics from leaking into production execution.

P6-003 atomic history/idempotency semantics and P6-004 declarative readiness guards remain unchanged.

## Tenant isolation and authorization

All Approval Route repository operations require core tenant enforcement and a locked tenant context. Object IDs are identifiers rather than authorization. Workflow, Version, Transition, membership and route persistence are all explicitly tenant scoped.

A user selector is accepted only when the selected user has an active membership in the current active tenant. Role selectors use the existing `TenantRolePolicy::isAssignable` allowlist rather than trusting arbitrary role strings.

## Concurrency and idempotency

Definition replacement is serialized on the exact draft Transition and is fully transactional. Shared tenant-user membership locks are canonicalized by unique ascending numeric user ID before any destructive route write.

Publication runs under the existing locked draft Workflow Version transaction and locks the Approval Route rows/stages/selectors it validates. Membership identities are accumulated from those authoritative locked selector rows and then acquired in the same unique ascending numeric order before publication can update the version. This removes the selector-order lock inversion identified during the P7-001 impact review without introducing tenant-wide serialization.

P7-001 does not create runtime request identity or decision idempotency yet; those belong to later P7 runtime tasks.

## Security and privacy

The schema stores only tenant-scoped IDs, canonical role codes and declarative routing metadata. It does not store executable code, credentials, arbitrary SQL, secrets or free-form approval logic. Selected user IDs remain internal tenant membership identifiers and are not exposed through any new public API in P7-001.

## API / admin / mobile / landing impact

No REST endpoint, WordPress admin authoring UI, Flutter/mobile Approval UI, notification surface, export/report execution or public landing claim is added in P7-001. Future surfaces must use the service/policy boundary rather than directly persisting approval rows.

## Legacy / Safe Contract impact

Legacy `safecontracts_contracts.status`, `ContractStatus`, existing Safe Contract lifecycle behavior and `main` remain independent. No ESC Approval definition is copied or backported to Safe Contract.

## Test and Gate evidence

Initial implementation Gate #405 passed on head `b471ce2a53ee1fb52e623d5196a0dca1bc43a450` with the original P7-001 regression at 58/58 assertions.

Impact review then identified two hardening gaps: deterministic `tenant_user` membership-lock ordering and fail-closed ordinary route-read sentinel overflow. They were corrected in source head `fac6474053949f0538e8716410f4e3e96d2c6710` and regression head `62300f2a4b4ef98939a0556176aa03bde19ab338`.

Exact-source validation Gate #411 passed on head `3f7704b16f13fdaa136cf986e0ba9cee36c516a1` with:

- ESC foundation validation green;
- Android identity/release isolation green;
- Enterprise artifact isolation green;
- full backend and Enterprise tenancy regressions green;
- P6-003: 77/77 assertions;
- P6-004: 60/60 assertions;
- P7-001: 65/65 assertions;
- Flutter formatting, analysis and tests green.

The hardened P7-001 regression additionally proves:

- invalid `tenant_user` membership fails before Approval Route persistence writes;
- authored selector order `90,55` acquires membership locks `55,90` exactly once each;
- publication selector order `90,55` also acquires membership locks `55,90` exactly once each;
- ordinary route reads reject 33-stage sentinel overflow;
- ordinary route reads reject 65-selector-per-stage sentinel overflow.

## Explicit non-goals / next boundary

P7-001 does not include:

- runtime Approval Request/Instance creation;
- approve/reject/delegate decisions;
- P6 transition blocking or release;
- organization-unit/team selectors;
- conditional approval expressions;
- reminders, escalation, timeout or automation;
- REST/admin/Flutter Approval UI;
- public feature marketing;
- legacy ContractStatus synchronization.

The next runtime task must snapshot the exact published Approval Route when a transition requires approval and must define immutable request/decision/idempotency semantics before P6 execution is allowed to depend on approvals.
