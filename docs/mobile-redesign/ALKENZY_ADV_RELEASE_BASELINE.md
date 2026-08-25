# ALKENZY ADV — Locked Mobile Release Baseline

Status: **PROJECT-APPROVED SOURCE / LOCKED**

This file defines the mandatory source lineage for every future Alkenzy ADV Android change and APK handoff.

## Current approved release

- Product: `Alkenzy ADV`
- Approved unified release: `0.3.6+10`
- Approved plugin version: `0.3.6`
- Approved versionName: `0.3.6`
- Approved build number: `10`
- Immutable release baseline branch: `release/alkenzy-adv-mobile-0.3.6`
- Exact approved functional source commit: `9171f1c357822f9118eb8058aab6fb145c475fc3`
- Approval and merge vehicle: PR `#652`
- Previous approved release: `0.3.5+9`
- Previous approved functional source: `458e3580d07eb182224c3652bb18d3c82b87adbd`
- Original owner-approved lineage ancestor: `3d4dcd2205b5cfa7d0814e5635db577ee5dcefed`
- Approval date: `2026-08-25`

The approved source represented by this baseline contains the complete PR #652 Dashboard/mobile-landing/payment-follow-up/demo-data scope, B084 server-authoritative pagination, and the previous accepted biometric-login, Remember-me default-off, report/download, and Arabic RTL report corrections. Commit `458e3580d07eb182224c3652bb18d3c82b87adbd` is a real ancestor of the new functional source; it is not merely referenced in documentation.

Exact-head CI artifacts prove the candidate build path. A production-signed APK remains subject to the separate signing, real-device and business-UAT gates in `AGENTS.md`; this source approval does not fabricate those external proofs.

## Mandatory forward-only rule

Every future Alkenzy ADV plugin or mobile modification MUST start from `release/alkenzy-adv-mobile-0.3.6` or from a commit that is proven to be a descendant of that release baseline. Starting a new change from any older commit, stale PR head, abandoned worker branch, historical APK branch, or pre-`0.3.6+10` snapshot is forbidden.

No future change may silently restore or wholesale-copy an older implementation over files from this release baseline. Porting from another branch must be surgical and must preserve all behavior already present in this baseline unless the project owner explicitly requests its removal.

## Mandatory version rule

`0.3.6+10` is consumed and may never be reused for another release.

For every APK handed to the project owner after this baseline:

1. `wordpress-plugin/safecontracts/safecontracts.php` and `mobile/pubspec.yaml` MUST advance to the same new semantic product version; mobile MUST also use a strictly larger build number.
2. The default next release is **at least `0.3.7+11`**. A higher semantic version is allowed, but `0.3.6` and build number `10` may not be reused.
3. The version bump must be committed before the final release build is produced.
4. The APK filename, CI artifact metadata, release notes, checksum record and handoff message must all identify the same version.
5. A production-code PR is not release-ready if its version still equals `0.3.6+10` or duplicates any later consumed release number.
6. `python3 scripts/validate-release-version.py` is the CI enforcement gate for canonical-version consistency and forward-only production changes.

## Mandatory ancestry check

Every future mobile PR/handoff must record:

```text
ALKENZY-ADV-RELEASE-LINEAGE: LOCKED
PREVIOUS-APPROVED-RELEASE: 0.3.6+10
BASELINE-BRANCH: release/alkenzy-adv-mobile-0.3.6
BASELINE-COMMIT: 9171f1c357822f9118eb8058aab6fb145c475fc3
NEW-VERSION: <must be at least 0.3.7+11>
BASELINE-ANCESTOR-VERIFIED: YES
NO-STALE-BRANCH-REPLACEMENT: YES
```

The Lead must verify that the approved baseline is an ancestor of the new functional release lineage before declaring a later APK accepted.

## Supersession

This baseline remains authoritative until the project owner explicitly approves a later release. When a later release is approved, update this file in the same governance change, preserving forward lineage from `0.3.6+10`. Never delete the lineage history by resetting to an earlier base.

## Preserved release history

| Release | Functional source | State |
|---|---|---|
| `0.3.5+9` | `458e3580d07eb182224c3652bb18d3c82b87adbd` | Superseded, preserved ancestor |
| `0.3.6+10` | `9171f1c357822f9118eb8058aab6fb145c475fc3` | Current approved source baseline |
