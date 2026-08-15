<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0009FollowupAudit implements Migration
{
    public function up(object $wpdb): void
    {
        if (! function_exists('dbDelta')) {
            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        }

        $charset = method_exists($wpdb, 'get_charset_collate') ? $wpdb->get_charset_collate() : '';
        $followups = $wpdb->prefix . 'safecontracts_payment_followups';
        $audit = $wpdb->prefix . 'safecontracts_audit_log';

        dbDelta("CREATE TABLE {$followups} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            payment_id bigint(20) unsigned NOT NULL,
            state varchar(32) NOT NULL,
            note text NULL,
            promised_date date NULL,
            deferred_until date NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY payment_timeline (payment_id, created_at, id),
            KEY state_timeline (state, created_at, id),
            KEY promised_date (promised_date, payment_id),
            KEY deferred_until (deferred_until, payment_id)
        ) {$charset};");

        dbDelta("CREATE TABLE {$audit} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(32) NOT NULL,
            entity_id bigint(20) unsigned NULL,
            event_type varchar(80) NOT NULL,
            actor_user_id bigint(20) unsigned NULL,
            before_json longtext NULL,
            after_json longtext NULL,
            context_json longtext NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY entity_timeline (entity_type, entity_id, created_at, id),
            KEY event_timeline (event_type, created_at, id),
            KEY actor_timeline (actor_user_id, created_at, id)
        ) {$charset};");
    }
}
