# ESC-P6-004 Full Impact Review — Declarative Transition Guards

## Scope

ESC-P6-004 adds bounded, tenant-owned, declarative Workflow Transition Guards to the Enterprise Safe Contracts track only. The first and only supported guard type is `dynamic_fields_ready`, backed by the existing P5 Dynamic Field readiness validation. The feature does not introduce a general expression language, approval routing, automation, timers, REST/UI exposure, or synchronization with legacy `ContractStatus`.

## Database impact

- Adds migration `1.37.0` through `Migration0038EnterpriseWorkflowTransitionGuards`.
- Adds only `safecontracts_workflow_transition_guards`; no legacy table is altered.
- Every guard is tenant-owned and scoped to one exact Workflow, immutable Workflow Version, and Transition.
- Guard rows persist ordered position plus immutable Transition/source/destination identity snapshots.
- Unique constraints prevent duplicate guard types and duplicate positions for the same exact version/transition.

## Authoring impact

- Reads require `ACCESS`; guard mutation requires `MANAGE_REFERENCE_DATA`, both narrowed by tenant-role authorization.
- Guard replacement is allowed only on an active tenant Workflow, active Contract Type, exact draft Workflow Version, and exact Transition.
- Authoring runs transactionally and locks the authoritative draft Transition before replacing guard rows.
- Published-version guard configuration is immutable.
- The allowlist accepts only string guard types. `dynamic_fields_ready` is the only supported value; duplicate, unsupported, parameterized/object guards, and oversized lists fail closed.

## Publication impact

Workflow publication still validates the bounded P6-001 graph first. Before the draft version can be published, the same publication transaction checks every stored guard against the current exact Transition graph. Publication fails closed when a guard is orphaned, unsupported, or has a stale Transition/source/destination snapshot.

This prevents a draft graph edit from silently leaving a guard attached to different semantics.

## Runtime execution order

P6-003 transition execution now follows this order inside one transaction:

1. Lock the current tenant contract and exact Workflow Instance.
2. Check the P6-003 idempotency identity.
3. Return the original immutable history immediately for an exact retry; guards are not re-run.
4. Resolve the exact Transition from the instance's exact published Workflow Version and locked current state.
5. Evaluate exact Transition Guards.
6. Insert immutable transition history.
7. Compare-and-set only the dedicated Workflow Instance current state.
8. Commit; any failure rolls back the whole transition.

Unguarded transitions therefore retain P6-003 behavior except for a bounded empty guard lookup.

## P5 readiness and concurrency impact

`dynamic_fields_ready` reuses P5-003 validation semantics, but through a transition-only locked snapshot path. The transition already owns the outer database transaction and the contract/Workflow Instance lock. The readiness path then locks, in bounded tenant/contract scope:

- the P4 Contract Type binding;
- the active Dynamic Field definition range;
- the contract's set Dynamic Field value range.

The readiness decision is therefore held stable until the transition commits or rolls back. Concurrent writes affecting those indexed ranges must serialize behind the guard transaction rather than changing readiness between validation and state mutation.

No nested transaction is introduced. Runtime lock ordering remains contract/instance first, then subordinate P4/P5 rows/ranges. Guard authoring uses draft Workflow locks and does not acquire runtime contract locks, keeping authoring and execution lock domains separate.

## Idempotency impact

Exact P6-003 retry semantics are preserved deliberately: the idempotency history check occurs before guard evaluation. Once a transition has committed, retrying the same normalized idempotency key and Transition returns the original immutable history without re-evaluating current Dynamic Field readiness or generating another transition/history row.

Reusing an idempotency key for a different operation remains fail-closed under P6-003.

## Authorization and tenant isolation

- Object IDs and Transition codes remain identifiers, not authorization.
- All guard repository access requires core tenant enforcement and the locked tenant context.
- Authoring and runtime lookups bind tenant + Workflow + exact Workflow Version + Transition identity.
- P5 readiness retains the existing contract data-scope rules and tenant-role capability ceiling.
- Cross-tenant/foreign IDs cannot be used to author or evaluate a guard.

## Compatibility and regression impact

Gate #393 exposed one compatibility issue in the P6-003 test double: its fake Transition row omitted redundant `workflow_id` and `workflow_version_id` fields that the new evaluator requires. Production SQL already selected those fields. The fix enriches guard-evaluation input from the already-authoritative locked Workflow Instance when those redundant fields are absent, preserving production identity checks and older P6-003 test-double compatibility.

Gate #394 on commit `ce09a47caf8ac7ce9cee0aee3fff2d3ada0277a2` passed:

- ESC foundation validation;
- Android identity/release isolation;
- Enterprise artifact isolation;
- full backend and Enterprise tenancy regressions;
- P6-003: 77/77 assertions;
- P6-004: 60/60 assertions;
- Flutter formatting, analysis, and tests.

Existing P5 regressions also remained green, including P5-002 (81 assertions) and P5-003 (36 assertions), confirming the existing value-write API semantics were not changed.

## Explicit non-goals / unaffected surfaces

- No generic condition or expression engine; no `eval`, scripts, callbacks, regex rules, or arbitrary guard parameters.
- No approval routing; that remains a later P7 concern.
- No timers, SLA/escalation, cron, or automatic transitions.
- No Workflow Version migration/rebinding.
- No REST endpoint, WordPress admin UI, or Flutter UI for guards yet.
- No public landing-page or plan claim is added.
- No mutation or synchronization of `safecontracts_contracts.status` / legacy `ContractStatus`.
- No P4/P5 data is mutated by Workflow execution; P5 is read/locked only for readiness.
- No Safe Contract/main-track changes are included.

## Residual risks and follow-up boundary

- Future guard types must remain explicitly allowlisted and require their own bounded validation/locking analysis before publication/runtime support is added.
- P7 approvals must layer on top of P6 transition execution without turning guard configuration into executable policy code.
- Any future REST/admin/mobile authoring surface must preserve the exact tenant/version/transition authorization and published immutability established here.

## Completion evidence

Implementation validation source: ESC Foundation Gate run #394, head `ce09a47caf8ac7ce9cee0aee3fff2d3ada0277a2`, fully green. Exact-source completion evidence is recorded separately after documentation/status commits are validated on their own final head.
