<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0050EnterpriseContractFinancialAdjustmentRevisions implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_adjustment_revisions';

        dbDelta("CREATE TABLE {$revisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            revision_uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            financial_currency_profile_id bigint(20) unsigned NOT NULL,
            line_uuid char(36) NOT NULL,
            revision_number bigint(20) unsigned NOT NULL,
            adjustment_kind varchar(16) NOT NULL,
            description varchar(191) NOT NULL,
            amount decimal(20,4) NOT NULL,
            currency_code char(3) NOT NULL,
            line_state varchar(16) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY financial_adjustment_revision_uuid (revision_uuid),
            UNIQUE KEY tenant_contract_adjustment_revision (tenant_id, contract_id, line_uuid, revision_number),
            KEY tenant_contract_adjustment_latest (tenant_id, contract_id, line_uuid, revision_number, id),
            KEY tenant_contract_adjustment_kind (tenant_id, contract_id, adjustment_kind, line_state),
            KEY tenant_adjustment_profile (tenant_id, financial_currency_profile_id, contract_id)
        ) {$charset};");
    }
}
