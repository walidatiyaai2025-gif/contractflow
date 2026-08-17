<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0039EnterpriseWorkflowTransitionApprovalRoutes implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $routes = $wpdb->prefix . 'safecontracts_workflow_transition_approval_routes';
        $stages = $wpdb->prefix . 'safecontracts_workflow_transition_approval_stages';
        $selectors = $wpdb->prefix . 'safecontracts_workflow_transition_approval_selectors';

        dbDelta("CREATE TABLE {$routes} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            workflow_id bigint(20) unsigned NOT NULL,
            workflow_version_id bigint(20) unsigned NOT NULL,
            transition_id bigint(20) unsigned NOT NULL,
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
            UNIQUE KEY tenant_version_transition (tenant_id, workflow_version_id, transition_id),
            KEY tenant_workflow_version (tenant_id, workflow_id, workflow_version_id, transition_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$stages} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            route_id bigint(20) unsigned NOT NULL,
            position_no int(10) unsigned NOT NULL,
            stage_code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            decision_policy varchar(20) NOT NULL,
            required_approvals int(10) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_route_position (tenant_id, route_id, position_no),
            UNIQUE KEY tenant_route_code (tenant_id, route_id, stage_code),
            KEY tenant_route (tenant_id, route_id, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$selectors} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            route_id bigint(20) unsigned NOT NULL,
            stage_id bigint(20) unsigned NOT NULL,
            position_no int(10) unsigned NOT NULL,
            selector_type varchar(30) NOT NULL,
            selector_user_id bigint(20) unsigned NULL,
            selector_role_code varchar(50) NULL,
            selector_key varchar(100) NOT NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_stage_position (tenant_id, stage_id, position_no),
            UNIQUE KEY tenant_stage_selector (tenant_id, stage_id, selector_key),
            KEY tenant_route_stage (tenant_id, route_id, stage_id, id),
            KEY tenant_user_selector (tenant_id, selector_user_id, stage_id),
            KEY tenant_role_selector (tenant_id, selector_role_code, stage_id)
        ) {$charset};");
    }
}
