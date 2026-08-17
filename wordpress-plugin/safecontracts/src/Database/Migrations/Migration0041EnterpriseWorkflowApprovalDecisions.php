<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0041EnterpriseWorkflowApprovalDecisions implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $decisions = $wpdb->prefix . 'safecontracts_workflow_approval_decisions';

        dbDelta("CREATE TABLE {$decisions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            request_id bigint(20) unsigned NOT NULL,
            request_stage_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            action varchar(20) NOT NULL,
            decision_key_hash char(64) NOT NULL,
            comment text NULL,
            decided_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_decision_key (tenant_id, decision_key_hash),
            UNIQUE KEY tenant_stage_user (tenant_id, request_stage_id, user_id),
            KEY tenant_request_decided (tenant_id, request_id, decided_at, id),
            KEY tenant_stage_action (tenant_id, request_stage_id, action, user_id)
        ) {$charset};");
    }
}
