# ESC-P7-003 Full Impact Review — Immutable Approval Decisions and Sequential Stage Progression

## Scope

ESC-P7-003 adds tenant-owned immutable `approve` / `reject` Decision records over the immutable P7-002 Approval Request snapshots and derives sequential approval-stage progression from those snapshots. It intentionally stops before releasing the approved P6 Workflow Transition.

The task remains Enterprise Safe Contracts only. It does not modify Safe Contract/main, legacy ContractStatus semantics, public marketing surfaces, REST/admin/Flutter approval UI, timers, delegation, reminders, escalation or notification delivery.

## Database impact

Migration `1.40.0` adds one dedicated table:

- `safecontracts_workflow_approval_decisions`

Each Decision stores tenant, exact Approval Request, exact request-stage snapshot, deciding user, canonical action, SHA-256 idempotency identity, bounded optional comment and server timestamp.

The migration is additive. P7-001 route definitions and P7-002 request/stage/selector/candidate snapshot schemas are not altered. P6 Workflow Instance/history schemas are not altered.

Runtime mutation of existing P7-002 storage is deliberately minimal: only `safecontracts_workflow_approval_requests.status` may move from `pending` to `approved` or `rejected` when a terminal approval result is reached. Request stage, selector and candidate snapshots remain immutable.

## Decision identity and idempotency

Decision actions are allowlisted to `approve` and `reject` only. A bounded client idempotency key is normalized and only its SHA-256 hash is persisted. Public decision read/create/retry models exclude the stored hash.

The repository locks the exact tenant Approval Request first, then checks the decision hash before mutable contract or stage revalidation. An exact retry therefore returns the original immutable Decision even after the request later becomes terminal. Retry does not create a duplicate Decision or repeat request-status mutation.

A decision key reused for another request, actor or action fails closed. Because the active stage is always derived server-side and callers cannot supply a stage, reusing the same key later cannot create a Decision in another stage; it resolves to the original immutable Decision. A genuinely new later-stage Decision requires a new idempotency key.

A separate unique `(tenant_id, request_stage_id, user_id)` constraint guarantees at most one effective terminal Decision per user per stage. A second key from the same user for the same stage fails closed rather than rewriting history.

## Transaction and lock order

New Decision creation executes in one transaction:

1. Lock exact current-tenant Approval Request.
2. Check exact Decision idempotency identity.
3. Reject a new key if the Approval Request is already terminal.
4. Lock the request's same-tenant unarchived contract to prevent archive/check races.
5. Lock the bounded immutable request-stage snapshot in canonical stage order.
6. Lock the bounded immutable candidate snapshot in deterministic stage/user order.
7. Lock bounded Decision history in deterministic stage/user/id order.
8. Validate snapshot integrity and derive the current active stage.
9. Verify the actor is an immutable candidate of that active stage and has no existing effective Decision there.
10. Insert the immutable Decision.
11. If terminal, compare-and-set only the Approval Request `pending` status to `approved` or `rejected`.
12. Commit; every failure rolls back the entire operation.

Exact retries intentionally exit after the authoritative existing Decision and referenced stage are validated; they do not depend on later mutable contract state because they are not a new business mutation.

## Immutable candidate eligibility

P7-003 treats the P7-002 candidate snapshot as authoritative eligibility for the already-open Approval Request. Current tenant membership or role changes do not reinterpret that immutable candidate set.

Authorization still requires the currently authenticated current-tenant user and appropriate Enterprise capability. Candidate identity is necessary for the approval action but object IDs and candidate rows are never sufficient authorization by themselves.

Candidate snapshot reads fail closed when they contain:

- orphan stage identities;
- invalid user identities;
- duplicate stage/user identities;
- zero candidates for a stage;
- more than 256 candidates for one stage;
- more than 1024 candidates for the request.

Decision history must also map exactly to immutable candidates and supported actions. Duplicate Decision history, unsupported actions or a stored rejection while the request still claims `pending` are treated as inconsistent state and fail closed.

## Sequential stage progression

P7-003 does not add mutable stage status columns. Stage state is derived from immutable stage policy, immutable candidates and immutable Decisions.

Stages are processed strictly by contiguous `position_no` order. The first incomplete stage is the only active stage. Any Decision already present in a future stage before activation is inconsistent and fails closed.

For `all`, a stage completes only when every distinct snapshotted candidate approved. For `quorum`, it completes when distinct approvals reach the snapshotted threshold. Quorum must remain between 1 and the immutable candidate count. `all` retains canonical `required_approvals_snapshot = 0`.

Completion of a non-final stage creates no mutable advancement marker; on the next Decision command, the active stage is derived again and becomes the next incomplete stage.

A valid `reject` from a candidate of the active stage immediately changes the Approval Request to `rejected`. No later stage becomes actionable.

