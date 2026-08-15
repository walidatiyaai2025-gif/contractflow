# Last verified SafeContracts APK

This directory is the permanent repository slot for the **single latest verified** Android APK.

Expected retained files after the first verified publication:

- `SafeContracts-latest.apk`
- `SafeContracts-latest.apk.sha256`
- `VERIFIED.json`

Do not keep historical APKs here. Historical release binaries belong in GitHub Releases.

An APK may only be placed here when it is a release build from the exact source commit that passed all SafeContracts Quality Gates, uses the production HTTPS API configuration, is release-signed using secret-safe signing material, and has passed the required real-device/UAT checks.

The repository currently has no committed `mobile/android/` scaffold, so no APK should be labelled production-verified until that blocker is resolved.

Use `scripts/verified_artifacts.py publish` to replace this artifact together with the verified plugin ZIP, then run `python3 scripts/verified_artifacts.py check`.

Never store keystores, signing passwords, Firebase private credentials, debug APKs or locally modified builds in this directory.