# ESC-P6-002 — Contract Workflow Instance foundation

Issue: #467  
Product: Enterprise Safe Contracts (ESC) only  
Branch: `enterprise-safecontracts`

## Purpose

P6-002 connects an existing tenant-owned contract to one immutable, published P6-001 Workflow Version by creating one tenant-owned Contract Workflow Instance. The instance starts at the exact Workflow Version's unique initial state, which is derived server-side. This task does **not** execute transitions and does **not** replace or reinterpret the existing Safe Contract/legacy `ContractStatus` lifecycle.

## Delivered model

Additive schema version `1.35.0` creates `safecontracts_contract_workflow_instances` with one current Workflow Instance per `(tenant_id, contract_id)`.

The instance stores:

- tenant and contract identity;
- the P4 Contract Type binding used at initialization;
- Workflow and Workflow Version identity;
- immutable Workflow Version number snapshot;
- immutable Workflow machine-code snapshot;
- exact current Workflow State identity;
- current Workflow State machine-code snapshot;
- start/update actor and timestamps.

The schema is additive. It does not alter `safecontracts_contracts`, Contract Types, Templates, Dynamic Fields, Workflow Definitions, approvals, or legacy lifecycle columns.

## Initialization contract

`ContractWorkflowInstanceService::initialize()` accepts only:

- `contract_id`;
- `workflow_id`;
- `workflow_version_id`.

The caller cannot supply `current_state_id` or `current_state_code`. The service derives the initial state from P6-001 storage and requires exactly one state with `is_initial = 1` for the exact published Workflow Version.

Initialization requires all of the following:

1. core Enterprise tenant enforcement is enabled;
2. a locked current tenant exists;
3. the authenticated user has global `EDIT_CONTRACTS` and the tenant role also allows that capability;
4. the contract exists in the current tenant and is inside the user's existing contract data scope;
5. the contract is not archived;
6. the contract already has a P4 Contract Type binding;
7. the Workflow exists in the same tenant, is active, and belongs to the contract's bound Contract Type;
8. the selected Workflow Version belongs to that Workflow and is published;
9. the exact Workflow Version has exactly one valid initial state.

Legacy contract status is intentionally not used as the Workflow Instance state. P6-002 does not mutate `safecontracts_contracts.status` and does not couple the new engine to `ContractStatus`.

## Atomicity and concurrency

Repository initialization is transactional:

1. `START TRANSACTION`;
2. lock the exact tenant contract joined to its P4 binding with `FOR UPDATE`;
3. lock/revalidate the active same-Type Workflow, exact published Version, and unique initial state with `FOR UPDATE`;
4. lock any existing tenant+contract Workflow Instance;
5. if the existing instance is the exact immutable binding, return idempotently;
6. if it differs, fail closed instead of silently rebinding;
7. otherwise perform one `INSERT ... SELECT` into the dedicated instance table, rechecking the authoritative contract/binding/Workflow/Version/state predicates at write time;
8. `COMMIT` only after exactly one row is inserted;
9. any failure executes `ROLLBACK`.

Locking the contract/P4 binding serializes competing initialization attempts for the same contract. The unique `(tenant_id, contract_id)` key provides an additional database invariant.

There is deliberately no `ON DUPLICATE KEY UPDATE`; an existing different Workflow binding cannot be overwritten implicitly.

## Tenant isolation and authorization impact

All repository reads and writes require `CoreTenantEnforcement` plus `TenantContextStore::context()->requireTenantId()`.

Object IDs never authorize access. Contract, binding, Workflow, Version, state, and instance lookups are tenant-scoped.

Reads require `ACCESS` and preserve existing contract data scope:

- `VIEW_ALL` may read the tenant contract's instance;
- `VIEW_ASSIGNED` may read only when the current user is the contract's assigned accountant.

Initialization requires `EDIT_CONTRACTS` and the same contract data scope. `TenantAuthorization::allowsCapability()` remains a second authorization ceiling, so a tenant role cannot bypass the global capability model.

## Historical/read behavior

Once created, the instance contains Workflow/version/state snapshots sufficient to preserve the identity selected at initialization even if mutable catalog metadata changes later.

P6-002 does not add automatic migration to another Workflow Version and does not reinterpret an instance against a newer draft or published Workflow Version.

A later task must define explicit transition/history/version-migration semantics rather than mutating this foundation implicitly.

## Full Impact Review

### Business/domain model

Reviewed. Introduces the first runtime bridge from a contract to the declarative P6-001 Workflow domain. One contract owns at most one current Workflow Instance in this foundation.

### Tenant model/isolation

Reviewed and implemented. Every instance is tenant-owned; all source lookups and authoritative write-time joins are tenant-scoped. Foreign IDs fail closed.

### Database/migrations/indexes

