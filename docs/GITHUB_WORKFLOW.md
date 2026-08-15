# SafeContracts — GitHub Delivery Workflow

GitHub is the execution memory and source of delivery truth for the team.

## Rules

1. Every implementation task has a stable ID: `SC-Px-NNN`.
2. Before coding, review open Issues/PRs to avoid duplicate or conflicting work.
3. Work is performed on a branch and submitted through a PR.
4. Commits and PRs reference the relevant task ID(s).
5. Requirements/architecture decisions that affect implementation are updated in `docs/` in the same PR or a linked documentation PR.
6. A task is not considered Done only because code exists locally; it is Done when its GitHub Issue is closed after the agreed validation/merge conditions.
7. CI/tests relevant to the change must pass before merge.
8. Team members build on existing merged/open work rather than re-implementing it.
9. `docs/PROJECT_STATUS.md` is machine-maintained from task Issues.
10. Sensitive changes (permissions, finance, API auth, migrations) require explicit test coverage/review evidence.

## Status model

| GitHub state | Project status |
|---|---|
| Open + no assignee | To Do |
| Open + one or more assignees | In Progress |
| Closed | Done |

## Task identification

A production task issue must have:

- Title starting with a task ID such as `SC-P3-014`
- `safecontracts-task` label
- Phase label such as `phase:P3`
- Acceptance criteria in its body

## Branch naming

Recommended:

- `feat/SC-P2-004-contract-financial-items`
- `fix/SC-P5-011-notification-dedupe`
- `test/SC-P8-021-accountant-scope`
- `docs/SC-P0-010-api-conventions`

## Pull requests

Each PR should state:

- Task IDs addressed
- What changed
- Database/API impact
- Permission/security impact
- Tests run
- Screenshots for UI changes where useful
- Follow-up work not included

## Live plan automation

- `Plan Sync` creates any missing planned task Issues.
- `Project Status` recalculates per-phase task totals/status from GitHub Issues.
- The status workflow reacts to issue state/assignment changes and also runs hourly as a safety sync.
- The table in `docs/PROJECT_STATUS.md` must not be manually used as a substitute for updating the actual Issue.
