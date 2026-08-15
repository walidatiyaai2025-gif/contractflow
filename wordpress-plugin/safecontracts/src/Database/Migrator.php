<?php

declare(strict_types=1);

namespace SafeContracts\Database;

use SafeContracts\Database\Migrations\Migration0001Foundation;
use SafeContracts\Database\Migrations\Migration0002MasterData;
use SafeContracts\Database\Migrations\Migration0003ReferenceDataCapability;
use SafeContracts\Database\Migrations\Migration0004Contracts;
use SafeContracts\Database\Migrations\Migration0005ContractFinancials;
use RuntimeException;

final class Migrator
{
    public const VERSION_OPTION = 'safecontracts_db_version';
    public const LATEST_VERSION = '1.4.0';

    /** @var array<string, class-string<Migration>> */
    private const MIGRATIONS = [
        '1.0.0' => Migration0001Foundation::class,
        '1.1.0' => Migration0002MasterData::class,
        '1.2.0' => Migration0003ReferenceDataCapability::class,
        '1.3.0' => Migration0004Contracts::class,
        '1.4.0' => Migration0005ContractFinancials::class,
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

            // Version is advanced only after the migration succeeds.
            update_option(self::VERSION_OPTION, $version, false);
            update_option('safecontracts_db_migrated_at', gmdate('c'), false);
            $current = $version;

            do_action('safecontracts_database_migrated', $version);
        }
    }
}
