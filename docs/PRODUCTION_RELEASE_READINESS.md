# SafeContracts production release readiness

`SC-P10-011..016` introduce a blocking release-readiness contract. A build is **not production-ready** merely because it compiles; the exact merge candidate must pass repository standards, backend regressions, Flutter format/analyze/tests, and the dedicated release-readiness verifier.

## Blocking gate

The GitHub `Quality Gates` workflow runs a final `release-readiness` job after the repository, backend and mobile jobs succeed. The job executes:

```bash
python3 scripts/backup_manifest.py --check
python3 scripts/release_readiness.py --check
```

The verifier fails closed if required audit hooks, migration continuity, accessibility/RTL markers, backup scope, UAT scenario coverage, release documentation, or CI wiring are missing.

## P10 implementation evidence

- **SC-P10-011 Audit completeness** — critical financial, assignment, lifecycle, follow-up, import/export events must remain registered in `AuditRecorder`; secret-like audit context is filtered before persistence.
- **SC-P10-012 RTL/accessibility pass** — admin RTL/responsive CSS and mobile RTL locale handling are verified; mobile status feedback uses semantics/live regions and forbidden state cannot expose retry behavior that implies authorization recovery.
- **SC-P10-013 Backup/restore verification** — `scripts/backup_manifest.py` derives the SafeContracts table boundary from migrations; `docs/BACKUP_RESTORE_RUNBOOK.md` defines restore evidence and external-secret exclusions.
- **SC-P10-014 Migration/upgrade testing** — migration versions/classes are checked for deterministic ordering, complete source files and equality between the maximum migration version and `Migrator::LATEST_VERSION`; PHP regression confirms latest-version idempotence.
- **SC-P10-015 UAT scenarios** — `ops/uat-scenarios.json` defines role-specific contract, scope, collection, follow-up, export, read-only, mobile and upgrade/restore acceptance flows with expected outcomes and evidence.
- **SC-P10-016 Production release readiness** — CI runs the final verifier only after all primary Quality Gates pass.

## Mandatory retained release artifacts

Passing Quality Gates does not automatically place a binary in the repository. After the exact candidate has also satisfied the applicable environment/device acceptance checks, the release operator must retain the latest verified artifacts according to `AGENTS.md` and `docs/PRODUCTION_ENVIRONMENT_BUILD.md`:

- `Last verified Plugin/SafeContracts-latest.zip`
- `Last verified Plugin/SafeContracts-latest.zip.sha256`
- `Last verified Plugin/VERIFIED.json`
- `Last verified apk/SafeContracts-latest.apk`
- `Last verified apk/SafeContracts-latest.apk.sha256`
- `Last verified apk/VERIFIED.json`

Only one current plugin ZIP and one current APK are kept in those folders. Historical packages belong in GitHub Releases.

Use:

```bash
python3 scripts/verified_artifacts.py check
```

and, after both binaries are genuinely verified:

```bash
python3 scripts/verified_artifacts.py publish \
  --plugin /path/to/SafeContracts.zip \
  --apk /path/to/app-release.apk \
  --source-sha <40-char-commit-sha> \
  --quality-run-id <github-actions-run-id> \
  --quality-gates-passed
```

The helper refuses APK publication while `mobile/android/` is absent, which prevents a debug/fabricated file from being recorded as production verified.

## Human/environment acceptance still required

Repository automation cannot prove a real production database restore, live Firebase delivery, real-device APK behavior, Android release signing, or business-owner acceptance by itself. Before go-live, attach the environment-specific evidence described in the backup runbook, production environment build checklist and UAT manifest to the deployment/change record. The automated gate ensures the implementation contract and regression evidence are present and internally consistent; it does not fabricate external operational evidence.

At the time the retained-artifact policy was introduced, the repository's Flutter tree did not yet include `mobile/android/`. A real production APK therefore remains blocked until the Android scaffold, production application identity and secret-safe release signing are added and reviewed.
