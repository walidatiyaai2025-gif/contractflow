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
use RuntimeException;

final class Migrator
{
    public const VERSION_OPTION = 'safecontracts_db_version';
    public const LATEST_VERSION = '1.9.0';

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
    ];

    public function maybeMigrate(): void
    {
        $current = (string) get_option(self::VERSION_OPTION, '0.0.0');

        if (version_compare($current, self::LATEST_VERSION, '<')) {
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

        foreach (self::MIGRATIONS as $version => $migrationClass) {
            if (version_compare($current, $version, '>=')) {
                continue;
            }

            /** @var Migration $migration */
            $migration = new $migrationClass();
            $migration->up($wpdb);

            update_option(self::VERSION_OPTION, $version, false);
            update_option('safecontracts_db_migrated_at', gmdate('c'), false);
            $current = $version;

            do_action('safecontracts_database_migrated', $version);
        }
    }
}
