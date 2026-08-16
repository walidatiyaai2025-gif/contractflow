# ESC-P6-001 — Versioned Workflow Definition Foundation

## Scope

ESC-P6-001 establishes the Enterprise-only configuration foundation for versioned Workflows. It introduces a tenant-owned Workflow catalog, immutable published Workflow Versions, ordered States and Transitions, and strict graph validation.

This task is configuration/domain infrastructure only. It does **not** bind a Workflow to a runtime contract, create Workflow Instances, execute transitions, evaluate transition conditions, assign approval roles, mutate existing `ContractStatus`, or alter Safe Contract/client behavior.

A Workflow belongs to exactly one Contract Type. Workflow configuration authoring and publication require that both the Workflow and its Contract Type remain active in the current locked tenant.

## Persistence Model

Schema version `1.34.0` registers `Migration0035EnterpriseWorkflowDefinitions` and adds four ESC-only tables:

- `safecontracts_workflows`
- `safecontracts_workflow_versions`
- `safecontracts_workflow_states`
- `safecontracts_workflow_transitions`

All four tables are tenant owned.

### Workflow catalog

A Workflow stores:

- server-generated UUID;
- immutable tenant-local `workflow_code` machine identity;
- immutable `contract_type_id` binding;
- display name and description;
- active/inactive status;
- creator/updater and timestamps.

`workflow_code` is unique inside a tenant. Creation uses `INSERT … SELECT` from the current tenant's active Contract Type, so the Contract Type relationship is revalidated atomically at persistence time rather than trusted from request-time validation alone.

### Workflow Versions

A Workflow Version stores a server-controlled monotonically increasing `version_no`, draft/published status, publication actor/time and audit metadata.

Published versions are immutable. A new version is created as a new draft rather than editing a published graph.

Draft-version numbering is concurrency safe: creation starts a transaction, locks the active Workflow joined to its active same-tenant Contract Type with `FOR UPDATE`, calculates the current maximum version while that Workflow identity is locked, inserts `max + 1`, then commits. Lock/concurrency failure rolls back.

### States

Each State belongs to an exact Workflow Version and stores:

- version-local immutable `state_code` machine identity;
- display name/description;
- explicit sort order;
- `is_initial`;
- `is_terminal`.

State codes are unique per tenant + Workflow Version.

### Transitions

Each Transition belongs to an exact Workflow Version and stores:

- version-local immutable `transition_code`;
- exact source State ID;
- exact destination State ID;
- display name/description;
- explicit sort order.

Transition codes are unique per tenant + Workflow Version. Endpoint IDs always reference States persisted by the same graph replacement operation.

## Graph Contract

`WorkflowDefinitionPolicy` accepts only bounded declarative State/Transition objects. Unknown properties fail closed; there is no condition/expression/evaluation language in this foundation.

Hard limits are:

- 1–64 States;
- 0–256 Transitions;
- machine codes up to 100 bytes;
- names up to 191 bytes;
- descriptions up to 5,000 bytes;
- bounded integer sort order.

Graph invariants:

1. States and Transitions must be ordered lists.
2. Exactly one State is initial.
3. Normalized State codes must be unique.
4. Normalized Transition codes must be unique.
5. Every Transition source/destination must exist in the same version graph.
6. Self-transitions are rejected.
7. Terminal States cannot have outgoing Transitions.
8. Every non-initial State must be reachable from the initial State.
9. Reachable non-self cycles are allowed.

Cycles are intentionally allowed because legitimate enterprise workflows may return from review/rework to an earlier State. P6-001 is therefore a bounded directed graph, not a DAG. The restrictions that prevent ambiguous no-op behavior are endpoint validation, self-transition rejection, reachability, and terminal-state finality.

## Draft Graph Replacement

Graph replacement is draft-only and transactional:

1. start transaction;
2. lock the exact draft Workflow Version joined to the active Workflow and active same-tenant Contract Type with `FOR UPDATE`;
3. delete prior Transitions;
4. delete prior States;
5. persist the fully normalized bounded State set;
6. retain the exact newly inserted State IDs by machine code;
7. persist Transitions using those exact State IDs;
8. commit only after every row succeeds;
9. roll back on any persistence/concurrency failure.

An adversarial regression forces Transition persistence failure after State replacement and proves that `ROLLBACK` occurs and `COMMIT` does not, preventing partial graph configuration.

## Publication Contract

Only a draft version can be published.

Publication starts a transaction, locks/revalidates the exact draft version plus active Workflow and active Contract Type, reads the authoritative stored graph under the lock, reruns bounded graph validation, and only then performs the one-way `draft → published` update.

A missing, empty or invalid authoritative graph causes publication failure and rollback. Published versions cannot subsequently use draft graph replacement.

## Authorization and Tenant Boundary

Repository access requires core tenant enforcement plus a locked `TenantContext`; there is no unscoped tenant fallback.

- Reads/search/version/graph reads require `ACCESS` plus the tenant-role authorization ceiling.
- Workflow creation/metadata mutation/deactivation/version creation/graph replacement/publication require `MANAGE_REFERENCE_DATA` plus the tenant-role authorization ceiling.
- Object IDs are never authorization. Workflow, Version and Contract Type identities are always resolved through current-tenant repositories/queries.

Workflow creation and draft/publication writes revalidate active Contract Type/Workflow state at persistence time under the relevant transactional boundary.

## Explicit Non-Scope

ESC-P6-001 does not add:

