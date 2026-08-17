# Android Coexistence UAT — Safe Contract + Enterprise Safe Contracts

## Purpose

This runbook is the release gate proving that Safe Contract and Enterprise Safe Contracts (ESC) are separate Android applications and can coexist on one physical device without package, signing, Firebase, storage, notification or deep-link collisions.

Expected production identities:

- Safe Contract: `com.safecontracts.safecontracts_mobile`
- Enterprise Safe Contracts: `com.safecontracts.enterprise`

A screenshot alone is not sufficient. Retain command output plus the linked UAT/device evidence used by the release workflow.

## Preconditions

- Physical Android test device with USB debugging enabled.
- Current verified Safe Contract production APK.
- ESC production candidate APK built from `enterprise-safecontracts`.
- `adb`, Android Build Tools `aapt` and `apksigner` available.
- ESC Firebase production app registered for `com.safecontracts.enterprise`.
- Test accounts and push/deep-link fixtures for both products.

Do not use a Safe Contract keystore, Firebase Android registration, retained artifact slot or release tag when preparing the ESC candidate.

## 1. Binary identity preflight

From repository root:

```bash
python3 scripts/verify_esc_android_isolation.py \
  --esc-apk /path/to/EnterpriseSafeContracts-candidate.apk \
  --safe-apk /path/to/SafeContracts-production.apk \
  --aapt /path/to/aapt \
  --apksigner /path/to/apksigner
```

The command must report:

- Safe Contract package = `com.safecontracts.safecontracts_mobile`.
- ESC package = `com.safecontracts.enterprise`.
- Package IDs are different.
- Signing certificate lineages are different.

Stop the release if this preflight fails.

## 2. Clean coexistence install

Record the device identifier and Android version:

```bash
adb devices -l
adb shell getprop ro.build.version.release
adb shell getprop ro.build.version.sdk
```

Install both apps without uninstalling either one:

```bash
adb install /path/to/SafeContracts-production.apk
adb install /path/to/EnterpriseSafeContracts-candidate.apk
```

Confirm both packages exist simultaneously:

```bash
adb shell pm list packages | grep -E 'com\.safecontracts\.(safecontracts_mobile|enterprise)$'
```

Expected: two separate package rows. Any uninstall, replacement or signature-conflict prompt is a release blocker.

## 3. Independent launch and task identity

Launch each application independently:

```bash
adb shell monkey -p com.safecontracts.safecontracts_mobile 1
adb shell monkey -p com.safecontracts.enterprise 1
```

Verify visually that each app has its own product label/launcher identity and opens the correct product. Capture evidence for both launches.

## 4. Session and local-state isolation

1. Sign in to Safe Contract with the approved Safe Contract test account.
2. Sign in to ESC with the approved enterprise tenant/account.
3. Force-stop and relaunch both applications.
4. Confirm each app retains only its own session and tenant context.
5. Clear ESC data only:

```bash
adb shell pm clear com.safecontracts.enterprise
```

6. Confirm Safe Contract remains installed, signed in and functionally unchanged.
7. Re-establish the ESC session, then clear Safe Contract data only and confirm ESC remains unchanged.

Any cross-product session, cache, database or credential effect is a release blocker.

## 5. Firebase / notification isolation

Using the approved Firebase/test notification path:

1. Register both apps on the same device.
2. Send a notification targeted only to the Safe Contract registration token; ESC must not receive it.
3. Send a notification targeted only to the ESC registration token; Safe Contract must not receive it.
4. Tap each notification and verify it launches only the intended application.
5. Record the ESC Firebase Android app/package evidence and the notification test evidence reference.

ESC uses the notification channel ID `enterprise_safe_contracts_alerts`; a Safe Contract channel/token must never be reused.

## 6. Deep-link isolation

Verify the ESC-only custom scheme routes to ESC:

```bash
adb shell am start -W \
  -a android.intent.action.VIEW \
  -d 'esc-safecontracts://contracts/1'
```

Confirm Android resolves the request to `com.safecontracts.enterprise` and not Safe Contract. Run the approved Safe Contract deep-link fixture separately and confirm it does not resolve to ESC.

