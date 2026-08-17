<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0052EnterpriseContractFinancialTaxRuleRevisions implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $revisions = $wpdb->prefix . 'safecontracts_contract_financial_tax_rule_revisions';

        dbDelta("CREATE TABLE {$revisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            revision_uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            financial_currency_profile_id bigint(20) unsigned NOT NULL,
            tax_rule_uuid char(36) NOT NULL,
            revision_number bigint(20) unsigned NOT NULL,
            tax_kind varchar(16) NOT NULL,
            label varchar(120) NOT NULL,
            rate_percent decimal(7,4) NOT NULL,
            tax_rule_state varchar(16) NOT NULL DEFAULT 'configured',
            created_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY financial_tax_rule_revision_uuid (revision_uuid),
            UNIQUE KEY tenant_contract_tax_rule_revision (tenant_id, contract_id, tax_rule_uuid, revision_number),
            KEY tenant_contract_tax_rule_latest (tenant_id, contract_id, tax_rule_uuid, revision_number, id),
            KEY tenant_contract_tax_kind_state (tenant_id, contract_id, tax_kind, tax_rule_state),
            KEY tenant_tax_rule_profile (tenant_id, financial_currency_profile_id, contract_id)
        ) {$charset};");
    }
}
