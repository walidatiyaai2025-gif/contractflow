<?php

declare(strict_types=1);

namespace SafeContracts\Database;

use SafeContracts\Database\Migrations\Migration0001Foundation;
use SafeContracts\Database\Migrations\Migration0002MasterData;
use SafeContracts\Database\Migrations\Migration0003ReferenceDataCapability;
use SafeContracts\Database\Migrations\Migration0004Contracts;
use SafeContracts\Database\Migrations\Migration0005ContractFinancials;
use SafeContracts\Database\Migrations\Migration0006ContractHistory;
use SafeContracts\Database\Migrations\Migration0007Payments;
use SafeContracts\Database\Migrations\Migration0008Collections;
use SafeContracts\Database\Migrations\Migration0009FollowupAudit;
use SafeContracts\Database\Migrations\Migration0010NotificationRules;
use SafeContracts\Database\Migrations\Migration0011NotificationDelivery;
use SafeContracts\Database\Migrations\Migration0012Import;
use SafeContracts\Database\Migrations\Migration0013SafeDeletion;
use SafeContracts\Database\Migrations\Migration0014NotificationSchedule;
use SafeContracts\Database\Migrations\Migration0015NotificationCenter;
use SafeContracts\Database\Migrations\Migration0016MobileCrudCapabilities;
use SafeContracts\Database\Migrations\Migration0017CounterpartySupplierApar;
use SafeContracts\Database\Migrations\Migration0018SupplierFinanceReconciliation;
use SafeContracts\Database\Migrations\Migration0019NullableLegacyCustomer;
use RuntimeException;
use Throwable;

final class Migrator
{
    public const VERSION_OPTION = 'safecontracts_db_version';
    public const LATEST_VERSION = '1.18.0';

    /**
     * All migrations introduced after this already-released baseline must use
     * ProductionMigration so preflight, verification and rollback are explicit.
     */
    public const PRODUCTION_GUARD_BASELINE = '1.17.0';

    /** @var array<string, class-string<Migration>> */
    private const MIGRATIONS = [
        '1.0.0' => Migration0001Foundation::class,
        '1.1.0' => Migration0002MasterData::class,
        '1.2.0' => Migration0003ReferenceDataCapability::class,
        '1.3.0' => Migration0004Contracts::class,
        '1.4.0' => Migration0005ContractFinancials::class,
        '1.5.0' => Migration0006ContractHistory::class,
        '1.6.0' => Migration0007Payments::class,
        '1.7.0' => Migration0008Collections::class,
        '1.8.0' => Migration0009FollowupAudit::class,
        '1.9.0' => Migration0010NotificationRules::class,
        '1.10.0' => Migration0011NotificationDelivery::class,
        '1.11.0' => Migration0012Import::class,
        '1.12.0' => Migration0013SafeDeletion::class,
        '1.13.0' => Migration0014NotificationSchedule::class,
        '1.14.0' => Migration0015NotificationCenter::class,
        '1.15.0' => Migration0016MobileCrudCapabilities::class,
        '1.16.0' => Migration0017CounterpartySupplierApar::class,
        '1.17.0' => Migration0018SupplierFinanceReconciliation::class,
        '1.18.0' => Migration0019NullableLegacyCustomer::class,
    ];

    /** @var array<string, class-string<Migration>> */
    private array $migrations;
    private MigrationGuard $guard;
    private string $latestVersion;

    /**
     * @param array<string, class-string<Migration>>|null $migrations
     */
    public function __construct(
        ?array $migrations = null,
        ?MigrationGuard $guard = null,
        ?string $latestVersion = null
    ) {
        $this->migrations = $migrations ?? self::MIGRATIONS;
        $this->guard = $guard ?? new MigrationGuard();
        $this->latestVersion = $latestVersion ?? self::LATEST_VERSION;
    }

    public function maybeMigrate(): void
    {
        $current = (string) get_option(self::VERSION_OPTION, '0.0.0');
        $this->guard->assertDatabaseCompatible($current, $this->latestVersion);

        // Keep the released P10 production invariant explicit while retaining
        // an injectable latest version for isolated migration-guard tests.
        $needsMigration = $this->latestVersion === self::LATEST_VERSION
            ? version_compare($current, self::LATEST_VERSION, '<')
            : version_compare($current, $this->latestVersion, '<');

        if ($needsMigration) {
            $this->migrate();
        }
    }

    public function migrate(): void
    {
        global $wpdb;

        if (! is_object($wpdb)) {
            throw new RuntimeException('SafeContracts database migration requires WordPress $wpdb.');
        }

        $current = (string) get_option(self::VERSION_OPTION, '0.0.0');
        $this->guard->assertDatabaseCompatible($current, $this->latestVersion);

        if (version_compare($current, $this->latestVersion, '>=')) {
            return;
        }

        $this->guard->withLock(function () use ($wpdb, &$current): void {
            // Re-read after acquiring the single-writer lock in case another
            // request completed the migration immediately before this one.
            $current = (string) get_option(self::VERSION_OPTION, '0.0.0');
            $this->guard->assertDatabaseCompatible($current, $this->latestVersion);

            foreach ($this->migrations as $version => $migrationClass) {
                if (version_compare($version, $this->latestVersion, '>')) {
                    continue;
                }
                if (version_compare($current, $version, '>=')) {
                    continue;
                }

                $fromVersion = $current;
                $runId = $this->guard->startMigration($fromVersion, $version, $migrationClass);
                $rollbackStatus = 'not_supported';

                /** @var Migration $migration */
                $migration = new $migrationClass();

                try {
                    if (version_compare($version, self::PRODUCTION_GUARD_BASELINE, '>') && ! $migration instanceof ProductionMigration) {
                        throw new RuntimeException(sprintf(
                            'Migration %s (%s) must implement ProductionMigration before it can run in production.',
                            $migrationClass,
                            $version
                        ));
                    }

                    if ($migration instanceof ProductionMigration) {
                        $rollbackStatus = 'not_required';
                        $migration->preflight($wpdb);
                    }

                    $migration->up($wpdb);

                    if ($migration instanceof ProductionMigration) {
                        $migration->verify($wpdb);
                    }

                    if (! update_option(self::VERSION_OPTION, $version, false)) {
                        throw new RuntimeException('SafeContracts could not persist the migrated database version.');
                    }
                    update_option('safecontracts_db_migrated_at', gmdate('c'), false);

                    $this->guard->markSucceeded($runId, $fromVersion, $version, $migrationClass);
                    $current = $version;
                    do_action('safecontracts_database_migrated', $version);
                } catch (Throwable $error) {
                    if ($migration instanceof ProductionMigration) {
                        try {
                            $migration->rollback($wpdb);
                            $rollbackStatus = 'succeeded';
                        } catch (Throwable) {
                            $rollbackStatus = 'failed_restore_backup_required';
                        }
                    }

                    $this->guard->markFailed(
                        $runId,
                        $fromVersion,
                        $version,
                        $migrationClass,
                        $error,
                        $rollbackStatus
                    );

                    throw new RuntimeException(
                        sprintf(
                            'SafeContracts migration to %s failed. Database version remains %s. Review migration recovery evidence before retrying.',
                            $version,
                            $fromVersion
                        ),
                        0,
                        $error
                    );
                }
            }
        });
    }
}
