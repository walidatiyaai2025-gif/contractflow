# SafeContracts V1 — Backup & Restore Runbook

## Purpose

This runbook defines the minimum recoverable SafeContracts state without storing or printing environment secret values. WordPress remains the system host and database owner; Firebase is delivery transport only.

## Backup set

1. **SafeContracts database tables** — include every table suffix returned by `SafeContracts\Support\RecoveryManifest::tableSuffixes()` under the active WordPress table prefix.
2. **SafeContracts WordPress options** — include every key returned by `RecoveryManifest::optionKeys()`.
3. **SafeContracts user meta** — include every key returned by `RecoveryManifest::userMetaKeys()` for all relevant WordPress users.
4. **WordPress Media Library** — back up database metadata and uploaded files because contract attachments and collection proofs reference Media attachment IDs.
5. **WordPress users and role assignments** — user IDs and role assignments participate in contract/accountant ownership, audit actors and notification recipients.
6. **Deployment environment secret values** — back these up only in the approved infrastructure secret manager. WordPress stores a Firebase credential *reference identifier*, not the service-account secret value itself.

Do not treat plugin deactivation as a backup operation. SafeContracts deactivation is intentionally non-destructive, but it is not a substitute for a database/media backup.

## Restore order

Use `RecoveryManifest::minimumRestoreOrder()` as the canonical sequence:

1. Restore the SafeContracts-owned database tables under the intended WordPress prefix.
2. Restore SafeContracts WordPress options and notification read-state user meta.
3. Restore the WordPress Media Library while preserving attachment IDs referenced by SafeContracts records.
4. Restore WordPress users and role assignments while preserving user IDs where possible.
5. Restore secret values in the infrastructure secret manager using the restored WordPress reference identifiers. Never copy secret values into SafeContracts options.
6. Activate the SafeContracts plugin.
7. Allow the SafeContracts migrator to upgrade the restored schema to the code's current `Migrator::LATEST_VERSION`.
8. Run repository Quality Gates and the UAT smoke scenarios before reopening production traffic.

## Integrity checks after restore

- `safecontracts_db_version` equals `Migrator::LATEST_VERSION` after migrations complete.
- Contract numbers remain unique and contract/customer/accountant assignments are intact.
- Scheduled payment `(contract_id, sequence_no)` uniqueness is intact.
- Collection ledger totals reconcile with each payment's paid/remaining balances.
- Follow-up and audit timelines remain append-only and readable.
- Notification rules/templates/device registrations/delivery logs exist; notification read-state user meta is restored.
- Import runs/errors and export/import audit evidence are present.
- General/mobile/Firebase **public** configuration is restored. Firebase credential option contains only a secret reference identifier.
- Contract attachment and collection proof Media IDs resolve to restored WordPress attachments.

## Recovery acceptance

Recovery is accepted only after:

- migrations complete without destructive SQL;
- `scripts/test-php.sh` passes;
- Flutter format/analyze/test passes;
- `scripts/validate-release-readiness.py` passes; and
- the role/scope scenarios in `docs/UAT_V1.md` pass for Administrator, Manager, Accountant and Viewer test accounts.
