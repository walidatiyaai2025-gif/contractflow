# ESC-P7-002 Idempotency Identity Hardening

## Purpose

Approval Request idempotency is a persistence concern, not a public runtime field. The SHA-256 `request_key_hash` exists only to match exact retries and detect conflicting use of a client idempotency key. It must not be returned from normal Approval Request models or list responses.

## Production change

Commit `c3530c53cc9efde1635bb49f4278547fbd496fae` splits Approval Request columns into internal and public projections:

- `INTERNAL_REQUEST_COLUMNS` includes `request_key_hash` for the locked idempotency lookup only.
- `PUBLIC_REQUEST_COLUMNS` excludes `request_key_hash` from normal request listing/output.
- Exact retry matching uses the internal row, verifies the hash-bound operation, then removes the hash before returning the immutable request model.
- Newly created request responses likewise expose no idempotency hash.

The raw client idempotency key was already never persisted; only its SHA-256 hash remains stored in the dedicated runtime table.

## Regression structure

The first inline regression attempt introduced a PHP quoting parse error in Gate #428. Production source was not changed to address that test-only failure.

The hardening regression was isolated into `tests/php/enterprise_workflow_approval_request_identity_p7_002.php` and explicitly wired into the backend Gate. This keeps the main P7-002 behavioral regression focused while independently proving the internal/public identity boundary.

## Exact-source evidence

Gate #431 on head `bc9fb6c9e00ccfa1f1a1182da01c300ff2f2a574` is fully green:

- P7-002 main regression: 64/64 assertions.
- P7-002 internal identity regression: 8/8 assertions.
- P7-001: 65/65 assertions.
- P6-004: 60/60 assertions.
- P6-003: 77/77 assertions.
- Full backend and Enterprise tenancy regressions passed.
- ESC Android identity/release isolation and verified-artifact isolation passed.
- Flutter format, analyze and tests passed.

A compare from production-hardening commit `c3530c53cc9efde1635bb49f4278547fbd496fae` to Gate head `bc9fb6c9e00ccfa1f1a1182da01c300ff2f2a574` shows only the dedicated identity regression file and one Gate-wiring line. There is no production source drift after the hardening commit.
