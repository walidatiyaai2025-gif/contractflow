# ESC Android — Production-Signed UAT Candidate

## Purpose

The `ESC Android UAT Candidate` workflow (`.github/workflows/esc-uat-candidate.yml`) builds the exact production-signed ESC APK needed to execute the physical-device coexistence gate in #421.

This closes a release-ordering gap without weakening production publication: the stable `publish-mobile-latest.yml` workflow correctly requires finalized UAT/coexistence/Firebase evidence, so it cannot be the mechanism that creates the binary used to perform that same UAT.

A UAT candidate is **not a release** and is never treated as a verified retained production artifact.

## Security prerequisites

The workflow is deliberately fail-closed.

Before production signing material is touched:

1. the dispatch must target `enterprise-safecontracts`;
2. GitHub must report that integration branch as protected;
3. the current `enterprise-safecontracts` head must still equal the exact dispatch `GITHUB_SHA`;
4. the job must enter the existing `esc-production` GitHub environment;
5. the approved ESC-only production signing and Firebase secrets must exist.

Branch protection is therefore an execution dependency. Until #522 is complete and GitHub reports the branch protected, the candidate workflow must fail before materializing production signing/Firebase data.

Required secret namespace:

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

## Build and verification

The candidate job:

1. verifies branch protection and exact-head identity;
2. validates the ESC production secret set;
3. materializes temporary ESC signing/Firebase files under the runner temporary directory;
4. runs `scripts/verify_esc_android_isolation.py`;
5. runs `scripts/bootstrap_android.sh` and validates all three ESC Firebase Android identities;
6. runs Flutter dependency resolution, formatting, analysis and tests;
7. builds `--flavor production --release` with `ESC_ENV=production` and the supplied production API base URL;
8. verifies the APK package is `com.safecontracts.enterprise` and is not `com.safecontracts.safecontracts_mobile`;
9. verifies the APK signing certificate SHA-256 exactly matches `ESC_ANDROID_CERT_SHA256`;
10. uploads only the short-lived UAT candidate artifact.

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
ESC_UAT_CANDIDATE.json
```

`ESC_UAT_CANDIDATE.json` records:

- `state = UAT_CANDIDATE_NOT_RELEASED`;
- `release_eligible = false`;
- repository;
- exact source SHA;
- workflow run ID and attempt;
- ESC application ID;
- APK filename and SHA-256;
- verified signing certificate SHA-256;
- sanitized API origin/path;
- UTC creation time;
- the physical-device UAT-only purpose.

## What the workflow cannot do

The candidate workflow intentionally contains no path to:

- write `Last verified Enterprise apk/EnterpriseSafeContracts-latest.apk`;
- call `enterprise_verified_artifacts.py publish-apk`;
- create/update the `esc-mobile-latest` GitHub Release;
- validate or manufacture a coexistence PASS record;
- build/finalize the coexistence evidence bundle;
- close #421;
- bypass #522 branch protection.

The candidate is production-signed only so that install/update/signing-lineage behavior tested on the physical device matches the intended ESC production lineage. Production signing does not make the artifact release-eligible.

## Physical-device handoff

Download the candidate artifact from the exact workflow run and independently verify its `.sha256` file before use.

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

The candidate provenance should be retained with the #421 evidence set so the tested APK can be traced back to the exact source, Actions run, digest and ESC signing lineage.

## Promotion boundary

After all #421 physical-device, Firebase and business evidence is complete, use the separate content-addressed evidence bundle/finalizer/validator path. Only a finalized exact-source PASS record may be supplied to `publish-mobile-latest.yml`.

Stable production publication remains separate and continues to require its existing evidence, branch-protection, signing, package and provenance gates.
