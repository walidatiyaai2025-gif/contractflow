<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0005ContractFinance implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $items = $wpdb->prefix . 'safecontracts_contract_financial_items';
        $attachments = $wpdb->prefix . 'safecontracts_contract_attachments';

        dbDelta("CREATE TABLE {$items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            item_type varchar(16) NOT NULL,
            description varchar(191) NOT NULL,
            amount decimal(20,4) NOT NULL,
            display_order int(11) unsigned NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY contract_type_active_order (contract_id, item_type, is_active, display_order),
            KEY contract_active (contract_id, is_active)
        ) {$charset};");

        dbDelta("CREATE TABLE {$attachments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            media_id bigint(20) unsigned NOT NULL,
            label varchar(191) NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_media (contract_id, media_id),
            KEY contract_active (contract_id, is_active)
        ) {$charset};");
    }
}
