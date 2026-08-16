<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0035EnterpriseWorkflowDefinitions implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $workflows = $wpdb->prefix . 'safecontracts_workflows';
        $versions = $wpdb->prefix . 'safecontracts_workflow_versions';
        $states = $wpdb->prefix . 'safecontracts_workflow_states';
        $transitions = $wpdb->prefix . 'safecontracts_workflow_transitions';

        dbDelta("CREATE TABLE {$workflows} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_type_id bigint(20) unsigned NOT NULL,
            workflow_code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            description longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            UNIQUE KEY tenant_code (tenant_id, workflow_code),
            KEY tenant_type_status (tenant_id, contract_type_id, status, name, id),
            KEY tenant_status_name (tenant_id, status, name, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$versions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            workflow_id bigint(20) unsigned NOT NULL,
            version_no bigint(20) unsigned NOT NULL,
            version_status varchar(20) NOT NULL DEFAULT 'draft',
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            published_by bigint(20) unsigned NULL,
            published_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_workflow_version (tenant_id, workflow_id, version_no),
            KEY tenant_workflow_status (tenant_id, workflow_id, version_status, version_no, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$states} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            workflow_id bigint(20) unsigned NOT NULL,
            workflow_version_id bigint(20) unsigned NOT NULL,
            state_code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            description longtext NULL,
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            is_initial tinyint(1) NOT NULL DEFAULT 0,
            is_terminal tinyint(1) NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_version_code (tenant_id, workflow_version_id, state_code),
            KEY tenant_version_order (tenant_id, workflow_version_id, sort_order, id),
            KEY tenant_workflow_version (tenant_id, workflow_id, workflow_version_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$transitions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            workflow_id bigint(20) unsigned NOT NULL,
            workflow_version_id bigint(20) unsigned NOT NULL,
            transition_code varchar(100) NOT NULL,
            source_state_id bigint(20) unsigned NOT NULL,
            destination_state_id bigint(20) unsigned NOT NULL,
            name varchar(191) NOT NULL,
            description longtext NULL,
            sort_order int(10) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_version_code (tenant_id, workflow_version_id, transition_code),
            KEY tenant_version_order (tenant_id, workflow_version_id, sort_order, id),
            KEY tenant_version_source (tenant_id, workflow_version_id, source_state_id, id),
            KEY tenant_version_destination (tenant_id, workflow_version_id, destination_state_id, id)
        ) {$charset};");
    }
}
