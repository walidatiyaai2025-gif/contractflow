# SafeContracts

SafeContracts is a contract receivables tracking platform for an advertising company. WordPress + the SafeContracts custom plugin are the backend and single source of truth; the Flutter mobile application is an API client for monitoring and light operational updates.

## Repository structure

- `wordpress-plugin/safecontracts/` — WordPress backend plugin.
- `mobile/` — Flutter/Dart mobile client foundation.
- `tests/` — automated/contract tests.
- `scripts/` — validation helpers.
- `docs/` — approved specifications, roadmap, architecture and standards.
- `assets/` — approved visual identity references.

## Foundation validation

PHP foundation:

```bash
bash scripts/test-php.sh
```

Repository and mobile architecture contracts:

```bash
bash scripts/validate-repository.sh
bash scripts/validate-mobile-foundation.sh
```

The PHP suite syntax-checks plugin/test PHP and validates plugin lifecycle registration, versioned migration behavior, role/capability defaults, Accountant/Manager scope primitives, environment safety and REST namespace registration.

Pull requests to `main` run `.github/workflows/quality-gates.yml` so the same foundation/policy checks are enforced by GitHub rather than depending only on local execution.

## Environment & mobile

- Server environment/secrets rules: `docs/ENVIRONMENT_AND_SECRETS.md`.
- Mobile architecture: `docs/MOBILE_ARCHITECTURE.md`.
- Mobile API base URL is supplied via `SAFECONTRACTS_API_BASE_URL`; no production endpoint or server secret is hard-coded into the app.

## Delivery tracking

GitHub Issues with stable `SC-Px-NNN` IDs are authoritative. `docs/PROJECT_STATUS.md` is regenerated from live issue state/assignment.
