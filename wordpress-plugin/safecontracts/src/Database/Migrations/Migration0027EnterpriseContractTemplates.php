<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0027EnterpriseContractTemplates implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $templates = $wpdb->prefix . 'safecontracts_contract_templates';
        $versions = $wpdb->prefix . 'safecontracts_contract_template_versions';

        dbDelta("CREATE TABLE {$templates} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_type_id bigint(20) unsigned NOT NULL,
            template_code varchar(100) NOT NULL,
            name varchar(191) NOT NULL,
            description longtext NULL,
            status varchar(20) NOT NULL DEFAULT 'active',
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            UNIQUE KEY tenant_code (tenant_id, template_code),
            KEY tenant_type_status (tenant_id, contract_type_id, status, name, id),
            KEY tenant_status_name (tenant_id, status, name, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$versions} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            template_id bigint(20) unsigned NOT NULL,
            version_no bigint(20) unsigned NOT NULL,
            version_status varchar(20) NOT NULL DEFAULT 'draft',
            definition_json longtext NOT NULL,
            notes longtext NULL,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            published_by bigint(20) unsigned NULL,
            published_at datetime NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY tenant_template_version (tenant_id, template_id, version_no),
            KEY tenant_template_status (tenant_id, template_id, version_status, version_no, id)
        ) {$charset};");
    }
}
