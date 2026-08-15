# SafeContracts P10 final validation — SC-P10-027..031

This batch validates five production-readiness workstreams without overlapping the team's concurrent SC-P10-017..026 PR. The validation is fail-closed and runs inside the existing `release-readiness` Quality Gates job after repository standards, backend regressions and Flutter checks have passed.

## SC-P10-027 — Audit completeness — Validate

- Pins the complete critical audit registry and rejects duplicate, missing or silently added critical events until the baseline is reviewed.
- Requires every registered critical event to remain mapped by `AuditRecorder`.
- Verifies recursive context sanitization remains present.
- Protects token, secret, password, credential, authorization, private-key, service-account, storage-key, SHA-256, temporary-name and workbook-content/path material from audit context.

## SC-P10-028 — RTL/accessibility pass — Validate

- Revalidates WordPress automatic direction boundaries and decorative-mark semantics.
- Pins keyboard `:focus-visible` coverage for links, buttons, inputs, selects and textareas.
- Revalidates admin RTL selectors and responsive breakpoints.
- Revalidates Flutter Arabic locale variants (`ar-KW`, `AR_eg`), RTL direction, bounded adaptive layout, semantic live states, decorative icon exclusion and fail-closed Retry behavior.
- Requires the existing Flutter widget/regression coverage to remain present.

## SC-P10-029 — Backup/restore verification — Validate

`backup_manifest.py` now derives migration coverage from `Migrator::MIGRATIONS`, not from an unconstrained directory glob.

The gate rejects:

- a registered migration with no source file;
- a migration source file that is not registered;
- duplicate migration versions/classes;
- manifest schema drift from `Migrator::LATEST_VERSION`;
- manifest table drift from the registered migration-owned tables;
- missing SafeContracts option/user-meta selectors;
- weakened external-secret exclusions;
- incomplete restore acceptance evidence in the runbook.

This verifies the backup/restore contract and manifest. A real database snapshot/restore is still an environment-specific go-live exercise and must retain the evidence required by `docs/BACKUP_RESTORE_RUNBOOK.md`.

## SC-P10-030 — Migration/upgrade testing — Validate

- Requires the registered production chain to remain contiguous from `1.0.0` through the current final minor version.
- Requires migration class numbering to match registry order (`Migration0001` ... `Migration0012`).
- Requires every registered class to implement the migration contract.
- Requires `LATEST_VERSION` to equal the final registry entry.
- Protects `maybeMigrate`, skip-on-current-version and version-persistence semantics.
- Verifies the existing runtime regression still invokes migration twice and explicitly asserts latest-version idempotence.

## SC-P10-031 — UAT scenarios — Validate

The machine-readable baseline retains the production scenario identities and role/flow bindings:

- `UAT-001` — system administrator / contract lifecycle
- `UAT-002` — accountant / assigned scope
- `UAT-003` — accountant / collection settlement
- `UAT-004` — accountant / follow-up workflow
- `UAT-005` — manager / report export
- `UAT-006` — viewer / read-only boundary
- `UAT-007` — accountant / mobile notification deep link
- `UAT-008` — system administrator / upgrade, backup and restore

Every scenario must retain non-empty preconditions, steps, expected results and at least two evidence items. `UAT-008` specifically requires backup manifest, row counts, schema version and Quality Gates run evidence.

The validator checks the UAT contract; it does not fabricate business-owner sign-off or a live production restore result.

## CI enforcement

Quality Gates now execute:

```text
python3 scripts/backup_manifest.py --check
python3 scripts/release_readiness.py --check
python3 scripts/p10_validation_027_031.py --check
```

The final validation command is itself protected by `scripts/validate-foundation.py`, so removal from the release job breaks repository standards before a release candidate can pass.

## Coordination

Concurrent PR #356 owns SC-P10-017..026 and changes `tests/php/p10_validation_017_026.php`, `scripts/test-php.sh`, notification delivery hardening and its own evidence document. This batch intentionally avoids those paths except for consuming the shared green backend gate as a prerequisite.
