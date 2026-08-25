# ALKENZY ADV — Locked Mobile Release Baseline

Status: **PROJECT-APPROVED / LOCKED**

This file defines the mandatory source lineage for every future Alkenzy ADV Android change and APK handoff.

## Current approved release

- Product: `Alkenzy ADV`
- Approved version: `0.3.5+9`
- Approved versionName: `0.3.5`
- Approved build number: `9`
- Immutable release baseline branch: `release/alkenzy-adv-mobile-0.3.5`
- Exact approved functional source commit: `458e3580d07eb182224c3652bb18d3c82b87adbd`
- Original owner-approved lineage ancestor: `3d4dcd2205b5cfa7d0814e5635db577ee5dcefed`
- Exact-head Quality Gates run: `32813745920` (`Quality Gates #1513`)
- Approved APK SHA-256: `0ad0558fc993aa1b462280d44bfefe42bb0e44768670558e03e481b3b6408874`
- Approval date: `2026-08-25`

The approved APK represented by this baseline includes the accepted dashboard lineage plus the latest accepted biometric-login, Remember-me default-off, report/download, and Arabic RTL report corrections.

## Mandatory forward-only rule

Every future Alkenzy ADV mobile modification MUST start from `release/alkenzy-adv-mobile-0.3.5` or from a commit that is proven to be a descendant of that release baseline. Starting a new mobile change from any older commit, stale PR head, abandoned worker branch, historical APK branch, or pre-`0.3.5+9` snapshot is forbidden.

No future change may silently restore or wholesale-copy an older implementation over files from this release baseline. Porting from another branch must be surgical and must preserve all behavior already present in this baseline unless the project owner explicitly requests its removal.

## Mandatory version rule

`0.3.5+9` is consumed and may never be reused for another APK.

For every APK handed to the project owner after this baseline:

1. `mobile/pubspec.yaml` MUST use a different `versionName` and a strictly larger build number.
2. The default next release is **at least `0.3.6+10`**. A higher semantic version is allowed, but `0.3.5` and build number `9` may not be reused.
3. The version bump must be committed before the final release build is produced.
4. The APK filename, CI artifact metadata, release notes, checksum record and handoff message must all identify the same version.
5. A code-changing PR that can produce a user-facing APK is not release-ready if its version still equals `0.3.5+9` or duplicates any later consumed release number.

## Mandatory ancestry check

Every future mobile PR/handoff must record:

```text
ALKENZY-ADV-RELEASE-LINEAGE: LOCKED
PREVIOUS-APPROVED-RELEASE: 0.3.5+9
BASELINE-BRANCH: release/alkenzy-adv-mobile-0.3.5
BASELINE-COMMIT: 458e3580d07eb182224c3652bb18d3c82b87adbd
NEW-VERSION: <must differ from 0.3.5+9>
BASELINE-ANCESTOR-VERIFIED: YES
NO-STALE-BRANCH-REPLACEMENT: YES
```

The Lead must verify that the approved baseline is an ancestor of the new functional release lineage before declaring a later APK accepted.

## Supersession

This baseline remains authoritative until the project owner explicitly approves a later APK. When a later APK is approved, update this file in the same change that records the new release, preserving forward lineage from `0.3.5+9`. Never delete the lineage history by resetting to an earlier base.
