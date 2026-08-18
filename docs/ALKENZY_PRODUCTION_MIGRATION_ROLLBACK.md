# Alkenzy ADV production migration and rollback runbook

Scope: **Alkenzy ADV / SafeContracts on `main` and `cms.50sols.com` only.** This runbook does not apply to Enterprise Safe Contracts (ESC).

## Safety model

Alkenzy ADV treats WordPress/MySQL schema migrations as production-critical operations. MySQL DDL is not assumed to be transactionally reversible. The application therefore uses two recovery layers:

1. **Application rollback contract** for every migration introduced after database baseline `1.17.0` (`ProductionMigration::rollback()`). This is best effort and is executed automatically when `up()` or `verify()` fails.
2. **Verified pre-deployment backup restore** as the authoritative recovery path whenever rollback fails, data integrity is uncertain, or the migration is not safely reversible in place.

A migration is not complete until its `verify()` method succeeds and `safecontracts_db_version` is advanced. A failed migration leaves the previous database version marker intact and writes recovery evidence to the WordPress options `safecontracts_migration_journal` and `safecontracts_migration_failure`.

## Mandatory pre-deployment evidence

Before installing a plugin package that contains a database migration:

- Record the exact plugin artifact SHA-256 and release/commit SHA.
- Record the current `safecontracts_db_version`.
- Create a database backup according to `docs/BACKUP_RESTORE_RUNBOOK.md`.
- Verify the backup can be read and record its SHA-256, size, timestamp and storage location in the deployment evidence.
- Confirm the backup contains all WordPress tables and all `${table_prefix}safecontracts_*` tables.
- Confirm sufficient free disk/database capacity for the migration.
- Put the deployment into a controlled maintenance window. Do not allow parallel plugin updates or migration attempts.
- Run the normal Quality Gates and production release-readiness checks on the exact artifact that will be deployed.

## Runtime migration guard

The plugin enforces the following sequence:

1. Refuse to run if the database schema version is newer than the plugin's supported latest schema.
2. Acquire a single-writer migration lock (`safecontracts_migration_lock`).
3. Write a `running` journal entry with source version, target version and migration class.
4. For all post-`1.17.0` migrations, run `preflight()` before any mutation.
5. Run `up()`.
6. Run `verify()`.
7. Only after verification succeeds, advance `safecontracts_db_version` and record migration completion.
8. Release the migration lock.

If any step fails, the plugin does not advance the version marker. For a `ProductionMigration`, it immediately attempts `rollback()` and records whether that rollback succeeded or whether backup restoration is required.

## Failure response

If the Alkenzy ADV Recovery screen or the `Database upgrade requires attention` notice appears:

1. **Stop. Do not repeatedly retry the migration.** Keep Alkenzy ADV business operations unavailable until the database state is understood.
2. Capture the current values of:
   - `safecontracts_db_version`
   - `safecontracts_migration_failure`
   - `safecontracts_migration_journal`
   - exact deployed plugin artifact SHA-256 / commit SHA
3. Preserve web/PHP/database error logs covering the migration window.
4. Compare the failure target version and migration class with the release notes and migration source.
5. Follow the applicable recovery path below.

### Path A — application rollback succeeded

Use this only when `rollback_status=succeeded` and schema/data checks show the pre-migration state is intact.

- Confirm `safecontracts_db_version` is still the pre-migration version.
- Validate required tables, columns, indexes and critical row counts against the pre-deployment evidence.
- Run read-only application/API smoke checks.
- Fix the migration defect in code and pass CI on a new artifact.
- Create/verify a fresh backup before one controlled retry.
- Do not manually edit the database version marker to bypass the migration.

### Path B — rollback failed or integrity is uncertain

Use this when `rollback_status=failed_restore_backup_required`, when the migration is legacy/non-reversible, or whenever database integrity cannot be proven.

- Keep application traffic stopped.
- Restore the **verified pre-deployment database backup** using `docs/BACKUP_RESTORE_RUNBOOK.md`.
- Restore/redeploy the exact plugin artifact that was compatible with that backup's `safecontracts_db_version`.
- Verify the database version marker matches the restored schema.
- Validate all `${table_prefix}safecontracts_*` tables required by that version.
- Run authenticated API and key business smoke checks before reopening production.
- Retain the failed artifact, logs, migration journal and restore evidence for incident review.

## Post-recovery validation

Do not reopen production until all applicable checks pass:

- WordPress and plugin load without a migration recovery notice.
- Database version equals the schema expected by the running plugin.
- SafeContracts health/API endpoints return expected JSON responses.
- Login/session works.
- Customer and supplier lookups load.
- Customer receivable and supplier payable contracts can be read within scope.
- Payment schedules, collections and finance views reconcile with known sample records.
- Reports load with correct authorization scope.
- Users/Roles show friendly permission names and do not expose raw capability codes.
- Contextual User Guide opens for authorized pages.

## Rules for developers creating a new migration

Every migration after version `1.17.0` must implement `SafeContracts\Database\ProductionMigration` and therefore provide:

- `preflight(object $wpdb): void` — prove prerequisites and fail before mutation when unsafe.
- `up(object $wpdb): void` — perform the minimal forward change.
- `verify(object $wpdb): void` — prove the post-migration schema/data contract.
- `rollback(object $wpdb): void` — best-effort reverse of changes introduced by `up()`.

Additional rules:

- Never drop data merely to simplify rollback.
- Prefer additive, backward-compatible schema changes.
- For destructive transformations, use expand/migrate/verify/contract across separate releases where practical.
- Make data migrations idempotent or explicitly detect already-migrated state.
- Do not depend on the `safecontracts_db_version` marker as proof that schema changes succeeded; verification must inspect the intended state.
- Never manually mark a migration complete in production to bypass a failed verification.
- Update this runbook and automated migration tests when the recovery model changes.
