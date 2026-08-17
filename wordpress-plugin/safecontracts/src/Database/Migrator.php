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
use SafeContracts\Database\Migrations\Migration0027EnterpriseContractTemplates;
use SafeContracts\Database\Migrations\Migration0028EnterpriseContractConfigurationBindings;
use SafeContracts\Database\Migrations\Migration0029EnterpriseCustomFieldDefinitions;
use SafeContracts\Database\Migrations\Migration0030EnterpriseCustomFieldValues;
use SafeContracts\Database\Migrations\Migration0031EnterpriseTemplateFieldSets;
use SafeContracts\Database\Migrations\Migration0032EnterpriseCustomFieldMetadata;
use SafeContracts\Database\Migrations\Migration0033EnterpriseCustomFieldVisibilityRules;
use SafeContracts\Database\Migrations\Migration0034EnterpriseCustomFieldCalculationRules;
use SafeContracts\Database\Migrations\Migration0035EnterpriseWorkflowDefinitions;
use SafeContracts\Database\Migrations\Migration0036EnterpriseContractWorkflowInstances;
use SafeContracts\Database\Migrations\Migration0037EnterpriseWorkflowTransitionHistory;
use SafeContracts\Database\Migrations\Migration0038EnterpriseWorkflowTransitionGuards;
use SafeContracts\Database\Migrations\Migration0039EnterpriseWorkflowTransitionApprovalRoutes;
use SafeContracts\Database\Migrations\Migration0040EnterpriseWorkflowApprovalRequests;
use SafeContracts\Database\Migrations\Migration0041EnterpriseWorkflowApprovalDecisions;
use SafeContracts\Database\Migrations\Migration0042EnterpriseWorkflowApprovalReleases;
use SafeContracts\Database\Migrations\Migration0043EnterpriseContractObligations;
use SafeContracts\Database\Migrations\Migration0044EnterpriseContractMilestones;
use SafeContracts\Database\Migrations\Migration0045EnterpriseContractRenewalTerms;
use SafeContracts\Database\Migrations\Migration0046EnterpriseContractNoticePeriodRules;
use SafeContracts\Database\Migrations\Migration0047EnterpriseContractDeliverables;
use SafeContracts\Database\Migrations\Migration0048EnterpriseContractFinancialCurrencyProfiles;
use SafeContracts\Database\Migrations\Migration0049EnterpriseContractFinancialBaseValueRevisions;
use SafeContracts\Database\Migrations\Migration0050EnterpriseContractFinancialAdjustmentRevisions;
use SafeContracts\Database\Migrations\Migration0051EnterpriseContractFinancialVariationRevisions;
use SafeContracts\Database\Migrations\Migration0052EnterpriseContractFinancialTaxRuleRevisions;
use SafeContracts\Database\Migrations\Migration0053EnterpriseContractFinancialRetentionRuleRevisions;

final class Migrator
{
    public const VERSION_OPTION = 'safecontracts_db_version';
    public const LATEST_VERSION = '1.52.0';

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
        '1.26.0' => Migration0027EnterpriseContractTemplates::class,
        '1.27.0' => Migration0028EnterpriseContractConfigurationBindings::class,
        '1.28.0' => Migration0029EnterpriseCustomFieldDefinitions::class,
        '1.29.0' => Migration0030EnterpriseCustomFieldValues::class,
        '1.30.0' => Migration0031EnterpriseTemplateFieldSets::class,
        '1.31.0' => Migration0032EnterpriseCustomFieldMetadata::class,
        '1.32.0' => Migration0033EnterpriseCustomFieldVisibilityRules::class,
        '1.33.0' => Migration0034EnterpriseCustomFieldCalculationRules::class,
        '1.34.0' => Migration0035EnterpriseWorkflowDefinitions::class,
        '1.35.0' => Migration0036EnterpriseContractWorkflowInstances::class,
        '1.36.0' => Migration0037EnterpriseWorkflowTransitionHistory::class,
        '1.37.0' => Migration0038EnterpriseWorkflowTransitionGuards::class,
        '1.38.0' => Migration0039EnterpriseWorkflowTransitionApprovalRoutes::class,
        '1.39.0' => Migration0040EnterpriseWorkflowApprovalRequests::class,
        '1.40.0' => Migration0041EnterpriseWorkflowApprovalDecisions::class,
        '1.41.0' => Migration0042EnterpriseWorkflowApprovalReleases::class,
        '1.42.0' => Migration0043EnterpriseContractObligations::class,
        '1.43.0' => Migration0044EnterpriseContractMilestones::class,
        '1.44.0' => Migration0045EnterpriseContractRenewalTerms::class,
        '1.45.0' => Migration0046EnterpriseContractNoticePeriodRules::class,
        '1.46.0' => Migration0047EnterpriseContractDeliverables::class,
        '1.47.0' => Migration0048EnterpriseContractFinancialCurrencyProfiles::class,
        '1.48.0' => Migration0049EnterpriseContractFinancialBaseValueRevisions::class,
        '1.49.0' => Migration0050EnterpriseContractFinancialAdjustmentRevisions::class,
        '1.50.0' => Migration0051EnterpriseContractFinancialVariationRevisions::class,
        '1.51.0' => Migration0052EnterpriseContractFinancialTaxRuleRevisions::class,
        '1.52.0' => Migration0053EnterpriseContractFinancialRetentionRuleRevisions::class,
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
