# ESC Branch Protection Apply Helper

## Purpose

`scripts/configure_esc_branch_protection.ps1` is the repository-admin operator
helper for parent #522.

It exists because ESC release/UAT workflows intentionally refuse to use
production signing material while `enterprise-safecontracts` is unprotected.

The helper is deliberately safe by default:

- without `-Apply`, it only prints the exact protection payload;
- with `-Apply`, it operates only on
  `walidatiyaai2025-gif/contractflow` / `enterprise-safecontracts`;
- it uses the operator's existing authenticated `gh` session;
- it never reads or stores a GitHub token in repository files;
- after GitHub accepts the update, it captures live configuration and runs
  `audit_esc_branch_protection.py`;
- it refuses to overwrite an existing evidence directory.

## Proposed protection controls

The helper configures legacy branch protection with:

- pull requests required for branch changes;
- **zero mandatory approving reviewers**;
- no last-push approval requirement;
- `esc-foundation` required;
- `esc-mobile` required;
- strict/up-to-date required status checks;
- administrator enforcement;
- conversation resolution;
- force-push disabled;
- branch deletion disabled.

The zero-reviewer setting is intentional. GitHub supports
`required_approving_review_count=0` while pull-request protection remains
configured. Parent #522 requires PR-only delivery and mandatory CI, but does not
require an independent human reviewer. Requiring one approval or last-push
approval could deadlock a personal/solo-maintained repository without adding an
acceptance requirement from #522.

If the repository later has an approved independent reviewer policy, increasing
the approval count should be a separate explicit governance decision rather than
an implicit release prerequisite.

The helper does not lock the branch, require linear history, restrict branch
creation or configure push restrictions that are unavailable/undesirable for
this user-owned repository.

## Preview first

From the repository root in PowerShell:

```powershell
pwsh -File .\scripts\configure_esc_branch_protection.ps1
```

The default execution is preview-only. Review the printed JSON before making any
administrative change. Confirm the preview contains:

```text
required_approving_review_count = 0
require_last_push_approval = false
```

alongside strict `esc-foundation` and `esc-mobile` required checks.

## Authentication

Authenticate GitHub CLI with the approved repository-owner/admin identity:

```powershell
gh auth status
```

If necessary:

```powershell
gh auth login
```

The authenticated identity must be able to administer branch protection for the
repository. Do not paste tokens into the script or commit them anywhere.

## Apply

After reviewing the preview:

```powershell
pwsh -File .\scripts\configure_esc_branch_protection.ps1 -Apply
```

Optional explicit evidence destination:

```powershell
pwsh -File .\scripts\configure_esc_branch_protection.ps1 `
  -Apply `
  -EvidenceRoot 'C:\ESC-UAT\branch-protection-evidence'
```

The directory must not already exist.

## What happens after apply

The helper:

1. verifies the target repository and branch are exact;
2. verifies `gh` and Python are available;
3. verifies `gh` is authenticated;
4. applies the reviewed protection payload through the GitHub REST API;
5. captures:
   - `branch.json`;
   - `rules.json`;
   - `protection.json`;
6. runs `scripts/audit_esc_branch_protection.py`;
7. writes `esc-branch-protection-audit.json`;
8. returns success only if the independent audit passes.

If GitHub accepts the update but the audit fails, the helper exits with an error
and leaves all evidence in place for diagnosis. It does not weaken or roll back
protection automatically.

## Evidence review

A successful run is not, by itself, permission to claim physical-device UAT.

Before #522 can close, retain and review:

- the three captured GitHub JSON snapshots;
- the content-addressed audit JSON;
- the approved break-glass statement;
- evidence that the intended repository administration controls are active.

The helper sets `enforce_admins=true`, so routine administrator bypass is not part
of the configured policy. Emergency protection changes remain a repository-owner
administrative act and must be explicitly approved and recorded in #522.

## Safety boundary

Do not test the real protected branch by attempting force-push or deletion.
Configuration evidence should be retained safely; any destructive enforcement
exercise belongs on an approved disposable branch/ruleset scenario.

This helper does not:

- run automatically in CI;
- store credentials;
- dispatch the ESC production-signed UAT candidate;
- build or publish an APK;
- fabricate branch-protection evidence;
- close #522 automatically;
- prove or close #421 real-device/Firebase coexistence UAT.

After #522 controls are applied and reviewed, re-check the live branch state and
only then dispatch `ESC Android UAT Candidate` on the exact
`enterprise-safecontracts` head.
