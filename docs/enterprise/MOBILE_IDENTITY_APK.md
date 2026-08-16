# Enterprise Safe Contracts — Mobile Identity & APK Coexistence

## Requirement

Safe Contract and Enterprise Safe Contracts (ESC) must be installable simultaneously on one Android device during development, staging, UAT and production validation.

The operating system must treat them as separate applications. ESC must never upgrade, replace, share unintended local state with, or hijack links/notifications from Safe Contract.

## Product identities

Current Safe Contract production application ID:

- `com.safecontracts.safecontracts_mobile`

ESC package identities:

- Development: `com.safecontracts.enterprise.dev`
- Staging: `com.safecontracts.enterprise.staging`
- Production: `com.safecontracts.enterprise`

The Safe Contract identity is not to be changed as part of ESC work.

## ESC isolation baseline

ESC requires independent:

- Android `applicationId`
- Android namespace
- display label
- launcher/adaptive icon
- splash identity
- deep link / app link hosts and intent filters
- Firebase Android registration/configuration
- FCM registration/token lifecycle
- notification channel IDs
- secure storage namespace
- SharedPreferences/local cache/database naming
- analytics/crash reporting application identity
- signing key/release lineage
- versionCode/versionName sequence
- build configuration namespace
- artifact naming and release tags

The Flutter compile-time namespace is ESC-only:

- `ESC_ENV`
- `ESC_API_BASE_URL`

Do not use Safe Contract `SC_ENV`, `SC_API_BASE_URL` or `SC_ANDROID_*` release variables in the ESC product line.

## Build environments

ESC Android product flavors:

- ESC Dev → `com.safecontracts.enterprise.dev`
- ESC Staging → `com.safecontracts.enterprise.staging`
- ESC Production → `com.safecontracts.enterprise`

Each environment must map explicitly to:

- API base URL
- its own Firebase Android app registration/configuration
- app label/icon marker where useful
- logging/debug policy
- release signing policy

Dev/Staging builds should remain visually distinguishable from production to reduce operator mistakes.

No real `google-services.json` is committed. `scripts/bootstrap_android.sh` requires three explicit ESC Firebase configuration paths and fails if a Safe Contract Firebase app ID/package is reused.

## Notification and deep-link namespaces

ESC reserves:

- Flutter/native method channel: `enterprise_safecontracts/notifications`
- Android notification channel ID: `enterprise_safe_contracts_alerts`
- Custom deep-link scheme: `esc-safecontracts`

These identifiers belong only to ESC. New App Link hosts or notification channels must preserve the same product boundary.

## Local state

ESC authentication tokens use an ESC-specific secure-storage key. Any future SharedPreferences, SQLite/Drift/Hive/Isar database, cache directory or analytics/crash-reporting identifier must use an ESC-specific namespace and must be included in the full-impact review before release.

Package separation provides Android sandbox separation, but code-level namespaces are still required to prevent accidental migration/import/export or automation collisions.

## Static and binary isolation gate

Run from repository root:

```bash
python3 scripts/verify_esc_android_isolation.py
```

For an ESC production candidate:

```bash
python3 scripts/verify_esc_android_isolation.py \
  --esc-apk /path/to/EnterpriseSafeContracts-candidate.apk \
  --aapt /path/to/aapt
```

When a verified Safe Contract APK is available, also pass `--safe-apk` and `--apksigner`; the gate verifies the exact package IDs and requires distinct signing certificate lineages.

The ESC Foundation Gate executes the static form automatically on every ESC branch push/PR.

## Coexistence validation

Release readiness must include the physical-device procedure in `docs/enterprise/ANDROID_COEXISTENCE_UAT.md` and prove:

1. Safe Contract installs successfully.
2. ESC installs alongside it without uninstall/update prompts.
3. Both launch independently.
4. Each retains independent login/session/local storage.
5. Each receives only its own intended notifications.
6. Each opens only its intended app/deep links.
7. Updating one app does not alter/remove the other.
8. App data clearing/uninstall of one does not damage the other.
9. The two production APKs have the expected distinct package IDs and signing lineages.

This gate cannot be replaced by emulator-only evidence or a source-code inspection.

## Production publishing

The ESC production publishing workflow is branch-gated to `enterprise-safecontracts`, uses the `esc-production` GitHub environment and requires only `ESC_*` Android/Firebase secrets. It must not reference Safe Contract signing secrets or publish under Safe Contract release names.

Verified ESC release namespace:

- retained APK: `Last verified Enterprise apk/EnterpriseSafeContracts-latest.apk`
- stable GitHub Release tag: `esc-mobile-latest`

Publication requires explicit real-device, UAT, coexistence and Firebase evidence references. `scripts/enterprise_verified_artifacts.py` writes the verified provenance record.

## Release artifacts

ESC artifacts are separate from Safe Contract artifacts. Stable names:

- `EnterpriseSafeContracts-latest.apk`
- `EnterpriseSafeContracts-latest.zip` for the enterprise plugin package when applicable.

Never publish an ESC binary into the existing Safe Contract `Last verified ...` retention slots, Safe Contract GitHub Release tags or Safe Contract mobile signing/Firebase namespace.
