# SafeContracts Android release scaffold contract

SafeContracts keeps its Flutter business/UI sources under `mobile/` and generates the Android platform boilerplate reproducibly with the exact Flutter stable toolchain used by CI.

Run from the repository root:

```bash
./scripts/bootstrap_android.sh
```

The bootstrap process:

1. recreates `mobile/android/` using `flutter create --platforms=android`,
2. fixes the visible application label to `Alkenzy ADV`,
3. installs the supplied Alkenzy Advertising PNG as both launcher and round launcher identity,
4. replaces the generated app Gradle file with `app-build.gradle.kts`,
5. never falls back to debug signing for a release build.

## Release signing environment

Production signing uses all four values together:

- `SC_ANDROID_KEYSTORE_PATH`
- `SC_ANDROID_KEYSTORE_PASSWORD`
- `SC_ANDROID_KEY_ALIAS`
- `SC_ANDROID_KEY_PASSWORD`

If none are present, the release build is an **unsigned release candidate only**. If only some are present, the build fails closed. Keystores and passwords must never be committed.

The stable Android application ID is:

`com.safecontracts.safecontracts_mobile`

A production APK additionally requires a real HTTPS `SC_API_BASE_URL`, successful Quality Gates, signature verification, real-device evidence and business UAT evidence before it may replace `Last verified apk/SafeContracts-latest.apk`.