- contract-to-Workflow binding;
- Workflow Instance/current-State runtime storage;
- runtime Transition execution;
- transition conditions or expression evaluation;
- roles/permissions on individual transitions;
- approval chains or approver assignments;
- P7 approval semantics;
- automatic lifecycle movement of existing contracts;
- changes to existing `ContractStatus` or `ContractService`;
- REST endpoints;
- WordPress/admin UI;
- Flutter/mobile UI or offline Workflow state;
- notifications/escalations;
- reporting/import/export execution;
- public landing-page feature availability;
- changes to P4 Template storage;
- changes to P5 Dynamic Field storage/calculations/visibility;
- any Safe Contract/client `main` change.

Future P6 tasks must consume this versioned definition contract rather than creating a second Workflow graph representation.

## Full Impact Review

### Business requirement / domain model

Introduces the Workflow definition/configuration domain: catalog identity, Contract Type ownership, immutable versions and bounded directed State/Transition graphs. Runtime orchestration remains deliberately separate.

### Tenant model / isolation

Every Workflow/version/state/transition row is tenant owned. Reads and writes require locked tenant context. Contract Type and Workflow ownership are revalidated in the current tenant, including under write locks for version authoring/publication.

### Database / migrations / indexes

Migration `0035` is additive and registered as schema `1.34.0`. Four dedicated tables preserve catalog/version/history separation. Tenant-local unique machine codes/version numbers and tenant/version ordering/source/destination indexes support deterministic lookups and bounded graph reads.

### Backend business logic

Added `WorkflowDefinitionPolicy`, `WorkflowDefinitionRepository`, and `WorkflowDefinitionService`. Graph normalization/validation is centralized in the policy; transactional persistence and authoritative publication validation remain repository responsibilities; authorization/domain orchestration stays in the service.

### Authorization / scopes / roles

Reads require `ACCESS`. Mutations require `MANAGE_REFERENCE_DATA`. Existing tenant-role authorization ceilings are enforced. No new tenant RBAC role or transition-level permission model is added here.

### REST API / version compatibility

N/A. No REST route, request schema or response contract is introduced or changed.

### WordPress/admin UI

N/A. No Workflow designer/admin screen is introduced.

### Flutter/mobile UI / offline state

N/A. No mobile model, screen, transition cache or offline Workflow execution is added.

### Android identity / environments

N/A to Workflow behavior. Existing ESC Android package/Firebase/signing/artifact separation remains unchanged and is still validated by the ESC Foundation Gate.

### Landing / public messaging

No public availability claim is added. This is backend configuration foundation and must not be marketed as a completed public Workflow execution feature until later exposed features are explicitly registered/approved.

### Design system / theme

N/A. No visual surface is added.

### Feature registry / plans / entitlements

No new public feature/plan entitlement surface is added in this foundation. A future exposed Workflow designer or runtime executor must define registry lifecycle, permissions and plan entitlement before exposure.

### Search / filter / sort / bulk

Only bounded tenant Workflow catalog search/filter is introduced at the service/repository level. There is no runtime Workflow Instance search, bulk transition execution or generic graph query engine.

### Reports / import / export

N/A. No Workflow report, import/export format or aggregation execution is introduced.

### Notifications / escalation

N/A. No notification/escalation trigger is executed by Workflow definitions in P6-001.

### Audit / compliance

Configuration mutations emit bounded domain actions for later audit integration. No separate audit storage/event payload contract is introduced by this task.

### Documents / storage

N/A.

### Localization / RTL / timezone / currency

Machine codes are locale-independent stable identities. Display labels/descriptions are stored as bounded text for future localized UI surfaces. No timezone/currency semantics are introduced.

### Security / privacy / rate limits

Graph input is strictly allowlisted and bounded; unsupported properties fail closed. There is no executable condition/formula/template language, preventing code/expression injection through Workflow graph definitions. Existing API/rate-limit surfaces are unchanged because no endpoint is added.

### Performance / concurrency / idempotency

Graph complexity is bounded to 64 States/256 Transitions; reachability uses bounded BFS. Draft version numbering is protected by Workflow row locking. Graph replacement/publication lock the exact draft and revalidate active parent identities. Partial replacement failures roll back. Published versions are immutable.

### Automated tests

`tests/php/enterprise_workflow_definitions_p6_001.php` is explicitly wired into `scripts/test-php.sh`. It covers schema/migration registration, graph limits/invariants, code normalization, allowed cycles, self-transition rejection, terminal/reachability behavior, tenant/Contract Type ownership, immutable catalog identities, concurrency-safe version numbering, transactional graph replacement, authoritative publication validation, published immutability, authorization ceilings, foreign-ID isolation and forced rollback paths.

### Documentation / onboarding / demo data

This document is the P6-001 domain contract and Full Impact Review. No demo data/onboarding workflow designer is required for this backend foundation.

### CI / build / release / rollback

The ESC Foundation Gate validates PHP syntax/backend/tenant regressions, Android identity/release/artifact isolation, and Flutter format/analyze/test. The schema change is additive. Operational rollback of feature exposure is achieved by not authoring/using Workflow definitions; destructive migration rollback is intentionally not automated.

### Backward compatibility

Existing contract lifecycle/status behavior remains unchanged. P4 Contract Templates, P4 configuration bindings, P5 Dynamic Fields and Safe Contract/client `main` are not modified by Workflow definition/runtime coupling.

## Implementation Gate Evidence

ESC Foundation Gate **#369** on branch `enterprise-safecontracts`, head `c263e06fa004052be0b14d1a6225bb2ee150c563`, passed both `esc-foundation` and `esc-mobile`.

The explicitly wired P6-001 regression passed **89/89 assertions**. The same Gate passed all existing backend/tenancy regressions, ESC Android identity/release isolation, verified-artifact isolation, and Flutter format/analyze/test.

This implementation Gate validates the source implementation. Issue #466 must remain open until this documentation/status record is committed and a fresh exact-source Gate is green on the final source head.