# Enterprise Safe Contracts — Mobile Identity & APK Coexistence

## Requirement
Safe Contract and Enterprise Safe Contracts (ESC) must be installable simultaneously on one Android device during development, staging, UAT and production validation.

The operating system must treat them as separate applications. ESC must never upgrade, replace, share unintended local state with, or hijack links/notifications from Safe Contract.

## Current Safe Contract identity
The existing Safe Contract Android release configuration uses its own current package/application identity. That identity is not to be changed as part of ESC work.

## ESC identity baseline
Production package baseline: `com.safecontracts.enterprise`.

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
- artifact naming

## Build environments
Plan flavors/configurations for:
- ESC Dev
- ESC Staging
- ESC Production

Each environment must map explicitly to:
- API base URL
- Firebase app/project configuration
- app label/icon marker where useful
- logging/debug policy
- release signing policy

Dev/Staging builds should remain visually distinguishable from production to reduce operator mistakes.

## Coexistence validation
Release readiness must include a real-device scenario that proves:
1. Safe Contract installs successfully.
2. ESC installs alongside it without uninstall/update prompts.
3. Both launch independently.
4. Each retains independent login/session/local storage.
5. Each receives only its own intended notifications.
6. Each opens only its intended app/deep links.
7. Updating one app does not alter/remove the other.
8. App data clearing/uninstall of one does not damage the other.

## Release artifacts
ESC artifacts are separate from Safe Contract artifacts. Planned stable names:
- `EnterpriseSafeContracts-latest.apk`
- `EnterpriseSafeContracts-latest.zip` for the enterprise plugin package when applicable.

Never publish an ESC binary into the existing Safe Contract `Last verified ...` retention slots.
