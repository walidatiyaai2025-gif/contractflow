<?php

declare(strict_types=1);

namespace SafeContracts\Database;

use RuntimeException;
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
use SafeContracts\Database\Migrations\Migration0016EnterpriseTenancy;
use SafeContracts\Database\Migrations\Migration0017CoreTenantOwnershipExpand;
use SafeContracts\Database\Migrations\Migration0018NonCoreTenantOwnershipExpand;
use SafeContracts\Database\Migrations\Migration0019EnterpriseRateLimits;
use SafeContracts\Database\Migrations\Migration0020EnterpriseParties;
use SafeContracts\Database\Migrations\Migration0021EnterprisePartyRoles;
use SafeContracts\Database\Migrations\Migration0022EnterprisePartyRelationships;
use SafeContracts\Database\Migrations\Migration0023EnterpriseOrgUnits;
use SafeContracts\Database\Migrations\Migration0024EnterpriseOrgUnitMemberships;
use SafeContracts\Database\Migrations\Migration0025EnterpriseCustomerPartyLinks;
use SafeContracts\Database\Migrations\Migration0026EnterpriseContractTypes;

final class Migrator
{
    public const VERSION_OPTION = 'safecontracts_db_version';
    public const LATEST_VERSION = '1.25.0';

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
        '1.15.0' => Migration0016EnterpriseTenancy::class,
        '1.16.0' => Migration0017CoreTenantOwnershipExpand::class,
        '1.17.0' => Migration0018NonCoreTenantOwnershipExpand::class,
        '1.18.0' => Migration0019EnterpriseRateLimits::class,
        '1.19.0' => Migration0020EnterpriseParties::class,
        '1.20.0' => Migration0021EnterprisePartyRoles::class,
        '1.21.0' => Migration0022EnterprisePartyRelationships::class,
        '1.22.0' => Migration0023EnterpriseOrgUnits::class,
        '1.23.0' => Migration0024EnterpriseOrgUnitMemberships::class,
        '1.24.0' => Migration0025EnterpriseCustomerPartyLinks::class,
        '1.25.0' => Migration0026EnterpriseContractTypes::class,
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
