# P6 Validation — SC-P6-029..038

This batch independently validates the second half of the SafeContracts Admin UI & Reports surface without duplicating the existing screens.

## Validated tasks

- SC-P6-029 — Collections screen
- SC-P6-030 — Follow-up screen
- SC-P6-031 — Notifications screen
- SC-P6-032 — Reports screen
- SC-P6-033 — Users/roles screen
- SC-P6-034 — SafeContracts settings
- SC-P6-035 — Payment-method settings
- SC-P6-036 — Notification settings
- SC-P6-037 — Firebase settings UI
- SC-P6-038 — Mobile configuration UI

## Production hardening found during validation

1. Report viewing and exporting now use separate capabilities: `VIEW_REPORTS` for reading and `EXPORT_REPORTS` for XLSX export. The export service independently enforces the export grant.
2. Follow-up admin actions fail closed on unknown operation names instead of silently converting malformed input into a contact note.
3. Firebase configuration moved behind `MANAGE_SYSTEM`; notification managers cannot read/write the credential-reference boundary.
4. Shared strict scalar/integer parsing rejects array/object coercion in collection, follow-up and settings mutation paths.
5. Payment-method and notification cadence inputs reach their domain validators without lossy admin-layer casts.
6. General and mobile configuration reject non-scalar mutation values while remaining tolerant of malformed legacy stored options on read.

## Preserved business boundaries

- Collection writes remain delegated to `CollectionService`; payment method is mandatory and proof optional.
- Follow-up promise/deferred dates never rewrite contractual `due_date`.
- Notification operations expose no raw Firebase credential or device-token values.
- Reports use server-side assignment scope and contractual due-date semantics.
- Users/roles remains a read-only WordPress capability directory.
- Payment-method codes remain stable and lifecycle is active/deactivated rather than hard delete.
- Notification rules remain validated by the server-owned rule model.
- Mobile configuration remains non-secret bootstrap data and does not implement P8 REST work early.

## Automated evidence

`tests/php/admin_ui_validation_029_038.php` provides task-specific regression coverage and is registered in `scripts/test-php.sh` alongside the full P0-P6 suite.

The PR for this batch must pass repository standards, the complete PHP regression suite, and Flutter format/analyze/test on the exact merge candidate before these ten Issues are closed.
