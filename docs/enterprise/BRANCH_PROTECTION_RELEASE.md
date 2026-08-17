# Enterprise Safe Contracts branch protection and release contract

## Purpose

`enterprise-safecontracts` is the ESC integration and production source branch. Production publication must not depend on bypassing its pull-request protections or on a weak `protected=true` signal.

## Required GitHub enforcement

The repository administrator must complete parent #522 for `enterprise-safecontracts` with all schema-v2 controls:

- pull requests required for ordinary changes;
- required status checks `esc-foundation` and `esc-mobile`;
- required checks evaluated against the current/up-to-date merge candidate;
- administrators enforced for routine branch writes;
- pull-request conversation resolution required;
- force pushes disabled;
- branch deletion disabled;
- explicit retained break-glass policy.

The branch is not release-ready while GitHub reports `protected=false`, while any schema-v2 control is absent, or while the independent branch-protection audit does not return `decision=PASS`.

## Read-only verification credential

GitHub's authoritative branch-protection endpoint requires repository Administration read access. The `esc-production` environment therefore contains the same ESC-scoped read-only credential used by the UAT candidate gate:

```text
ESC_GITHUB_ADMIN_READ_TOKEN
```

The credential must be a fine-grained GitHub credential scoped only to `walidatiyaai2025-gif/contractflow` with repository **Administration: read only**. Do not grant Administration write.

The stable publication workflow uses this credential only to read:

- the current `enterprise-safecontracts` branch object;
- effective branch rules;
- legacy branch-protection configuration.

It contains no branch-protection mutation path.

## Publication behavior

`Publish Enterprise Safe Contracts Mobile Latest` is dispatched from `enterprise-safecontracts` and checks out the exact dispatch SHA. Before inspecting or materializing production signing/Firebase material, the workflow:

1. requires `ESC_GITHUB_ADMIN_READ_TOKEN`;
2. captures the live branch object and requires the current branch head to equal the exact dispatch `GITHUB_SHA`;
3. captures effective branch rules and authoritative legacy protection;
4. runs `scripts/audit_esc_branch_protection.py`;
5. requires audit schema version `2`, `decision=PASS`, and every required #522 control to be true;
6. records the sanitized audit SHA-256 for stable provenance.

A partially protected branch therefore cannot reach production signing, verified-artifact retention or `esc-mobile-latest` publication.

If the branch advances after dispatch, start a new release from the new exact head. Do not rewrite the UAT evidence source SHA.

## Stable provenance and retained assets

The workflow does **not** commit or push `Last verified Enterprise apk/` back to the integration branch. `enterprise_verified_artifacts.py` uses that directory only as an isolated staging contract inside the release job.

For Android publication, `VERIFIED.json` now embeds and validates:

- exact ESC source SHA;
- build/run identity;
- APK SHA-256;
- signing, Android package and Firebase identity confirmation;
- finalized exact-source coexistence/UAT evidence;
- the sanitized schema-v2 branch-protection audit;
- the audit SHA-256;
- an explicit binding between that audit and the exact stable source SHA.

The script rejects publication if the audit is malformed, non-canonical, targets another branch, has `decision!=PASS`, is missing `esc-foundation`/`esc-mobile`, does not prove administrator enforcement, does not prove conversation resolution, has an invalid captured-input digest, or does not contain every required schema-v2 control.

The retained workflow artifact and ESC-only GitHub Release contain:

```text
EnterpriseSafeContracts-latest.apk
EnterpriseSafeContracts-latest.apk.sha256
VERIFIED.json
ESC_BRANCH_PROTECTION_AUDIT.json
ESC_BRANCH_PROTECTION_AUDIT.json.sha256
```

The GitHub Release tag remains `esc-mobile-latest` and targets the exact source SHA.

## Bypass / break-glass policy

Normal publication requires no branch-protection bypass. Do not grant a general human or GitHub Actions bypass merely to make the release workflow succeed.

If an emergency administrator break-glass action is ever unavoidable, it must be time-bounded, explicitly approved, logged in the release incident/evidence record, and removed immediately after the emergency action. A bypass must never substitute for failing `esc-foundation`, `esc-mobile`, Android coexistence UAT, Firebase identity evidence, signing verification or exact-source release validation.

## Verification

Repository CI pins the workflow contract with:

```bash
python3 tests/python/esc_release_branch_protection_contract_test.py
```

It also validates the stable provenance parser with adversarial schema-v2 audit cases:

```bash
python3 tests/python/enterprise_verified_artifacts_branch_audit_test.py
```

These repository checks are defense in depth. They do not configure GitHub branch protection; the administrative enforcement tracked by #522 remains mandatory before UAT signing or stable production release.
