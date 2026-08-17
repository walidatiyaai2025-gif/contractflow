<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0038EnterpriseWorkflowTransitionGuards implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $guards = $wpdb->prefix . 'safecontracts_workflow_transition_guards';

        dbDelta("CREATE TABLE {$guards} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            workflow_id bigint(20) unsigned NOT NULL,
            workflow_version_id bigint(20) unsigned NOT NULL,
            transition_id bigint(20) unsigned NOT NULL,
            position_no int(10) unsigned NOT NULL,
            guard_type varchar(50) NOT NULL,
            transition_code_snapshot varchar(100) NOT NULL,
            source_state_id_snapshot bigint(20) unsigned NOT NULL,
            source_state_code_snapshot varchar(100) NOT NULL,
            destination_state_id_snapshot bigint(20) unsigned NOT NULL,
            destination_state_code_snapshot varchar(100) NOT NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_version_transition_type (tenant_id, workflow_version_id, transition_id, guard_type),
            UNIQUE KEY tenant_version_transition_position (tenant_id, workflow_version_id, transition_id, position_no),
            KEY tenant_workflow_version (tenant_id, workflow_id, workflow_version_id, transition_id)
        ) {$charset};");
    }
}
