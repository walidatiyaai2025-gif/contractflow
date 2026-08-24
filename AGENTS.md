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

## ALKENZY ADV PLUGIN UI — LOCKED VISUAL GOVERNANCE

Before modifying any SafeContracts / Alkenzy ADV WordPress Admin UI, every contributor MUST fetch latest `main` and read:

- `docs/plugin-redesign/PLUGIN_UI_CONSTITUTION.md`
- `docs/plugin-redesign/PLUGIN_REDESIGN_EXECUTION_PLAN.md`
- `docs/plugin-redesign/PLUGIN_UI_SCREEN_MATRIX.md`
- `docs/plugin-redesign/PLUGIN_UI_PROGRESS.md`
- `assets/design/plugin-redesign/reference/REFERENCE_MANIFEST.json`

The files under `assets/design/plugin-redesign/reference/` are the approved locked visual source of truth. Never delete, replace, crop, recolor, move, overwrite or silently supersede a locked reference. A new visual baseline requires explicit project-owner approval, a new Reference ID, a new SHA-256 manifest entry and re-review of affected screens.

The P0 redesign gate is mandatory: Worker #1/#2/#3 may not begin plugin redesign implementation until the governance foundation and all seven locked reference binaries are merged to `main` and `python3 scripts/validate-plugin-design-references.py` passes.

`docs/plugin-redesign/PLUGIN_UI_SCREEN_MATRIX.md` is the exactly-one-owner authority. A route owner owns every user-visible state rendered inside that route. Do not edit another owner's screen and do not create overlapping ownership.

The LEAD exclusively owns shared redesign surfaces, including `wordpress-plugin/safecontracts/src/Plugin.php`, `wordpress-plugin/safecontracts/src/Admin/AdminShell.php`, `wordpress-plugin/safecontracts/src/Admin/AdminNavigationGroups.php`, navigation cleanup, common Admin primitives, shared existing `wordpress-plugin/safecontracts/assets/admin/*.css` and `wordpress-plugin/safecontracts/assets/admin/*.js`, design tokens, common redesign primitives and all plugin-redesign governance/reference files. Workers must not make uncoordinated edits to these paths; request the smallest shared change from the LEAD instead.

After the governance foundation lands, LEAD/WORKER-1/WORKER-2/WORKER-3 must branch from the exact same final `main` foundation SHA stated in the governance merge. Every redesign PR must record that SHA, preserve real WordPress permissions/nonces/business behavior, attach real runtime screenshot evidence, pass Arabic RTL + responsive QA, and pass the repository Quality Gates plus the plugin-design validator.

Continue from `PLUGIN_UI_PROGRESS.md`; do not restart approved screens and do not rely on chat history as project state.
