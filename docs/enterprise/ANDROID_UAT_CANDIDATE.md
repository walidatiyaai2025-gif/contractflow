# ESC Android — Production-Signed UAT Candidate

## Purpose

The `ESC Android UAT Candidate` workflow (`.github/workflows/esc-uat-candidate.yml`) builds the exact production-signed ESC APK needed to execute the physical-device coexistence gate in #421.

This closes a release-ordering gap without weakening production publication: the stable `publish-mobile-latest.yml` workflow correctly requires finalized UAT/coexistence/Firebase evidence, so it cannot be the mechanism that creates the binary used to perform that same UAT.

A UAT candidate is **not a release** and is never treated as a verified retained production artifact.

## Security prerequisites

The workflow is deliberately fail-closed.

Before production signing or Firebase material is validated or materialized:

1. the dispatch must target `enterprise-safecontracts`;
2. the job must enter the existing `esc-production` GitHub environment;
3. `ESC_GITHUB_ADMIN_READ_TOKEN` must be available in that environment;
4. the workflow captures live branch, effective-rules and legacy branch-protection JSON using that read-only credential;
5. GitHub must report `enterprise-safecontracts` protected;
6. the current branch head must still equal the exact dispatch `GITHUB_SHA`;
7. `scripts/audit_esc_branch_protection.py` must return **schema v2** `decision=PASS` with every required control true;
8. only after that release-control gate may the approved ESC-only production signing and Firebase secrets be inspected or materialized.

A bare `protected=true` value is not sufficient. The live schema-v2 audit also requires the #522 controls including PR-only delivery, strict `esc-foundation` + `esc-mobile`, administrator enforcement, conversation resolution, force-push/deletion blocking and the retained break-glass policy.

Branch protection is therefore an execution dependency. Until #522 is complete and a fresh live schema-v2 audit passes, the candidate workflow must fail before production signing/Firebase materialization.

## Read-only GitHub administration credential

`ESC_GITHUB_ADMIN_READ_TOKEN` is separate from Android signing/Firebase material. Store it only in the `esc-production` environment.

It must be a fine-grained GitHub credential scoped to `walidatiyaai2025-gif/contractflow` with **Administration: read** only, sufficient to read the authoritative branch-protection and effective-rules configuration required by the schema-v2 audit.

Do not grant Administration write access to this credential. The UAT workflow contains no branch-protection mutation path and performs no `PUT` or `DELETE` administration call.

Rotate/revoke the credential through the normal repository security process. Do not commit it, print it, place it in candidate provenance or copy it into UAT evidence.

## Production secret namespace

After the branch-enforcement gate passes, the workflow requires the existing ESC-only production material:

- `ESC_ANDROID_KEYSTORE_BASE64`
- `ESC_ANDROID_KEYSTORE_PASSWORD`
- `ESC_ANDROID_KEY_ALIAS`
- `ESC_ANDROID_KEY_PASSWORD`
- `ESC_ANDROID_CERT_SHA256`
- `ESC_FIREBASE_ANDROID_CONFIG_DEV_BASE64`
- `ESC_FIREBASE_ANDROID_CONFIG_STAGING_BASE64`
- `ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION_BASE64`

Safe Contract signing/Firebase secrets are not inputs to this workflow.

## Dispatch

In GitHub Actions, select **ESC Android UAT Candidate**, choose `enterprise-safecontracts`, and provide the production ESC API base URL ending in:

```text
/wp-json/safecontracts/v1/
```

The input must use HTTPS and cannot include credentials, query parameters or fragments.

The workflow checks out `${{ github.sha }}` explicitly. If the integration branch advances after dispatch, the workflow fails and a fresh exact-head candidate must be dispatched.

## Live #522 audit gate

Before signing, the workflow captures under the runner temporary directory:

```text
branch.json
rules.json
protection.json
esc-branch-protection-audit.json
```

The raw GitHub configuration snapshots remain runner-local. They are used to execute the same independent schema-v2 verifier documented in `BRANCH_PROTECTION_AUDIT.md`.

The gate requires:

- audit `schema_version = 2`;
- `decision = PASS`;
- every emitted control is `true`;
- the required named controls include administrator enforcement and conversation resolution;
- the branch snapshot is for the exact dispatch head.

