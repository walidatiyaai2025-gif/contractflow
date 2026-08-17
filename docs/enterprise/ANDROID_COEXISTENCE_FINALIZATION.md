# Finalizing ESC Android coexistence UAT evidence

## Boundary

`finalize_esc_android_coexistence_evidence.py` does not perform device or Firebase UAT. It only turns an exact-source `PENDING` draft into a candidate final `PASS` record after the real-device operator has separately completed, reviewed, and retained every required runtime scenario.

The input draft must already contain objective PASS evidence for:

- `dual_install`
- `independent_launch`
- `deep_link_isolation`

Those three objective check records are preserved unchanged in the final record.

## Required retained evidence bundle

Before finalization, place the exact objective draft and the completed evidence files under one evidence root. The manifest requires exactly these artifact keys:

- `objective_draft`
- `session_isolation`
- `safe_only_push`
- `esc_only_push`
- `independent_update`
- `clear_data_uninstall_isolation`
- `esc_firebase_identity`
- `device`
- `business_uat`
- `coexistence`
- `firebase_delivery`

The files may be logs, screenshots, videos, exported records, signed text/PDF evidence, or other approved retained artifacts. The tooling does **not** decide whether their contents are semantically sufficient; the tester and release owner remain responsible for that review.

## 1. Build the content-addressed manifest

Every `--artifact` value uses `KEY=RELATIVE_PATH`. Paths are relative to `--evidence-root`. POSIX/Windows absolute paths, backslashes, dot segments, `..` traversal, duplicate paths, missing files, and empty files are rejected.

```bash
python3 scripts/build_esc_android_coexistence_evidence_bundle.py \
  --evidence-root /secure/esc-uat \
  --source-sha <EXACT_40_CHAR_ESC_SHA> \
  --collected-at-utc 2026-08-17T20:00:00Z \
  --artifact objective_draft=objective/esc-android-coexistence-draft.json \
  --artifact session_isolation=runtime/session-isolation.txt \
  --artifact safe_only_push=runtime/safe-only-push.txt \
  --artifact esc_only_push=runtime/esc-only-push.txt \
  --artifact independent_update=runtime/independent-update.txt \
  --artifact clear_data_uninstall_isolation=runtime/clear-data-uninstall.txt \
  --artifact esc_firebase_identity=firebase/esc-android-identity.txt \
  --artifact device=device/device-evidence.txt \
  --artifact business_uat=approvals/business-uat-signoff.txt \
  --artifact coexistence=runtime/coexistence-summary.txt \
  --artifact firebase_delivery=firebase/dual-delivery-evidence.txt \
  --output /secure/esc-uat/esc-android-coexistence-evidence-manifest.json
```

The builder records each safe relative path, byte size and SHA-256 digest, then computes a canonical `bundle_sha256` bound to the exact ESC source SHA.

## 2. Finalize only from the verified manifest

```bash
python3 scripts/finalize_esc_android_coexistence_evidence.py \
  --draft /secure/esc-uat/objective/esc-android-coexistence-draft.json \
  --source-sha <EXACT_40_CHAR_ESC_SHA> \
  --tested-at-utc 2026-08-17T21:00:00Z \
  --evidence-manifest /secure/esc-uat/esc-android-coexistence-evidence-manifest.json \
  --evidence-root /secure/esc-uat \
  --output /secure/esc-uat/esc-android-coexistence-final.json
```

Before writing the final record, the finalizer:

1. requires the exact-source `PENDING` draft and the three objective PASS checks;
2. verifies the supplied draft file size/SHA-256 against the manifest `objective_draft` artifact;
3. validates the exact manifest schema, exact source SHA, collection timestamp and canonical `bundle_sha256`;
4. re-opens all eleven retained artifacts and re-checks their byte sizes and SHA-256 values;
5. preserves the objective check evidence exactly as captured by the objective session harness;
6. promotes only the five remaining manual runtime checks using references derived from the verified bundle/artifact digests;
7. derives ESC Firebase, device, business-UAT, coexistence and Firebase-delivery references from the same verified bundle;
8. invokes the existing `validate_esc_android_coexistence_evidence.py` final validator.

If the objective draft or any retained evidence file is modified, deleted, substituted, or moved outside the declared evidence root, finalization fails closed.

## Evidence reference format

Operators no longer type free-form evidence references into the finalizer. Runtime/Firebase/business references are generated from the verified manifest and bind:

- canonical evidence bundle SHA-256;
- artifact role;
- artifact SHA-256;
- safe relative artifact path.

Example shape:

`esc-evidence-bundle:sha256:<BUNDLE_SHA256>/artifact:safe_only_push/sha256:<ARTIFACT_SHA256>/path:runtime/safe-only-push.txt`

## What this still does not prove

Hashing proves retention and integrity, not semantic truth. The tooling does not inspect a screenshot, video, log, Firebase export, ticket, or sign-off to decide whether the real-world UAT scenario actually passed.

It also performs no ADB install/update/uninstall, data clear, FCM send, authentication, network call, or backend-session mutation.

Do not build/finalize the manifest until every required runtime scenario has actually been completed and reviewed. If any scenario is incomplete, keep the UAT draft `PENDING` and do not publish the production release.
