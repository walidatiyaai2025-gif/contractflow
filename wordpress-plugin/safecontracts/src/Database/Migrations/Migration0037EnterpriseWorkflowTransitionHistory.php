<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0037EnterpriseWorkflowTransitionHistory implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $history = $wpdb->prefix . 'safecontracts_contract_workflow_transition_history';

        dbDelta("CREATE TABLE {$history} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            instance_id bigint(20) unsigned NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            workflow_id bigint(20) unsigned NOT NULL,
            workflow_version_id bigint(20) unsigned NOT NULL,
            transition_id bigint(20) unsigned NOT NULL,
            transition_code_snapshot varchar(100) NOT NULL,
            from_state_id bigint(20) unsigned NOT NULL,
            from_state_code_snapshot varchar(100) NOT NULL,
            to_state_id bigint(20) unsigned NOT NULL,
            to_state_code_snapshot varchar(100) NOT NULL,
            request_key_hash char(64) NOT NULL,
            actor_user_id bigint(20) unsigned NULL,
            occurred_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_instance_request (tenant_id, instance_id, request_key_hash),
            KEY tenant_contract_recent (tenant_id, contract_id, occurred_at, id),
            KEY tenant_instance_recent (tenant_id, instance_id, occurred_at, id),
            KEY tenant_version_transition (tenant_id, workflow_version_id, transition_id, id)
        ) {$charset};");
    }
}