If production App Links are enabled later, repeat this section for every claimed host and retain `adb shell pm get-app-links` output for both packages.

## 7. Independent update lineage

With both apps installed:

1. Install a newer ESC candidate over ESC without uninstalling Safe Contract.
2. Confirm ESC updates successfully and Safe Contract remains installed/data-intact.
3. Install a newer Safe Contract build over Safe Contract without uninstalling ESC.
4. Confirm Safe Contract updates successfully and ESC remains installed/data-intact.

Useful package evidence:

```bash
adb shell dumpsys package com.safecontracts.safecontracts_mobile | grep -E 'versionName|versionCode|firstInstallTime|lastUpdateTime'
adb shell dumpsys package com.safecontracts.enterprise | grep -E 'versionName|versionCode|firstInstallTime|lastUpdateTime'
```

## 8. Independent uninstall behavior

1. Uninstall ESC and confirm Safe Contract still launches and retains its data.
2. Reinstall ESC and complete a minimal smoke test.
3. If the UAT device can be reset/repeated, perform the inverse uninstall check for Safe Contract and confirm ESC remains intact.

```bash
adb uninstall com.safecontracts.enterprise
```

Do not uninstall the other product as a workaround for an install/update collision; a collision fails the gate.

## Evidence record

Record the following in the UAT ticket/run record:

- ESC source commit SHA.
- Safe Contract APK version and SHA-256.
- ESC APK version and SHA-256.
- Physical device model / Android version / device identifier or approved anonymized device reference.
- `aapt` package output for both APKs.
- `apksigner` SHA-256 certificate digest output for both APKs.
- Dual-install package-list output.
- Independent launch evidence.
- Session/local-state isolation result.
- Safe Contract-only push result.
- ESC-only push result.
- Deep-link isolation result.
- Independent update result.
- Independent clear-data/uninstall result.
- ESC Firebase identity evidence reference.
- Business UAT evidence reference.
- Tester, date/time and final PASS/FAIL decision.

### Machine-readable PASS record

A production publication also requires a machine-readable record derived from `docs/enterprise/ANDROID_COEXISTENCE_EVIDENCE_TEMPLATE.json`. Keep the completed record outside Git while testing; the publish workflow embeds the validated record and its canonical SHA-256 inside the retained APK `VERIFIED.json` provenance.

The record must contain the exact 40-character source commit SHA used to build the tested ESC candidate. Every required coexistence check must be `PASS`, the Safe Contract and ESC package/signing identities must remain distinct, and the four top-level evidence references must exactly match the production publish inputs.

Validate a completed record before dispatching publication:

```bash
python3 scripts/validate_esc_android_coexistence_evidence.py \
  --record /path/to/completed-esc-coexistence.json \
  --source-sha <exact-esc-source-sha> \
  --device-evidence '<device-evidence-reference>' \
  --uat-evidence '<business-uat-reference>' \
  --coexistence-evidence '<coexistence-evidence-reference>' \
  --firebase-evidence '<firebase-evidence-reference>'
```

The `Publish Enterprise Safe Contracts Mobile Latest` workflow accepts the completed JSON as the `coexistence_record_base64` input. Encode the local file without changing it.

Linux/macOS:

```bash
base64 -w 0 /path/to/completed-esc-coexistence.json
```

PowerShell:

```powershell
[Convert]::ToBase64String([IO.File]::ReadAllBytes('C:\path\completed-esc-coexistence.json'))
```

If `enterprise-safecontracts` advances after the tested candidate was built, do not rewrite the source SHA in the evidence record. Build and test a new candidate from the new exact source instead.

## Release decision

Only PASS evidence may be supplied to the ESC production publish workflow inputs `device_evidence`, `uat_evidence`, `coexistence_evidence` and `firebase_evidence`, together with the validated `coexistence_record_base64`.

The release workflow fails closed if the record is not valid Base64/JSON, does not say `PASS`, targets a different source SHA, reuses the Safe Contract package/signing identity, has a missing/failed coexistence check, or disagrees with the four publish evidence references. A verified ESC APK belongs only in `Last verified Enterprise apk/` and the `esc-mobile-latest` GitHub Release namespace. Never copy it into Safe Contract retention slots or releases.
