# SafeContracts repository instructions

These instructions apply to every contributor, coding agent, release operator and automation working in this repository.

## Source of truth and team safety

- WordPress + the SafeContracts plugin remain the backend and business source of truth.
- The Flutter application is an API client and must not duplicate server-side financial authority.
- Inspect open issues/PRs before starting work and never overwrite or duplicate active team work.
- Keep implementation evidence traceable through GitHub issues, commits, PRs and CI.
- Never commit credentials, tokens, database passwords, Firebase private credentials, signing keys, keystores or production configuration containing secrets.
- `docs/PROJECT_STATUS.md` is machine-maintained. Do not edit its status table manually.

## Alkenzy ADV mobile visual baseline

- The approved mobile redesign references belong under `assets/design/mobile_redesign/reference/` and are the visual baseline for Alkenzy ADV mobile work.
- Before changing mobile UI, consult `docs/mobile-redesign/MOBILE_UI_REFERENCE.md`, `docs/mobile-redesign/MOBILE_UI_SCREEN_MATRIX.md`, and `docs/mobile-redesign/MOBILE_UI_PROGRESS.md`.
- Do not replace the approved navy/cream/rose-gold design language with default Flutter/Material styling.
- Preserve existing API behavior, permissions, business rules, fields and workflows while changing presentation.
- Update the screen matrix and progress document before handing unfinished redesign work to another contributor.

## ALKENZY ADV owner-approved release baseline lock — mandatory

The project owner explicitly approved the unified **WordPress Plugin `0.3.25` + Alkenzy ADV Android `0.3.25+25`** release as the current canonical functional baseline on **2026-08-28**.

Locked identity:

- Approved plugin version: **`0.3.25`**.
- Approved Android version: **`0.3.25+25`**.
- Exact approved functional source commit: **`bebde8238e60bb98742564e901ebb345b4c0d69a`**.
- Approved source branch at acceptance: `fix/0.3.14-mobile-data-report-closure`.
- Approval / release vehicle: PR `#665` and GitHub Actions run `33135245766` (`ALKENZY 0.3.25 Release Candidate`).
- Approved production API target recorded by the release artifact: `https://sys.alkenzy.com/wp-json/safecontracts/v1/`.
- Plugin artifact SHA-256: `efb85bc60d1346b71aa23afaee4d26230b95e3daec827686494931d62834455f`.
- APK artifact SHA-256: `1cd8876390d3ce86c56e56dd0af9181b40072ba26cfb545e824ba019c2682d91`.
- The previous approved `0.3.6+10` source commit `9171f1c357822f9118eb8058aab6fb145c475fc3` is a proven ancestor of this baseline; GitHub comparison reports the `0.3.25` source ahead by 405 commits and behind by 0.

Rules for every future Alkenzy ADV plugin/mobile request, Worker, Lead, PR, ZIP and APK:

1. **Start from `bebde8238e60bb98742564e901ebb345b4c0d69a` or a commit proven to be its descendant.** Do not start from `main`, an older release branch, a stale PR head, an abandoned Worker branch, a historical APK branch or any snapshot that does not contain this exact approved baseline.
2. `0.3.25` and mobile build `25` are consumed forever. Never rebuild a different product state under the same release identity.
3. The next user-facing unified release must be **at least `0.3.26+26`**. Plugin version, mobile versionName and mobile build number must advance together unless the project owner explicitly approves a higher forward version.
4. No accepted `0.3.25` behavior may disappear because a future Worker copied an older file or restarted from an obsolete branch. Ports must be surgical and preserve the complete approved baseline unless the project owner explicitly requests removal.
5. Before implementation, every Worker/Lead must prove ancestry from the approved source commit. If ancestry cannot be proven, stop and reconcile onto this baseline before changing code.
6. Before release handoff, exact-source Quality Gates must be green and artifact filename, internal version, release metadata, source SHA and checksums must all refer to the same candidate.
7. A later release supersedes `0.3.25 / 0.3.25+25` only after explicit project-owner approval. The governance baseline record must be advanced in the same governance change; lineage may only move forward.
8. This owner-approved baseline lock **supersedes every older release-baseline/version/SHA statement anywhere in the repository when they conflict**, including historical `0.3.6+10` wording in older closure documents.

Mandatory block for every future production PR/handoff:

```text
[ALKENZY-ADV-RELEASE-LINEAGE-LOCK]
PREVIOUS-APPROVED-PLUGIN: 0.3.25
PREVIOUS-APPROVED-MOBILE: 0.3.25+25
BASELINE-COMMIT: bebde8238e60bb98742564e901ebb345b4c0d69a
NEW-VERSION: <must be at least 0.3.26+26>
BASELINE-ANCESTOR-VERIFIED: YES
NO-STALE-BRANCH-REPLACEMENT: YES
PRESERVE-APPROVED-0.3.25-BEHAVIOR: YES
```

The authoritative release-baseline record is `docs/mobile-redesign/ALKENZY_ADV_RELEASE_BASELINE.md`.

## Mandatory ALKENZY ADV 101-item bug closure constitution

For the current ALKENZY ADV mobile closure pass, every Lead, Worker, QA agent and automation that changes or validates mobile behavior MUST read and obey, in this order:

1. `AGENTS.md`
2. `docs/mobile-redesign/ALKENZY_ADV_RELEASE_BASELINE.md`
3. `docs/mobile-redesign/ALKENZY_ADV_BUG_CLOSURE_CONSTITUTION.md`
4. `docs/mobile-redesign/ALKENZY_ADV_BUG_UX_REGISTER_2026-08-24.md`
5. `docs/mobile-redesign/MOBILE_UI_REFERENCE.md`
6. `docs/mobile-redesign/MOBILE_UI_SCREEN_MATRIX.md`
7. `docs/mobile-redesign/MOBILE_UI_PROGRESS.md`

The bug register is frozen at **101 items (P0=5, P1=71, P2=25)** for this pass. The constitution owns the no-overlap Worker assignment, P0-first order, status flags, acceptance requirements and final zero-items gate.

Rules:

- Do not start mobile bug work without first identifying the exact owned Bug IDs from the constitution.
- Do not take another Worker’s Bug IDs unless the Lead explicitly reassigns them in the ledger.
- Do not close a functional bug with a visual-only patch.
- Do not use `[CLOSED]` without QA PASS on the integrated exact head.
- Preserve server-authoritative finance, permissions, API contracts and existing business semantics.
- Do not introduce unrelated features during this closure pass.

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
