# Enterprise Safe Contracts Android release scaffold

This directory belongs to the `enterprise-safecontracts` product line only. It must not reuse Safe Contract Android identity, Firebase registration, build configuration namespace, signing namespace, release artifacts, notification channel, deep-link scheme or retained APK slot.

## Stable package identities

- Development: `com.safecontracts.enterprise.dev`
- Staging: `com.safecontracts.enterprise.staging`
- Production: `com.safecontracts.enterprise`

The product flavors are `dev`, `staging` and `production`. Safe Contract remains a separate Android application using `com.safecontracts.safecontracts_mobile`; both products must remain installable on the same device.

## Firebase injection

No real ESC `google-services.json` is committed. Before running `./scripts/bootstrap_android.sh`, provide three local files through:

- `ESC_FIREBASE_ANDROID_CONFIG_DEV`
- `ESC_FIREBASE_ANDROID_CONFIG_STAGING`
- `ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION`

Each file must contain a Firebase Android app registered for its exact package above. The bootstrap fails if the Safe Contract app ID is reused or if two ESC flavors reuse one Firebase Android app registration.

## Release signing

Production signing uses the ESC-only variables:

- `ESC_ANDROID_KEYSTORE_PATH`
- `ESC_ANDROID_KEYSTORE_PASSWORD`
- `ESC_ANDROID_KEY_ALIAS`
- `ESC_ANDROID_KEY_PASSWORD`

Partial signing configuration fails closed. Keystores and passwords are never committed. ESC production must keep its own signing certificate and release lineage; it must never use the Safe Contract signing secret namespace.

## Build configuration namespace

ESC Flutter builds use only:

- `ESC_ENV`
- `ESC_API_BASE_URL`

The Safe Contract variables `SC_ENV` and `SC_API_BASE_URL` are intentionally invalid for the ESC build contract.

## Build examples

After bootstrap:

```bash
cd mobile
flutter build apk --flavor dev --debug \
  --dart-define=ESC_ENV=local \
  --dart-define=ESC_API_BASE_URL="http://127.0.0.1:8080/wp-json/safecontracts/v1/"

flutter build apk --flavor staging --release \
  --dart-define=ESC_ENV=staging \
  --dart-define=ESC_API_BASE_URL="https://staging.example.invalid/wp-json/safecontracts/v1/"

flutter build apk --flavor production --release \
  --dart-define=ESC_ENV=production \
  --dart-define=ESC_API_BASE_URL="https://esc-api.example.invalid/wp-json/safecontracts/v1/"
```

Replace example endpoints with the approved environment endpoints. Production requires HTTPS. Dev/staging must not silently connect to production, and production must not silently connect to a non-production backend.

## Static isolation gate

Run from repository root:

```bash
python3 scripts/verify_esc_android_isolation.py
```

After building an ESC production APK, verify the package encoded in the binary:

```bash
python3 scripts/verify_esc_android_isolation.py \
  --esc-apk mobile/build/app/outputs/flutter-apk/app-production-release.apk
```

If a verified Safe Contract APK is available, pass it with `--safe-apk` to prove the two binaries advertise distinct expected package IDs before device UAT.

## Coexistence release gate

An ESC production APK is not verified until evidence proves on a real Android device that Safe Contract and Enterprise Safe Contracts can both be installed, launched, updated and receive notifications independently. Follow `docs/enterprise/ANDROID_COEXISTENCE_UAT.md` and retain the evidence references required by `scripts/enterprise_verified_artifacts.py`.

ESC output must be retained only in the Enterprise artifact slots and, when published as a GitHub Release, under the ESC-only release/tag namespace.
