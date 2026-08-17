<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0036EnterpriseContractWorkflowInstances implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $instances = $wpdb->prefix . 'safecontracts_contract_workflow_instances';

        dbDelta("CREATE TABLE {$instances} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            contract_type_id bigint(20) unsigned NOT NULL,
            workflow_id bigint(20) unsigned NOT NULL,
            workflow_version_id bigint(20) unsigned NOT NULL,
            workflow_version_no bigint(20) unsigned NOT NULL,
            workflow_code_snapshot varchar(100) NOT NULL,
            current_state_id bigint(20) unsigned NOT NULL,
            current_state_code_snapshot varchar(100) NOT NULL,
            started_by bigint(20) unsigned NULL,
            started_at datetime NOT NULL,
            updated_by bigint(20) unsigned NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_contract (tenant_id, contract_id),
            KEY tenant_type (tenant_id, contract_type_id, id),
            KEY tenant_workflow_version (tenant_id, workflow_id, workflow_version_id, id),
            KEY tenant_state (tenant_id, workflow_version_id, current_state_id, id)
        ) {$charset};");
    }
}
