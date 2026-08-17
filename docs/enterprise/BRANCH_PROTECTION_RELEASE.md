# Enterprise Safe Contracts branch protection and release contract

## Purpose

`enterprise-safecontracts` is the ESC integration and production source branch. Production publication must not depend on bypassing its pull-request protections.

## Required GitHub enforcement

The repository administrator must enable branch protection or an equivalent ruleset for `enterprise-safecontracts` with these controls:

- pull requests required for ordinary changes;
- required status checks `esc-foundation` and `esc-mobile`;
- required checks evaluated against the current/up-to-date merge candidate or an equivalent merge queue;
- force pushes disabled;
- branch deletion disabled.

The branch is not release-ready while GitHub reports `protected=false`.

## Publication behavior

`Publish Enterprise Safe Contracts Mobile Latest` is dispatched from `enterprise-safecontracts`, but it builds the exact dispatch SHA rather than re-reading a moving branch head. Before loading production secrets or building the APK it queries GitHub and fails closed unless:

1. `enterprise-safecontracts` reports `protected=true`; and
2. the current protected branch head still equals `GITHUB_SHA` for the dispatch.

If the branch advances after dispatch, start a new release from the new exact head. Do not rewrite the UAT evidence source SHA.

The workflow does **not** commit or push `Last verified Enterprise apk/` back to the integration branch. `enterprise_verified_artifacts.py` still uses that directory as an isolated staging contract inside the release job, then the verified APK, SHA-256 sidecar and `VERIFIED.json` are retained as:

- a GitHub Actions artifact tied to the exact source SHA/run; and
- assets on the ESC-only `esc-mobile-latest` GitHub Release targeted at the exact source SHA.

This keeps publication compatible with PR-only branch protection and removes the need for a routine GitHub Actions branch-protection bypass.

## Bypass / break-glass policy

Normal publication requires no branch-protection bypass. Do not grant a general human or GitHub Actions bypass merely to make the release workflow succeed.

If an emergency administrator break-glass action is ever unavoidable, it must be time-bounded, explicitly approved, logged in the release incident/evidence record, and removed immediately after the emergency action. A bypass must never be used to substitute for failing `esc-foundation`, `esc-mobile`, Android coexistence UAT, Firebase identity evidence, signing verification or exact-source release validation.

## Verification

Repository CI pins this contract with:

```bash
python3 tests/python/esc_release_branch_protection_contract_test.py
```

This repository check is defense in depth only. It does not configure GitHub branch protection; the administrative enforcement tracked by ESC-REL-001 remains required before production release.
