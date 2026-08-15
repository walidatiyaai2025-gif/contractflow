<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0005ContractFinancials implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $items = $wpdb->prefix . 'safecontracts_contract_financial_items';
        $adjustments = $wpdb->prefix . 'safecontracts_contract_adjustments';
        $attachments = $wpdb->prefix . 'safecontracts_contract_attachments';

        dbDelta("CREATE TABLE {$items} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            description varchar(191) NOT NULL,
            amount decimal(20,4) NOT NULL DEFAULT 0.0000,
            display_order int(11) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY contract_order (contract_id, display_order, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$adjustments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            adjustment_type varchar(16) NOT NULL,
            description varchar(191) NOT NULL,
            amount decimal(20,4) NOT NULL DEFAULT 0.0000,
            display_order int(11) unsigned NOT NULL DEFAULT 0,
            created_by bigint(20) unsigned NULL,
            updated_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY contract_type_order (contract_id, adjustment_type, display_order, id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$attachments} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            media_id bigint(20) unsigned NOT NULL,
            label varchar(191) NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY contract_media (contract_id, media_id),
            KEY media_id (media_id)
        ) {$charset};");
    }
}
