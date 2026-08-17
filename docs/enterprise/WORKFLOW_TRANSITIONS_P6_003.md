# ESC-P6-003 — Atomic Workflow transition execution and immutable history

Issue: #468  
Product: Enterprise Safe Contracts (ESC) only  
Branch: `enterprise-safecontracts`

## Purpose

P6-003 adds the first runtime transition operation on top of the P6-001 immutable Workflow Definition graph and the P6-002 tenant-owned Contract Workflow Instance. A transition moves only the ESC Workflow Instance current state and creates one immutable tenant-owned transition-history row in the same database transaction.

The existing Safe Contract / legacy `safecontracts_contracts.status`, `ContractStatus`, and `ContractService` lifecycle remain independent and unchanged.

## Delivered model

Additive schema version `1.36.0` creates `safecontracts_contract_workflow_transition_history`.

Each immutable history record stores:

- tenant identity;
- Workflow Instance and contract identity;
- exact Workflow and published Workflow Version identity;
- exact Transition identity and machine-code snapshot;
- exact source State identity/code snapshot;
- exact destination State identity/code snapshot;
- SHA-256 idempotency-request hash;
- actor user identity;
- UTC occurrence timestamp.

The unique `(tenant_id, instance_id, request_key_hash)` constraint makes request identity durable at the database layer.

## Transition command contract

`ContractWorkflowTransitionService::execute()` accepts only:

- `contract_id`;
- `transition_code`;
- required caller idempotency key.

The caller cannot provide source state, destination state, Workflow Version, Transition ID, or current-state replacement values.

`transition_code` uses the same bounded normalized machine-code policy as P6-001. The idempotency key is trimmed, limited to 191 bytes, rejects control characters, and is persisted only as a SHA-256 hash.

## Authoritative runtime semantics

A valid execution requires:

1. core Enterprise tenant enforcement and a locked tenant context;
2. authenticated global `EDIT_CONTRACTS` permission plus tenant-role authorization;
3. a current-tenant unarchived contract inside the caller's existing contract data scope;
4. an existing P6-002 Workflow Instance for that contract;
5. an exact transition from the instance's immutable Workflow Version whose normalized transition code matches the request;
6. that transition's source State must equal the locked instance current State;
7. the exact Workflow Version must still be `published`;
8. source and destination State rows must belong to the same tenant, Workflow, and Workflow Version.

The destination State is derived exclusively from the authoritative P6-001 Transition row.

A Workflow catalog entry may later be deactivated for new authoring/initialization without invalidating already-created instances that reference an immutable published Workflow Version. Runtime execution is therefore version-centric: the exact published version remains the authoritative graph for its existing instance. P6-003 does not silently migrate an instance to a newer Workflow Version.

## Atomicity and concurrency

Transition execution is transactional:

1. `START TRANSACTION`;
2. lock the exact tenant contract joined to the exact P6-002 Workflow Instance with `FOR UPDATE`;
3. reject archived/missing/foreign instance identity;
4. lock/check the tenant+instance+request-key history identity;
5. for an exact retry, return the original immutable history row and commit without another state movement;
6. reject reuse of the same idempotency key for a different transition;
7. lock the exact published-version Transition and its source/destination States, constrained to the locked current source State;
8. insert the immutable history row;
9. compare-and-set update only the P6-002 instance current State, guarded by tenant, contract, Workflow, Workflow Version, and original current State;
10. `COMMIT` only after both history insert and instance state update succeed;
11. any failure executes `ROLLBACK`.

The contract+instance lock serializes competing transition commands for the same contract. The compare-and-set update provides a second concurrency invariant. If state movement fails after the history insert, the transaction rollback removes the uncommitted history row as well, preventing partial audit evidence.

## Idempotency semantics

Idempotency is mandatory for transition mutation.

- First valid request: one history row + one instance movement.
- Exact retry with the same key and same transition: returns the original history row, performs no new history insert, performs no state update, and emits no duplicate transition event.
- Same key reused for a different transition: fails closed.
- Because idempotency is checked after locking the current instance but before current-state transition resolution, an exact retry remains valid even after the original request has already moved the instance to its destination State.

## Tenant isolation and authorization

All repository access requires `CoreTenantEnforcement` and `TenantContextStore::context()->requireTenantId()`.

Object IDs and transition codes never authorize access. Contract, instance, history, Transition, Version, and State lookups remain tenant-scoped.

History reads require `ACCESS` and preserve existing contract data scope:

- `VIEW_ALL` may read the tenant contract history;
- `VIEW_ASSIGNED` may read only when the current user is the contract's assigned accountant.

Transition execution requires `EDIT_CONTRACTS`, the same contract data scope, and `TenantAuthorization::allowsCapability()` as the tenant-role ceiling.

## Full Impact Review

### Business/domain model

Reviewed and implemented. P6-003 introduces explicit runtime state movement for an existing P6-002 instance, driven only by P6-001 published graph data. Transition history is immutable evidence, not mutable state.

### Tenant model/isolation

Reviewed and implemented. History is tenant-owned; all current-state, idempotency, graph, and history operations are tenant-scoped and fail closed on foreign identity.

### Database/migrations/indexes

Reviewed and implemented as additive migration `1.36.0`. History has a durable idempotency uniqueness constraint and bounded tenant/contract/instance/version indexes. No existing table schema is altered.

