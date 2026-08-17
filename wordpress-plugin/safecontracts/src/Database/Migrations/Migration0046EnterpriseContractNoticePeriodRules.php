<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0046EnterpriseContractNoticePeriodRules implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $rules = $wpdb->prefix . 'safecontracts_contract_notice_period_rules';

        dbDelta("CREATE TABLE {$rules} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            notice_code varchar(64) NOT NULL,
            purpose varchar(32) NOT NULL,
            direction varchar(20) NOT NULL DEFAULT 'outbound',
            period_value int(10) unsigned NOT NULL,
            period_unit varchar(10) NOT NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            notes text NULL,
            revision bigint(20) unsigned NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY notice_period_rule_uuid (uuid),
            UNIQUE KEY tenant_contract_notice_code (tenant_id, contract_id, notice_code),
            KEY tenant_contract_active_purpose (tenant_id, contract_id, is_active, purpose, id),
            KEY tenant_purpose_active (tenant_id, purpose, is_active, id)
        ) {$charset};");
    }
}
