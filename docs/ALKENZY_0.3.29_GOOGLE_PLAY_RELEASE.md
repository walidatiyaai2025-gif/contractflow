# Alkenzy ADV 0.3.29 — Google Play release record

## Release identity

- Product: Alkenzy ADV
- Store version name: `0.3.29`
- Store version code: `29`
- Android package: `com.safecontracts.safecontracts_mobile`
- Target SDK: Android 16 / API 36
- Exact functional source: `1a02545546efbfa979659e405070dce05906f443`
- Functional source message: `build: package 0.3.29 premium contract details closure`
- Store release branch: `release/alkenzy-0.3.29-google-play`
- Production API: `https://sys.alkenzy.com/wp-json/safecontracts/v1/`

The store branch must not change `mobile/` or `scripts/bootstrap_android.sh` relative to the exact functional source above. The Google Play workflow enforces this before signing.

## Source APK inspected before store preparation

Artifact: `Alkenzy-ADV-0.3.29-PREMIUM-CONTRACT-FIX.apk`

- SHA-256: `ca57909f1bee6b4f5d9d1d02f7a124829ff002af9cec705a2be63ec638ec5f04`
- Package: `com.safecontracts.safecontracts_mobile`
- Version: `0.3.29` / versionCode `29`
- targetSdk: `36`
- Signing certificate subject: `CN=ALKENZY Runtime Inspector, OU=Diagnostics, O=ALKENZY, L=CI, ST=CI, C=US`
- Signing certificate SHA-256: `352155F1E230ACBAB054EDF8D1951975469A80500E07DF55CDE12E2485D81E03`
- Signing certificate validity ends: `2026-09-27T13:22:41Z`

This diagnostics/CI certificate is **not** approved as the Google Play upload key and must not be used for the Play AAB.

## Google Play build contract

Run `.github/workflows/build-google-play-aab.yml` on this release branch. It must:

1. lock the functional mobile source to the exact 0.3.29 commit;
2. require production upload signing secrets from the protected `production` environment;
3. require the exact `sys.alkenzy.com` production API;
4. verify production API health before signing;
5. require the production AdMob App ID path;
6. run Dart format validation, Flutter analyze and the Flutter test suite;
7. build a release Android App Bundle (`.aab`);
8. verify the AAB upload-signing certificate against the configured SHA-256 certificate fingerprint;
9. emit `Alkenzy-ADV-0.3.29-GOOGLE-PLAY.aab`, its SHA-256 file and a release manifest.

Required protected GitHub environment secrets:

- `SC_ANDROID_KEYSTORE_BASE64`
- `SC_ANDROID_KEYSTORE_PASSWORD`
- `SC_ANDROID_KEY_ALIAS`
- `SC_ANDROID_KEY_PASSWORD`
- `SC_ANDROID_CERT_SHA256`

Never commit the keystore or signing passwords to Git.

## Android developer verification / package registration gate

Before first Play submission, check the package registration state for `com.safecontracts.safecontracts_mobile` in Play Console. This package has already been used by off-Play test builds, so Play Console may classify it as an existing package name and request signing-key ownership verification.

Do not treat the short-lived Runtime Inspector certificate as the long-term Play upload key. If Play Console requires proof tied to an older known key and that private key is unavailable, resolve package-name registration in Play Console before final rollout. If the package is eligible for registration with the intended production signing identity, register it and continue with the production upload key.

## Store policy/public routes already defined by the product

- Privacy: `/alkenzy-adv/privacy/`
- Account deletion: `/alkenzy-adv/account-deletion/`
- Support: `/alkenzy-adv/support/`
- Terms: `/alkenzy-adv/terms/`

Validate the live HTTPS URLs immediately before submission.

## Final acceptance

The release is **STORE-CANDIDATE-READY** only after the workflow succeeds and the exact AAB is downloaded with its checksum. It is **PLAY-SUBMISSION-READY** only after package registration/app integrity is accepted in Play Console and the required App content declarations, Data safety, Ads declaration, reviewer access and store listing are complete.
