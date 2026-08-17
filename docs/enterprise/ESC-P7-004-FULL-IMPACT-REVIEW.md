# ESC-P7-004 Full Impact Review — Exactly-Once Approval Release into P6

## Scope

ESC-P7-004 completes the first end-to-end Approval Engine runtime loop by allowing an already-approved P7-002/P7-003 Approval Request to release its exact P6 Workflow Transition exactly once. Release is not a second state machine: it reuses the authoritative P6 transition transaction, revalidates P6-004 guards immediately before movement, and persists immutable Approval Release evidence in the same transaction as P6 transition history and Workflow Instance compare-and-set state movement.

The task is Enterprise Safe Contracts only. It does not change Safe Contract/main, legacy `ContractStatus`, public landing claims, REST/admin/Flutter approval UI, notification delivery, delegation, escalation or timers.

## Database impact

Migration `1.41.0` adds one tenant-owned table:

- `safecontracts_workflow_approval_releases`

Each Release records:

- exact Approval Request identity;
- exact P6 Workflow Instance identity;
- exact immutable P6 transition-history row identity;
- SHA-256 release idempotency identity;
- releasing actor and server time.

Database uniqueness enforces:

- one Release per tenant + Approval Request;
- one use of a Release idempotency hash per tenant;
- one Approval Release per tenant + P6 transition-history row.

The migration is additive. It does not alter P6 Workflow Instance/history tables, P7-001 route tables, P7-002 request/stage/selector/candidate snapshot tables, P7-003 Decision rows or legacy Contract storage.

## Domain-separated idempotency

The client supplies one bounded Approval Release key. The raw key is never persisted or returned.

`ApprovalReleasePolicy` derives two SHA-256 identities:

- the Approval Release evidence identity;
- a separately namespaced P6 transition-request identity using `esc-approval-release:v1:`.

The two hashes are intentionally different so an Approval Release cannot collide semantically with a normal P6 caller idempotency key.

An exact already-committed Release is returned idempotently after current authorization and contract data-scope checks but before later mutable archive/request-status guards. This preserves retry semantics if the contract is archived or other mutable lifecycle state changes after a successful Release, while still preventing unauthorized reads or retries.

A different Release key for an already-released Approval Request fails closed. Reusing one Release key for another Approval Request also fails closed.

## Authoritative P6 transaction reuse

P7-004 does not duplicate P6 transition SQL. `ContractWorkflowTransitionRepository::execute()` was extended only through appended optional parameters, preserving existing source compatibility:

- `afterInstanceLock` — runs after authoritative P6 instance locking and P6 idempotency lookup, before Transition resolution;
- existing `beforeMutation` — runs after exact Transition resolution and before P6 history/state mutation;
- `afterMutation` — runs after P6 history insert + Workflow Instance CAS but before final commit;
- `allowApprovalRouted` — defaults to `false`.

Existing P6 direct callers continue using the old argument shape. P7-004 is the explicit caller that opts into routed execution.

For a new Approval Release, the effective transaction is:

1. `START TRANSACTION` in P6.
2. Lock current-tenant unarchived Contract + exact P6 Workflow Instance.
3. Check P6 transition idempotency first.
4. Lock/validate exact approved Approval Request, immutable route identity and Release uniqueness through `afterInstanceLock`.
5. Resolve exact Transition from immutable Workflow Version + locked current source State.
6. Validate Transition identity/source/destination/route against the immutable approved request.
7. Re-evaluate P6-004 guards immediately before movement.
8. Insert immutable P6 transition history.
9. Compare-and-set P6 Workflow Instance current State.
10. Insert immutable Approval Release evidence linked to the newly created P6 history row through `afterMutation`.
11. `COMMIT`.

Any exception at any point causes P6 to `ROLLBACK`. In particular, a Release-evidence insert failure after P6 history/CAS rolls both P6 mutations back because evidence is still before the authoritative final commit.

No Approval repository/service opens a nested transaction.

## Direct P6 approval-bypass protection

The authoritative P6 Transition resolution now performs a `LEFT JOIN` against the exact P7-001 Approval Route identity for the same tenant, Workflow, immutable Workflow Version and Transition.

For ordinary P6 execution, `allowApprovalRouted` defaults to `false`. If an exact Approval Route exists, P6 fails closed before guards, history insertion or Workflow Instance state movement with the requirement that the Transition be released through an approved Approval Request.

For a Transition with no Approval Route, existing P6 behavior remains unchanged.

P6 does not own P7 Approval Request, Decision or Release orchestration. Its only P7-aware responsibility is the route-presence execution gate plus the generic transaction callbacks required for atomic integration.

## Approved Request validation

P7-004 never trusts caller-supplied Workflow/Version/Transition/from/to identities. The caller supplies only Approval Request ID plus Release idempotency key.

Inside the locked P6 transaction, the Approval Release repository validates that the request:

- belongs to the current tenant;
- is `approved`;
- belongs to the exact locked Contract and Workflow Instance;
- snapshots the exact Workflow + immutable Workflow Version;
- snapshots the same locked P6 source State ID/code;
- still points to the exact immutable Approval Route;
- matches the route's Transition/source/destination snapshots.

After P6 resolves the exact Transition, the request is checked again against the resolved Workflow/Version/Transition/from/to/route identity and codes.

If P6 state moved independently after approval, the approved request is stale and cannot release.

## Fresh P6-004 guard revalidation

P7-002 evaluated P6-004 guards when the Approval Request was opened, but readiness may change during the approval period. P7-004 therefore reuses `WorkflowTransitionGuardEvaluator::assertAllowed()` again inside the final P6 transaction after exact Transition resolution and before P6 history/state mutation.

