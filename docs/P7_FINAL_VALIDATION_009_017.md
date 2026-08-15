# P7 final import validation — SC-P7-009..017

This slice completes the SafeContracts P7 Import phase by adding import-run summary/audit evidence and validating the complete XLSX pipeline against production boundaries.

## SC-P7-009 — Import summary & audit — Implement

- Added `ImportSummaryService` with a `RUN_IMPORTS` capability boundary.
- Summary exposes run status, selected worksheet, duplicate strategy, row/result counts, actor and timestamps.
- Workbook-facing summary is deliberately limited to display filename, byte size, sheet count and mapped-field count.
- Private staging key, SHA-256 fingerprint and raw workbook content are not returned by the summary service or audit context.
- Audit lifecycle now records upload, discovery, mapping, validation and completion stages.
- Import audit entity identity is the actual `run_id`; this fixes the previous completion event mismatch where only `id` was inspected.

## SC-P7-010 — Excel upload — Validate

Validated the existing upload boundary:

- `.xlsx` only and 20 MiB maximum.
- Server-side scalar upload metadata validation and ZIP signature checks.
- SHA-256 is retained only for internal staging identity.
- Production storage may be rooted at `SAFECONTRACTS_PRIVATE_DIR`; otherwise the system temporary directory is used.
- Storage is not based on the WordPress public uploads directory; directories/files are hardened to 0700/0600 where supported.
- Upload/discovery audit events intentionally exclude workbook hashes, storage keys and workbook bytes.

## SC-P7-011 — Workbook field discovery — Validate

Validated `WorkbookReader` limits and hostile-content rejection:

- Bounded ZIP entries, expanded bytes, worksheets, columns and rows.
- VBA projects, external links and workbook connections are rejected.
- XML parsing is non-networked and rejects entity/doctype declarations.
- Formula cells are rejected rather than calculated.
- Both explicit XLSX cell references and positional cells produced by the SafeContracts writer are supported.
- Regression coverage mutates a real generated workbook to insert a formula and verifies discovery fails closed.

## SC-P7-012 — Column mapping — Validate

- Mapping remains server-side and target-field allow-listed.
- Required customer/contract identity fields remain mandatory.
- One workbook source column cannot silently feed multiple target fields.
- Mapping changes emit bounded audit evidence.
- Completed, running and failed runs are read-only; a terminal run cannot be remapped back into an executable state.

## SC-P7-013 — Import preview — Validate

- Preview remains bounded to at most 100 rows.
- It re-reads the private workbook and validated mapping.
- Original workbook row numbers are preserved.
- Preview contains no `$wpdb`, customer service, contract service or other business persistence path.

## SC-P7-014 — Row validation — Validate

- Dates, date ranges, positive IDs/sequences, emails and fixed-point monetary values remain normalized before execution.
- Payment rows still require sequence, contractual due date and positive amount.
- Array/object-like malformed values fail as validation errors instead of being implicitly cast into strings.

## SC-P7-015 — Duplicate strategy — Validate

- Only `fail`, `skip` and `update` are accepted.
- Unknown overwrite semantics fail closed.
- Contract collisions across customers are rejected.
- Existing payment amount and reference cannot be rewritten by an import update; only safe mutable date fields may be updated.

## SC-P7-016 — Import execution — Validate

Validation identified and repaired a state-machine gap: an already-completed run could previously reach the execution path again.

The execution boundary now:

- Accepts only `mapped` or `validated` runs.
- Treats `running`, `completed`, `completed_with_errors` and `failed` as terminal.
- Clears stale row errors only for an allowed fresh validation attempt.
- Validates all workbook rows before any business entity is mutated.
- Preserves per-row `START TRANSACTION` / `COMMIT` / `ROLLBACK` semantics.
- Continues to delegate business writes to existing Customer, Contract and Payment domain services.

The admin screen mirrors the same state machine: terminal runs expose summary, preview and errors as read-only evidence and do not render mapping/execution controls.

## SC-P7-017 — Row error reporting — Validate

Validation identified two persistence hardening improvements:

- Row errors from a previous non-terminal validation attempt are cleared before a fresh validation attempt, preventing stale/duplicated error evidence.
- `field_name` now uses `$wpdb->prepare()` rather than manual SQL quoting.

The ledger remains bounded on read, strips markup from error text, normalizes error codes and preserves source workbook row number.

## Regression evidence

`tests/php/import_validation_009_017.php` exercises the nine tasks above, including real XLSX generation/discovery when PHP `ZipArchive` is available, formula rejection, audit sanitization, terminal-run rejection before mutation and parameterized error persistence.

The suite is wired into `scripts/test-php.sh` and therefore executes under the repository Quality Gates.
