# MigrationNNNN — <title>

- Source schema version: `<version>`
- Target schema version: `<version>`
- Related issue/PR: `<link or number>`
- Risk level: `low | medium | high`

## Preconditions
List exact schema/data/application assumptions that must be true before execution.

## Preflight
List read-only checks that prove the migration is safe to start.

## Backup and recovery checkpoint
State the required database backup, previous verified plugin package, configuration backup if applicable, restore owner and restore validation evidence.

## Forward plan
Describe the exact migration sequence. Prefer expand → migrate/backfill → verify. State how retries remain idempotent.

## Backfill and restart safety
Explain batching, checkpoints, duplicate prevention and how a partially completed run resumes safely.

## Post-migration invariants
List objective checks: row counts, foreign-key/reference integrity, totals/balances, required indexes/columns and business invariants.

## Rollback trigger
Define the measurable conditions that require rollback instead of continued troubleshooting.

## Rollback plan
Describe code/plugin rollback, database rollback/compensating actions and configuration rollback separately.

## Compatibility matrix
| Plugin/code version | Schema version(s) supported | Notes |
|---|---|---|
| previous | before | rollback baseline |
| candidate | before + after where possible | compatibility window |
| future contract release | after | only after rollback window closes |

## Destructive-change declaration
`none`

If not `none`, document production-owner approval, restore test evidence and why an additive two-release approach is impossible.
