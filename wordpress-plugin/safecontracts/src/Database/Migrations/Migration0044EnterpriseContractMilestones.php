<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0044EnterpriseContractMilestones implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $milestones = $wpdb->prefix . 'safecontracts_contract_milestones';

        dbDelta("CREATE TABLE {$milestones} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            milestone_code varchar(64) NOT NULL,
            title varchar(191) NOT NULL,
            description text NULL,
            target_date date NULL,
            status varchar(20) NOT NULL DEFAULT 'planned',
            achieved_at datetime NULL,
            achieved_by bigint(20) unsigned NULL,
            cancelled_at datetime NULL,
            cancelled_by bigint(20) unsigned NULL,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY milestone_uuid (uuid),
            UNIQUE KEY tenant_contract_milestone_code (tenant_id, contract_id, milestone_code),
            KEY tenant_contract_status_target (tenant_id, contract_id, status, target_date, id),
            KEY tenant_target_status (tenant_id, target_date, status, id)
        ) {$charset};");
    }
}
