# Enterprise Safe Contracts Mobile

Flutter client for the `enterprise-safecontracts` product line.

## Product boundary

Enterprise Safe Contracts (ESC) is a separate product from Safe Contract. The Android application identity, Firebase registration, build configuration, local state, notifications, deep links, signing lineage and release artifacts must remain independent so both apps can be installed on the same device.

WordPress + the Enterprise Safe Contracts backend remain the source of truth. This client must not duplicate authoritative contract, payment, permission, tenant or financial logic. Server responses and server-enforced tenant scope drive the mobile experience.

## Configuration

Public compile-time configuration is read through ESC-only Dart defines:

```bash
flutter run --dart-define-from-file=config/local.example.json
```

Supported values:

- `ESC_ENV`: `local`, `staging`, `production`
- `ESC_API_BASE_URL`: absolute Enterprise Safe Contracts REST base URL

Do not use the Safe Contract `SC_ENV` or `SC_API_BASE_URL` names in ESC builds. Keeping a distinct configuration namespace prevents build pipelines or operator scripts from silently feeding Safe Contract settings into an ESC binary.

Do not place passwords, tokens, Firebase private credentials or other secrets in these files. Real `config/*.json` files are ignored by Git; only `*.example.json` belongs in the repository.

## Android release bootstrap

The generated `mobile/android` directory is not the source of truth. Bootstrap it from the committed ESC overlay and three separate Firebase Android registrations:

```bash
ESC_FIREBASE_ANDROID_CONFIG_DEV=/secure/esc-dev-google-services.json \
ESC_FIREBASE_ANDROID_CONFIG_STAGING=/secure/esc-staging-google-services.json \
ESC_FIREBASE_ANDROID_CONFIG_PRODUCTION=/secure/esc-production-google-services.json \
./scripts/bootstrap_android.sh
```

See `android-release/README.md` and `../docs/enterprise/MOBILE_IDENTITY_APK.md` for package IDs, signing policy and coexistence requirements.

## Validation

```bash
flutter pub get
dart format --output=none --set-exit-if-changed lib test
flutter analyze
flutter test
cd ..
python3 scripts/verify_esc_android_isolation.py
```

A production APK is not considered verified until the real-device Safe Contract + ESC coexistence UAT is recorded.
