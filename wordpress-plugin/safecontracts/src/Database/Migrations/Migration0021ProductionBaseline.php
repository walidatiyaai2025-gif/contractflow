<?php

declare(strict_types=1);

namespace SafeContracts\Database\Migrations;

use RuntimeException;
use SafeContracts\Database\ProductionMigration;

/**
 * Historical production baseline marker for schema version 1.20.0.
 *
 * Version 1.20.0 was already deployed before the governed entity-attachment
 * schema was introduced. This migration intentionally performs no DDL or data
 * mutation. It keeps the migration registry contiguous for older installations
 * while validating that the core production schema expected at this boundary
 * is readable before the version marker advances.
 *
 * Installations already reporting 1.20.0 skip this migration and proceed
 * directly to the next real schema migration.
 */
final class Migration0021ProductionBaseline implements ProductionMigration
{
    public function preflight(object $wpdb): void
    {
        $this->assertBaselineReadable($wpdb);
    }

    public function up(object $wpdb): void
    {
        // Deliberate no-op: 1.20.0 represents an already-deployed production
        // baseline and must not fabricate schema changes during replay.
        unset($wpdb);
    }

    public function verify(object $wpdb): void
    {
        $this->assertBaselineReadable($wpdb);
    }

    public function rollback(object $wpdb): void
    {
        // No application mutation was performed, so there is nothing to undo.
        unset($wpdb);
    }

    private function assertBaselineReadable(object $wpdb): void
    {
        foreach ([
            $wpdb->prefix . 'safecontracts_contracts',
            $wpdb->prefix . 'safecontracts_scheduled_payments',
            $wpdb->prefix . 'safecontracts_payment_collections',
            $wpdb->prefix . 'safecontracts_notification_rules',
        ] as $table) {
            $rows = $wpdb->get_results("SELECT id FROM {$table} ORDER BY id ASC LIMIT 1", ARRAY_A);
            if (! is_array($rows)) {
                throw new RuntimeException('SafeContracts production baseline prerequisite is unavailable: ' . $table);
            }
            if (property_exists($wpdb, 'last_error') && trim((string) $wpdb->last_error) !== '') {
                throw new RuntimeException('SafeContracts production baseline verification failed: ' . trim((string) $wpdb->last_error));
            }
        }
    }
}
