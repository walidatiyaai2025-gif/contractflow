<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0045EnterpriseContractRenewalTerms implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $renewalTerms = $wpdb->prefix . 'safecontracts_contract_renewal_terms';

        dbDelta("CREATE TABLE {$renewalTerms} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            renewal_mode varchar(20) NOT NULL DEFAULT 'none',
            interval_value int(10) unsigned NULL,
            interval_unit varchar(10) NULL,
            max_occurrences int(10) unsigned NULL,
            notes text NULL,
            revision bigint(20) unsigned NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY renewal_terms_uuid (uuid),
            UNIQUE KEY tenant_contract_renewal_terms (tenant_id, contract_id),
            KEY tenant_renewal_mode (tenant_id, renewal_mode, contract_id)
        ) {$charset};");
    }
}