The sanitized audit JSON itself is retained with the short-lived candidate, and its SHA-256 is written into candidate provenance. The GitHub administration credential is never retained.

## Build and verification

The candidate job:

1. verifies the exact target branch and live schema-v2 #522 enforcement using the read-only GitHub administration credential;
2. validates the ESC production secret set only after the release-control audit passes;
3. validates the production API input;
4. materializes temporary ESC signing/Firebase files under the runner temporary directory;
5. runs `scripts/verify_esc_android_isolation.py`;
6. runs `scripts/bootstrap_android.sh` and validates all three ESC Firebase Android identities;
7. runs Flutter dependency resolution, formatting, analysis and tests;
8. builds `--flavor production --release` with `ESC_ENV=production` and the supplied production API base URL;
9. verifies the APK package is `com.safecontracts.enterprise` and is not `com.safecontracts.safecontracts_mobile`;
10. verifies the APK signing certificate SHA-256 exactly matches `ESC_ANDROID_CERT_SHA256`;
11. re-hashes the retained branch-protection audit and refuses provenance generation if it changed after the gate;
12. uploads only the short-lived UAT candidate artifact.

## Candidate artifact

The uploaded Actions artifact is named:

```text
esc-uat-candidate-<source-sha>-<workflow-run-id>
```

Retention is **14 days**.

Its contents are:

```text
EnterpriseSafeContracts-UAT-CANDIDATE-<source-sha>.apk
EnterpriseSafeContracts-UAT-CANDIDATE-<source-sha>.apk.sha256
ESC_BRANCH_PROTECTION_AUDIT.json
ESC_UAT_CANDIDATE.json
```

`ESC_UAT_CANDIDATE.json` records:

- candidate provenance schema version;
- `state = UAT_CANDIDATE_NOT_RELEASED`;
- `release_eligible = false`;
- repository;
- exact source SHA;
- workflow run ID and attempt;
- ESC application ID;
- APK filename and SHA-256;
- verified signing certificate SHA-256;
- branch-protection audit filename, schema version, PASS decision and SHA-256;
- sanitized API origin/path;
- UTC creation time;
- the physical-device UAT-only purpose.

The retained audit record is configuration provenance only. It does not turn the candidate into a release and does not prove any physical-device check.

## What the workflow cannot do

The candidate workflow intentionally contains no path to:

- enable, weaken or otherwise mutate branch protection;
- write `Last verified Enterprise apk/EnterpriseSafeContracts-latest.apk`;
- call `enterprise_verified_artifacts.py publish-apk`;
- create/update the `esc-mobile-latest` GitHub Release;
- validate or manufacture a coexistence PASS record;
- build/finalize the coexistence evidence bundle;
- close #421;
- bypass #522 branch protection or schema-v2 audit requirements.

The candidate is production-signed only so that install/update/signing-lineage behavior tested on the physical device matches the intended ESC production lineage. Production signing does not make the artifact release-eligible.

## Physical-device handoff

Download the candidate artifact from the exact workflow run and independently verify its `.sha256` file before use. Also retain `ESC_BRANCH_PROTECTION_AUDIT.json` with the run provenance and verify its digest matches `ESC_UAT_CANDIDATE.json`.

Use the APK path and the same source SHA with:

```text
scripts/run_esc_android_coexistence_uat_windows.ps1
```

That runner still leaves the environment-specific checks pending. Execute and retain the actual physical-device evidence for:

- `session_isolation`;
- `safe_only_push`;
- `esc_only_push`;
- `independent_update`;
- `clear_data_uninstall_isolation`.

The candidate provenance should be retained with the #421 evidence set so the tested APK can be traced back to the exact source, Actions run, digest, live #522 audit and ESC signing lineage.

## Promotion boundary

After all #421 physical-device, Firebase and business evidence is complete, use the separate content-addressed evidence bundle/finalizer/validator path. Only a finalized exact-source PASS record may be supplied to `publish-mobile-latest.yml`.

Stable production publication remains separate and continues to require its existing evidence, branch-protection, signing, package and provenance gates.
