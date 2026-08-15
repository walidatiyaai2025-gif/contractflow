# SafeContracts

SafeContracts is a contract receivables tracking platform for an advertising company. WordPress + the SafeContracts custom plugin are the backend and single source of truth; the Flutter mobile application is an API client for monitoring and light operational updates.

## Repository structure

- `wordpress-plugin/safecontracts/` — WordPress backend plugin and business source of truth.
- `mobile/` — Flutter mobile client; no competing business database or financial rules.
- `tests/` — automated/contract tests.
- `scripts/` — repeatable local/CI validation helpers.
- `docs/` — approved specifications, roadmap, architecture and standards.
- `assets/` — approved visual identity references.

## Foundation validation

Backend validation requires PHP 8.1+:

```bash
./scripts/test-php.sh
```

Repository/foundation validation requires Python 3.11+:

```bash
python3 scripts/validate-foundation.py
```

Mobile validation is run from `mobile/` with Flutter stable:

```bash
flutter pub get
dart format --output=none --set-exit-if-changed lib test
flutter analyze
flutter test
```

GitHub Actions runs the repository, backend and mobile gates on pull requests and pushes to `main`.

## Environment and secrets

Environment conventions are documented in `docs/ENVIRONMENT.md`. Mobile receives only non-secret compile-time configuration (for example the API base URL). Server credentials, Firebase private credentials and other secrets stay server-side and must never be committed.

## Delivery tracking

GitHub Issues with stable `SC-Px-NNN` IDs are authoritative. `docs/PROJECT_STATUS.md` is regenerated from live issue state/assignment.
