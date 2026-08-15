<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0002MasterData implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $customers = $wpdb->prefix . 'safecontracts_customers';
        $paymentMethods = $wpdb->prefix . 'safecontracts_payment_methods';

        dbDelta("CREATE TABLE {$customers} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            internal_code varchar(100) NULL,
            name varchar(191) NOT NULL,
            contact_name varchar(191) NULL,
            email varchar(191) NULL,
            phone varchar(64) NULL,
            notes text NULL,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY internal_code (internal_code),
            KEY active_name (is_active, name)
        ) {$charset};");

        dbDelta("CREATE TABLE {$paymentMethods} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            code varchar(64) NOT NULL,
            name varchar(191) NOT NULL,
            display_order int(11) unsigned NOT NULL DEFAULT 0,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY code (code),
            KEY active_order (is_active, display_order)
        ) {$charset};");

        $now = gmdate('Y-m-d H:i:s');
        foreach ([
            ['cash', 'Cash', 10],
            ['bank_transfer', 'Bank Transfer', 20],
            ['wallet', 'Wallet', 30],
        ] as [$code, $name, $order]) {
            $sql = $wpdb->prepare(
                "INSERT INTO {$paymentMethods} (code, name, display_order, is_active, created_at, updated_at)
                 VALUES (%s, %s, %d, 1, %s, %s)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), display_order = VALUES(display_order), updated_at = VALUES(updated_at)",
                $code,
                $name,
                $order,
                $now,
                $now
            );
            $wpdb->query($sql);
        }
    }
}