### Backend business logic

Reviewed and implemented through dedicated `ContractWorkflowTransitionPolicy`, `ContractWorkflowTransitionRepository`, and `ContractWorkflowTransitionService`. Only the P6-002 instance current-state fields are updated.

### Authorization/scopes/roles

Reviewed and implemented. History reads require `ACCESS`; mutation requires `EDIT_CONTRACTS`; tenant-role narrowing and existing `VIEW_ALL` / own `VIEW_ASSIGNED` data scope are enforced.

### REST API/version compatibility

N/A in P6-003. No REST route is exposed. A future API must require a client idempotency key and call this service rather than recreating transition logic.

### WordPress/admin UI

N/A. No admin surface is exposed.

### Flutter/mobile UI and local state

N/A. No mobile transition API/model/UI is exposed. Flutter format/analyze/test remains in the ESC Gate.

### Android identity/build environments

No identity/build change. Existing ESC/Safe Contract application/Firebase/storage/signing/artifact isolation remains mandatory and green.

### Landing/marketing/public feature catalog

No public claim. Runtime Workflow transition execution is still an implementation-stage capability and must not yet be marketed as a complete workflow/approval product.

### Design system/theme

N/A. No UI is added.

### Feature registry/feature flags/subscription plans

No entitlement surface is introduced here. Future P13 work must gate any exposed runtime Workflow feature coherently.

### Search/filter/sort/bulk actions

Only bounded exact-contract history pagination is included. No bulk transition operation is introduced.

### Reports/import/export

No report/import/export mutation path is added. Immutable history is designed so later reporting can consume it without reconstructing state changes from mutable rows.

### Notifications/escalation

No notification or escalation execution is added. A WordPress action is emitted only for a newly committed transition path; exact idempotent retries do not emit duplicate actions. Delivery semantics remain future P11 work.

### Audit/compliance

Implemented through immutable transition-history rows containing exact graph/state snapshots, actor, request hash, and UTC occurrence time. The runtime current-state row is not used as the sole audit source.

### Documents/storage

N/A.

### Localization/RTL/LTR/timezone/currency

N/A to core semantics. Machine codes are normalized identifiers; timestamps use database UTC conventions. No user-facing text/UI is introduced.

### Security/privacy/rate limits

Reviewed. Caller cannot choose destination state. No executable condition/expression/formula language exists. Idempotency keys are bounded and stored only as hashes. REST rate limits become applicable only when a network API is exposed.

### Performance/concurrency/idempotency

Reviewed and implemented. History pagination is bounded to 100 rows per request; transition lookup is exact-version/current-source constrained; contract+instance locking serializes competing movements; durable idempotency uniqueness and compare-and-set current-state update protect retries and races.

### Automated tests

`tests/php/enterprise_contract_workflow_transitions_p6_003.php` is explicitly wired into `scripts/test-php.sh` and covers:

- additive `1.36.0` schema and migration registration;
- tenant-owned immutable history schema and idempotency uniqueness;
- transition-code/idempotency-key normalization and bounds;
- disabled/unlocked tenant enforcement;
- valid server-derived transition execution;
- exact Workflow Version/current-source constraints;
- history-before-state atomic transaction shape;
- compare-and-set instance movement;
- exact idempotent retry after state advancement;
- rejection of idempotency-key reuse for another transition;
- wrong-current-state/missing transition rejection;
- archived/foreign contract and missing instance rejection;
- bounded history reads;
- `VIEW_ALL` / own `VIEW_ASSIGNED` data scope;
- global and tenant-role mutation denial;
- rollback of both attempted history and state movement on compare-and-set failure;
- no mutation to legacy contract status, P4/P5, or P6-001 definitions;
- no conditions/expressions or approval routing;
- backend Gate wiring.

Implementation Gate #378 passed on head `822677c620f829bbe5173942db65e5eb1e8295fc`; P6-003 passed **77/77 assertions**, with all backend/tenancy regressions, ESC Android/artifact isolation, and Flutter format/analyze/test green.

### Documentation/demo data/onboarding

This document is the implementation Full Impact Review. No demo data or onboarding UI is introduced.

### CI/build/release/rollback

Reviewed. Runtime partial failures use database rollback. Schema is additive and follows forward-migration discipline; no destructive downgrade is introduced. No verified release artifact is produced by this task.

### Backward compatibility

Preserved. P6-003 does not mutate `safecontracts_contracts.status`, `ContractStatus`, `ContractService`, P4/P5 storage, P6-001 Workflow Definition rows, or Safe Contract/main.

## Explicit non-goals / next boundaries

P6-003 does not implement:

- conditional transition rules or expression execution;
- P5 readiness/lifecycle blocking;
- approval routing (P7);
- timers, SLA, escalation, cron, or automatic transitions;
- Workflow Version migration/rebinding;
- transition reversal/deletion or history rewriting;
- REST/admin/Flutter UI;
- public landing-page availability claim;
- legacy ContractStatus synchronization;
- Safe Contract or `main` changes.

The next coherent P6 step should add bounded declarative transition guards/readiness integration only after their semantics are explicit, or expose the completed P6 foundation through a versioned service-backed API/UI task. Approval routing remains P7 rather than being folded into P6-003.