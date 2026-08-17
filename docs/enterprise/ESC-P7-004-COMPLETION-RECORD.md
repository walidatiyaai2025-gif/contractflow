# ESC-P7-004 Completion Record

Status: exact-source implementation validated; pending final documentation/status-head Gates before Issue #473 closure.

- Issue: #473 — ESC-P7-004 — Release approved Approval Requests through P6 transitions exactly once.
- Schema: additive `1.41.0` Approval Release evidence in `safecontracts_workflow_approval_releases`.
- Release identity is SHA-256 only and is domain-separated from the derived P6 transition request identity.
- One tenant Approval Request can produce at most one Release; one Release key can be used once per tenant; one P6 transition-history row can link to at most one Approval Release.
- P6 transition execution was extended through appended optional callbacks and `allowApprovalRouted=false` default, preserving existing caller compatibility.
- Direct P6 execution of a Transition with an exact P7-001 Approval Route now fails closed before history/state movement; no-route P6 execution remains unchanged.
- P7-004 release opts into routed execution and reuses the authoritative P6 transaction instead of duplicating P6 transition SQL.
- New Release transaction validates the exact approved request/route against the locked P6 Contract/Instance/current State, resolves the exact Transition, revalidates P6-004 guards, inserts P6 history, compare-and-set updates P6 state, inserts immutable Release evidence, then commits.
- Release-evidence failure after P6 history/CAS rolls the complete P6 transaction back.
- Fresh guard failure occurs before P6 history/state movement.
- Exact already-committed Release retry is returned after authorization + contract data-scope checks and before later mutable archive/request-status guards, preserving idempotency without permitting a new mutation.
- Raw Release/P6 idempotency identities are not returned.
- P7-001 definitions, P7-002 stage/selector/candidate snapshots and P7-003 Decisions remain immutable.
- No legacy `ContractStatus`, Safe Contract/main, REST/admin/Flutter Approval UI, notification delivery or public landing changes are included.

## Regression evidence

P7-004 is explicitly wired into the ESC backend Gate:

- foundation: 22/22 assertions;
- runtime/atomicity: 49/49 assertions;
- service boundary: 10/10 assertions.

Compatibility suite on the exact validation head also passes:

- P6-001: 89/89;
- P6-002: 66/66;
- P6-003: 77/77;
- P6-004: 60/60;
- P7-001: 65/65;
- P7-002: 64/64;
- P7-002 internal identity: 8/8;
- P7-003 foundation: 27/27;
- P7-003 runtime: 65/65.

## Source and Gate provenance

- Production source last changed at `e9d92192383c8f4960696b0fb3ab05ae526eae6e` for exact Release retry behavior across later mutable archive/request lifecycle changes.
- Service-ordering regression was corrected without production changes at `56446281066ef587260d5f7c6b5e86e416d5bc0c`.
- Exact source + regressions + dedicated workflow validation head: `7e32b166aa45944f0e3b1dd7f162e79f6c861a39`.
- ESC Foundation Gate run #470 / Actions run `31995262318` passed on that exact head with `esc-foundation` and `esc-mobile` green, including Android/artifact isolation and Flutter format/analyze/test.
- Dedicated `ESC P7-004 Approval Release Gate` run #1 / Actions run `31995262373` passed on the same exact head.
- Full Impact Review: `docs/enterprise/ESC-P7-004-FULL-IMPACT-REVIEW.md`.

No production source change is permitted for P7-004 after the exact validation head without reopening implementation validation. Issue #473 may close only after the final documentation/Master Plan status head receives fully green global ESC and dedicated P7-004 Gates.