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
