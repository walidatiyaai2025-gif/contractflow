# SafeContracts Mobile

SafeContracts Mobile is a Flutter client. WordPress + the SafeContracts plugin remain the single source of truth for business data, authorization, financial calculations, statuses, reference lists, notification rules and server-generated exports.

## Foundation rules

- The client never connects directly to the WordPress/MySQL database.
- No business secrets are compiled into the app.
- The WordPress site/API base URL is supplied with `--dart-define=SAFECONTRACTS_API_BASE_URL=...`.
- Environment name is supplied with `--dart-define=SAFECONTRACTS_ENV=development|staging|production`.
- Missing API configuration produces an explicit unconfigured foundation state rather than falling back to an unknown server.
- Server-driven branding/content/reference configuration will be layered on through authenticated SafeContracts APIs in its planned tasks.

## Run

```bash
flutter pub get
flutter run \
  --dart-define=SAFECONTRACTS_ENV=development \
  --dart-define=SAFECONTRACTS_API_BASE_URL=https://your-wordpress-site.example/
```

Platform runner folders can be generated from the approved Flutter SDK when the build/release tasks begin; source architecture, configuration contract and tests are kept in this repository.
