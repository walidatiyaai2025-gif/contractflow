<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0040EnterpriseWorkflowApprovalRequests implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $requests = $wpdb->prefix . 'safecontracts_workflow_approval_requests';
        $stages = $wpdb->prefix . 'safecontracts_workflow_approval_request_stages';
        $selectors = $wpdb->prefix . 'safecontracts_workflow_approval_request_selectors';
        $candidates = $wpdb->prefix . 'safecontracts_workflow_approval_request_candidates';

        dbDelta("CREATE TABLE {$requests} (
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
            route_id_snapshot bigint(20) unsigned NOT NULL,
            request_key_hash char(64) NOT NULL,
            status varchar(20) NOT NULL DEFAULT 'pending',
            requester_user_id bigint(20) unsigned NOT NULL,
            requested_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_instance_request_key (tenant_id, instance_id, request_key_hash),
            KEY tenant_contract_requested (tenant_id, contract_id, requested_at, id),
            KEY tenant_instance_pending (tenant_id, instance_id, status, transition_id, from_state_id, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$stages} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            request_id bigint(20) unsigned NOT NULL,
            route_stage_id_snapshot bigint(20) unsigned NOT NULL,
            position_no int(10) unsigned NOT NULL,
            stage_code_snapshot varchar(100) NOT NULL,
            name_snapshot varchar(191) NOT NULL,
            decision_policy_snapshot varchar(20) NOT NULL,
            required_approvals_snapshot int(10) unsigned NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_request_position (tenant_id, request_id, position_no),
            UNIQUE KEY tenant_request_stage_code (tenant_id, request_id, stage_code_snapshot),
            KEY tenant_request_stage (tenant_id, request_id, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$selectors} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            request_id bigint(20) unsigned NOT NULL,
            request_stage_id bigint(20) unsigned NOT NULL,
            route_selector_id_snapshot bigint(20) unsigned NOT NULL,
            position_no int(10) unsigned NOT NULL,
            selector_type_snapshot varchar(30) NOT NULL,
            selector_user_id_snapshot bigint(20) unsigned NULL,
            selector_role_code_snapshot varchar(50) NULL,
            selector_key_snapshot varchar(100) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_request_stage_position (tenant_id, request_stage_id, position_no),
            UNIQUE KEY tenant_request_stage_selector (tenant_id, request_stage_id, selector_key_snapshot),
            KEY tenant_request_selector (tenant_id, request_id, request_stage_id, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$candidates} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            request_id bigint(20) unsigned NOT NULL,
            request_stage_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_request_stage_user (tenant_id, request_stage_id, user_id),
            KEY tenant_request_user (tenant_id, request_id, user_id, request_stage_id),
            KEY tenant_user_request (tenant_id, user_id, request_id, request_stage_id)
        ) {$charset};");
    }
}
