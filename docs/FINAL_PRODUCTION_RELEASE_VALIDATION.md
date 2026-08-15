# SafeContracts final production release validation — SC-P10-032

`SC-P10-032` validates that the repository is release-candidate ready when the last roadmap tasks are merged. It does **not** fabricate evidence that requires a real production environment, real Firebase delivery, a physical Android device or business-owner acceptance.

## Automated repository gate

The release-readiness validator requires all of the following:

- the fixed roadmap total remains exactly **284** tasks;
- GitHub plan synchronization has materialized **284/284** task IDs;
- no planned task remains **To Do / unassigned**;
- all roadmap tasks are either already Done or are part of the final assigned closeout batch before merge;
- P9 and P10 each reconcile exactly to their planned counts;
- backend PHP/lint regression is wired for `SC-P9-016..019` and `SC-P9-038..044`;
- mobile validation `SC-P9-038..044` is present and therefore executed by `flutter test`;
- backup/restore manifest, migration chain, audit completeness, UAT catalog, RTL/accessibility and release-readiness checks remain wired into Quality Gates;
- repository standards, backend, mobile format/analyze/test and release-readiness jobs must all be green on the final merge candidate.

## Post-merge completion condition

After the final closeout PR merges and GitHub status synchronization runs, `docs/PROJECT_STATUS.md` must report:

- `284` Planned;
- `0` To Do;
- `0` In Progress;
- `284` Done;
- `100.0%` completion.

This post-merge state is verified from GitHub before the roadmap itself is declared complete.

## External production evidence that is still environment-specific

The repository can validate procedures and automation, but the following evidence must come from the target environment and must not be invented by CI:

- a restore rehearsal against the actual production backup set and database;
- successful Firebase delivery using the real production project/service account;
- signed or release-built APK verification on at least one real target Android device;
- final business-owner/UAT sign-off for the deployed environment;
- production monitoring/rollback observation after deployment.

These external checks are deployment evidence, not missing application implementation tasks. The runbooks/UAT manifests define how they are collected.

## Closeout evidence

- `scripts/release_readiness.py --check`
- `docs/PRODUCTION_RELEASE_READINESS.md`
- `docs/BACKUP_RESTORE_RUNBOOK.md`
- `ops/uat-scenarios.json`
- `tests/php/p10_release_readiness_011_016.php`
- `tests/php/p10_validation_017_026.php`
- `tests/php/rest_mobile_mutations_016_019.php`
- `tests/php/p9_validation_038_044.php`
- `mobile/test/mobile_validation_038_044_test.dart`
- GitHub Quality Gates on the final merge candidate.
