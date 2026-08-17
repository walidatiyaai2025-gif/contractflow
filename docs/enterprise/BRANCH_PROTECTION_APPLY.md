# ESC Branch Protection Apply Helper

## Purpose

`scripts/configure_esc_branch_protection.ps1` is the repository-admin operator helper for parent #522.

It exists because ESC release/UAT workflows intentionally refuse production material while `enterprise-safecontracts` does not satisfy the full release-control contract.

The helper is deliberately safe by default:

- without `-Apply`, it only resolves the required GitHub Actions source identity and prints the exact protection payload;
- with `-Apply`, it operates only on `walidatiyaai2025-gif/contractflow` / `enterprise-safecontracts`;
- it uses the operator's existing authenticated `gh` session for the administrative write;
- it never reads or stores a GitHub token in repository files;
- after GitHub accepts the update, it captures live configuration and runs `audit_esc_branch_protection.py`;
- it refuses to overwrite an existing evidence directory.

## Required-check source identity

Before building the payload, the helper resolves the current `github-actions` GitHub App through GitHub's official public App API:

```text
GET https://api.github.com/apps/github-actions
```

The returned App ID must be a positive integer and the returned slug must be exactly `github-actions`. The helper does **not** hardcode a numeric App ID.

That resolved App ID is placed explicitly on both required status checks:

```text
esc-foundation -> app_id = <resolved GitHub Actions App ID>
esc-mobile     -> app_id = <resolved GitHub Actions App ID>
```

The helper never uses `app_id=-1` and never intentionally configures an any-source required status check.

## Proposed protection controls

The helper configures legacy branch protection with:

- pull requests required for branch changes;
- **zero mandatory approving reviewers**;
- no last-push approval requirement;
- `esc-foundation` required and source-pinned to GitHub Actions;
- `esc-mobile` required and source-pinned to GitHub Actions;
- strict/up-to-date required status checks;
- administrator enforcement;
- conversation resolution;
- force-push disabled;
- branch deletion disabled.

The zero-reviewer setting is intentional. Parent #522 requires PR-only delivery and mandatory CI but does not require an independent human reviewer. Requiring one approval or last-push approval could deadlock a solo-maintained repository without adding an acceptance requirement from #522.

## Preview first

From the repository root in PowerShell:

```powershell
pwsh -File .\scripts\configure_esc_branch_protection.ps1
```

Preview now requires network access to GitHub's public App identity endpoint so the exact source-pinned payload can be shown. If GitHub Actions identity cannot be resolved, preview fails closed instead of falling back to an unbound status check.

Review the printed output. It must show a positive resolved `github-actions` App ID and both mandatory checks must use that same ID. Also confirm:

```text
required_approving_review_count = 0
require_last_push_approval = false
```

alongside strict status checks, admin enforcement, conversation resolution and disabled force-push/deletion.

## Authentication

Authenticate GitHub CLI with the approved repository-owner/admin identity:

```powershell
gh auth status
```

If necessary:

```powershell
gh auth login
```

The authenticated identity must be able to administer branch protection. Do not paste tokens into the script or commit them anywhere.

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

1. verifies the exact target repository and branch;
2. resolves and validates the current GitHub Actions App ID;
3. constructs a source-pinned protection payload using that ID for `esc-foundation` and `esc-mobile`;
4. verifies `gh` and Python are available;
5. verifies `gh` is authenticated;
6. applies the reviewed protection payload through GitHub's REST API;
7. captures:
   - `branch.json`;
   - `rules.json`;
   - `protection.json`;
   - `github-actions-app.json` containing only the resolved slug/ID used by the helper;
8. runs `scripts/audit_esc_branch_protection.py` with `--expected-status-check-app-id` set to the same resolved ID;
9. writes schema-v3 `esc-branch-protection-audit.json`;
10. returns success only if the independent audit passes.

If GitHub accepts the update but the audit fails, the helper exits with an error and leaves all evidence in place for diagnosis. It does not weaken or roll back protection automatically.

## Evidence review

Before #522 can close, retain and review:

- the captured GitHub JSON snapshots;
- `github-actions-app.json` showing the source identity used to construct the payload;
- the content-addressed schema-v3 audit JSON;
- the approved break-glass statement;
- evidence that all intended repository administration controls are active.

The schema-v3 audit must show:

- `expected_status_check_app_slug = github-actions`;
- the positive expected App ID;
- that both `esc-foundation` and `esc-mobile` include that ID in `observed_required_check_source_ids`;
- `required_status_check_sources_verified = true`;
- all other #522 controls true.

A matching check name without source binding is not acceptable release evidence.

## Safety boundary

Do not test the real protected branch by attempting force-push or deletion. Configuration evidence should be retained safely; destructive enforcement exercises belong on an approved disposable branch/ruleset scenario.

This helper does not:

- run automatically in CI;
- hardcode the GitHub Actions App ID;
- configure any-source checks;
- store authentication credentials;
- dispatch the ESC production-signed UAT candidate;
- build or publish an APK;
- fabricate branch-protection evidence;
- close #522 automatically;
- prove or close #421 real-device/Firebase coexistence UAT.

After #522 controls are applied and schema-v3 evidence is reviewed, re-check the live branch state and only then dispatch `ESC Android UAT Candidate` on the exact `enterprise-safecontracts` head.
