# SafeContracts Environment & Secrets Conventions

SafeContracts uses three environment names: `local`, `staging` and `production`. WordPress remains the system of record in every environment; mobile only receives public connection metadata needed to call the versioned API.

## Rules

1. **Never commit secrets.** Passwords, API tokens, signing material, Firebase private credentials, database credentials and production service-account files belong in the hosting platform's secret store or server configuration outside Git.
2. **Do not ship server secrets to mobile.** Anything compiled into an APK/IPA must be treated as public. Mobile receives only values such as environment name and SafeContracts API base URL.
3. **Use HTTPS in production.** The mobile foundation rejects a production API URL that is not HTTPS.
4. **Keep local runtime files untracked.** Real `mobile/config/*.json`, `.env` and `.env.*` files are ignored. Commit examples only.
5. **Do not log secret material.** CI, WordPress logs and application telemetry must avoid credentials and private configuration.

## Mobile compile-time values

The P0 mobile foundation reads:

- `SC_ENV` — `local`, `staging` or `production`.
- `SC_API_BASE_URL` — absolute URL to the SafeContracts REST namespace, normally ending in `/wp-json/safecontracts/v1/`.

For local development:

```bash
cd mobile
flutter run --dart-define-from-file=config/local.example.json
```

For staging/production, create a local untracked JSON file or inject individual `--dart-define` values from CI/CD. Do not commit the resulting runtime file.

## WordPress/server configuration

Server-only credentials are injected by the deployment environment or `wp-config.php` and consumed by plugin code when the related feature is implemented. Firebase private credentials in particular stay in WordPress/server storage; mobile receives only the public Firebase client configuration required by the platform SDK when that phase is implemented.

## Rotation and incident handling

If a secret is ever committed, treat it as compromised: rotate/revoke it first, then remove it from the repository history through the normal security process. Deleting only the latest file is not sufficient.
