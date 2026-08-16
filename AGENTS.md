# SafeContracts repository instructions

These instructions apply to every contributor, coding agent, release operator and automation working in this repository.

## Source of truth and team safety

- WordPress + the SafeContracts plugin remain the backend and business source of truth.
- The Flutter application is an API client and must not duplicate server-side financial authority.
- Inspect open issues/PRs before starting work and never overwrite or duplicate active team work.
- Keep implementation evidence traceable through GitHub issues, commits, PRs and CI.
- Never commit credentials, tokens, database passwords, Firebase private credentials, signing keys, keystores or production configuration containing secrets.
- `docs/PROJECT_STATUS.md` is machine-maintained. Do not edit its status table manually.

## Mandatory quality gate

No build, package, APK or plugin ZIP may be called **verified**, **release**, or **production-ready** unless the exact functional source candidate has passed all required SafeContracts Quality Gates:

1. repository standards,
2. backend foundation/regression tests,
3. Flutter format/analyze/tests,
4. release-readiness verification.

The post-gate `release-candidates` job must also prove deterministic plugin packaging and the Android release build/signing path before release-engineering work is closed.

Live-environment evidence that CI cannot prove (production restore, Firebase delivery, real-device behavior and business UAT) must be recorded separately and must never be fabricated.

## Mandatory latest verified artifact retention

The repository contains two permanent artifact locations:

- `Last verified Plugin/` — must contain only the latest verified SafeContracts WordPress plugin ZIP plus checksum/provenance metadata.
- `Last verified apk/` — must contain only the latest verified Android APK plus checksum/provenance metadata.

Rules:

1. Replace the previous binary only after the replacement candidate has passed the exact-source Quality Gates.
2. Keep one current binary per folder, not a growing history. Historical releases belong in GitHub Releases, not these `Last verified ...` folders.
3. Use stable filenames:
   - `Last verified Plugin/SafeContracts-latest.zip`
   - `Last verified apk/SafeContracts-latest.apk`
4. Each binary must have a matching `.sha256` file and a `VERIFIED.json` provenance record containing source commit, Quality Gates run ID, UTC publication time, byte size and SHA-256.
5. Never place debug, unsigned, CI-candidate, locally modified or unverified output in these folders.
6. The APK may only be retained as production-verified when the reproducible Android scaffold contract is present, the real production API configuration is HTTPS, production signing is verified outside Git, and real-device/UAT requirements are satisfied.
7. The plugin ZIP must be built deterministically from `wordpress-plugin/safecontracts/` without local secrets, caches, logs or unrelated repository content by `scripts/package_plugin.py`.
8. Run `python3 scripts/verified_artifacts.py check` before closing any release/publishing work.
9. Publish the plugin independently with `python3 scripts/verified_artifacts.py publish-plugin ...` once its exact source candidate is green.
10. Publish the APK independently with `python3 scripts/verified_artifacts.py publish-apk ...` only after production signing, HTTPS API, real-device evidence and UAT evidence are available.
11. The CI-generated `SafeContracts-apk-release-candidate.apk` uses a short-lived candidate key and a reserved `.invalid` URL. It proves the build path only and must never be treated as production.

## Production environment

Production build/deployment work must follow `docs/PRODUCTION_ENVIRONMENT_BUILD.md`, `docs/ENVIRONMENT.md`, `docs/BACKUP_RESTORE_RUNBOOK.md`, `docs/PRODUCTION_RELEASE_READINESS.md` and `ops/uat-scenarios.json`.

If a human-only production dependency is missing, record the exact blocker and smallest required action instead of bypassing the gate.

# Enterprise Safe Contracts branch instructions

The following rules apply whenever work is performed on `enterprise-safecontracts` or an ESC-specific branch/task.

## Critical product separation

- **Safe Contract** means the existing client-specific/current product.
- **Enterprise Safe Contracts (ESC)** means the separate multi-tenant enterprise/SaaS CLM product.
- ESC's official public URL is `https://esc.50sols.com/`.
- Never merge, port, copy, expose or backport ESC functionality into Safe Contract unless the product owner explicitly requests that exact transfer.
- Never create a PR from `enterprise-safecontracts` to `main` merely to synchronize the branches.
- Safe Contract and ESC must have separate task streams, mobile application identities, Firebase registrations, release artifacts and deployment targets.
- ESC task IDs use `ESC-Px-NNN`; do not reuse client `SC-*` IDs for ESC work.

## ESC architecture and impact rule

- Preserve WordPress/plugin as authoritative backend/business source of truth and Flutter as an API client unless an explicit approved architecture decision changes that baseline.
- Tenant isolation is server-side and mandatory. IDs, filters, exports, attachments and client payloads are never authorization.
- Build a generic contract platform plus configuration/templates; do not fork the codebase per industry by default.
- Every ESC feature/change must review its complete impact across tenant isolation, schema/migrations, backend, authorization, API, admin UI, Flutter, Android identity/builds, landing page, design system, feature registry/plans, search/reports/import/export, notifications, audit, documents, localization, security, performance, tests, documentation, CI and release/rollback behavior.
- A dimension may be N/A only after explicit review.
- The owner should not need to list every necessary downstream change; implement the coherent affected surfaces required for a complete feature and create separate tasks for larger optional expansions.

## ESC mobile identity

- Safe Contract and ESC must be simultaneously installable on the same Android device.
- ESC must use a distinct application/package identity, namespace, app label/icons, Firebase app/configuration, notification channels, deep links, local storage namespace, signing/release lineage, version stream and artifacts.
- The current ESC production package baseline is `com.safecontracts.enterprise` until superseded by an explicit architecture decision before first production publication.
- Dev/staging/production ESC builds must not accidentally connect to the wrong backend environment.

## ESC public landing and design

- `https://esc.50sols.com/` is a first-class ESC product surface.
- Landing, admin, mobile, login, emails and branded reports must use the same ESC design system and localization/RTL principles.
- Only features with an appropriate public lifecycle state in the ESC Feature Registry may be marketed as generally available.

## ESC release isolation

- Never place ESC output in the existing Safe Contract `Last verified Plugin/` or `Last verified apk/` slots.
- ESC release retention, checksums and provenance must be separate and subject to equivalent or stronger quality gates.
- Real-device coexistence verification must be part of ESC Android release readiness.

The authoritative ESC planning documents are `ENTERPRISE_SAFE_CONTRACTS_MASTER_PLAN.txt` and `docs/enterprise/`.
