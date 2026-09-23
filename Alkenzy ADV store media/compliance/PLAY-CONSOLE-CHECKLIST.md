# Alkenzy ADV — Google Play first-release checklist

## Identity
- App name: `Alkenzy ADV`
- Package currently built: `com.safecontracts.safecontracts_mobile`
- Version: `0.3.29`
- Version code: `29`
- Target SDK: `36`
- Production API: `https://sys.alkenzy.com/wp-json/safecontracts/v1/`
- Use Play App Signing with a Google-managed app-signing key for the first Play release.
- Upload AABs with the Alkenzy ADV upload key whose public certificate is in `../signing-public/`.

## Android developer verification
This package name has previously been installed outside Google Play using a different short-lived diagnostics/CI signing key.
When creating the app in Play Console, Google may classify the package as an existing package name and require proof of ownership of a known old signing key.
Do not claim that the new upload key proves ownership of that old off-Play signing identity.
If Play requires the unavailable old private key, resolve package registration before publishing; changing to a new canonical package name remains an option before the first public Play release.

## Main store listing
- App name complete
- Arabic and English short descriptions complete
- Arabic and English full descriptions complete
- 512x512 app icon prepared
- 1024x500 feature graphic prepared
- 6 phone screenshots prepared
- Verify every screenshot still represents the exact release before upload

## App content
- Privacy policy URL
- Ads declaration
- App access / reviewer credentials
- Target audience and content
- Content rating questionnaire
- Data safety
- Account deletion URL if users can create accounts in the app
- Any applicable sensitive-permission declarations

## Release
- Internal testing first
- Production-signed AAB
- AAB signer fingerprint verified
- Exact version/versionCode verified
- Pre-launch report reviewed
- Crash/ANR issues reviewed
- No debug/demo diagnostics exposed to ordinary production users
