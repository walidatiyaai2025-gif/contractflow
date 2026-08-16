<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0028EnterpriseContractConfigurationBindings implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $bindings = $wpdb->prefix . 'safecontracts_contract_configuration_bindings';

        dbDelta("CREATE TABLE {$bindings} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            contract_type_id bigint(20) unsigned NOT NULL,
            template_id bigint(20) unsigned NULL,
            template_version_id bigint(20) unsigned NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY tenant_contract (tenant_id, contract_id),
            KEY tenant_type (tenant_id, contract_type_id, contract_id),
            KEY tenant_template_version (tenant_id, template_id, template_version_id, contract_id)
        ) {$charset};");
    }
}
