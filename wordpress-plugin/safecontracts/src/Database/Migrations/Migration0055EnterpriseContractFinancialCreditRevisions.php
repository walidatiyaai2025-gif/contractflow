<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0055EnterpriseContractFinancialCreditRevisions implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_credit_revisions';

        dbDelta("CREATE TABLE {$revisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            revision_uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            financial_currency_profile_id bigint(20) unsigned NOT NULL,
            credit_uuid char(36) NOT NULL,
            revision_number bigint(20) unsigned NOT NULL,
            reason varchar(191) NOT NULL,
            amount decimal(20,4) NOT NULL,
            currency_code char(3) NOT NULL,
            credit_state varchar(16) NOT NULL DEFAULT 'proposed',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY financial_credit_revision_uuid (revision_uuid),
            UNIQUE KEY tenant_contract_credit_revision (tenant_id, contract_id, credit_uuid, revision_number),
            KEY tenant_contract_credit_latest (tenant_id, contract_id, credit_uuid, revision_number, id),
            KEY tenant_contract_credit_state (tenant_id, contract_id, credit_state),
            KEY tenant_credit_profile (tenant_id, financial_currency_profile_id, contract_id)
        ) {$charset};");
    }
}
