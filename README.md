# SafeContracts

SafeContracts is a contract receivables tracking platform for an advertising company. WordPress + the SafeContracts custom plugin are the backend and single source of truth; the Flutter mobile application is an API client for monitoring and light operational updates.

## Repository structure

- `wordpress-plugin/safecontracts/` — WordPress backend plugin and business source of truth.
- `mobile/` — Flutter mobile client; no competing business database or financial rules.
- `tests/` — automated/contract tests.
- `scripts/` — repeatable local/CI validation helpers.
- `docs/` — approved specifications, roadmap, architecture and standards.
- `assets/` — approved visual identity references.
- `Last verified Plugin/` — single latest verified installable SafeContracts plugin ZIP plus checksum/provenance.
- `Last verified apk/` — single latest verified Android APK plus checksum/provenance.

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

## Production environment

The complete production infrastructure/build checklist is `docs/PRODUCTION_ENVIRONMENT_BUILD.md`. It covers HTTPS/DNS, WordPress/PHP, database, backups/restores, Firebase, monitoring, UAT, Android release signing, deployment sequence and rollback evidence.

The current mobile source tree must not be treated as APK-production-capable until the Android platform scaffold and secret-safe release signing are added and verified.

## Verified release artifacts

Every contributor/release operator must follow `AGENTS.md`.

The two `Last verified ...` folders are permanent latest-artifact slots, not historical archives. Only an exact candidate that passed all required Quality Gates and applicable real-environment checks may replace them. Historical binaries belong in GitHub Releases.

Validate the retention policy with:

```bash
python3 scripts/verified_artifacts.py check
```

After a real release ZIP and APK have both been verified, publish them consistently with:

```bash
python3 scripts/verified_artifacts.py publish \
  --plugin /path/to/SafeContracts.zip \
  --apk /path/to/app-release.apk \
  --source-sha <40-char-commit-sha> \
  --quality-run-id <github-actions-run-id> \
  --quality-gates-passed
```

The helper replaces the previous retained binaries, uses stable `SafeContracts-latest.*` filenames and writes SHA-256/provenance metadata.

## Delivery tracking

GitHub Issues with stable `SC-Px-NNN` IDs are authoritative. `docs/PROJECT_STATUS.md` is regenerated from live issue state/assignment.
