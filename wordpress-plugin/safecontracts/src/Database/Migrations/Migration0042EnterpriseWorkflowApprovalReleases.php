<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0042EnterpriseWorkflowApprovalReleases implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $releases = $wpdb->prefix . 'safecontracts_workflow_approval_releases';

        dbDelta("CREATE TABLE {$releases} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            request_id bigint(20) unsigned NOT NULL,
            instance_id bigint(20) unsigned NOT NULL,
            transition_history_id bigint(20) unsigned NOT NULL,
            release_key_hash char(64) NOT NULL,
            released_by bigint(20) unsigned NOT NULL,
            released_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_request_release (tenant_id, request_id),
            UNIQUE KEY tenant_release_key (tenant_id, release_key_hash),
            UNIQUE KEY tenant_transition_history_release (tenant_id, transition_history_id),
            KEY tenant_instance_released (tenant_id, instance_id, released_at, id)
        ) {$charset};");
    }
}
