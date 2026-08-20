# Alkenzy ADV — sys.alkenzy.com client test

This branch is an isolated client real-data test line for **Alkenzy ADV / SafeContracts**.

## Runtime target

- WordPress site: `https://sys.alkenzy.com/`
- Mobile API: `https://sys.alkenzy.com/wp-json/safecontracts/v1/`
- Visible Android app name: `Alkenzy ADV`
- Android applicationId/package: `com.safecontracts.safecontracts_mobile`
- Firebase project/client: unchanged from the existing Alkenzy ADV production Firebase configuration
- Mobile client-test version: `0.1.3+4`

## Packaging contract

The Plugin ZIP, Theme ZIP and APK are produced from the same tested branch source. The WordPress plugin/theme do not hard-code the previous host; they run on the WordPress installation where they are deployed. The Android API base URL is injected at build time and is fixed to the sys.alkenzy.com SafeContracts v1 endpoint for this branch's CI candidate.

The generated APK is for direct client testing on real data. It uses a short-lived CI client-test signing key and is **not** a Google Play production artifact. Store privacy/Data Safety/AAB/permanent-signing work remains a separate release step.

ESC / `enterprise-safecontracts` is out of scope and must not be modified from this branch.
