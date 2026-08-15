<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0006ContractHistory implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $history = $wpdb->prefix . 'safecontracts_contract_history';

        dbDelta("CREATE TABLE {$history} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            contract_id bigint(20) unsigned NOT NULL,
            event_type varchar(64) NOT NULL,
            actor_user_id bigint(20) unsigned NULL,
            snapshot_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY contract_created (contract_id, created_at, id),
            KEY actor_created (actor_user_id, created_at, id),
            KEY event_created (event_type, created_at, id)
        ) {$charset};");
    }
}
