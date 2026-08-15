# SafeContracts Environment & Secrets Conventions

## Environments

SafeContracts recognizes these deployment names:

- `development`
- `staging`
- `production`
- `testing`

WordPress resolves the server environment from the `SAFECONTRACTS_ENV` constant first, then the process environment variable of the same name, and otherwise defaults to `production`.

Recommended `wp-config.php` setup:

```php
define('SAFECONTRACTS_ENV', 'production');
define('SAFECONTRACTS_DEBUG', false);
```

Do not commit real environment values that reveal credentials or infrastructure secrets.

## Secret storage rules

- Database credentials remain in the normal protected WordPress/server configuration.
- CI/CD secrets belong in GitHub Actions Secrets/Environments, never repository YAML or source files.
- Firebase server credentials configured through SafeContracts WordPress settings must remain server-side and must never be returned by mobile configuration APIs.
- Private keys, service-account JSON, access tokens, passwords and signing secrets must never be committed.
- Mobile builds receive only non-secret values such as the WordPress site URL and environment label.
- Production logging must redact credentials/tokens and must not dump raw secret-bearing configuration.

## Debug behavior

`SAFECONTRACTS_DEBUG` may be enabled only outside production. The runtime helper forces debug off when the resolved environment is `production`, even if an environment variable is accidentally truthy.

Accepted truthy environment values are `1`, `true`, `yes` and `on`.

## Mobile configuration

Non-secret mobile deployment values use Dart defines:

```bash
flutter run \
  --dart-define=SAFECONTRACTS_ENV=staging \
  --dart-define=SAFECONTRACTS_API_BASE_URL=https://staging.example/
```

The application contains no fallback production URL. An unset URL is treated as explicitly unconfigured.

## Repository guardrails

The repository validation script rejects common secret-bearing files and private-key material. These checks are defense-in-depth; they do not replace GitHub secret scanning or human review.
