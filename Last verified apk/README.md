# Last verified SafeContracts APK

This directory is the permanent repository slot for the **single latest verified** Android APK.

Expected retained files after the first verified publication:

- `SafeContracts-latest.apk`
- `SafeContracts-latest.apk.sha256`
- `VERIFIED.json`

Do not keep historical APKs here. Historical release binaries belong in GitHub Releases.

A production APK may only be placed here when it is a release build from the exact functional source candidate that passed all SafeContracts Quality Gates, uses the real production HTTPS API URL, is release-signed with production signing material kept outside Git, and has passed real-device/UAT checks.

The Android platform is generated reproducibly by:

```bash
bash scripts/bootstrap_android.sh
```

CI also builds `SafeContracts-apk-release-candidate.apk` using a short-lived CI-only signing key and a reserved `.invalid` HTTPS API URL. That file proves the Android release build/signing path but **must never** be copied here or treated as production.

Publish a real production APK only with:

```bash
python3 scripts/verified_artifacts.py publish-apk \
  --apk /path/to/app-release.apk \
  --source-sha <40-char-source-commit-sha> \
  --quality-run-id <github-actions-run-id> \
  --quality-gates-passed \
  --signing-verified \
  --api-base-url https://<production-host>/wp-json/safecontracts/v1/ \
  --device-evidence <release-record-reference> \
  --uat-evidence <uat-signoff-reference>
```

Then run:

```bash
python3 scripts/verified_artifacts.py check --require-apk
```

Never store keystores, signing passwords, Firebase private credentials, debug/candidate APKs or locally modified builds in this directory.
