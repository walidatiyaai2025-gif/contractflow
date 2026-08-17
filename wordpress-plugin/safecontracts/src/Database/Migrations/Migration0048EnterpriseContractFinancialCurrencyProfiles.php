<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0048EnterpriseContractFinancialCurrencyProfiles implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $profiles = $wpdb->prefix . 'safecontracts_contract_financial_currency_profiles';

        dbDelta("CREATE TABLE {$profiles} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            contract_currency char(3) NOT NULL,
            tenant_base_currency_snapshot char(3) NOT NULL,
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY financial_currency_profile_uuid (uuid),
            UNIQUE KEY tenant_contract_financial_currency_profile (tenant_id, contract_id),
            KEY tenant_contract_currency (tenant_id, contract_currency, contract_id)
        ) {$charset};");
    }
}
