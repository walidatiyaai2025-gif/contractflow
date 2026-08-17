# ESC Branch Protection Audit Evidence

## Purpose

Parent issue #522 requires repository-admin enforcement on `enterprise-safecontracts`
before production delivery or production-signed physical-device UAT may proceed.

`scripts/audit_esc_branch_protection.py` is a **read-only verifier** for captured
GitHub configuration. It does not configure branch protection, does not use an
administrator token itself and does not weaken any release gate.

The audit fails closed unless the captured evidence proves all of the following:

- the target is exactly `enterprise-safecontracts`;
- GitHub reports the branch protected;
- pull requests are required;
- both `esc-foundation` and `esc-mobile` are required status checks;
- required status checks are strict/up-to-date;
- force pushes are blocked;
- branch deletion is blocked;
- an explicit break-glass/bypass statement is retained.

The verifier supports legacy branch protection, repository/organization rulesets,
or a combination of both.

## Capture authoritative GitHub evidence

Use an authenticated GitHub CLI session belonging to a repository administrator
or another identity that has read access to the relevant administration/rules
configuration. Do not commit the token or raw authentication material.

From a clean evidence directory:

```powershell
$Repo = 'walidatiyaai2025-gif/contractflow'
$Branch = 'enterprise-safecontracts'

gh api "repos/$Repo/branches/$Branch" > branch.json
gh api "repos/$Repo/rules/branches/$Branch" > rules.json
```

If legacy branch protection is configured, also capture it:

```powershell
gh api "repos/$Repo/branches/$Branch/protection" > protection.json
```

If that endpoint returns `404` because enforcement is supplied exclusively by
rulesets, omit `--protection-json`. The effective-rules capture is still
mandatory.

Keep the captured JSON files unchanged. The audit record stores SHA-256 digests
for every supplied input so later evidence review can detect substitution.

## Run the verifier

Ruleset-only example:

```powershell
python .\scripts\audit_esc_branch_protection.py `
  --branch-json .\branch.json `
  --rules-json .\rules.json `
  --break-glass-note 'No routine bypass. Emergency repository-admin bypass is documented and requires owner approval.' `
  --output .\esc-branch-protection-audit.json
```

Legacy-protection example:

```powershell
python .\scripts\audit_esc_branch_protection.py `
  --branch-json .\branch.json `
  --rules-json .\rules.json `
  --protection-json .\protection.json `
  --break-glass-note 'No routine bypass. Emergency repository-admin bypass is documented and requires owner approval.' `
  --output .\esc-branch-protection-audit.json
```

The command returns exit code `0` only for `decision=PASS`. It still writes a
content-addressed audit file on failure so the exact blocker remains reviewable.

## Ruleset interpretation

For effective rules returned by GitHub, the verifier requires:

- `pull_request`;
- `required_status_checks`, including `esc-foundation` and `esc-mobile`;
- `strict_required_status_checks_policy=true`;
- `non_fast_forward`;
- `deletion`.

For legacy branch protection it accepts the equivalent controls:

- `required_pull_request_reviews`;
- strict `required_status_checks`;
- `allow_force_pushes.enabled=false`;
- `allow_deletions.enabled=false`.

Controls may be satisfied by a combination of legacy protection and active
rulesets.

## Break-glass boundary

The audit intentionally requires a human-authored break-glass statement because
effective branch rules do not by themselves prove the operational intent of
administrator or ruleset bypass actors.

The statement must describe the actual repository policy. Do not write a
placeholder merely to obtain PASS. Parent #522 remains responsible for retaining
the administrative configuration and any approved emergency-bypass procedure.

## Acceptance boundary

A PASS from this verifier is configuration evidence, not proof that destructive
operations were attempted against the protected production branch.

Do **not** test branch deletion or force-push by risking the real
`enterprise-safecontracts` branch. GitHub's captured active enforcement plus the
retained audit record is the safe configuration evidence; any separate controlled
enforcement test must use an approved disposable branch/ruleset scenario.

This verifier does not:

- enable or modify protection/rulesets;
- dispatch `ESC Android UAT Candidate`;
- make an unprotected branch eligible for production signing;
- prove physical-device/Firebase coexistence;
- close #421;
- close #522 by itself.

After repository administration enables #522 controls, capture fresh GitHub JSON,
obtain `decision=PASS`, retain the input snapshots and audit file with release
evidence, then re-check the live branch state before dispatching the exact-source
UAT candidate.