Reviewed and implemented as additive migration `1.35.0`. Unique tenant+contract ownership and bounded lookup indexes are present. No existing table is altered.

### Backend business logic

Reviewed and implemented through dedicated `ContractWorkflowInstanceRepository` and `ContractWorkflowInstanceService`. No direct integration into legacy `ContractService` is introduced.

### Authorization/scopes/roles

Reviewed and implemented. Reads use `ACCESS`; initialization uses `EDIT_CONTRACTS`; tenant-role narrowing and existing `VIEW_ALL` / own `VIEW_ASSIGNED` data scope are enforced.

### REST API/version compatibility

N/A in P6-002. No REST route is exposed. A later bounded API task must call the service layer rather than duplicate authorization or SQL.

### WordPress/admin UI

N/A in P6-002. No admin surface is exposed.

### Flutter/mobile UI and local state

N/A in P6-002. No mobile model/API surface is added. Existing Flutter format/analyze/test remains part of the ESC Gate.

### Android identity/build environments

No identity change. ESC/Safe Contract package, Firebase, storage, signing and artifact isolation gates remain mandatory and green.

### Landing/marketing/public feature catalog

No public claim. Workflow Instances remain implementation-stage capability and must not be marketed as an available runtime workflow engine yet.

### Design system/theme

N/A. No UI was added.

### Feature registry/feature flags/subscription plans

No feature-registry/entitlement surface is introduced in this task. Later P13 work must gate the eventual runtime/public surface coherently.

### Search/filter/sort/bulk actions

N/A. Only exact contract instance read/initialize operations are included.

### Reports/import/export

N/A. No reporting, import, export, or bulk mutation surface is added.

### Notifications/escalation

N/A. Initialization emits a WordPress action for downstream integration but does not send notifications or schedule escalation.

### Audit/compliance

Start/update actor and timestamp fields are persisted. No separate transition history exists because transition execution is explicitly outside P6-002.

### Documents/storage

N/A.

### Localization/RTL/LTR/timezone/currency

N/A to persisted domain semantics. Stored timestamps follow existing server UTC conventions; no user-facing copy/UI is added.

### Security/privacy/rate limits

Reviewed. Tenant scope and authorization fail closed before mutation; caller-supplied state is prohibited; no executable expression/condition input is introduced. REST rate limiting is N/A until an API is exposed.

### Performance/concurrency/idempotency

Reviewed and implemented. Exact initialization is idempotent, conflicting rebinding fails closed, source rows are locked, lookup cardinality is bounded, and write-time predicates are revalidated.

### Automated tests

`tests/php/enterprise_contract_workflow_instances_p6_002.php` is explicitly wired into `scripts/test-php.sh` and covers:

- additive schema and migration registration;
- disabled/unlocked tenant enforcement;
- tenant-scoped contract and Workflow identities;
- valid atomic initialization and server-derived initial state;
- exact idempotency;
- conflicting existing instance rejection;
- archived contract rejection;
- missing P4 binding;
- inactive/wrong-Type Workflow;
- draft Workflow Version;
- missing/multiple initial states;
- contract data scope;
- global and tenant-role capability denial;
- write-time concurrent drift and rollback;
- no silent upsert/rebind;
- no transition execution, approvals, P5 rewrite, legacy ContractStatus or ContractService integration;
- backend Gate wiring.

Implementation Gate #374 passed on head `fa2dbd7455280451bdf97df25a6e33e0312d5d5d`; P6-002 passed **66/66 assertions**, with all backend/tenancy regressions, ESC Android/artifact isolation, and Flutter format/analyze/test green.

### Documentation/demo data/onboarding

This Full Impact Review is the implementation documentation. No demo data or onboarding UI is introduced.

### CI/build/release/rollback

Reviewed. Migration is additive and rollback at runtime means transaction rollback for failed initialization. Database schema rollback remains forward-migration discipline; no destructive downgrade is introduced. No verified artifact is produced by this task.

### Backward compatibility

Preserved. Existing `safecontracts_contracts`, `ContractStatus`, `ContractService`, P4 configuration binding behavior, P5 storage, and P6-001 Workflow Definition history are not rewritten.

## Explicit non-goals / next boundaries

P6-002 does not implement:

- transition execution;
- transition authorization;
- conditions or formulas;
- approval routing (P7);
- Workflow transition/history audit rows;
- timers, SLA, reminders, escalation, cron or automation;
- automatic Workflow Version migration/rebinding;
- P5 readiness/lifecycle blocking;
- REST/admin/Flutter surface;
- public landing-page availability claim;
- Safe Contract or `main` changes.

The next P6 task should define explicit transition execution and immutable transition-history semantics on top of this instance foundation, without coupling ESC Workflow state to the legacy ContractStatus column.