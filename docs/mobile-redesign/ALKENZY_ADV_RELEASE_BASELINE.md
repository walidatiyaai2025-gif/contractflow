# ALKENZY ADV — Locked Mobile Release Baseline

Status: **PROJECT-OWNER APPROVED / LOCKED**

This file defines the mandatory source lineage for every future Alkenzy ADV WordPress plugin change, Android change, ZIP handoff and APK handoff.

## Current approved unified release

The project owner explicitly approved this release on **2026-08-28**:

- Product: `Alkenzy ADV`
- Approved WordPress plugin: **`0.3.25`**
- Approved Android release: **`0.3.25+25`**
- Approved versionName: `0.3.25`
- Approved build number: `25`
- Exact approved functional source commit: **`bebde8238e60bb98742564e901ebb345b4c0d69a`**
- Source branch at approval: `fix/0.3.14-mobile-data-report-closure`
- Approval / integration vehicle: PR `#665`
- Exact successful release-candidate workflow: `ALKENZY 0.3.25 Release Candidate`, run `33135245766`
- Release-candidate artifact: `alkenzy-0.3.25-release-candidate`
- Production API recorded in artifact metadata: `https://sys.alkenzy.com/wp-json/safecontracts/v1/`
- Plugin artifact: `SafeContracts-0.3.25.zip`
- Plugin SHA-256: `efb85bc60d1346b71aa23afaee4d26230b95e3daec827686494931d62834455f`
- APK artifact: `Alkenzy-ADV-0.3.25-sys.alkenzy.com.apk`
- APK SHA-256: `1cd8876390d3ce86c56e56dd0af9181b40072ba26cfb545e824ba019c2682d91`
- Previous locked baseline: `0.3.6+10`, source `9171f1c357822f9118eb8058aab6fb145c475fc3`
- Git ancestry proof: previous `0.3.6+10` source is the merge-base and ancestor of the approved `0.3.25` source; comparison is `ahead_by=405`, `behind_by=0`.

The `0.3.25 / 0.3.25+25` source therefore supersedes all earlier release baselines while preserving their ancestry. No later worker may restart from an older snapshot and silently lose accepted behavior.

## Mandatory forward-only source rule

Every future Alkenzy ADV modification MUST start from exact approved source commit:

`bebde8238e60bb98742564e901ebb345b4c0d69a`

or from a commit that is proven to be its descendant.

Forbidden starting points include:

- repository `main` merely because it is the default branch, unless its selected commit is proven to contain this baseline;
- an older release branch;
- a stale PR head;
- an abandoned Worker branch;
- a historical APK/plugin branch;
- any commit that cannot prove ancestry from the approved `0.3.25` functional source.

If a candidate branch is not a descendant, the Lead MUST reconcile the intended change onto the approved baseline before implementation continues.

No future change may wholesale-copy an older implementation over files from this release. Ports must be surgical and preserve all accepted `0.3.25` behavior unless the project owner explicitly requests removal or replacement.

## Mandatory version rule

`0.3.25` and build number `25` are consumed and may never be reused for a different build.

For every owner-facing release after this baseline:

1. `wordpress-plugin/safecontracts/safecontracts.php` and `mobile/pubspec.yaml` MUST advance to the same new semantic product version.
2. Mobile MUST use a strictly larger build number.
3. The default next release is **at least `0.3.26+26`**. A higher forward version is allowed if explicitly selected, but no lower version may be used.
4. The version bump must be committed before final release build generation.
5. Plugin ZIP filename, APK filename, internal metadata, CI artifact metadata, checksum record and handoff text must all identify the same exact release lineage.
6. Exact-source Quality Gates and release-readiness must pass before the release is described as verified or ready.
7. A later approved release becomes the new baseline only after explicit project-owner acceptance and a governance update of this file. Lineage may move forward only.

## Mandatory ancestry block

Every future production PR and handoff must include:

```text
[ALKENZY-ADV-RELEASE-LINEAGE-LOCK]
PREVIOUS-APPROVED-PLUGIN: 0.3.25
PREVIOUS-APPROVED-MOBILE: 0.3.25+25
BASELINE-COMMIT: bebde8238e60bb98742564e901ebb345b4c0d69a
NEW-VERSION: <must be at least 0.3.26+26>
BASELINE-ANCESTOR-VERIFIED: YES
NO-STALE-BRANCH-REPLACEMENT: YES
PRESERVE-APPROVED-0.3.25-BEHAVIOR: YES
```

The Lead must verify the ancestry claim rather than merely copy the text.

## Artifact identity

The owner-approved `0.3.25` release candidate was generated from the exact functional SHA above. Its release metadata records:

```text
source_sha=bebde8238e60bb98742564e901ebb345b4c0d69a
plugin_version=0.3.25
mobile_version=0.3.25+25
api=https://sys.alkenzy.com/wp-json/safecontracts/v1/
```

Artifact checksums are immutable acceptance evidence for this baseline:

```text
efb85bc60d1346b71aa23afaee4d26230b95e3daec827686494931d62834455f  SafeContracts-0.3.25.zip
1cd8876390d3ce86c56e56dd0af9181b40072ba26cfb545e824ba019c2682d91  Alkenzy-ADV-0.3.25-sys.alkenzy.com.apk
```

Do not label another binary as this approved release if its checksum differs.

## Supersession precedence

This file and the owner-approved baseline lock in root `AGENTS.md` supersede every older release-baseline/version/SHA statement elsewhere in the repository when they conflict, including historical `0.3.6+10` wording in the bug-closure constitution or older handoff documents.

The older records remain useful as lineage history; they are not valid future starting baselines.

## Preserved release history

| Release | Functional source | State |
|---|---|---|
| `0.3.5+9` | `458e3580d07eb182224c3652bb18d3c82b87adbd` | Superseded, preserved ancestor |
| `0.3.6+10` | `9171f1c357822f9118eb8058aab6fb145c475fc3` | Superseded, proven ancestor |
| `0.3.25+25` | `bebde8238e60bb98742564e901ebb345b4c0d69a` | **CURRENT PROJECT-OWNER APPROVED BASELINE** |
