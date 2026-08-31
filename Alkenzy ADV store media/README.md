# Alkenzy ADV store media

Canonical preparation folder for the first Google Play release of Alkenzy ADV.

## Release identity
- App: `Alkenzy ADV`
- Version name: `0.3.29`
- Version code: `29`
- Package: `com.safecontracts.safecontracts_mobile`
- Target SDK: `36`
- Exact mobile source: `1a02545546efbfa979659e405070dce05906f443`
- Store branch: `release/alkenzy-0.3.29-google-play`

## Store assets prepared
The owner delivery bundle contains:
- `app-icon-512.png`
- `feature-graphic-1024x500.jpg`
- six phone screenshots at `1080x1920`
- Arabic and English store listing copy
- Play Console checklist and Data Safety worksheet
- public Google Play upload certificate and fingerprint

## Secret separation
The Google Play upload **private key is intentionally NOT stored in Git**.
The owner receives it as a separate private recovery package. Git contains only the public certificate and SHA-256 fingerprint.

Never commit `.jks`, signing passwords, `SC_ANDROID_KEYSTORE_BASE64`, reviewer passwords, service-account files, or other credentials.

## First-release package registration gate
The current package has previously been installed outside Google Play using a short-lived diagnostics/CI signing identity. Android developer verification may therefore request proof of ownership of a known older signing key when the Play app is created. Resolve that gate before the first public release if Play Console presents it.
