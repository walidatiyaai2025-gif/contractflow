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

The mobile application may receive only public compile-time configuration. For production the required SafeContracts mobile values include:

- `SC_ENV=production`
- `SC_API_BASE_URL=https://<production-host>/wp-json/safecontracts/v1/`

A production API URL using plain HTTP is forbidden.

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

A production APK cannot be created from the current repository until the Flutter Android platform scaffold exists under `mobile/android/`.

Before producing `SafeContracts-latest.apk`:

1. Add and review the Android Flutter platform scaffold.
2. Set the production application ID/package name and versioning policy.
3. Configure Android SDK/Gradle/JDK versions compatible with the chosen Flutter stable toolchain.
4. Configure release signing. The keystore and passwords must live in CI/secret storage, never in Git.
5. Configure the production HTTPS API URL using `--dart-define`/`--dart-define-from-file` from a secret-safe CI source.
6. Configure any required public Firebase Android client configuration without embedding server private keys.
7. Run `dart format`, `flutter analyze` and `flutter test`.
8. Build a **release** APK, not a debug APK.
9. Install the exact APK on at least one representative real Android device and execute the mobile UAT flows.
10. Verify authentication, permissions, dashboard/filtering, contracts/payments, collection/follow-up, notifications/deep links, RTL and offline/error handling against the target production/staging API.
11. Only after those checks may the APK be published to `Last verified apk/SafeContracts-latest.apk`.

For Play Store distribution prefer an AAB in addition to the retained APK, but the repository's mandatory latest local artifact remains the APK requested by the project owner.

## 7. Plugin production package

The production plugin package must be created from `wordpress-plugin/safecontracts/` only.

The ZIP must:

- have `safecontracts/` as the installable plugin root,
- exclude Git metadata, repository docs, tests, local caches, logs and all secrets,
- contain the same source commit that passed Quality Gates,
- be install/upgrade tested on staging,
- pass backend regressions and migration verification before retention.

After verification publish/replace:

`Last verified Plugin/SafeContracts-latest.zip`

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

1. Freeze the exact candidate commit.
2. Pass all four GitHub Quality Gates on that candidate.
3. Take and record the pre-release backup/snapshot.
4. Rehearse/verify restore if required by the change class.
5. Deploy/upgrade the plugin on staging and run migration/UAT smoke tests.
6. Deploy the exact verified plugin ZIP to production.
7. Verify schema, REST health, permissions, audit trail, scheduled work and core business smoke tests.
8. Build/sign/test the exact mobile release against the production HTTPS endpoint.
9. Publish verified artifacts using `scripts/verified_artifacts.py publish`.
10. Retain only the newest verified ZIP/APK in their mandatory repository folders; historical binaries go to GitHub Releases.
11. Monitor production closely and roll back using the recorded snapshot/package if acceptance fails.

## 12. Required release evidence

A production release/change record should contain:

- source commit SHA,
- GitHub Quality Gates run ID and successful conclusion,
- plugin ZIP SHA-256,
- APK SHA-256,
- database snapshot/backup identifier,
- schema version after deployment,
- staging/production smoke-test result,
- real-device APK test result,
- Firebase notification/deep-link evidence when enabled,
- UAT sign-off,
- release operator and UTC deployment time,
- rollback package/snapshot reference.

## 13. Current repository blockers before first real production APK

At the time this document was added, `mobile/` contains Flutter Dart sources/tests but no committed `mobile/android/` platform scaffold. Therefore there is no legitimate production APK build path yet. The smallest required next mobile-production action is to add/review the Android scaffold and configure secret-safe release signing. Until that is complete, `Last verified apk/` must not contain a fabricated/debug APK labelled as production verified.
