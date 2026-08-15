# SafeContracts production environment build

This is the operational checklist for building and promoting SafeContracts into a real production environment. Passing repository CI is necessary but is not, by itself, proof that the live environment is ready.

## 1. Target production architecture

Minimum production topology:

- Public DNS name dedicated to SafeContracts.
- HTTPS termination with a valid trusted certificate; HTTP must redirect to HTTPS.
- WordPress production instance running the SafeContracts plugin.
- Dedicated production database and credentials that are not reused from local/staging.
- Persistent WordPress uploads/media storage with backup coverage.
- Outbound connectivity required by configured notification/Firebase services.
- Monitoring/logging and alerting for web availability, PHP errors, database health, scheduled jobs and notification failures.
- A separate staging environment is strongly preferred for restore drills, migrations and release rehearsal.

The WordPress plugin is the system of record. Mobile communicates only through the versioned SafeContracts REST API.

Current production WordPress host supplied by the operator:

- WordPress: `https://cms.50sols.com/`
- SafeContracts API: `https://cms.50sols.com/wp-json/safecontracts/v1/`
- Public non-destructive health endpoint: `https://cms.50sols.com/wp-json/safecontracts/v1/health`

## 2. WordPress / web-server prerequisites

Before go-live provide:

- A supported WordPress installation with PHP meeting the repository baseline (PHP 8.1+) and a currently supported production PHP runtime.
- Required PHP extensions for WordPress/application operation, including database, JSON, HTTP/TLS, multibyte string and ZIP support as applicable to the hosting image.
- HTTPS enabled before any production mobile build is accepted.
- Production `wp-config.php`/hosting secret injection outside Git.
- Correct filesystem ownership/permissions; application code must not require world-writable permissions.
- A real cron strategy for time-sensitive scheduled work. If WP-Cron is disabled, replace it with a monitored server scheduler.
- Web/PHP upload limits sized for the proof/media files accepted by the application.
- Web server/WAF rules that permit the SafeContracts REST namespace while protecting the normal WordPress attack surface.
- Admin access restricted to authorized operators; production debugging/display of PHP errors disabled.

## 3. Database prerequisites

Provide a production MySQL/MariaDB database compatible with the selected WordPress runtime and configure:

- dedicated database/user,
- least-privilege credentials appropriate for WordPress schema operation and SafeContracts migrations,
- `utf8mb4` character support,
- encrypted transport when database traffic crosses hosts/networks,
- storage/capacity monitoring,
- automated backups with defined retention,
- a tested restore path into an isolated environment.

Before deployment run the committed migration/upgrade tests. After deployment verify the database schema reaches `Migrator::LATEST_VERSION` and a second migration run is idempotent.

## 4. Secrets and configuration

Never commit production secrets.

Server-side secret/configuration sources must cover, as applicable:

- production database credentials,
- WordPress salts/keys,
- Firebase/service-account private credentials,
- SMTP/notification credentials,
- hosting/API credentials,
- Android signing keystore/passwords used by CI/release tooling.

The mobile application may receive only public compile-time configuration. The production SafeContracts mobile values are now fixed to:

- `SC_ENV=production`
- `SC_API_BASE_URL=https://cms.50sols.com/wp-json/safecontracts/v1/`

A production API URL using plain HTTP is forbidden. The CI release candidate runs the public `/health` smoke check against this endpoint before it builds the APK candidate.

## 5. Firebase / notifications

Before declaring notification delivery production-ready:

- install/configure the required Firebase project and platform application identities,
- keep server private credentials outside Git,
- verify registration/device-token lifecycle with real production-like devices,
- verify notification delivery and deep-link routing end to end,
- record failures/retries without logging raw secret material,
- attach delivery evidence to the release/change record.

CI can verify the implementation contract but cannot replace live Firebase/device evidence.

## 6. Android production build prerequisites

The Android platform boilerplate is generated reproducibly with the exact Flutter stable toolchain used by CI:

```bash
bash scripts/bootstrap_android.sh
```

The committed release contract lives in `mobile/android-release/`. It fixes the application ID to `com.safecontracts.safecontracts_mobile`, prevents release builds from falling back to debug signing, and requires all signing inputs together or none.

The `release-candidates` CI job generates the scaffold and builds a real **release-mode APK candidate** using a short-lived CI-only signing key. The candidate is now compiled against the real production API base URL `https://cms.50sols.com/wp-json/safecontracts/v1/`, but it remains a candidate because the signing key is not the production key. Before the APK build starts, CI calls the public SafeContracts `/health` endpoint and verifies `service=SafeContracts`, `api_version=v1`, `status=ok`, a non-empty plugin version and same-origin HTTPS behavior.

That artifact proves the Android/Gradle build path and production endpoint binding, but it is not the production Android release and must never be retained as `SafeContracts-latest.apk`.

Before producing the real `SafeContracts-latest.apk`:

1. Keep the production HTTPS API bound to `https://cms.50sols.com/wp-json/safecontracts/v1/` and require the CI health smoke check to pass.
2. Provide production Android release signing material outside Git through:
   - `SC_ANDROID_KEYSTORE_PATH`
   - `SC_ANDROID_KEYSTORE_PASSWORD`
   - `SC_ANDROID_KEY_ALIAS`
   - `SC_ANDROID_KEY_PASSWORD`