When the final stage completes successfully, the Approval Request changes to `approved`. No P6 state movement occurs in P7-003.

## P6 Workflow impact and final-release boundary

P7-003 never updates `safecontracts_contract_workflow_instances` and never inserts `safecontracts_contract_workflow_transition_history`.

An `approved` Approval Request is therefore an authorization/runtime fact, not a completed Workflow transition. A later explicit task must release the exact approved Transition by re-locking the P6 instance, verifying its current state still matches the immutable Approval Request source snapshot, revalidating P6-004 guards, and executing the P6 transition exactly once with a defined idempotency relationship to transition history.

This separation prevents approval decisions from silently moving lifecycle state and prevents stale approvals from bypassing readiness rules that may change while approval is pending.

## Authorization and contract data scope

Decision-history reads require `ACCESS` and preserve existing contract data scope: `VIEW_ALL` or the established own `VIEW_ASSIGNED` accountant scope.

New Decisions require `EDIT_CONTRACTS`, current tenant context, tenant-role narrowing through `TenantAuthorization`, an authenticated user, and the same contract data scope. Archived contracts are rejected by the service and revalidated under lock in the repository for new mutations.

Cross-tenant request IDs fail through tenant-scoped request lookup and locked repository queries. Candidate/request IDs are never authorization tokens.

## Concurrency and duplicate prevention

The locked Approval Request serializes competing Decisions for one approval process. The unique decision-key hash and unique stage/user identity provide database-level protection in addition to repository validation.

All stage/candidate/Decision snapshots are bounded and locked before deriving the active stage. Terminal request status is compare-and-set from `pending`, so concurrent attempts cannot silently overwrite a terminal result.

There is intentionally no mutable per-stage state that could double-advance or drift from immutable Decision history.

## Security and privacy

Raw Decision idempotency keys are never persisted or returned. Optional comments are bounded to 2000 bytes, reject null bytes, contain no executable semantics, and missing comments persist as SQL `NULL`.

P7-003 introduces no expression, template, regex, script, callback or arbitrary SQL execution surface. Public/internal read models are separated so the SHA-256 identity is internal only.

## API / admin / mobile / notifications / landing impact

No REST endpoint, WordPress approval UI, Flutter approval UI, offline approval state, notification delivery, reminder/escalation job, report/export surface, plan entitlement or public landing-page claim is included in P7-003.

The service emits a bounded `safecontracts_enterprise_workflow_approval_decided` domain hook only for a new non-idempotent Decision. Future notification/integration work may consume it, but P7-003 itself performs no delivery.

## Localization / theme / artifacts

No new user-facing UI copy, design tokens, RTL/LTR surface, Android identity, Firebase configuration or release artifact format is introduced. Existing ESC Android/APK and verified-artifact isolation gates remain mandatory and green.

## Test and Gate evidence

Foundation head `a2949ab75c1aabf6688a930dba051aa1677c6efb` passed Gate #440 with P7-003 foundation 27/27 assertions and both `esc-foundation` and `esc-mobile` green.

The first fully wired runtime head `d981d0a55164b9d2ed5e367e5af37b5281e80461` passed the backend regression with P7-003 runtime 54/54 assertions. Review then hardened contract re-locking, orphan/duplicate candidate rejection and SQL `NULL` comment persistence in source commit `ede03ebeb78f1cbbb2198738ce45a5eec5359f11`, with regression hardening completed on exact-source head `cdbf3604329fc3d72d90dacbb8735fe74dee4c94`.

On `cdbf3604329fc3d72d90dacbb8735fe74dee4c94`:

- ESC Foundation Gate #447 passed fully;
- dedicated ESC P7-003 Approval Decision Gate passed;
- P7-003 foundation: 27/27 assertions;
- P7-003 runtime: 65/65 assertions;
- P7-002 request: 64/64 assertions;
- P7-002 internal identity: 8/8 assertions;
- P7-001: 65/65 assertions;
- P6-004: 60/60 assertions;
- P6-003: 77/77 assertions;
- all backend and Enterprise tenancy regressions green;
- ESC Android identity/artifact isolation green;
- Flutter format/analyze/test green.

## Explicit non-goals / next boundary

P7-003 does not include:

- final P6 Workflow Transition release/execution;
- P6-004 guard revalidation at final movement;
- delegation, reassignment or substitution;
- abstain/request-changes actions;
- timers, expiry, reminders, escalation or notification delivery;
- REST/admin/Flutter approval UI;
- public feature marketing;
- legacy `safecontracts_contracts.status` / `ContractStatus` synchronization;
- Safe Contract/main changes.

The next Approval Engine task should release an already-approved request through the exact P6 Transition atomically and idempotently, only after authoritative current-state matching and fresh P6-004 guard revalidation.