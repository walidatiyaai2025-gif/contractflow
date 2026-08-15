# SafeContracts Development Standards

## Repository layout

- `wordpress-plugin/safecontracts/` — authoritative WordPress backend/plugin code.
- `mobile/` — Flutter mobile API client.
- `tests/` — automated regression tests and lightweight contract tests.
- `docs/` — product, architecture, API and operating documentation.
- `scripts/` — repeatable local/CI validation helpers.
- `assets/` — approved product/brand source assets.

## PHP baseline

- PHP 8.1+ and WordPress 6.5+.
- New PHP classes use `declare(strict_types=1)` and the `SafeContracts\\` namespace.
- Public WordPress entry files guard against direct execution.
- WordPress input is sanitized before use and output is escaped at the rendering boundary.
- SQL values must be parameterized; table/column identifiers must come from trusted code.

## Authorization baseline

- Menu visibility is never authorization.
- Every read/write path must enforce capability and data scope server-side.
- Accountants default to `assigned` scope; Managers default to `all` scope.
- `safecontracts_edit_contracts` is an independent capability and must remain grantable/removable without code changes.

## Data and migrations

- Business/high-volume workflow data uses dedicated plugin tables.
- All schema changes are versioned through `SafeContracts\\Database\\Migrator`.
- Migration versions are monotonic and must be safe to run once.
- Never delete financial/audit data during plugin deactivation.

## REST conventions

- Namespace is versioned: `/wp-json/safecontracts/v1/...`.
- Protected routes require server-side capabilities/scopes.
- Responses use a top-level `data` value and optional `meta` object.
- Do not expose secrets, Firebase private credentials, raw database details or internal stack traces.

## Mobile baseline

- Flutter/Dart is the mobile implementation baseline.
- Mobile is an API client; authoritative business rules, financial calculations and authorization remain in WordPress.
- Mobile configuration uses compile-time `--dart-define`/`--dart-define-from-file` values for public environment metadata only.
- API tokens, passwords, Firebase private credentials and other secrets must not be compiled into or committed with the mobile app.
- Production API URLs must use HTTPS.

## Environment and secrets

- Environment names are `local`, `staging` and `production`.
- Real `.env` files and `mobile/config/*.json` runtime files are ignored; only documented examples are committed.
- Server secrets belong in host secret storage or `wp-config.php`/environment injection outside the repository.
- Logs, CI output and client-visible configuration must not print secret material.

## Quality gates

- `python3 scripts/validate-foundation.py` validates repository structure, standards and secret-safe examples.
- `./scripts/test-php.sh` syntax-checks and validates backend foundation/lifecycle behavior.
- Flutter CI runs formatting, analysis and unit/widget tests.
- Pull requests and `main` pushes must pass the applicable quality gates.

## GitHub workflow

- One bounded issue ID per production task (`SC-Px-NNN`).
- Branches and PRs/commits reference all task IDs delivered by the change.
- Run relevant tests before merge/closure.
- Close task Issues only after merged implementation/validation evidence exists.