3. Configure any required public Firebase Android client configuration without embedding server private keys.
4. Run repository, backend, Flutter and release-readiness Quality Gates on the exact functional source candidate.
5. Bootstrap the Android platform with the same Flutter toolchain used for the build.
6. Build a **release** APK, never a debug APK.
7. Verify the APK signature using Android build-tools (`apksigner verify`).
8. Install the exact signed APK on at least one representative real Android device.
9. Execute the mobile UAT flows against the production API.
10. Verify authentication, permissions, dashboard/filtering, contracts/payments, collection/follow-up, notifications/deep links, RTL and offline/error handling.
11. Record real-device and UAT evidence references.
12. Only then publish the APK with `scripts/verified_artifacts.py publish-apk`.

For Play Store distribution prefer an AAB in addition to the retained APK, but the repository's mandatory latest local artifact remains the APK requested by the project owner.

## 7. Plugin production package

The production plugin package must be created from `wordpress-plugin/safecontracts/` only.

Build and validate it with:

```bash
python3 scripts/package_plugin.py build --output dist/SafeContracts-plugin-candidate.zip
python3 scripts/package_plugin.py check dist/SafeContracts-plugin-candidate.zip
```

The deterministic ZIP must:

- have `safecontracts/` as the installable plugin root,
- exclude Git metadata, repository docs, tests, local caches, logs and all secrets,
- contain the same functional source candidate that passed Quality Gates,
- contain the WordPress entry point, readme, `Plugin.php` and autoloader,
- be install/upgrade tested on staging,
- pass backend regressions and migration verification before retention.

After verification publish/replace only the plugin with:

```bash
python3 scripts/verified_artifacts.py publish-plugin \
  --plugin dist/SafeContracts-plugin-candidate.zip \
  --source-sha <source-sha> \
  --quality-run-id <run-id> \
  --quality-gates-passed
```

The plugin is intentionally publishable independently of the mobile APK. The operator has installed the plugin on the production WordPress host at `https://cms.50sols.com/`; CI health verification is the automated proof that the deployed plugin exposes the expected public SafeContracts API health contract.

## 8. Backup and restore gate

Before first go-live and before every schema-changing release:

- run `python3 scripts/backup_manifest.py --check`,
- take a database/application snapshot,
- record snapshot ID/time and row-count evidence,
- restore the snapshot into an isolated environment,
- configure external secrets separately (they are intentionally excluded from application backup),
- run migrations and verify idempotence,
- run backend/mobile/release tests against the restored environment as applicable,
- complete the restore UAT scenario and retain sign-off evidence.

Follow `docs/BACKUP_RESTORE_RUNBOOK.md` for the authoritative restore procedure.

## 9. UAT / business acceptance

Execute the committed scenarios in `ops/uat-scenarios.json` with real environment evidence. At minimum verify:

- contract lifecycle,
- assigned-accountant scope,
- collection/settlement,
- follow-up workflow,
- report/Excel export,
- viewer/read-only boundary,
- mobile notification/deep link,
- backup/upgrade/restore.

Record tester, environment, date, result and evidence. Do not convert CI assertions into fictional UAT sign-off.

## 10. Monitoring and operational readiness

Before opening production to users configure:

- external HTTPS availability checks,
- PHP/application error monitoring,
- database availability/capacity monitoring,
- scheduled-job/cron monitoring,
- notification/Firebase failure visibility,
- backup-success and restore-drill tracking,
- certificate-expiry monitoring,
- disk/media-storage capacity alerts,
- an operator path for disabling/revoking compromised sessions/devices,
- incident contacts and rollback ownership.

Logs must not expose authorization headers, passwords, tokens, Firebase private keys or database credentials.

## 11. Release sequence

Recommended release order:

1. Freeze the exact functional source candidate.
2. Pass all four GitHub Quality Gates and the post-gate `release-candidates` job, including the live production `/health` smoke check.
3. Take and record the pre-release backup/snapshot.
4. Rehearse/verify restore if required by the change class.
5. Build the deterministic plugin ZIP and deploy/upgrade it on staging.
6. Run migration/UAT smoke tests and publish the verified plugin with `publish-plugin`.
7. Deploy the exact verified plugin ZIP to production.
8. Verify schema, REST health, permissions, audit trail, scheduled work and core business smoke tests.
9. Build/sign/test the exact mobile release against `https://cms.50sols.com/wp-json/safecontracts/v1/`.
10. Verify the APK signature and execute real-device/UAT acceptance.
11. Publish the verified APK with `publish-apk` only after the external evidence exists.
12. Retain only the newest verified ZIP/APK in their mandatory repository folders; historical binaries go to GitHub Releases.
13. Monitor production closely and roll back using the recorded snapshot/package if acceptance fails.

## 12. Required release evidence

A production release/change record should contain:

- source commit SHA,
- GitHub Quality Gates run ID and successful conclusion,
- plugin ZIP SHA-256,
- APK SHA-256,
- production `/health` smoke-test result and reported plugin version,
- database snapshot/backup identifier,
- schema version after deployment,
- staging/production business smoke-test result,
- APK signature verification result,
- real-device APK test result,
- Firebase notification/deep-link evidence when enabled,
- UAT sign-off,
- release operator and UTC deployment time,
- rollback package/snapshot reference.

## 13. Current blockers before first real production APK

The repository contains a reproducible Android build contract and CI exercises a release APK/signing candidate on every successful Quality Gates run. The production WordPress/API target is now known and committed as public configuration:

`https://cms.50sols.com/wp-json/safecontracts/v1/`

The remaining human/environment-specific requirements before the first **production-verified** APK are:

- production Android signing keystore + passwords stored outside Git,
- any required Firebase Android client configuration,
- signature verification of the exact production-signed APK,
- real-device acceptance evidence,
- business UAT sign-off,
- production backup/restore evidence required for go-live governance.

Until those are available, `Last verified apk/` must remain without a production APK. The CI release candidate is valid evidence of the production endpoint/build-path contract only and must not be promoted as the production-signed application.
