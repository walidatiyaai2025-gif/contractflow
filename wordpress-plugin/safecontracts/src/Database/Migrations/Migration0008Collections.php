<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0008Collections implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $collections = $wpdb->prefix . 'safecontracts_payment_collections';

        dbDelta("CREATE TABLE {$collections} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            payment_id bigint(20) unsigned NOT NULL,
            amount decimal(20,4) NOT NULL,
            collection_date date NOT NULL,
            payment_method_id bigint(20) unsigned NOT NULL,
            reference varchar(191) NULL,
            note text NULL,
            proof_media_id bigint(20) unsigned NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            is_reversed tinyint(1) NOT NULL DEFAULT 0,
            reversed_at datetime NULL,
            reversed_by bigint(20) unsigned NULL,
            reversal_reason text NULL,
            PRIMARY KEY  (id),
            KEY payment_active_date (payment_id, is_reversed, collection_date, id),
            KEY method_date (payment_method_id, collection_date, id),
            KEY collection_date (collection_date, id),
            KEY proof_media (proof_media_id)
        ) {$charset};");
    }
}
