# ESC Branch Protection Audit Evidence

## Purpose

Parent issue #522 requires repository-admin enforcement on `enterprise-safecontracts` before production delivery or production-signed physical-device UAT may proceed.

`scripts/audit_esc_branch_protection.py` is a **read-only verifier** for captured GitHub configuration. It does not configure branch protection and does not weaken any release gate.

The audit fails closed unless the captured evidence proves all of the following:

- the target is exactly `enterprise-safecontracts`;
- GitHub reports the branch protected;
- pull requests are required;
- both `esc-foundation` and `esc-mobile` are required status checks;
- each mandatory check is source-pinned to the current GitHub Actions App identity;
- required status checks are strict/up-to-date;
- administrator enforcement is explicitly enabled in captured legacy branch protection;
- pull-request conversation resolution is required by legacy protection or an active pull-request ruleset;
- force pushes are blocked;
- branch deletion is blocked;
- an explicit break-glass/bypass statement is retained.

## Schema v3 — authenticated required-check sources

Schema version 3 adds authenticated source binding for mandatory status checks. Context names alone are no longer sufficient.

GitHub exposes two equivalent source-binding fields:

- legacy branch protection: `required_status_checks.checks[].app_id`;
- rulesets: `required_status_checks[].integration_id`.

The verifier requires both mandatory contexts to be bound to the expected GitHub Actions App ID through at least one active enforcement source. Missing IDs, `-1` any-source values, or bindings to another integration do not satisfy `required_status_check_sources_verified`.

The expected identity is **not hardcoded**. If `--expected-status-check-app-id` is omitted, the verifier resolves the current `github-actions` App from GitHub's official `GET /apps/github-actions` endpoint and fails closed if that identity cannot be resolved to a positive App ID.

The audit records:

- `expected_status_check_app_slug = github-actions`;
- `expected_status_check_app_id`;
- `observed_required_check_source_ids` for `esc-foundation` and `esc-mobile`;
- `unbound_required_check_contexts`;
- `checks.required_status_check_sources_verified`.

Evidence from schema v1/v2 must not be reused for #522 after this change.

## Administrator-enforcement boundary

The verifier may combine legacy branch-protection evidence with effective ruleset rules for PR, status, strictness, source binding, conversation, force-push and deletion controls.

For #522, however, a ruleset-only effective-rules snapshot is intentionally **not sufficient** to prove administrator enforcement because the effective branch-rules endpoint does not prove the full bypass-actor policy. A PASS therefore requires captured legacy protection with `enforce_admins.enabled=true` unless the audit is explicitly extended in the future to consume authoritative ruleset bypass metadata.

## Capture authoritative GitHub evidence

Use an authenticated GitHub CLI session belonging to a repository administrator or another identity with read access to the relevant administration/rules configuration. Do not commit the token or raw authentication material.

```powershell
$Repo = 'walidatiyaai2025-gif/contractflow'
$Branch = 'enterprise-safecontracts'

gh api "repos/$Repo/branches/$Branch" > branch.json
gh api "repos/$Repo/rules/branches/$Branch" > rules.json
gh api "repos/$Repo/branches/$Branch/protection" > protection.json
```

The current #522 apply helper uses legacy branch protection deliberately, so `protection.json` is mandatory for a successful #522 audit. Keep all captured JSON files unchanged. The audit stores SHA-256 digests for every supplied configuration input.

## Run the verifier

The normal independent command resolves the GitHub Actions App identity at runtime:

```powershell
python .\scripts\audit_esc_branch_protection.py `
  --branch-json .\branch.json `
  --rules-json .\rules.json `
  --protection-json .\protection.json `
  --break-glass-note 'No routine bypass. Emergency repository-admin changes are documented and require owner approval.' `
  --output .\esc-branch-protection-audit.json
```

The admin apply helper passes the exact App ID it resolved before constructing the protection payload:

```text
--expected-status-check-app-id <resolved-positive-app-id>
```

This proves the post-apply audit is checking the same GitHub Actions source identity the helper configured.

The command returns exit code `0` only for `decision=PASS`. It still writes a content-addressed audit file for configuration failures after valid inputs have been captured so the exact blocker remains reviewable.

## Ruleset interpretation

Effective rules returned by GitHub can contribute:

- `pull_request`;
- `pull_request.parameters.required_review_thread_resolution=true`;
- `required_status_checks` including `esc-foundation` and `esc-mobile`;
- each required check's `integration_id` source binding;
- `strict_required_status_checks_policy=true`;
- `non_fast_forward`;
- `deletion`.

For legacy branch protection the verifier requires/accepts the equivalent controls:

- `required_pull_request_reviews`;
- strict `required_status_checks`;
- `checks[].app_id` source binding for mandatory checks;
- `enforce_admins.enabled=true`;
- `required_conversation_resolution.enabled=true` unless the effective `pull_request` ruleset proves review-thread resolution;
- `allow_force_pushes.enabled=false`;
- `allow_deletions.enabled=false`.

A matching context with no expected source binding is not authenticated release evidence.

## Why source pinning is required

GitHub status-check names are identifiers, not authentication boundaries. GitHub allows required checks to specify the GitHub App that must provide a status. ESC therefore treats `esc-foundation` and `esc-mobile` as valid release gates only when GitHub's captured protection/rules bind them to the resolved `github-actions` App.

This prevents another person/integration with status capabilities from satisfying #522 merely by emitting the same context name.

## Break-glass boundary

The audit requires a human-authored break-glass statement because effective rules do not by themselves prove the operational intent of administrator or ruleset bypass actors.

Do not write a placeholder merely to obtain PASS. Parent #522 remains responsible for retaining the administrative configuration and approved emergency-bypass procedure.

## Acceptance boundary

A PASS from this verifier is configuration evidence, not proof that destructive operations were attempted against the production branch.

Do **not** test branch deletion or force-push by risking the real `enterprise-safecontracts` branch. Captured active enforcement plus the retained audit record is the safe configuration evidence.

This verifier does not:

- enable or modify protection/rulesets;
- dispatch `ESC Android UAT Candidate`;
- publish `esc-mobile-latest`;
- make an unprotected branch eligible for production signing;
- prove physical-device/Firebase coexistence;
- close #421;
- close #522 by itself.

After repository administration enables #522 controls, obtain fresh **schema-v3** `decision=PASS`, retain the captured configuration and audit evidence, then re-check the live branch state before exact-source UAT candidate dispatch. UAT and stable publication independently run the same schema-v3 source-pinned audit before production material is available.
