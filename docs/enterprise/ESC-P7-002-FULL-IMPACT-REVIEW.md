# ESC-P7-002 Full Impact Review — Immutable Runtime Approval Requests

## Scope

ESC-P7-002 adds the first runtime Approval Engine object: a tenant-owned immutable Approval Request snapshot created from the exact published P7-001 Approval Route for the contract's current P6 Workflow Transition. The task intentionally stops before approval decisions, stage advancement, transition release, rejection handling, delegation, reminders, escalation, or final P6 state movement.

This task is Enterprise Safe Contracts only and does not change Safe Contract/main.

## Database impact

Migration `1.39.0` adds four dedicated tenant-owned tables:

- `safecontracts_workflow_approval_requests`
- `safecontracts_workflow_approval_request_stages`
- `safecontracts_workflow_approval_request_selectors`
- `safecontracts_workflow_approval_request_candidates`

No existing P4/P5/P6/P7-001 or legacy Safe Contract table is altered.

The request snapshots exact contract, Workflow Instance, immutable Workflow/Version, Transition, from/to State, P7-001 route identity, requester, request time, status, and a separate SHA-256 idempotency identity. Stage, selector, and resolved candidate rows snapshot the published route rather than referencing mutable membership state at decision time.

## Request identity and idempotency

Approval Request idempotency is deliberately independent from P6 transition-history idempotency because P7-002 does not move Workflow state or create P6 transition history.

A bounded client request key is normalized server-side and only its SHA-256 hash is persisted. The unique tenant + Workflow Instance + request-key identity provides retry safety.

The SHA-256 request identity is internal-only. `listRequests()` uses a public projection that excludes `request_key_hash`; exact retry uses an internal projection for matching and strips the hash before returning the immutable request; newly created request responses also omit it. The raw request key is never persisted.

Inside the locked transaction:

- an exact retry returns the existing immutable Approval Request before route, candidate, or guard re-evaluation;
- reuse of the same key for a different Transition fails closed;
- a different key cannot create a second `pending` Approval Request for the same instance + exact Transition + locked source State.

This prevents parallel approval processes from being opened for the same logical Transition while preserving deterministic retries.

## Transaction and lock order

Request creation runs in one transaction with the following order:

1. Lock the current tenant contract and exact P6 Workflow Instance.
2. Check Approval Request idempotency.
3. Resolve the exact Transition from the instance's exact published Workflow Version and locked current State.
4. Check for an existing different pending request for the same Transition/source State.
5. Lock and validate the exact published P7-001 Approval Route.
6. Lock and revalidate bounded stage/selector snapshots.
7. Lock the relevant active tenant memberships in deterministic `user_id ASC` order.
8. Resolve and validate distinct candidate users.
9. Re-evaluate P6-004 guards through the existing guard evaluator.
10. Persist immutable request/stage/selector/candidate snapshots.
11. Commit; any failure rolls back the entire operation.

The deterministic membership lock order follows the hardened P7-001 membership ordering and avoids introducing a competing approval-domain lock order.

## Published route snapshot validation

The runtime request never trusts caller-supplied route, stage, selector, candidate, source State, destination State, or quorum values.

It derives the exact Transition from P6, loads the route by tenant + Workflow + exact immutable Version + Transition, then checks route Transition/source/destination snapshots against that authoritative Transition. Stored stage/selector rows are bounded, ordered, and reconstructed into the existing `ApprovalRoutePolicy` so runtime creation and publication share the same declarative semantics.

A stale, orphaned, malformed, unsupported, or over-bounded published route fails closed.

## Candidate resolution

P7-002 supports the P7-001 selector set only:

- `tenant_user`: the selected user must still have an active membership in the same active tenant when the request is opened;
- `tenant_role`: resolves to all active same-tenant memberships currently holding that role.

Candidate users are de-duplicated within each stage, then sorted deterministically. Bounds are fail-closed:

- maximum 256 distinct candidates per stage;
- maximum 1024 candidate memberships/request;
- maximum 1024 total candidate snapshots/request.

A stage resolving to zero active candidates fails closed. For `quorum`, the stored threshold must not exceed the distinct resolved candidate count after de-duplication. This prevents overlapping explicit-user and role selectors from artificially inflating quorum capacity.

The hardened regression explicitly proves that 1025 matching membership rows fail at the request-wide sentinel boundary before any Approval Request insert. The resulting valid candidate set is snapshotted; later membership/role changes do not silently reinterpret an already-open request.

## P6-004 guard impact

A routed request is not persisted until P6-004 guards pass on the same locked contract/instance/Transition identity. This prevents opening an approval process for a Transition that is already invalid under current declarative readiness rules.

The guard evaluator reuses the P5 transition-only locked readiness snapshot under the already-active Approval Request transaction; no nested transaction is introduced. The P4 binding plus bounded active-definition/value ranges remain locked through commit/rollback, preventing check-then-race readiness drift.

Exact Approval Request retries do not re-run guards after the original request committed; they return the immutable request. A later task that actually releases the approved Transition must re-evaluate P6-004 guards before final P6 state mutation because contract readiness may have changed during the approval period.

## No-route behavior

When the exact current Transition has no published P7-001 Approval Route, P7-002 returns an explicit `approval_required = false` result and commits without creating Approval Request snapshot rows or evaluating the routed-request guard callback.

This service does **not** silently execute the P6 Transition. Direct transition execution remains owned by P6; orchestration between no-route transitions, approval creation, decisions, and final release belongs to later integration work.

