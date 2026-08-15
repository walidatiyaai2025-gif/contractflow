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

## Human/environment acceptance still required

Repository automation cannot prove a real production database restore, live Firebase delivery, real-device APK behavior, or business-owner acceptance by itself. Before go-live, attach the environment-specific evidence described in the backup runbook and UAT manifest to the deployment/change record. The automated gate ensures the implementation contract and regression evidence are present and internally consistent; it does not fabricate external operational evidence.
