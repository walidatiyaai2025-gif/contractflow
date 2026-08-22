# Alkenzy ADV — Google Play, AdMob and AppLovin release checklist

Issue: #614

## Implemented in repository

- Android release target is pinned to API 36 for the Google Play requirement taking effect on 2026-08-31.
- Flutter embeds both Google Mobile Ads and AppLovin MAX so the active provider can be changed remotely without publishing another app build.
- WordPress `Mobile Configuration` is the runtime control plane for:
  - advertising on/off;
  - active provider: Google AdMob or AppLovin MAX;
  - QA/test mode;
  - banner on/off;
  - AdMob banner Ad Unit ID;
  - AppLovin SDK key and banner Ad Unit ID.
- AdMob UMP consent is requested before Google ad requests and the app exposes Google's privacy-options entry point when required.
- AppLovin is initialized only when AppLovin MAX is the selected provider. The plugin-hosted privacy/terms URLs are passed to the MAX privacy flow.
- Ads fail closed: the default is disabled; missing or malformed production provider settings do not request ads.
- AdMob QA mode uses Google's Android test banner unit.
- AppLovin QA requires a MAX test device configured with the device GAID; AppLovin does not provide one universal public banner test unit equivalent to Google's demo unit.
- The production AdMob **App ID** is `ca-app-pub-3218037275900725~7401372044` and is treated as public Android application configuration, not a credential. Production builds inject it into AndroidManifest automatically.
- Non-production bootstrap continues to use Google's public sample App ID unless explicitly overridden, preventing local/CI development from accidentally depending on the production application identity.
- AppLovin uses the **SDK key** at runtime. Never store an AppLovin Management Key, API Key, or Ad Review Key in WordPress/mobile configuration.
- `scripts/bootstrap_android.sh` injects the correct AdMob App ID into AndroidManifest and rejects Google's sample App ID when a production build is required.
- `.github/workflows/build-google-play-aab.yml` creates a signed AAB candidate when production signing inputs are present; a separate AdMob App ID secret is no longer required.
- Built-in public store/compliance pages are served by the plugin:
  - `/alkenzy-adv/privacy/`
  - `/alkenzy-adv/account-deletion/`
  - `/alkenzy-adv/support/`
  - `/alkenzy-adv/terms/`

## AdMob setup

1. The Android AdMob app has been created and its App ID is already configured in the release build: `ca-app-pub-3218037275900725~7401372044`.
2. In AdMob, create a **Banner** ad unit for Alkenzy ADV and copy its Ad Unit ID (`ca-app-pub-.../...`).
3. In WordPress open **Safe Contracts → Mobile Configuration → Advertising providers**.
4. Select **Google AdMob**.
5. Paste the Banner Ad Unit ID.
6. Keep **Test / QA mode** enabled while testing. Google demo inventory is used in this mode.
7. Configure **Privacy & messaging** in AdMob so UMP can present any required consent message.
8. Complete AdMob identity/payment verification and blocking/content-rating controls before production monetization.
9. After Play/AdMob verification, disable QA mode and enable mobile advertising.
10. After the application is published on Google Play, link the AdMob app to its Play Store listing from AdMob. This does not require changing the embedded App ID.

## AppLovin MAX setup / AdMob replacement

1. Sign in to AppLovin MAX and create the Android app with the same package ID.
2. From **Account → General → Keys**, copy the **SDK Key** only.
3. Create a Banner ad unit in MAX and copy the Banner Ad Unit ID.
4. In WordPress open **Safe Contracts → Mobile Configuration → Advertising providers**.
5. Paste the SDK Key and Banner Ad Unit ID.
6. For QA, add the test device GAID under **MAX → Mediation → Manage → Test Mode** and select the network to test.
7. When ready, select **AppLovin MAX** as the active provider and save.
8. If AdMob is suspended or intentionally disabled later, selecting AppLovin MAX and saving is sufficient. The mobile app stops requesting AdMob and uses AppLovin on the next remote-config refresh/app start; no APK/AAB rebuild is required.

Do **not** paste AppLovin Management Key, API Key, or Ad Review Key into WordPress. Those account-level keys are not needed by the mobile runtime.

## Required GitHub production secrets

Only signing material remains secret:

- `SC_ANDROID_KEYSTORE_BASE64`
- `SC_ANDROID_KEYSTORE_PASSWORD`
- `SC_ANDROID_KEY_ALIAS`
- `SC_ANDROID_KEY_PASSWORD`
- `SC_ANDROID_CERT_SHA256`

`SC_ADMOB_APP_ID` is no longer required. The AdMob App ID is a public identifier shipped inside the Android package and is configured in the repository release bootstrap. `SC_ADMOB_APP_ID` remains an optional build-time override for controlled migration/testing, but normal Alkenzy production builds do not need it.

Do not commit any keystore or signing secret.

## Plugin-hosted URLs to use in Play Console

Use the actual production host, for example `https://sys.alkenzy.com`, plus these paths:

- Privacy policy: `https://sys.alkenzy.com/alkenzy-adv/privacy/`
- Account deletion: `https://sys.alkenzy.com/alkenzy-adv/account-deletion/`
- Support: `https://sys.alkenzy.com/alkenzy-adv/support/`
- Terms: `https://sys.alkenzy.com/alkenzy-adv/terms/`

The WordPress Mobile Configuration page renders the canonical URLs dynamically from `home_url()` so staging and production show the correct host.

## Required Play Console work before production publication

Repository code cannot truthfully complete these account-side declarations. Complete them in Play Console using the final release behavior:

- Create/select the Google Play application for package `com.safecontracts.safecontracts_mobile`.
- Enable/confirm Play App Signing and verify the expected upload/signing certificate arrangement.
- Use the plugin-hosted production Privacy Policy URL.
- Complete **Data safety**, including Firebase plus both embedded advertising SDKs and the fact that only the remotely selected provider is requested to serve ads.
- Declare **Contains ads**.
- Complete **Target audience and content**, content rating, and ad suitability settings.
- Complete **App access** instructions for reviewer login if authentication gates the app.
- Complete store listing: app name, short/full description, icon, feature graphic, phone screenshots, support email/site, and category.
- If the app allows account creation, provide the plugin-hosted external account-deletion URL and a readily discoverable in-app deletion path; retained accounting/audit data must be limited to legitimate retention obligations disclosed in the privacy policy.
- Run the final signed AAB on internal/closed testing and record real-device/UAT evidence before promoting to production.

## Build the AAB

Run GitHub Actions workflow **Build Alkenzy ADV Google Play AAB** with the production API base URL. The workflow rejects incomplete signing, formatting/analyze/test failures, a signing-certificate mismatch, and any production bootstrap that resolves to Google's sample AdMob App ID.

The result is a short-lived `Alkenzy-ADV-google-play.aab` workflow artifact. Passing this build proves the repository/build candidate, not Play Console approval or production UAT.
