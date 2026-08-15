# SafeContracts P9 final validation + P10 readiness SC-P10-011..016

This batch completes the final four independent P9 validation tasks while SC-P9-013..019 remain owned by the parallel team PR, and implements the next six P10 production-readiness workstreams without weakening existing authorization or financial boundaries.

## SC-P9-047 — Profile/session/device screen — Validate

- Device responses remain scoped to `current_user` and duplicate IDs fail closed.
- Mobile accepts only positive IDs and known Android/iOS/web platforms.
- Device payloads are bounded to 32 entries to prevent unbounded profile rendering.
- Raw Firebase token/hash fields remain outside the modeled mobile projection.
- Regression confirms the profile repository reads only the protected `/devices` endpoint.

## SC-P9-048 — RTL/responsive mobile UX — Validate

- Arabic locale variants such as `ar-KW` and `ar_EG` resolve RTL instead of falling through to LTR.
- Breakpoints remain deterministic at 600 and 1024 logical pixels.
- Adaptive layout rejects non-positive maximum widths.
- Directionality is validated with Flutter widget coverage.

## SC-P9-049 — Offline/error/loading states — Validate

- 401, 403, validation, transport, malformed-payload and server failures retain deterministic classification.
- Loading and forbidden states suppress Retry; recoverable offline/error states may expose retry.
- State feedback uses Flutter semantics/live regions and decorative icons do not create duplicate spoken content.

## SC-P9-050 — Mobile test automation — Validate

- The hermetic test harness remains local and fake-transport backed.
- Validation performs no real network access or credential reads.
- Exact method/path/body recording is asserted and every committed `_test.dart` remains enforced by the existing `flutter test` Quality Gate.

## SC-P10-011 — Audit completeness — Implement

- The release verifier protects critical contract financial, assignment, lifecycle, payment, follow-up, export and import audit hooks.
- Audit context sanitization markers for tokens, secrets, passwords, authorization, private keys and service-account material are enforced.
- Backend regression registers `AuditRecorder` and confirms every protected event hook is active.

## SC-P10-012 — RTL/accessibility pass — Implement

- WordPress SafeContracts controls now expose an explicit `:focus-visible` outline for keyboard navigation.
- Existing admin `dir="auto"`, RTL selectors and responsive breakpoints are protected by release verification.
- Mobile Arabic locale variants, semantics, live-region feedback and fail-closed retry policy are regression-tested.

## SC-P10-013 — Backup/restore verification — Implement

- `scripts/backup_manifest.py` derives all `safecontracts_*` custom tables directly from committed migrations.
- The manifest also scopes SafeContracts WordPress options/user-meta and explicitly excludes external secret material.
- `docs/BACKUP_RESTORE_RUNBOOK.md` defines isolated restore acceptance, row-count evidence, schema-version checks and required UAT evidence.

## SC-P10-014 — Migration/upgrade testing — Implement

- The release verifier validates unique monotonically ordered migration versions and ensures `Migrator::LATEST_VERSION` matches the newest registered migration.
- Every registered migration class must exist and implement the Migration contract.
- PHP regression upgrades a simulated `1.10.0` database to `1.11.0`, then verifies a second migration run is a no-op at the latest version.

## SC-P10-015 — UAT scenarios — Implement

- `ops/uat-scenarios.json` provides eight structured UAT flows covering system administrator, manager, accountant and viewer roles.
- Flows cover contract lifecycle, assigned scope, collections, follow-up, reporting/export, read-only enforcement, mobile notification/deep-link behavior and upgrade/backup/restore.
- Every scenario carries non-empty preconditions, steps, expected outcomes and evidence requirements and is machine-validated.

## SC-P10-016 — Production release readiness — Implement

- Quality Gates include a blocking `release-readiness` job that depends on successful repository, backend and mobile jobs.
- The final job executes both backup-manifest verification and `scripts/release_readiness.py --check`.
- Repository foundation validation also protects the presence of this release job so it cannot be silently removed.
- Automation does not claim a real production restore, live Firebase delivery, real-device APK acceptance or business-owner sign-off; those remain mandatory environment-specific go-live evidence.

## Regression evidence

- `mobile/test/mobile_final_validation_047_050_test.dart`
- `tests/php/p10_release_readiness_011_016.php`
- `scripts/backup_manifest.py`
- `scripts/release_readiness.py`
- `ops/uat-scenarios.json`
- `docs/BACKUP_RESTORE_RUNBOOK.md`
- `docs/PRODUCTION_RELEASE_READINESS.md`
- `.github/workflows/quality-gates.yml`

The exact PR merge candidate must pass repository standards, all PHP regressions, Dart formatting, Flutter analysis/tests, backup-manifest verification and the final release-readiness verifier before merge.
