<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0049EnterpriseContractFinancialBaseValueRevisions implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_base_value_revisions';

        dbDelta("CREATE TABLE {$revisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            financial_currency_profile_id bigint(20) unsigned NOT NULL,
            revision_number bigint(20) unsigned NOT NULL,
            amount decimal(20,4) NOT NULL,
            currency_code char(3) NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY financial_base_value_revision_uuid (uuid),
            UNIQUE KEY tenant_contract_base_value_revision (tenant_id, contract_id, revision_number),
            KEY tenant_contract_latest_base_value (tenant_id, contract_id, revision_number, id),
            KEY tenant_financial_profile_base_value (tenant_id, financial_currency_profile_id, revision_number)
        ) {$charset};");
    }
}
