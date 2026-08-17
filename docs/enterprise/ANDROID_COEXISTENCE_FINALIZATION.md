# Finalizing ESC Android coexistence UAT evidence

## Boundary

`build_esc_android_coexistence_evidence_bundle.py` and `finalize_esc_android_coexistence_evidence.py` do not perform device testing, send FCM, install/update/uninstall apps, clear app data, authenticate users, or decide whether a screenshot/video/log is semantically truthful.

They only bind already-collected real-device evidence files to one exact ESC source SHA, re-verify those retained files by size and SHA-256, derive immutable evidence references, and allow a candidate final `PASS` record to be written only after the existing coexistence validator accepts it.

The input draft must already contain objective PASS results for:

- `dual_install`
- `independent_launch`
- `deep_link_isolation`

The remaining runtime scenarios must still be `PENDING` in the draft until the evidence bundle has been created from completed real-device evidence.

## Required retained evidence files

Create one explicit evidence root directory for the exact release candidate/source SHA. Place one non-empty retained file under that root for each fixed key below:

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

The files may be screenshots, videos, exported logs, signed UAT records, console exports, or other retained release evidence. The tools validate file existence and integrity only; the tester/release owner remains responsible for evidence meaning and approval.

Artifact paths supplied to the bundle builder must be POSIX-style relative paths below the evidence root. Absolute paths, Windows drive paths, backslashes, `.`/`..` traversal, missing files, empty files, duplicate paths, or missing/unexpected artifact keys fail closed.

## 1. Build the content-addressed manifest

Example evidence layout:

```text
/path/to/esc-evidence/
  runtime/session-isolation.txt
  runtime/safe-only-push.txt
  runtime/esc-only-push.txt
  runtime/independent-update.txt
  runtime/clear-data-uninstall.txt
  identity/esc-firebase-identity.txt
  release/device.txt
  release/business-uat.txt
  release/coexistence.txt
  release/firebase-delivery.txt
```

Build the manifest:

```bash
python3 scripts/build_esc_android_coexistence_evidence_bundle.py \
  --evidence-root /path/to/esc-evidence \
  --source-sha <EXACT_40_CHAR_ESC_SHA> \
  --collected-at-utc 2026-08-17T19:00:00Z \
  --artifact session_isolation=runtime/session-isolation.txt \
  --artifact safe_only_push=runtime/safe-only-push.txt \
  --artifact esc_only_push=runtime/esc-only-push.txt \
  --artifact independent_update=runtime/independent-update.txt \
  --artifact clear_data_uninstall_isolation=runtime/clear-data-uninstall.txt \
  --artifact esc_firebase_identity=identity/esc-firebase-identity.txt \
  --artifact device=release/device.txt \
  --artifact business_uat=release/business-uat.txt \
  --artifact coexistence=release/coexistence.txt \
  --artifact firebase_delivery=release/firebase-delivery.txt \
  --output /path/to/esc-evidence-manifest.json
```

The manifest records, for every required artifact:

- a safe relative path;
- byte size;
- SHA-256 digest.

It also records the exact ESC source SHA, UTC collection timestamp, and a canonical `bundle_sha256` over the manifest content excluding the bundle-hash field itself.

Retain the manifest together with the evidence root. Moving the evidence root as a whole is safe because manifest paths are relative; modifying, deleting, replacing, or emptying a retained artifact after manifest creation causes finalization to fail.

## 2. Finalize from the verified bundle

Run the finalizer only after the real-device scenarios are actually complete:

```bash
python3 scripts/finalize_esc_android_coexistence_evidence.py \
  --draft /path/to/esc-android-coexistence-draft.json \
  --source-sha <EXACT_40_CHAR_ESC_SHA> \
  --tested-at-utc 2026-08-17T19:30:00Z \
  --evidence-manifest /path/to/esc-evidence-manifest.json \
  --evidence-root /path/to/esc-evidence \
  --output /path/to/esc-android-coexistence-final.json
```

There are no free-form final evidence-reference CLI arguments. During finalization the tool:

1. validates the exact-source `PENDING` draft boundary;
2. validates the manifest schema and exact source SHA;
3. resolves every retained artifact under the explicit evidence root;
4. re-checks every file's current byte size and SHA-256;
5. recomputes and verifies the canonical bundle SHA-256;
6. derives all eight check evidence references, all top-level evidence references, and the ESC Firebase identity reference from the verified bundle/artifact digests;
7. records bundle provenance in the final record; and
8. calls `validate_esc_android_coexistence_evidence.py` as the final gate before writing `PASS`.

Any source-SHA mismatch, manifest tampering, malformed hash, path escape, missing/empty file, file size change, file digest change, missing/unexpected artifact, or bundle-hash mismatch fails closed before a PASS file is written.

## Release evidence retention

Retain together:

- the exact-source PENDING draft;
- the complete evidence root containing all ten files;
- the generated evidence manifest;
- the final PASS JSON;
- the exact ESC source SHA and release candidate artifacts used during UAT.

Do not rebuild a manifest after evidence has been modified merely to make finalization pass. A new manifest represents a new evidence bundle and must be reviewed/approved as such.

## What these tools do not prove

Content addressing proves that the final PASS references the retained bytes that were bundled and that those bytes did not change between bundle creation and finalization. It does not prove that a screenshot, video, device log, Firebase record, ticket, or business sign-off is truthful or sufficient.

If any runtime scenario is incomplete, disputed, or not retained as evidence, keep the coexistence draft `PENDING`. Real-device coexistence acceptance under #421 remains a separate release responsibility.
