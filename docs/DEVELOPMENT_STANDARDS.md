# SafeContracts Development Standards

## Repository layout

- `wordpress-plugin/safecontracts/` — authoritative WordPress backend/plugin code.
- `mobile/` — Flutter/Dart mobile client.
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

## Mobile baseline

- Native mobile client uses Flutter/Dart.
- Mobile is an API client; it never connects directly to WordPress/MySQL storage.
- Business rules, financial truth, capabilities, data scope, reference lists and server-generated reports remain authoritative in WordPress.
- Environment/site URL is deployment configuration; no server secrets are compiled into the app.
- Third-party Flutter dependencies are introduced only by tasks that need and validate them.

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

## Environment and secrets

- Supported environment names are `development`, `staging`, `production` and `testing`.
- Production debug is forced off by the environment helper.
- Secrets belong in protected server/WordPress configuration or GitHub Actions secrets/environments, never source control.
- Firebase private credentials, tokens, passwords, signing material and database credentials must never be returned by mobile APIs.
- See `docs/ENVIRONMENT_AND_SECRETS.md`.

## REST conventions

- Namespace is versioned: `/wp-json/safecontracts/v1/...`.
- Protected routes require server-side capabilities/scopes.
- Responses use a top-level `data` value and optional `meta` object.
- Do not expose secrets, Firebase private credentials, raw database details or internal stack traces.

## Quality gates

Every pull request to `main` must pass the SafeContracts Quality Gates workflow:

- repository structure/secret guardrails;
- mobile architecture-boundary validation;
- PHP syntax and foundation tests on the supported minimum and current CI baselines.

Flutter build/analyze gates are added when the platform-runner validation task is implemented; P0 mobile source is currently protected by source-contract validation.

## GitHub workflow

- One bounded issue ID per production task (`SC-Px-NNN`).
- Branches and PRs reference all task IDs delivered by the change.
- Run relevant tests before merge.
- Close task Issues only after merged implementation/validation evidence exists.
