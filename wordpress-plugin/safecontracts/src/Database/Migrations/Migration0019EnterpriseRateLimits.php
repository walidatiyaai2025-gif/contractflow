<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use SafeContracts\Database\Migration;

final class Migration0019EnterpriseRateLimits implements Migration
{
    public function up(object $wpdb): void
    {
        $table = $wpdb->prefix . 'safecontracts_esc_rate_limits';
        $charset = method_exists($wpdb, 'get_charset_collate')
            ? (string) $wpdb->get_charset_collate()
            : '';

        dbDelta("CREATE TABLE {$table} (
            bucket_key char(64) NOT NULL,
            window_expires_at datetime NOT NULL,
            request_count int(10) unsigned NOT NULL DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY (bucket_key),
            KEY expires_at (window_expires_at)
        ) {$charset};");
    }
}
