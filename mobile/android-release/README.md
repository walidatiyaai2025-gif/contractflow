# Enterprise Safe Contracts Android release scaffold

This directory belongs to the `enterprise-safecontracts` product line only. It must not reuse Safe Contract Android identity, Firebase registration, signing namespace, release artifacts, notification channel, deep-link scheme or retained APK slot.

## Stable package identities

- Development: `com.safecontracts.enterprise.dev`
- Staging: `com.safecontracts.enterprise.staging`
- Production: `com.safecontracts.enterprise`

The product flavors are `dev`, `staging` and `production`. Safe Contract remains a separate Android application and can be installed on the same device.

## Firebase injection

No real ESC `google-services.json` is committed. Before running `./scripts/bootstrap_android.sh`, provide three local files through:

- `ESC_FIREBASE_ANDROID_CONFIG_DEV`
- `ESC_FIREBASE_ANDROID_CONFIG_STAGING`
- `ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION`

Each file must contain a Firebase Android app registered for its exact package above. The bootstrap fails if the old Safe Contract app id is reused or if two ESC flavors reuse one Firebase Android app registration.

## Release signing

Production signing uses the ESC-only variables:

- `ESC_ANDROID_KEYSTORE_PATH`
- `ESC_ANDROID_KEYSTORE_PASSWORD`
- `ESC_ANDROID_KEY_ALIAS`
- `ESC_ANDROID_KEY_PASSWORD`

Partial signing configuration fails closed. Keystores and passwords are never committed.

## Build examples

After bootstrap:

```bash
cd mobile
flutter build apk --flavor dev --debug --dart-define=SC_ENV=development
flutter build apk --flavor staging --release --dart-define=SC_ENV=staging
flutter build apk --flavor production --release --dart-define=SC_ENV=production
```

The API URL for every flavor must be supplied by environment/build configuration and validated so dev/staging cannot silently connect to production and production cannot silently connect to a non-production backend.

## Coexistence release gate

An ESC production APK is not verified until evidence proves on a real Android device that Safe Contract and Enterprise Safe Contracts can both be installed, launched, updated and receive notifications independently. ESC output must be retained only in the Enterprise artifact slots.
