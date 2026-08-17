<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0043EnterpriseContractObligations implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $obligations = $wpdb->prefix . 'safecontracts_contract_obligations';

        dbDelta("CREATE TABLE {$obligations} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            tenant_id bigint(20) unsigned NOT NULL,
            uuid char(36) NOT NULL,
            contract_id bigint(20) unsigned NOT NULL,
            obligation_code varchar(100) NOT NULL,
            title varchar(191) NOT NULL,
            description text NULL,
            due_date date NULL,
            status varchar(20) NOT NULL DEFAULT 'open',
            completed_at datetime NULL,
            completed_by bigint(20) unsigned NULL,
            cancelled_at datetime NULL,
            cancelled_by bigint(20) unsigned NULL,
            created_by bigint(20) unsigned NOT NULL,
            updated_by bigint(20) unsigned NOT NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uuid (uuid),
            UNIQUE KEY tenant_contract_code (tenant_id, contract_id, obligation_code),
            KEY tenant_contract_status_due (tenant_id, contract_id, status, due_date, id),
            KEY tenant_status_due (tenant_id, status, due_date, id),
            KEY tenant_contract_updated (tenant_id, contract_id, updated_at, id)
        ) {$charset};");
    }
}
