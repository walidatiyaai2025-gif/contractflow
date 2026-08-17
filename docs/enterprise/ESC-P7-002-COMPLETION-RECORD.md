# ESC-P7-002 Completion Record

Status: completion candidate pending final status-head Gate.

- Issue: #471 — ESC-P7-002 — Create immutable runtime Approval Requests from published Transition routes.
- Initial fully wired head: `7e638e64863753844948944bf16e0cc252093c32`.
- Gate #421 stopped before P7-002 because the older P7-001 regression required an exact global schema version instead of permitting future migrations.
- Forward-compatible P7-001 migration assertion fix head: `6e6066063c5a70c327191b4a361cd7e1ff3216bf`.
- Gate #422 passed fully on that head; P7-002 passed 60/60 assertions.
- Full Impact Review: `docs/enterprise/ESC-P7-002-FULL-IMPACT-REVIEW.md`.
- Candidate-bound hardening head: `cb6e34b6d0424a3934884098a7653f8409197129`; Gate #424 passed fully with P7-002 64/64 assertions.
- Production security hardening commit: `c3530c53cc9efde1635bb49f4278547fbd496fae` keeps Approval Request `request_key_hash` internal to persistence/idempotency matching and excludes it from public request/list/retry models.
- Security appendix: `docs/enterprise/ESC-P7-002-IDEMPOTENCY-HARDENING.md`.
- The first inline security regression introduced a PHP quoting parse error in Gate #428; production source was not changed for that test-only failure.
- The internal/public idempotency boundary was moved into dedicated regression `tests/php/enterprise_workflow_approval_request_identity_p7_002.php` and explicitly wired into the backend Gate.
- Exact-source Gate #431 passed fully on head `bc9fb6c9e00ccfa1f1a1182da01c300ff2f2a574`:
  - P7-002 main regression: 64/64 assertions.
  - P7-002 internal idempotency identity regression: 8/8 assertions.
  - P7-001: 65/65 assertions.
  - P6-004: 60/60 assertions.
  - P6-003: 77/77 assertions.
  - all backend/Enterprise tenancy regressions green;
  - Android identity/artifact isolation green;
  - Flutter format/analyze/test green.
- Compare from production hardening `c3530c53cc9efde1635bb49f4278547fbd496fae` to Gate head `bc9fb6c9e00ccfa1f1a1182da01c300ff2f2a574` contains only the dedicated security regression and one Gate-wiring line; there is no production source drift.
- Runtime invariant: Approval Request creation never updates the P6 Workflow Instance current State and never inserts P6 transition history.
- Exact retries return the immutable Approval Request without exposing the stored idempotency hash; a different key cannot create a second pending process for the same exact Transition/source State.
- Candidate resolution is tenant-scoped, active-membership only, deterministic, de-duplicated and bounded.
- P6-004 guards run before new request snapshot persistence; later final transition release must revalidate guards again.
- No approve/reject/delegate decisions, stage advancement, final P6 transition release, REST/admin/Flutter Approval UI, legacy ContractStatus synchronization, or Safe Contract/main changes are included.

The canonical Master Plan must record the Gate #431 security-hardening evidence and mark P7-002 as a final completion candidate. Issue #471 may close only after that final docs/status head receives a fully green Gate. No production source change is permitted after the exact-source Gate #431 evidence for this task.
