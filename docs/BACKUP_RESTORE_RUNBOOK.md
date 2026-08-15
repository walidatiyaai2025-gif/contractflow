# SafeContracts backup / restore verification runbook

This runbook defines the production data boundary used by `SC-P10-013`. It is intentionally independent of hosting-provider tooling: the database engine or managed backup service performs the physical copy, while SafeContracts supplies a deterministic manifest and restore acceptance checks.

## 1. Generate the application data manifest

Run from the repository root:

```bash
python3 scripts/backup_manifest.py --check
python3 scripts/backup_manifest.py > safecontracts-backup-manifest.json
```

The manifest is derived from the committed migration chain and therefore expands automatically when a new `safecontracts_*` table is introduced. It also requires the WordPress rows whose option/meta keys begin with `safecontracts_`.

## 2. Backup boundary

Capture all database tables listed in `safecontracts-backup-manifest.json`, plus:

- `wp_options` rows matching `option_name LIKE 'safecontracts_%'` (using the site's actual table prefix).
- `wp_usermeta` rows matching `meta_key LIKE 'safecontracts_%'` (using the site's actual table prefix).

The backup must be transactionally consistent or taken from a database snapshot with equivalent consistency guarantees. Record the database snapshot identifier, UTC timestamp, SafeContracts schema version and row counts for every listed table.

## 3. Secret boundary

Do **not** package these external secrets inside the SafeContracts application/database backup artifact:

- Firebase service-account JSON/private keys.
- Environment variables or secret-manager values.
- Database server credentials.
- WordPress authentication salts or hosting credentials.

SafeContracts stores configuration references/state where applicable; the underlying secret must be restored through the organization's secret-management process.

## 4. Restore verification

Restore into an isolated environment first. Before accepting the restore:

1. Confirm every manifest table exists and compare row counts with the captured backup evidence.
2. Restore the `safecontracts_%` option and user-meta rows.
3. Configure external secrets through the target environment/secret manager, not from the application backup.
4. Activate/load SafeContracts and run migrations.
5. Confirm `safecontracts_db_version` equals `Migrator::LATEST_VERSION`.
6. Run migrations again and confirm they are idempotent (no additional schema migration is applied at the latest version).
7. Run `./scripts/test-php.sh`, `flutter test` from `mobile/`, and `python3 scripts/release_readiness.py --check`.
8. Execute `UAT-008` from `ops/uat-scenarios.json` and retain the evidence bundle.

## 5. Required restore evidence

A restore exercise is not accepted without:

- Backup/snapshot identifier and UTC timestamp.
- Generated SafeContracts backup manifest.
- Pre-backup and post-restore row counts.
- Restored schema version.
- Quality Gates run identifier or equivalent local test transcript.
- UAT-008 result and reviewer/sign-off identity.

This repository implements and tests the verification mechanism. A real production restore remains an environment-specific operational exercise and must be performed before production go-live under the deployment change record.
