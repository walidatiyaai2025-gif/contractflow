# ESC-P7-003 Completion Record

Status: exact-source implementation validated; pending final docs/status-head Gates before Issue #472 closure.

- Issue: #472 — ESC-P7-003 — Add immutable Approval Decisions and sequential stage progression.
- Foundation schema/policy Gate #440 passed on `a2949ab75c1aabf6688a930dba051aa1677c6efb` with P7-003 foundation 27/27 and global ESC foundation/mobile green.
- Runtime repository/service implementation introduced immutable candidate-only `approve` / `reject` Decisions, derived sequential `all` / `quorum` stage progression, terminal request `approved` / `rejected` status and no P6 state/history movement.
- First fully wired runtime backend Gate #444 passed on `d981d0a55164b9d2ed5e367e5af37b5281e80461` with runtime 54/54 assertions.
- Runtime hardening source commit `ede03ebeb78f1cbbb2198738ce45a5eec5359f11` added same-transaction active-contract locking for new Decisions, fail-closed orphan/duplicate candidate validation and true SQL `NULL` persistence for absent comments without weakening idempotency-first retry behavior.
- Exact-source regression head: `cdbf3604329fc3d72d90dacbb8735fe74dee4c94`.
- Full Impact Review: `docs/enterprise/ESC-P7-003-FULL-IMPACT-REVIEW.md`.
- Exact-source ESC Foundation Gate #447 passed fully on `cdbf3604329fc3d72d90dacbb8735fe74dee4c94`:
  - P7-003 foundation: 27/27 assertions;
  - P7-003 runtime: 65/65 assertions;
  - P7-002 request: 64/64 assertions;
  - P7-002 internal identity: 8/8 assertions;
  - P7-001: 65/65 assertions;
  - P6-004: 60/60 assertions;
  - P6-003: 77/77 assertions;
  - all backend/Enterprise tenancy regressions green;
  - ESC Android identity and artifact isolation green;
  - Flutter format/analyze/test green.
- The dedicated `ESC P7-003 Approval Decision Gate` also passed on the same exact source head.
- Decision idempotency hash is internal-only. Exact retries return the original immutable Decision even after terminal request completion and cannot create a later-stage Decision; a genuine later-stage Decision requires a new key.
- One effective Decision per user/stage is enforced by runtime validation and database uniqueness.
- Current active stage is derived from immutable stage/candidate/Decision history; no mutable per-stage advancement status is introduced.
- `all` requires all distinct immutable candidates; `quorum` uses the snapshotted threshold against distinct candidates; reject is immediately terminal.
- Only the P7-002 Approval Request `status` may move from pending to approved/rejected. P7-001 definitions and P7-002 stage/selector/candidate snapshots remain immutable.
- P7-003 never updates the P6 Workflow Instance and never inserts P6 transition history.
- Final P6 transition release remains a later task and must re-lock the authoritative instance/current state and revalidate P6-004 guards before movement.
- No REST/admin/Flutter approval UI, notification delivery, delegation/escalation, public landing claim, legacy ContractStatus mutation or Safe Contract/main change is included.

Issue #472 may close only after the final documentation/status head receives fully green global ESC and P7-003-specific Gates. No production source change is permitted after exact-source head `cdbf3604329fc3d72d90dacbb8735fe74dee4c94` for this task.