## P6 runtime and legacy lifecycle isolation

P7-002 never updates `safecontracts_contract_workflow_instances` and never inserts into `safecontracts_contract_workflow_transition_history`.

P6 current State and transition history therefore remain unchanged while an Approval Request is pending. Legacy `safecontracts_contracts.status`, `ContractStatus`, P4 bindings, P5 data, P6 definition/history, and P7-001 route definitions are also not mutated.

## Authorization and data scope

- Reads require `ACCESS` and preserve existing `VIEW_ALL` / own `VIEW_ASSIGNED` contract scope.
- Request creation requires `EDIT_CONTRACTS` plus tenant-role authorization narrowing.
- Core tenant enforcement and locked tenant context remain mandatory.
- Object IDs and Transition codes are identifiers, not authorization.
- Cross-tenant contract, instance, version, route, membership, selector, and candidate access fails closed through tenant-scoped queries and server-derived identities.

## Concurrency and duplicate prevention

The contract + exact Workflow Instance lock serializes request creation with other P6/P7 operations acting on the same runtime object. The pending-request check is performed while that lock is held, so a second idempotency key cannot create another pending approval process for the same Transition/source State.

Candidate membership rows are acquired in ascending `user_id` order. All runtime snapshots are written after candidate and guard validation. Any selector-resolution failure, invalid quorum, candidate overflow, guard failure, insert error, or concurrent identity drift rolls back before a partial request can become visible.

## Security and privacy

The runtime schema stores IDs, canonical route snapshots, candidate user IDs, status, and hashed idempotency identity only. It stores no executable code, credentials, arbitrary SQL, raw client idempotency key, or generic expression language.

The hashed idempotency identity is not exposed by the Approval Request public/list model. Candidate user IDs remain internal tenant runtime identities. P7-002 adds no public API/UI surface.

## API / admin / mobile / notifications / landing impact

No REST endpoint, WordPress admin Approval UI, Flutter Approval UI, notification dispatch, reminders, escalation, report/export surface, plan entitlement, or public landing-page claim is added here. Future surfaces must use the service/repository boundary and must not write runtime snapshot tables directly or expose internal `request_key_hash`.

## Test and Gate evidence

The first fully wired head was `7e638e64863753844948944bf16e0cc252093c32`. Gate #421 reached the backend but stopped in the older P7-001 regression because that test incorrectly required the global schema version to equal `1.38.0` after P7-002 legitimately advanced it to `1.39.0`.

The P7-001 regression was made forward-compatible without weakening its exact Migration0039 registration check in `6e6066063c5a70c327191b4a361cd7e1ff3216bf`. Gate #422 then passed with P7-002 at 60/60 assertions.

Additional P7-002 hardening added:

- future-compatible P7-002 migration-version assertion while preserving exact `1.39.0` registration evidence;
- explicit 1025-member request-wide candidate overflow failure before any request insert;
- schema proof that only the SHA-256 request identity, not the raw key, is persisted;
- internal/public request projection separation so `request_key_hash` cannot leak through list/create/retry results;
- a dedicated explicitly wired internal-identity regression.

Internal identity source hardening landed in `c3530c53cc9efde1635bb49f4278547fbd496fae`; the dedicated identity regression was wired by `bc9fb6c9e00ccfa1f1a1182da01c300ff2f2a574`.

ESC Foundation Gate #431 passed fully on exact head `bc9fb6c9e00ccfa1f1a1182da01c300ff2f2a574`:

- ESC foundation validation green;
- Android identity/release isolation green (15 check groups);
- Enterprise artifact isolation green (11 checks);
- full backend and Enterprise tenancy regressions green;
- P6-003: 77/77 assertions;
- P6-004: 60/60 assertions;
- P7-001: 65/65 assertions;
- P7-002 primary regression: 64/64 assertions;
- P7-002 internal identity regression: 8/8 assertions;
- Flutter formatting, analysis, and tests green.

P7-002 regression coverage includes schema/version registration, request-key normalization, happy-path immutable snapshots, deterministic candidate locking/resolution, candidate de-duplication, no-route semantics, exact retry, idempotency conflict, duplicate pending-process prevention, stale route rejection, zero candidates, quorum after de-duplication, stage and request-wide candidate overflow, guard failure rollback, authorization denial, internal idempotency identity privacy, and explicit absence of P6 state/history mutation.

## Explicit non-goals / next boundary

P7-002 does not include:

- approve/reject/delegate decisions;
- stage advancement or request completion;
- final P6 transition release after approval;
- rejection/cancellation semantics;
- timeout, reminders, escalation, notifications, or cron;
- organization-unit/team selectors;
- REST/admin/Flutter Approval UI;
- public feature marketing;
- legacy ContractStatus synchronization;
- Safe Contract/main changes.

The next Approval task must define immutable decision records and sequential-stage evaluation, including exactly who may decide, duplicate/conflicting decision idempotency, `all`/`quorum` completion semantics, rejection semantics, and the boundary for eventually releasing P6 state movement only after the request is fully approved and guards are revalidated.

## Completion boundary

Source is frozen at/after the Gate #431 validated head `bc9fb6c9e00ccfa1f1a1182da01c300ff2f2a574`. Issue #471 must remain open until the Master Plan and completion record point to this hardened evidence and the final exact-head Gate is green for both `esc-foundation` and `esc-mobile`.
