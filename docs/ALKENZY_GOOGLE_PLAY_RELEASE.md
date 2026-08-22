# Alkenzy ADV — Google Play release and AdMob checklist

Issue: #614

## Implemented in repository

- Android release target is pinned to API 36 for the Google Play requirement taking effect on 2026-08-31.
- Flutter Google Mobile Ads integration is present using a Dart 3.6-compatible plugin line.
- UMP consent is requested before ad requests and the app exposes the privacy-options entry point when Google requires it.
- Ads fail closed: the default is disabled; malformed/missing production ad unit IDs do not request ads.
- QA/test mode uses Google's Android test banner unit and must remain enabled until production AdMob setup is complete.
- WordPress `Mobile Configuration` is the runtime control plane for:
  - advertising on/off;
  - test mode on/off;
  - banner on/off;
  - Android banner Ad Unit ID.
- The AdMob **App ID** is intentionally build-time configuration and is not stored in WordPress or committed to Git.
- `scripts/bootstrap_android.sh` injects `SC_ADMOB_APP_ID` into AndroidManifest and can reject Google's sample App ID for production builds.
- `.github/workflows/build-google-play-aab.yml` creates a signed AAB candidate only when production signing and AdMob inputs are present.
- The existing signed APK release workflow now also requires a production AdMob App ID so a production-signed build cannot silently ship with Google's sample App ID.

## Required GitHub production secret

Add this to the repository's `production` environment before building the store AAB:

- `SC_ADMOB_APP_ID` — Android AdMob application ID in `ca-app-pub-XXXXXXXXXXXXXXXX~YYYYYYYYYY` format.

The existing Android signing secrets are also required:

- `SC_ANDROID_KEYSTORE_BASE64`
- `SC_ANDROID_KEYSTORE_PASSWORD`
- `SC_ANDROID_KEY_ALIAS`
- `SC_ANDROID_KEY_PASSWORD`
- `SC_ANDROID_CERT_SHA256`

Do not commit any keystore or signing secret.

## Required AdMob setup

1. Create/confirm the Android app in AdMob for package `com.safecontracts.safecontracts_mobile`.
2. Obtain the production AdMob App ID and place it in `SC_ADMOB_APP_ID`.
3. Create a Banner ad unit.
4. In SafeContracts admin, open **Mobile Configuration → Advertising (Google AdMob)**.
5. During QA keep **Test mode** enabled.
6. Enter the production Banner Ad Unit ID.
7. After AdMob/Play/privacy verification, disable Test mode and enable mobile advertising.
8. Configure Privacy & messaging in AdMob so UMP has the required user message to display.
9. Review AdMob blocking/content-rating controls to keep served ads consistent with the app's Play content rating.

## Required Play Console work before production publication

Repository code cannot truthfully complete these account-side declarations. They must be completed in the Play Console using the final release behavior:

- Create/select the Google Play application for package `com.safecontracts.safecontracts_mobile`.
- Enable/confirm Play App Signing and verify the expected upload/signing certificate arrangement.
- Add the production privacy-policy URL. The policy must identify Alkenzy ADV/developer, cover app + Firebase + AdMob data handling, retention/deletion, and privacy contact details.
- Complete **Data safety**, including data handled by Firebase and Google Mobile Ads SDK.
- Declare **Contains ads**.
- Complete **Target audience and content**, content rating, and ad suitability settings.
- Complete **App access** instructions for reviewer login if authentication gates the app.
- Complete store listing: app name, short/full description, icon, feature graphic, phone screenshots, support email/site, and category.
- Verify whether the app offers account creation. If it does, provide the Play-required accessible account-deletion path and associated data deletion handling before submission.
- Run the final signed AAB on internal/closed testing and record real-device/UAT evidence before promoting to production.

## Build the AAB

Run GitHub Actions workflow **Build Alkenzy ADV Google Play AAB** with the production API base URL. The workflow rejects incomplete signing, a missing/malformed AdMob App ID, Google's sample AdMob App ID, formatting/analyze/test failures, and a signing-certificate mismatch.

The result is a short-lived `Alkenzy-ADV-google-play.aab` workflow artifact. Passing this build proves the repository/build candidate, not Play Console approval or production UAT.
