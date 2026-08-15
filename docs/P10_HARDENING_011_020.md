# P10 Hardening Evidence — SC-P10-011..020

This evidence batch hardens release/recovery concerns independently of the parallel P9 mobile workflow PR. All controls remain server-authoritative and capability/scope based.

## SC-P10-011 — Audit completeness — Implement

- Expanded `AuditRecorder` to cover contract create/edit/attachments, payment create, collection recording, notification-rule changes, general/mobile/Firebase settings, device registration/revocation and database runtime upgrades in addition to existing financial/assignment/follow-up/import/export events.
- Firebase credential-reference changes record the event/actor only; the reference or secret value is not copied into audit JSON.
- Device events deliberately do not copy token hashes into the audit payload.
- Collection audit stores only business metadata and the optional WordPress Media ID, never proof binary content.
- `Plugin::boot()` registers audit before `maybeMigrate()` so runtime code upgrades are observable after an installed audit schema exists.
- `AuditRepository` remains append-only: insert plus bounded timeline reads, with no update/delete audit mutation API.

Regression: `tests/php/p10_release_hardening_011_016.php`.

## SC-P10-012 — RTL/accessibility pass — Implement

- Added `safeContractsTextDirection()` so `ar`, `ar-KW`, `ar_EG` and equivalent Arabic locale-family values resolve RTL; non-Arabic locales remain LTR.
- Bootstrap application icon and loading progress expose explicit semantics labels.
- Existing adaptive breakpoints and admin RTL/responsive CSS remain the reusable layout contract.
- Added enlarged-text, regional-locale, breakpoint and semantic widget regressions.

Regression: `mobile/test/p10_rtl_accessibility_012_test.dart` plus PHP static release checks.

## SC-P10-013 — Backup/restore verification — Implement

- Added `SafeContracts\Support\RecoveryManifest` enumerating 18 plugin-owned tables, critical WordPress options, notification read-state user meta and external dependencies.
- Added `docs/RECOVERY_RUNBOOK.md` with restore ordering and post-restore financial/audit/media checks.
- WordPress Media Library and users/role assignments are explicit recovery dependencies because business rows reference Media/user IDs.
- Secret values remain in the infrastructure secret manager; only approved reference identifiers are restored to WordPress.
- Deactivation remains non-destructive and is explicitly not treated as a backup mechanism.

Regression: `tests/php/p10_release_hardening_011_016.php`.

## SC-P10-014 — Migration/upgrade testing — Implement

- Regression upgrades a representative `1.8.0` installation through notification/import migrations to `Migrator::LATEST_VERSION`.
- Verifies only expected later schemas run, a second current-version pass is idempotent, and a hypothetical newer stored version is never downgraded.
- Migration catalog is checked for destructive `DROP TABLE`/`TRUNCATE TABLE` operations.

Regression: `tests/php/p10_release_hardening_011_016.php`.

## SC-P10-015 — UAT scenarios — Implement

- Added `docs/UAT_V1.md` with Administrator, Manager, Accountant, Viewer, collection settlement, notification/inbox, import, export and recovery scenarios.
- Each scenario contains preconditions, actions and measurable pass criteria.
- Executable PHP checks assert the baseline role capability matrix: Manager view-all/edit/export, Accountant assigned/create without default contract-edit, Viewer assigned read/report only, System Administrator all capabilities.

Regression: `tests/php/p10_release_hardening_011_016.php`.

## SC-P10-016 — Production release readiness — Implement

- Added `scripts/validate-release-readiness.py` as a fail-closed evidence gate.
- Gate checks required release docs/tests, migration-version consistency, recovery-manifest critical keys, P10 test-runner wiring, UAT scenario coverage and release placeholders.
- Gate is intentionally secret-blind: it never reads runtime environment variables, WordPress values or secret-manager contents.
- `repository-standards` now runs this validator on every Quality Gates run.

## SC-P10-017 — Permission penetration tests — Validate

- Revalidates missing access, missing scope and horizontal assigned-accountant denial.
- Enumerates the currently registered REST surface and asserts `/health` is the only intentionally public endpoint; every other route must declare a non-public permission callback.

Regression: `tests/php/p10_validation_017_020.php`.

## SC-P10-018 — Accountant-scope tests — Validate

- Revalidates assigned-only `ApiScope`, explicit `VIEW_ALL` widening and independent Excel-export capability.
- Confirms notification inbox/read APIs query by current user and ownership before read-state mutation.
- Confirms export accountant filter widening remains conditional on server `VIEW_ALL`.

Regression: `tests/php/p10_validation_017_020.php`.

## SC-P10-019 — Financial regression tests — Validate

- Revalidates exact four-decimal string arithmetic, reconciliation and negative-balance rejection.
- Verifies collection settlement retains lock/ledger/integrity/transaction/over-collection guards.
- Scans mobile library code to ensure authoritative money is not converted with `double.parse`/`num.parse`.

Regression: `tests/php/p10_validation_017_020.php`.

## SC-P10-020 — API security tests — Validate

- Revalidates unknown parameter, parameter-pollution, oversized scalar, malformed ID and bounded-window rejection.
- Confirms notification inbox paging uses explicit limits.
- Confirms internal failures return a generic 500 envelope without exception-detail leakage.

Regression: `tests/php/p10_validation_017_020.php`.

## Quality gates

The batch is complete only when the pull-request merge ref passes:

1. repository foundation + production release-readiness validator;
2. the full PHP/lint regression suite including both new P10 suites; and
3. Dart formatting, Flutter analyze and all Flutter tests including the new RTL/accessibility regression.
