# P7 Import foundation — SC-P7-001..008

This slice implements the first SafeContracts Excel import pipeline while keeping WordPress/domain services authoritative.

## SC-P7-001 — Excel upload
- Dedicated `RUN_IMPORTS` capability and nonce-protected admin action.
- `.xlsx` only, 20 MiB maximum, strict scalar upload metadata, ZIP signature and SHA-256 fingerprint.
- Workbooks are staged outside WordPress public content by default under the system temporary directory; production can set `SAFECONTRACTS_PRIVATE_DIR` to an explicitly provisioned private path.
- Staged files are SHA-addressed, directory/file permissions are restricted, and Apache/index denial files are added as defense in depth.
- Import-run metadata is persisted separately from workbook bytes.

## SC-P7-002 — Workbook field discovery
- XLSX package inspection uses bounded `ZipArchive` + non-network XML parsing.
- Package entry count and expanded size are capped.
- VBA/macros, external links, workbook connections, XML entity declarations and formula cells are rejected.
- Sheet names, header row, workbook columns and original/normalized labels are discovered without executing workbook content.
- Both explicit XLSX cell references and positional cells are supported without weakening formula/content restrictions.

## SC-P7-003 — Column mapping
- Mapping is validated server-side from SafeContracts target fields to discovered workbook columns.
- Unsupported target fields and unavailable/duplicated source columns fail closed.
- `customer_name` and `contract_number` are mandatory baseline identity mappings.
- Saved mapping is persisted against the import run and never trusted solely from client state.

## SC-P7-004 — Import preview
- Preview re-reads the private workbook using the stored sheet/mapping.
- Preview is bounded to 100 rows maximum and preserves original workbook row numbers.
- Preview performs no business mutation and contains no persistence/domain writer.

## SC-P7-005 — Row validation
- All rows are normalized and validated before any execution begins.
- Customer/contract identities, email, positive integer IDs/sequences, calendar dates/date ranges and fixed-point amounts are validated.
- Non-scalar array/object-like values fail closed before string/date/amount normalization.
- Payment rows require sequence, contractual due date and positive amount.
- Validation errors are field-level and tied to the original workbook row.

## SC-P7-006 — Duplicate strategy
- Explicit strategies: `fail` (default), `skip`, `update`.
- Unknown/implicit overwrite strategies are rejected.
- Existing parents may be safely reused for payment rows, but cross-customer contract collisions fail.
- `update` never changes existing payment original amount or reference; those financial identity changes are rejected.

## SC-P7-007 — Import execution
- Execution requires `RUN_IMPORTS` and re-reads persisted workbook + mapping server-side.
- If any source row fails validation, no business data is mutated.
- Valid execution delegates business writes to `CustomerService`, `ContractService` and `PaymentService`, preserving their capabilities/domain rules.
- Each executed row runs inside `START TRANSACTION` / `COMMIT`, with `ROLLBACK` on row failure.
- Completion emits bounded run/count metadata through `safecontracts_import_completed`.

## SC-P7-008 — Row error reporting
- `safecontracts_import_errors` stores run ID, original row number, optional field, normalized error code/message and timestamp.
- Error messages are stripped/bounded before persistence.
- Admin detail shows a bounded error ledger with WordPress escaping.

## Database

Migration `1.11.0` adds:
- `safecontracts_import_runs`
- `safecontracts_import_errors`

## Regression evidence

`tests/php/import_foundation_001_008.php` covers schema, upload validation/private storage, workbook security/discovery, mapping, preview, row validation (including complex malformed values), duplicate policy, transactional/domain execution boundaries, error reporting and admin integration. The suite is executed by `scripts/test-php.sh` in Quality Gates.

## Runtime dependency

Production XLSX inspection requires PHP `ZipArchive`. If it is unavailable, SafeContracts fails explicitly instead of attempting a weaker parser.