A fresh guard failure rolls the transaction back before P6 movement and before Release evidence.

This prevents stale approval from bypassing current Dynamic Field readiness or future supported guard semantics.

## Exactly-once Release evidence

A successful new Release produces exactly one immutable P6 history row and one immutable Approval Release row linked by `transition_history_id`.

The Release table's tenant/request, tenant/release-key and tenant/history uniqueness constraints provide database-level duplicate prevention in addition to transactional repository checks.

If P6 identifies an idempotent history retry during a concurrent exact-key attempt, the service requires matching committed Approval Release evidence; an orphan P6 history without evidence is treated as inconsistent rather than silently reported as a successful Approval Release.

## Authorization and contract data scope

Release evidence reads require `ACCESS` and preserve the existing Contract data-scope rule: `VIEW_ALL` or established own `VIEW_ASSIGNED` accountant scope.

A new Release requires:

- `EDIT_CONTRACTS`;
- active Enterprise tenant context;
- `TenantAuthorization` role narrowing;
- authenticated user;
- existing Contract data scope.

The releasing actor does not need to be one of the approvers once the immutable Approval Request is fully approved, but must be authorized to edit the Contract.

Object IDs are never authorization. All request/release/route/history lookups are tenant scoped.

## Concurrency and rollback

The locked P6 Workflow Instance serializes final movement for the same runtime object. Release uniqueness is checked under the same P6 transaction before Transition resolution.

P6 state movement uses the existing compare-and-set update against the locked source State, Workflow and immutable Workflow Version. The Release therefore fails rather than silently moving from a state different from the approved snapshot.

Adversarial regression proves:

- routed direct P6 execution is blocked before P6 history/state mutation;
- no-route direct P6 execution still commits normally;
- stale approved request fails before Transition movement;
- fresh guard failure rolls back before P6 history/state;
- Release-evidence failure after P6 history/CAS still rolls the whole transaction back;
- Transition destination snapshot mismatch fails before P6 history;
- same request cannot receive another Release identity;
- the same Release key cannot target another request.

## P7 immutability impact

P7-004 does not rewrite:

- P7-001 Approval Route definitions;
- P7-002 request stage/selector/candidate snapshots;
- P7-003 immutable Decision history.

The P7-002 request remains an approved historical approval fact. Final Workflow movement is represented separately by P6 transition history plus P7-004 immutable Release evidence.

## Security and privacy

Raw Release and derived P6 request keys are not exposed. Public Release/history results omit stored internal hash identities.

P7-004 introduces no expression language, callback supplied by external clients, executable template, arbitrary SQL or script surface. The transaction callbacks are internal typed PHP integration points between trusted domain services.

## API / admin / mobile / notifications / landing impact

P7-004 adds no REST endpoint, WordPress approval UI, Flutter approval UI, offline approval state, notification delivery, reminder/escalation job, bulk release, report/export surface, plan entitlement or public landing-page claim.

A bounded `safecontracts_enterprise_workflow_approval_released` domain action fires only for a newly committed Release, never an exact retry. Future notification/integration work may consume it; P7-004 itself performs no delivery.

## Android / artifact isolation

No Android identity, Firebase registration, package/application ID, notification channel, signing lineage or APK artifact behavior changes. ESC Android identity and verified-artifact isolation gates remain green and separate from Safe Contract.

## Test and Gate evidence

The P7-004 regression set is explicitly wired into `scripts/test-php.sh`:

- Approval Release foundation: **22/22 assertions**;
- Approval Release runtime/atomicity: **49/49 assertions**;
- Approval Release service boundary: **10/10 assertions**.

Backward-compatibility source assertions in P6-003 and P7-001 were updated only where their previous wording incorrectly prohibited the new route-presence bypass gate. Their behavioral coverage remains intact:

- P6-003: **77/77**;
- P6-004: **60/60**;
- P7-001: **65/65**;
- P7-002: **64/64** plus internal identity **8/8**;
- P7-003: foundation **27/27** plus runtime **65/65**.

Production source last changed in `e9d92192383c8f4960696b0fb3ab05ae526eae6e` to preserve exact Release retry semantics across later mutable archive/request lifecycle changes. The service-boundary regression was corrected without production changes in `56446281066ef587260d5f7c6b5e86e416d5bc0c`.

Exact source + regression + dedicated workflow validation head is `7e32b166aa45944f0e3b1dd7f162e79f6c861a39`.

On that exact head:

- ESC Foundation Gate run #470 / run `31995262318` passed;
- `esc-foundation` passed;
- `esc-mobile` passed including Flutter format/analyze/test;
- Android identity isolation passed;
- Enterprise verified artifact isolation passed;
- full backend/Enterprise tenancy suite passed;
- P7-004 passed 22/22 + 49/49 + 10/10;
- dedicated `ESC P7-004 Approval Release Gate` run #1 / run `31995262373` passed on the same head.

## Explicit non-goals / next boundary

P7-004 does not include:

- delegation/reassignment/substitution;
- abstain/request-changes actions;
- reminders, expiry, escalation, cron or notification delivery;
- REST/admin/Flutter Approval UI;
- bulk release;
- Workflow Version migration;
- public feature marketing;
- legacy `safecontracts_contracts.status` / `ContractStatus` synchronization;
- Safe Contract/main changes.

The Approval Engine core loop is now structurally complete through route definition → immutable request → immutable decisions/stage progression → exactly-once final P6 release. Subsequent P7 tasks should add controlled product surfaces and operational behaviors without weakening this transaction boundary.